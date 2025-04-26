<?php
/*
Plugin Name: WooCommerce Prevent Fake Orders
Plugin URI: https://themepluginbd.com/
Description: Prevents customers from placing multiple orders with same IP, email or phone number.
Version: 2.2
Author: Shakkhar Khondokar
Author URI: https://www.facebook.com/shakkharkhondokar17
License: Commercial
Text Domain: wc-prevent-fake-orders
*/

defined('ABSPATH') || exit;

class WC_Prevent_Fake_Orders {

    private $settings;
    private $license_key;
    private $license_valid = false;
    private $license_page = 'https://themepluginbd.com/product/woocommerce-prevent-fake-orders-plugin/';
    private $gist_url = 'https://gist.githubusercontent.com/themepluginbd/a83212552329e23b5beb09f8fb9752e5/raw/f7ebaf9d49ab9d263d98d07ce777439c79a47efe/gistfile1.txt';

    public function __construct() {
        // Initialize with error handling
        try {
            $this->license_key = get_option('wc_pfo_license_key');
            $this->license_valid = $this->validate_license($this->license_key);
            
            add_action('admin_menu', array($this, 'register_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
            
            if ($this->license_valid || $this->is_localhost()) {
                $this->load_settings();
                $this->init_functionality();
            }
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>Plugin Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    public function register_admin_menu() {
        try {
            $menu_icon = 'dashicons-shield-alt';
            
            add_menu_page(
                __('Fake Order Prevention', 'wc-prevent-fake-orders'),
                __('Fake Order', 'wc-prevent-fake-orders'),
                'manage_options',
                'wc-prevent-fake-orders',
                array($this, 'render_license_page'),
                $menu_icon,
                56
            );

            add_submenu_page(
                'wc-prevent-fake-orders',
                __('License Activation', 'wc-prevent-fake-orders'),
                __('License', 'wc-prevent-fake-orders'),
                'manage_options',
                'wc-prevent-fake-orders',
                array($this, 'render_license_page')
            );

            if ($this->license_valid || $this->is_localhost()) {
                add_submenu_page(
                    'wc-prevent-fake-orders',
                    __('Fake Order Settings', 'wc-prevent-fake-orders'),
                    __('Settings', 'wc-prevent-fake-orders'),
                    'manage_options',
                    'wc-prevent-fake-orders-settings',
                    array($this, 'render_settings_page')
                );
            }
        } catch (Exception $e) {
            error_log('WC Prevent Fake Orders menu error: ' . $e->getMessage());
        }
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'wc-prevent-fake-orders') !== false) {
            wp_enqueue_style(
                'wc-pfo-admin', 
                plugins_url('admin.css', __FILE__),
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'admin.css')
            );
        }
    }

    public function register_settings() {
        try {
            register_setting('wc_pfo_license_group', 'wc_pfo_license_key', array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ));

            if ($this->license_valid || $this->is_localhost()) {
                register_setting('wc_pfo_settings_group', 'wc_pfo_settings', array(
                    'sanitize_callback' => array($this, 'sanitize_settings')
                ));

                add_settings_section(
                    'wc_pfo_settings_section',
                    __('Order Prevention Settings', 'wc-prevent-fake-orders'),
                    array($this, 'render_settings_section'),
                    'wc_pfo_settings_page'
                );

                $this->add_settings_fields();
            }
        } catch (Exception $e) {
            error_log('WC Prevent Fake Orders settings error: ' . $e->getMessage());
        }
    }

    private function add_settings_fields() {
        $fields = array(
            'enable_plugin' => array(
                'label' => __('Enable Protection', 'wc-prevent-fake-orders'),
                'type' => 'checkbox',
                'description' => __('Enable fake order prevention system', 'wc-prevent-fake-orders')
            ),
            'check_ip' => array(
                'label' => __('Check IP Address', 'wc-prevent-fake-orders'),
                'type' => 'checkbox',
                'description' => __('Prevent multiple orders from same IP', 'wc-prevent-fake-orders')
            ),
            'check_email' => array(
                'label' => __('Check Email', 'wc-prevent-fake-orders'),
                'type' => 'checkbox',
                'description' => __('Prevent multiple orders with same email', 'wc-prevent-fake-orders')
            ),
            'check_phone' => array(
                'label' => __('Check Phone Number', 'wc-prevent-fake-orders'),
                'type' => 'checkbox',
                'description' => __('Prevent multiple orders with same phone', 'wc-prevent-fake-orders')
            ),
            'time_restriction' => array(
                'label' => __('Time Restriction (hours)', 'wc-prevent-fake-orders'),
                'type' => 'number',
                'description' => __('Block repeat orders within this time frame', 'wc-prevent-fake-orders'),
                'attrs' => 'min="1" step="1"'
            ),
            'custom_message' => array(
                'label' => __('Error Message', 'wc-prevent-fake-orders'),
                'type' => 'textarea',
                'description' => __('Message to show when order is blocked', 'wc-prevent-fake-orders')
            )
        );

        foreach ($fields as $id => $field) {
            try {
                add_settings_field(
                    'wc_pfo_' . $id,
                    $field['label'],
                    array($this, 'render_settings_field'),
                    'wc_pfo_settings_page',
                    'wc_pfo_settings_section',
                    array(
                        'id' => $id,
                        'field' => $field
                    )
                );
            } catch (Exception $e) {
                error_log('WC Prevent Fake Orders field error: ' . $e->getMessage());
            }
        }
    }

