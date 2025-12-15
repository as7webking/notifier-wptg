<?php
/*
Plugin Name: Notifier WP-Telegram
Description: Sending Sureforms and Woocommerce notifications to Telegram bot
Version: 1.2
Author: Ahmed Sultanline
Author URI: https://ahmedsultanline.com
Text Domain: notifier-wptg
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define a placeholder for the Bot Token.
// IMPORTANT: In a production plugin, this token MUST be stored securely 
// as a setting or handled via an API service, NOT hardcoded.
if ( ! defined( 'NOTIFIER_WPTG_BOT_TOKEN' ) ) {
    // You should replace this with a secure method to fetch the token.
    define( 'NOTIFIER_WPTG_BOT_TOKEN', '8443299373:AAG9ghZ99Ltf6GbGe5HUR8T0LeaFTpd5J50' );
}

/**
 * Main plugin class for Notifier WP-Telegram.
 */
class Notifier_WP_Telegram {

    /**
     * Constructor. Set up hooks and actions.
     */
    public function __construct() {
        $this->setup_hooks();
    }

    /**
     * Initialize all plugin hooks.
     */
    private function setup_hooks() {
        // Core WordPress actions.
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Integration hooks.
        add_action( 'sureforms_form_submission', array( $this, 'handle_sureforms_submission' ), 10, 2 );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'handle_woocommerce_order' ), 10, 1 );
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'notifier-wptg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Add settings link to the plugin action links.
     *
     * @param array $links Array of action links.
     * @return array Modified array of action links.
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=telegram-notifier">' . esc_html__( 'Settings', 'notifier-wptg' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu_page() {
        add_options_page(
            esc_html__( 'Telegram Notifier', 'notifier-wptg' ),
            esc_html__( 'Telegram Notifier', 'notifier-wptg' ),
            'manage_options',
            'telegram-notifier',
            array( $this, 'settings_page_layout' )
        );
    }

    /**
     * Register plugin settings and sanitization callbacks.
     */
    public function register_plugin_settings() {
        // Sanitize Chat IDs (comma-separated list of numbers).
        register_setting( 'telegram_notifier_group', 'telegram_chat_ids', array(
            'sanitize_callback' => array( $this, 'sanitize_chat_ids' ),
        ) );
        // Sanitize Sound URL (must be a valid URL).
        register_setting( 'telegram_notifier_group', 'telegram_sound_url', array(
            'sanitize_callback' => 'esc_url_raw',
        ) );
        // Sanitize Checkbox (boolean).
        register_setting( 'telegram_notifier_group', 'telegram_enabled', array(
            'sanitize_callback' => 'absint', // Ensure it's 0 or 1.
        ) );
    }
    
    /**
     * Sanitization callback for chat IDs.
     * Ensures IDs are numeric and handles the comma-separated format.
     *
     * @param string $input Raw input string.
     * @return string Sanitized and validated output string.
     */
    public function sanitize_chat_ids( $input ) {
        $ids = explode( ',', $input );
        $sanitized_ids = array();

        foreach ( $ids as $id ) {
            $id = trim( $id );
            // Use regex to ensure it contains only digits and optional minus sign (for group IDs)
            if ( preg_match( '/^-?\d+$/', $id ) ) {
                $sanitized_ids[] = $id;
            }
        }

        return implode( ',', $sanitized_ids );
    }

    /**
     * Settings page layout (Admin UI).
     */
    public function settings_page_layout() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Telegram Notifier Settings', 'notifier-wptg' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'telegram_notifier_group' );
                do_settings_sections( 'telegram_notifier_group' );
                ?>
                
                <p>
                    <a href="https://t.me/notification_wp_bot" target="_blank">
                        <?php esc_html_e( 'To start using Notifier – connect this bot', 'notifier-wptg' ); ?>
                    </a>
                </p>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Chat IDs (comma separated)', 'notifier-wptg' ); ?></th>
                        <td>
                            <input type="text" name="telegram_chat_ids"
                                value="<?php echo esc_attr( get_option( 'telegram_chat_ids' ) ); ?>" style="width:300px;" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Sound alert file', 'notifier-wptg' ); ?></th>
                        <td>
                            <input type="text" id="telegram_sound_url" name="telegram_sound_url"
                                value="<?php echo esc_attr( get_option( 'telegram_sound_url' ) ); ?>" style="width:300px;"
                                readonly />
                            <button type="button" class="button"
                                id="select_sound"><?php esc_html_e( 'Choose file', 'notifier-wptg' ); ?></button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Enable Telegram notifications', 'notifier-wptg' ); ?></th>
                        <td>
                            <input type="checkbox" name="telegram_enabled" value="1" <?php checked( get_option( 'telegram_enabled' ), 1 ); ?> />
                            <label><?php esc_html_e( 'Send new orders and forms to Telegram', 'notifier-wptg' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Bot activation', 'notifier-wptg' ); ?></th>
                        <td>
                            <a href="https://t.me/notification_wp_bot" target="_blank" class="button button-primary">
                                🤖 <?php esc_html_e( 'Activate Bot', 'notifier-wptg' ); ?>
                            </a>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets (Media Uploader script).
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_telegram-notifier' !== $hook ) {
            return;
        }

        wp_enqueue_media(); // Connects the built-in media uploader.
        wp_enqueue_script(
            'notifier-wptg-media',
            plugin_dir_url( __FILE__ ) . 'notifier-wptg.js',
            array( 'jquery' ), // Added jQuery dependency for safety.
            '1.0',
            true
        );

        wp_localize_script( 'notifier-wptg-media', 'notifierWPTG', array(
            'selectTitle' => esc_html__( 'Select a sound file', 'notifier-wptg' ),
            'useText'     => esc_html__( 'Use this file', 'notifier-wptg' )
        ) );
    }

    /**
     * 📨 SureForms integration: handles form submission notification.
     *
     * @param array $form Array containing form details.
     * @param array $entry Array containing submission entry data.
     */
    public function handle_sureforms_submission( $form, $entry ) {
        if ( ! get_option( 'telegram_enabled' ) ) {
            return;
        }

        $bot_token = NOTIFIER_WPTG_BOT_TOKEN;
        $chat_ids  = explode( ',', get_option( 'telegram_chat_ids' ) );
        $sound_url = esc_url_raw( get_option( 'telegram_sound_url' ) );

        $message = "📩 *New form submission from:* " . get_bloginfo( 'name' ) . "\n";
        if ( ! empty( $form['title'] ) ) {
            $message .= "📝 Form: " . sanitize_text_field( $form['title'] ) . "\n\n";
        }

        foreach ( $entry['fields'] as $field ) {
            $label = sanitize_text_field( $field['label'] );
            $value = sanitize_text_field( $field['value'] );
            // Skip field based on label (using a more explicit check).
            if ( false !== stripos( $label, 'Datenschutz' ) ) {
                continue;
            }
            $message .= "• *{$label}*: {$value}\n";
        }

        $this->send_telegram_notifications( $chat_ids, $message, $sound_url, esc_html__( 'New form submission alert', 'notifier-wptg' ) );
    }

    /**
     * 🛒 WooCommerce integration: handles new order notification.
     *
     * @param int $order_id The ID of the processed order.
     */
    public function handle_woocommerce_order( $order_id ) {
        if ( ! get_option( 'telegram_enabled' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $chat_ids  = explode( ',', get_option( 'telegram_chat_ids' ) );
        $sound_url = esc_url_raw( get_option( 'telegram_sound_url' ) );

        $message = "🛍 *New WooCommerce order from:* " . get_bloginfo( 'name' ) . "\n";
        $message .= "Order ID: \#{$order_id}\n";
        $message .= "Customer: " . $order->get_formatted_billing_full_name() . "\n";
        $message .= "Phone: " . $order->get_billing_phone() . "\n";
        $message .= "-------------------\n";

        foreach ( $order->get_items() as $item ) {
            // Note: get_name() and get_quantity() are safe methods.
            $message .= "• " . $item->get_name() . " × " . $item->get_quantity() . "\n";
        }

        $this->send_telegram_notifications( $chat_ids, $message, $sound_url, esc_html__( 'New order received', 'notifier-wptg' ) );
    }

    /**
     * Helper function to send notifications to Telegram.
     *
     * @param array  $chat_ids Array of chat IDs.
     * @param string $message The message content (Markdown supported).
     * @param string $sound_url Optional URL for the audio file.
     * @param string $sound_caption Caption for the sound alert.
     */
    private function send_telegram_notifications( $chat_ids, $message, $sound_url = '', $sound_caption = '' ) {
        $bot_token = NOTIFIER_WPTG_BOT_TOKEN;

        foreach ( $chat_ids as $chat_id ) {
            $chat_id = trim( $chat_id );
            
            // Use curly braces for all control structures.
            if ( empty( $chat_id ) || ! preg_match( '/^-?\d+$/', $chat_id ) ) {
                continue;
            }

            // 1. Send Text Message
            $text_api_url = 'https://api.telegram.org/bot' . $bot_token . '/sendMessage';
            $response = wp_remote_post( $text_api_url, array(
                'body' => array(
                    'chat_id'    => $chat_id,
                    'text'       => $message,
                    'parse_mode' => 'Markdown',
                ),
                'timeout' => 10,
            ) );

            // IMPORTANT: Professional check for API errors.
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                // Log the error instead of failing silently.
                error_log( 'Notifier WP-Telegram Text Error: ' . print_r( $response, true ) );
            }

            // 2. Send Optional Sound Alert
            if ( $sound_url ) {
                $audio_api_url = 'https://api.telegram.org/bot' . $bot_token . '/sendAudio';
                $audio_response = wp_remote_post( $audio_api_url, array(
                    'body' => array(
                        'chat_id' => $chat_id,
                        'audio'   => $sound_url,
                        'caption' => '🔔 ' . $sound_caption,
                    ),
                    'timeout' => 10,
                ) );

                if ( is_wp_error( $audio_response ) || 200 !== wp_remote_retrieve_response_code( $audio_response ) ) {
                    error_log( 'Notifier WP-Telegram Audio Error: ' . print_r( $audio_response, true ) );
                }
            }
        }
    }
}

// Instantiate the main class to run the plugin.
new Notifier_WP_Telegram();
