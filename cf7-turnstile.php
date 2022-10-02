<?php
/**
 * CF7 Turnstile
 *
 * Cloudflares turnstile recaptcha
 *
 * @category WordPress_Plugin
 * @author   Nick Fairchild <nick.fairchild@gmail.com>
 * @license  GPLv3 <https://www.gnu.org/licenses/gpl-3.0.en.html>
 * @link     https://github.com/nickfairchild/cf7-turnstile/
 *
 * @wordpress-plugin
 * Plugin Name: CF7 Turnstile
 * Plugin URI:  https://github.com/nickfairchild/cf7-turnstile/
 * Description: Cloudflares turnstile recaptcha for Contact form 7.
 * Author:      Nick Fairchild <nick.fairchild@gmail.com>
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

use Nickfairchild\CF7Turnstile\Turnstile;

require_once __DIR__.'/vendor/autoload.php';

add_action('wpcf7_init', function () {
    $integration = WPCF7_Integration::get_instance();

    $integration->add_service('turnstile', Turnstile::getInstance());
}, 15, 0);

add_action('wp_enqueue_scripts', function () {
    $service = Turnstile::getInstance();

    if (! $service->is_active()) {
        return;
    }

    wp_enqueue_script(
        'turnstile',
        'https://challenges.cloudflare.com/turnstile/v0/api.js',
        [],
        '1.0',
        true
    );
}, 20, 0);

add_action('wpcf7_init', function () {
    $service = Turnstile::getInstance();

    if (! $service->is_active()) {
        return;
    }

    wpcf7_add_form_tag('turnstile',
        'wpcf7_add_form_tag_turnstile',
        ['display-block' => true]
    );
}, 10, 0);

function wpcf7_add_form_tag_turnstile($tag)
{
    $service = Turnstile::getInstance();

    $theme = 'auto';

    if ($tag->has_option('theme')) {
        $theme = $tag->get_option('theme', 'id', true);
    }

    return '<div class="cf-turnstile" data-sitekey="'.$service->get_sitekey().'" data-theme="'.$theme.'"></div>';
}

add_filter('wpcf7_spam', function ($spam, $submission) {
    if ($spam) {
        return $spam;
    }

    $service = Turnstile::getInstance();

    if (! $service->is_active()) {
        return $spam;
    }

    $token = isset($_POST['cf-turnstile-response']) ? trim($_POST['cf-turnstile-response']) : '';

    if ($service->verify($token)) {
        $spam = false;
    } else {
        $spam = true;
    }

    return $spam;
}, 9, 2);

add_action('wpcf7_admin_init', function () {
    $service = Turnstile::getInstance();

    if (! $service->is_active()) {
        return;
    }

    $tag_generator = \WPCF7_TagGenerator::get_instance();
    $tag_generator->add('turnstile', __('turnstile', 'cf7-turnstile'), 'wpcf7_tag_generator_turnstile');
}, 45, 0);

function wpcf7_tag_generator_turnstile($contact_form, $args = '')
{
    $args = wp_parse_args($args, []);
    ?>
    <div class="control-box">
        <fieldset>
            <legend><?= __('Generate form-tags for turnstile recaptcha and corresponding response input field.', 'cf7-turnstile') ?></legend>

            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( $args['content'] . '-theme' ); ?>"><?php echo esc_html( __( 'Theme', 'contact-form-7' ) ); ?></label>
                    </th>
                    <td>
                        <select name="theme" id="<?php echo esc_attr( $args['content'] . '-theme' ); ?>">
                            <option value="auto">Auto</option>
                            <option value="dark">Dark</option>
                            <option value="light">Light</option>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
        </fieldset>
    </div>

    <div class="insert-box">
        <input type="text" name="turnstile" class="tag code" readonly="readonly" onfocus="this.select()" />

        <div class="submitbox">
            <input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'cf7-turnstile' ) ); ?>" />
        </div>
    </div>
<?php
}
