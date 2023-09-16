<?php
/**
 * Plugin Name: USPS Labels
 * Description: Generate USPS Labels
 * Version: 0.0.1
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
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {
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

}

// Initialize the plugin
Init::get_instance();
