<?php
/**
 * Plugin Name: USPS Labels
 * Description: Generate USPS Labels
 * Version: 0.0.4
 * Author: kilbot
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: my-plugin
 */

namespace USPS_Labels;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Init {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_settings' ) );
        add_action( 'init', array( $this, 'add_custom_rewrite_rule' ) );
        add_action( 'wp', array( $this, 'wp_init' ) );
    }

    public function load_settings() {
        // Checks if WooCommerce is installed.
        if ( class_exists( 'WC_Integration' ) ) {
            include_once 'includes/settings.php';
            add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
        }
    }

    /**
     * Add a new integration to WooCommerce.
     */
    public function add_integration( $integrations ) {
        $integrations[] = 'USPS_Labels\Settings';
        return $integrations;
    }

    /**
     * Add rewrite rule for My Account > Shipping Label page
     */
    public function add_custom_rewrite_rule() {
        add_rewrite_endpoint( 'print-shipping-label', EP_ROOT | EP_PAGES );
        add_rewrite_endpoint( 'track-shipping', EP_ROOT | EP_PAGES );
    }

    /**
     * Initialize My Account page
     */
    public function wp_init() {
        if ( function_exists('is_account_page') && is_account_page() ) {
            include_once 'includes/my-account.php';
            new MyAccount();
        }
    }

}

// Initialize the plugin
Init::get_instance();
