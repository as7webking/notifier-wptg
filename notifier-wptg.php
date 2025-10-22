<?php
/*
Plugin Name: Notifier WP-Telegram
Description: Sending Sureforms and Woocommerce notifications to Telegram
Version: 1.1
Author: Lavlad
Author URI: https://ahmedsultanline.com
Text Domain: notifier-wptg
Domain Path: /languages
*/

if (!defined('ABSPATH'))
    exit;

// Load translations
add_action('plugins_loaded', function () {
    load_plugin_textdomain('notifier-wptg', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="options-general.php?page=telegram-notifier">' . __('Settings', 'notifier-wptg') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Add admin menu page
add_action('admin_menu', function () {
    add_options_page(
        __('Telegram Notifier', 'notifier-wptg'),
        __('Telegram Notifier', 'notifier-wptg'),
        'manage_options',
        'telegram-notifier',
        'telegram_notifier_settings_page'
    );
});

// Settings page layout
function telegram_notifier_settings_page()
{
    ?>
    <div class="wrap">
        <h1><?php _e('Telegram Notifier Settings', 'notifier-wptg'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('telegram_notifier_group');
            do_settings_sections('telegram_notifier_group');
            ?>
            <p><a href="https://t.me/notification_wp_bot"
                    target="_blank"><?php _e('To start using Notifier – connect this bot', 'notifier-wptg'); ?></a></p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Chat IDs (comma separated)', 'notifier-wptg'); ?></th>
                    <td><input type="text" name="telegram_chat_ids"
                            value="<?php echo esc_attr(get_option('telegram_chat_ids')); ?>" style="width:300px;" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Sound alert file', 'notifier-wptg'); ?></th>
                    <td>
                        <input type="text" id="telegram_sound_url" name="telegram_sound_url"
                            value="<?php echo esc_attr(get_option('telegram_sound_url')); ?>" style="width:300px;"
                            readonly />
                        <button type="button" class="button"
                            id="select_sound"><?php _e('Choose file', 'notifier-wptg'); ?></button>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Enable Telegram notifications', 'notifier-wptg'); ?></th>
                    <td>
                        <input type="checkbox" name="telegram_enabled" value="1" <?php checked(get_option('telegram_enabled'), 1); ?> />
                        <label><?php _e('Send new orders and forms to Telegram', 'notifier-wptg'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Bot activation', 'notifier-wptg'); ?></th>
                    <td>
                        <a href="https://t.me/notification_wp_bot" target="_blank" class="button button-primary">
                            🤖 <?php _e('Activate Bot', 'notifier-wptg'); ?>
                        </a>
                    </td>
                </tr>


            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register plugin settings
add_action('admin_init', function () {
    register_setting('telegram_notifier_group', 'telegram_chat_ids');
    register_setting('telegram_notifier_group', 'telegram_sound_url');
    register_setting('telegram_notifier_group', 'telegram_enabled');
});

// 📨 SureForms integration
add_action('sureforms_form_submission', function ($form, $entry) {
    $bot_token = '8443299373:AAG9ghZ99Ltf6GbGe5HUR8T0LeaFTpd5J50';
    $chat_ids = explode(',', get_option('telegram_chat_ids'));
    $sound_url = get_option('telegram_sound_url');

    $message = "📩 *New form submission from:* " . get_bloginfo('name') . "\n";
    if (!empty($form['title'])) {
        $message .= "📝 Form: " . $form['title'] . "\n\n";
    }

    foreach ($entry['fields'] as $field) {
        $label = sanitize_text_field($field['label']);
        $value = sanitize_text_field($field['value']);
        if (stripos($label, 'Datenschutz') !== false)
            continue;
        $message .= "• *{$label}*: {$value}\n";
    }

    foreach ($chat_ids as $chat_id) {
        $chat_id = trim($chat_id);
        if (!$chat_id)
            continue;

        // Text message
        wp_remote_get("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($message) . "&parse_mode=Markdown");

        // 🔊 Optional sound alert
        if ($sound_url) {
            wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendAudio", [
                'body' => [
                    'chat_id' => $chat_id,
                    'audio' => $sound_url,
                    'caption' => '🔔 ' . __('New form submission alert', 'notifier-wptg'),
                ],
            ]);
        }
    }
}, 10, 2);

// 🛒 WooCommerce integration
add_action('woocommerce_checkout_order_processed', function ($order_id) {
    $bot_token = '8443299373:AAG9ghZ99Ltf6GbGe5HUR8T0LeaFTpd5J50';
    $chat_ids = explode(',', get_option('telegram_chat_ids'));
    $sound_url = get_option('telegram_sound_url');
    $order = wc_get_order($order_id);

    $message = "🛍 *New WooCommerce order from:* " . get_bloginfo('name') . "\n";
    $message .= "Order ID: #{$order_id}\n";
    $message .= "Customer: " . $order->get_formatted_billing_full_name() . "\n";
    $message .= "Phone: " . $order->get_billing_phone() . "\n";
    $message .= "-------------------\n";

    foreach ($order->get_items() as $item) {
        $message .= "• " . $item->get_name() . " × " . $item->get_quantity() . "\n";
    }

    foreach ($chat_ids as $chat_id) {
        $chat_id = trim($chat_id);
        if (!$chat_id)
            continue;

        // Send message
        wp_remote_get("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($message) . "&parse_mode=Markdown");

        // Send optional sound alert
        if ($sound_url) {
            wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendAudio", [
                'body' => [
                    'chat_id' => $chat_id,
                    'audio' => $sound_url,
                    'caption' => '🔔 ' . __('New order received', 'notifier-wptg'),
                ],
            ]);
        }
    }
}, 10, 1);

//Connecting JS
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'settings_page_telegram-notifier')
        return;

    wp_enqueue_media(); // подключает встроенный media uploader
    wp_enqueue_script(
        'notifier-wptg-media',
        plugin_dir_url(__FILE__) . 'notifier-wptg.js',
        [],
        '1.0',
        true
    );

    wp_localize_script('notifier-wptg-media', 'notifierWPTG', [
        'selectTitle' => __('Select a sound file', 'notifier-wptg'),
        'useText' => __('Use this file', 'notifier-wptg')
    ]);
});