    public function render_license_page() {
        try {
            ?>
            <div class="wrap wc-pfo-license-wrap">
                <h1><span class="dashicons dashicons-shield-alt"></span> <?php _e('Fake Order Prevention License', 'wc-prevent-fake-orders'); ?></h1>
                
                <?php if (!$this->license_valid && !$this->is_localhost()) : ?>
                    <div class="wc-pfo-license-notice notice notice-error">
                        <p><?php printf(
                            __('You need a valid license to unlock all features. %sPurchase a license%s', 'wc-prevent-fake-orders'),
                            '<a href="' . esc_url($this->license_page) . '" target="_blank" class="button button-primary">',
                            '</a>'
                        ); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="wc-pfo-license-box">
                    <form method="post" action="options.php">
                        <?php settings_fields('wc_pfo_license_group'); ?>
                        
                        <h2><?php _e('License Activation', 'wc-prevent-fake-orders'); ?></h2>
                        
                        <div class="wc-pfo-license-field">
                            <label for="wc_pfo_license_key"><?php _e('License Key', 'wc-prevent-fake-orders'); ?></label>
                            <input type="text" id="wc_pfo_license_key" name="wc_pfo_license_key" 
                                   value="<?php echo esc_attr($this->license_key); ?>" 
                                   class="regular-text" placeholder="example.com|your-license-key">
                            <?php if (!empty($this->license_key)) : ?>
                                <span class="wc-pfo-license-status <?php echo $this->license_valid ? 'valid' : 'invalid'; ?>">
                                    <?php echo $this->license_valid ? __('Active', 'wc-prevent-fake-orders') : __('Invalid', 'wc-prevent-fake-orders'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="description"><?php _e('Enter your license key in the format: yourdomain.com|license-key', 'wc-prevent-fake-orders'); ?></p>
                        
                        <?php submit_button(__('Activate License', 'wc-prevent-fake-orders')); ?>
                    </form>
                </div>
                
                <?php if ($this->license_valid) : ?>
                    <div class="wc-pfo-license-success notice notice-success">
                        <p><?php _e('License successfully activated! You can now access all plugin features.', 'wc-prevent-fake-orders'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>' . __('Error displaying license page.', 'wc-prevent-fake-orders') . '</p></div>';
            error_log('WC Prevent Fake Orders license page error: ' . $e->getMessage());
        }
    }

    public function render_settings_page() {
        try {
            if (!$this->license_valid && !$this->is_localhost()) {
                wp_redirect(admin_url('admin.php?page=wc-prevent-fake-orders'));
                exit;
            }
            
            if (!function_exists('wc_get_orders')) {
                throw new Exception('WooCommerce is not active');
            }
            ?>
            <div class="wrap wc-pfo-settings-wrap">
                <h1><span class="dashicons dashicons-shield-alt"></span> <?php _e('Fake Order Prevention Settings', 'wc-prevent-fake-orders'); ?></h1>
                
                <div class="wc-pfo-settings-box">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wc_pfo_settings_group');
                        do_settings_sections('wc_pfo_settings_page');
                        submit_button(__('Save Settings', 'wc-prevent-fake-orders'));
                        ?>
                    </form>
                </div>
            </div>
            <?php
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>' . __('Error displaying settings page: ', 'wc-prevent-fake-orders') . esc_html($e->getMessage()) . '</p></div>';
            error_log('WC Prevent Fake Orders settings page error: ' . $e->getMessage());
        }
    }

    private function validate_license($license_key) {
        try {
            if ($this->is_localhost()) return true;
            if (empty($license_key)) return false;

            $response = wp_remote_get($this->gist_url, array('timeout' => 15));
            
            if (is_wp_error($response)) {
                error_log('License validation API error: ' . $response->get_error_message());
                return false;
            }
            
            if (wp_remote_retrieve_response_code($response) !== 200) {
                error_log('License validation API returned status: ' . wp_remote_retrieve_response_code($response));
                return false;
            }

            $license_data = wp_remote_retrieve_body($response);
            $current_domain = sanitize_text_field($_SERVER['HTTP_HOST']);

            foreach (explode("\n", $license_data) as $line) {
                if (empty(trim($line))) continue;
                
                $parts = explode('|', $line);
                if (count($parts) === 2 && trim($parts[0]) === $current_domain && trim($parts[1]) === $license_key) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            error_log('License validation error: ' . $e->getMessage());
            return false;
        }
    }

    private function is_localhost() {
        return in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
    }

    private function load_settings() {
        $defaults = array(
            'enable_plugin' => '1',
            'check_ip' => '1',
            'check_email' => '1',
            'check_phone' => '1',
            'time_restriction' => '24',
            'custom_message' => __('A customer can only place one order within 24 hours.', 'wc-prevent-fake-orders')
        );
        
        $this->settings = wp_parse_args(get_option('wc_pfo_settings'), $defaults);
    }

    private function init_functionality() {
        if ($this->settings['enable_plugin'] === '1') {
            add_action('woocommerce_checkout_process', array($this, 'validate_order'));
        }
    }

    public function validate_order() {
        try {
            if (!isset($this->settings['enable_plugin']) || $this->settings['enable_plugin'] !== '1') {
                return;
            }

            $customer_ip = $_SERVER['REMOTE_ADDR'];
            $billing_email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
            $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';

            $args = array(
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'date_created' => '>' . (time() - absint($this->settings['time_restriction']) * HOUR_IN_SECONDS),
            );

            if ($this->settings['check_ip'] === '1') $args['customer_ip'] = $customer_ip;
            if ($this->settings['check_email'] === '1' && !empty($billing_email)) $args['billing_email'] = $billing_email;
            if ($this->settings['check_phone'] === '1' && !empty($billing_phone)) $args['billing_phone'] = $billing_phone;

            $orders = wc_get_orders($args);

            if (!empty($orders)) {
                $message = !empty($this->settings['custom_message']) ? $this->settings['custom_message'] : __('Order restriction in effect.', 'wc-prevent-fake-orders');
                wc_add_notice($message, 'error');
            }
        } catch (Exception $e) {
            error_log('WC Prevent Fake Orders validation error: ' . $e->getMessage());
        }
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['enable_plugin'] = isset($input['enable_plugin']) ? '1' : '0';
        $sanitized['check_ip'] = isset($input['check_ip']) ? '1' : '0';
        $sanitized['check_email'] = isset($input['check_email']) ? '1' : '0';
        $sanitized['check_phone'] = isset($input['check_phone']) ? '1' : '0';
        $sanitized['time_restriction'] = isset($input['time_restriction']) ? absint($input['time_restriction']) : 24;
        $sanitized['custom_message'] = isset($input['custom_message']) ? sanitize_textarea_field($input['custom_message']) : '';
        
        return $sanitized;
    }

    public function render_settings_section() {
        echo '<p>' . __('Configure how the plugin prevents fake orders.', 'wc-prevent-fake-orders') . '</p>';
    }

    public function render_settings_field($args) {
        try {
            $id = $args['id'];
            $field = $args['field'];
            $value = isset($this->settings[$id]) ? $this->settings[$id] : '';
            $name = 'wc_pfo_settings[' . esc_attr($id) . ']';
            
            switch ($field['type']) {
                case 'checkbox':
                    echo '<label>';
                    echo '<input type="checkbox" name="' . $name . '" value="1" ' . checked($value, '1', false) . '>';
                    echo isset($field['description']) ? ' ' . esc_html($field['description']) : '';
                    echo '</label>';
                    break;
                    
                case 'textarea':
                    echo '<textarea name="' . $name . '" class="large-text" rows="3"';
                    echo isset($field['attrs']) ? ' ' . $field['attrs'] : '';
                    echo '>' . esc_textarea($value) . '</textarea>';
                    if (isset($field['description'])) {
                        echo '<p class="description">' . esc_html($field['description']) . '</p>';
                    }
                    break;
                    
                case 'number':
                    echo '<input type="number" name="' . $name . '" value="' . esc_attr($value) . '"';
                    echo isset($field['attrs']) ? ' ' . $field['attrs'] : '';
                    echo '>';
                    if (isset($field['description'])) {
                        echo '<p class="description">' . esc_html($field['description']) . '</p>';
                    }
                    break;
                    
                default:
                    echo '<input type="text" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text">';
                    if (isset($field['description'])) {
                        echo '<p class="description">' . esc_html($field['description']) . '</p>';
                    }
            }
        } catch (Exception $e) {
            echo '<p class="error">' . __('Error rendering field.', 'wc-prevent-fake-orders') . '</p>';
            error_log('WC Prevent Fake Orders field rendering error: ' . $e->getMessage());
        }
    }
}

// Initialize with error handling
try {
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        new WC_Prevent_Fake_Orders();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            _e('WooCommerce Prevent Fake Orders requires WooCommerce to be installed and active.', 'wc-prevent-fake-orders');
            echo '</p></div>';
        });
    }
} catch (Exception $e) {
    add_action('admin_notices', function() use ($e) {
        echo '<div class="notice notice-error"><p>';
        _e('Failed to initialize WooCommerce Prevent Fake Orders plugin.', 'wc-prevent-fake-orders');
        echo '</p></div>';
    });
    error_log('WC Prevent Fake Orders initialization error: ' . $e->getMessage());
}