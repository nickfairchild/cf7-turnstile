<?php

namespace Nickfairchild\CF7Turnstile;

if (! class_exists('WPCF7_Service')) {
    return;
}

class Turnstile extends \WPCF7_Service
{
    private static Turnstile $instance;

    private array $sitekeys;

    public static function getInstance(): Turnstile
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->sitekeys = \WPCF7::get_option('turnstile');
    }

    public function get_title()
    {
        return __('Turnstile', 'contact-form-7');
    }

    public function is_active()
    {
        $sitekey = $this->get_sitekey();
        $secret = $this->get_secret($sitekey);

        return $sitekey && $secret;
    }

    public function get_categories()
    {
        return array('spam_protection');
    }

    public function link()
    {
        echo wpcf7_link(
            'https://developers.cloudflare.com/turnstile/',
            'developers.cloudflare.com/turnstile'
        );
    }

    public function get_global_sitekey()
    {
        static $sitekey = '';

        if ($sitekey) {
            return $sitekey;
        }

        if (defined('WPCF7_TURNSTILE_SITEKEY')) {
            $sitekey = WPCF7_TURNSTILE_SITEKEY;
        }

        $sitekey = apply_filters('wpcf7_turnstile_sitekey', $sitekey);

        return $sitekey;
    }


    public function get_global_secret()
    {
        static $secret = '';

        if ($secret) {
            return $secret;
        }

        if (defined('WPCF7_TURNSTILE_SECRET')) {
            $secret = WPCF7_TURNSTILE_SECRET;
        }

        $secret = apply_filters('wpcf7_turnstile_secret', $secret);

        return $secret;
    }


    public function get_sitekey()
    {
        if ($this->get_global_sitekey() and $this->get_global_secret()) {
            return $this->get_global_sitekey();
        }

        if (empty($this->sitekeys)
            or ! is_array($this->sitekeys)) {
            return false;
        }

        $sitekeys = array_keys($this->sitekeys);

        return $sitekeys[0];
    }


    public function get_secret($sitekey)
    {
        if ($this->get_global_sitekey() and $this->get_global_secret()) {
            return $this->get_global_secret();
        }

        $sitekeys = (array) $this->sitekeys;

        if (isset($sitekeys[$sitekey])) {
            return $sitekeys[$sitekey];
        } else {
            return false;
        }
    }

    public function verify($token)
    {
        $endpoint = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $sitekey = $this->get_sitekey();
        $secret = $this->get_secret($sitekey);

        $request = [
            'body' => [
                'secret' => $secret,
                'response' => $token,
            ],
        ];

        $response = wp_remote_post(esc_url_raw($endpoint), $request);

        if (200 != wp_remote_retrieve_response_code($response)) {
//            if ( WP_DEBUG ) {
//                $this->log( $endpoint, $request, $response );
//            }

            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body, true);

        if (isset($response_body['error_codes']) && !empty($response_body['error_codes'])) {
            return false;
        }

        return true;
    }

    protected function menu_page_url($args = '')
    {
        $args = wp_parse_args($args, array());

        $url = menu_page_url('wpcf7-integration', false);
        $url = add_query_arg(array('service' => 'turnstile'), $url);

        if (! empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    protected function save_data()
    {
        \WPCF7::update_option('turnstile', $this->sitekeys);
    }

    protected function reset_data()
    {
        $this->sitekeys = null;
        $this->save_data();
    }

    public function load($action = '')
    {
        if ('setup' == $action and 'POST' == $_SERVER['REQUEST_METHOD']) {
            check_admin_referer('wpcf7-turnstile-setup');

            if (! empty($_POST['reset'])) {
                $this->reset_data();
                $redirect_to = $this->menu_page_url('action=setup');
            } else {
                $sitekey = isset($_POST['sitekey']) ? trim($_POST['sitekey']) : '';
                $secret = isset($_POST['secret']) ? trim($_POST['secret']) : '';

                if ($sitekey and $secret) {
                    $this->sitekeys = array($sitekey => $secret);
                    $this->save_data();

                    $redirect_to = $this->menu_page_url(array(
                        'message' => 'success',
                    ));
                } else {
                    $redirect_to = $this->menu_page_url(array(
                        'action' => 'setup',
                        'message' => 'invalid',
                    ));
                }
            }

            wp_safe_redirect($redirect_to);
            exit();
        }
    }

    public function admin_notice($message = '')
    {
        if ('invalid' == $message) {
            echo sprintf(
                '<div class="notice notice-error"><p><strong>%1$s</strong>: %2$s</p></div>',
                esc_html(__("Error", 'contact-form-7')),
                esc_html(__("Invalid key values.", 'contact-form-7')));
        }

        if ('success' == $message) {
            echo sprintf('<div class="notice notice-success"><p>%s</p></div>',
                esc_html(__('Settings saved.', 'contact-form-7')));
        }
    }

    public function display($action = '')
    {
        echo '<p>'.sprintf(
                esc_html(__('Turnstile protects you against spam and other types of automated abuse. With Contact Form 7&#8217;s Turnstile integration module, you can block abusive form submissions by spam bots. For details, see %s.',
                    'contact-form-7')),
                wpcf7_link(
                    __('https://blog.cloudflare.com/turnstile-private-captcha-alternative/', 'contact-form-7'),
                    __('Turnstile', 'contact-form-7')
                )
            ).'</p>';

        if ($this->is_active()) {
            echo sprintf(
                '<p class="dashicons-before dashicons-yes">%s</p>',
                esc_html(__("Turnstile is active on this site.", 'contact-form-7'))
            );
        }

        if ('setup' == $action) {
            $this->display_setup();
        } else {
            echo sprintf(
                '<p><a href="%1$s" class="button">%2$s</a></p>',
                esc_url($this->menu_page_url('action=setup')),
                esc_html(__('Setup Integration', 'contact-form-7'))
            );
        }
    }

    private function display_setup()
    {
        $sitekey = $this->is_active() ? $this->get_sitekey() : '';
        $secret = $this->is_active() ? $this->get_secret($sitekey) : '';

        ?>
        <form method="post" action="<?php echo esc_url($this->menu_page_url('action=setup')); ?>">
            <?php wp_nonce_field('wpcf7-turnstile-setup'); ?>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label for="sitekey"><?php echo esc_html(__('Site Key',
                                'contact-form-7')); ?></label></th>
                    <td><?php
                        if ($this->is_active()) {
                            echo esc_html($sitekey);
                            echo sprintf(
                                '<input type="hidden" value="%1$s" id="sitekey" name="sitekey" />',
                                esc_attr($sitekey)
                            );
                        } else {
                            echo sprintf(
                                '<input type="text" aria-required="true" value="%1$s" id="sitekey" name="sitekey" class="regular-text code" />',
                                esc_attr($sitekey)
                            );
                        }
                        ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="secret"><?php echo esc_html(__('Secret Key',
                                'contact-form-7')); ?></label></th>
                    <td><?php
                        if ($this->is_active()) {
                            echo esc_html(wpcf7_mask_password($secret, 4, 4));
                            echo sprintf(
                                '<input type="hidden" value="%1$s" id="secret" name="secret" />',
                                esc_attr($secret)
                            );
                        } else {
                            echo sprintf(
                                '<input type="text" aria-required="true" value="%1$s" id="secret" name="secret" class="regular-text code" />',
                                esc_attr($secret)
                            );
                        }
                        ?></td>
                </tr>
                </tbody>
            </table>
            <?php
            if ($this->is_active()) {
                if ($this->get_global_sitekey() and $this->get_global_secret()) {
                    // nothing
                } else {
                    submit_button(
                        _x('Remove Keys', 'API keys', 'contact-form-7'),
                        'small', 'reset'
                    );
                }
            } else {
                submit_button(__('Save Changes', 'contact-form-7'));
            }
            ?>
        </form>
        <?php
    }
}
