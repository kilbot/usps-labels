<?php

namespace USPS_Labels;

use WC_Order;
use WC_Shortcode_My_Account;

class MyAccount {

    public function __construct() {
        if ( !current_user_can('manage_options') ) {
            return;
        }

        add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_print_label_button' ), 10, 2 );
        add_filter( 'woocommerce_account_menu_item_classes', array( $this, 'shipping_label_menu_item' ), 10, 2 );
        add_action( 'woocommerce_account_print-shipping-label_endpoint', array( $this, 'print_shipping_label' ) );
        add_filter( 'the_title', array( $this, 'shipping_label_title' ) );
    }

    /**
     * Add a button to the My Account page to print USPS label
     * @param array $actions Existing actions
     * @param WC_Order $order WooCommerce Order object
     * @return array Modified actions
     */
    function add_print_label_button( array $actions, WC_Order $order ) {
        $actions['custom_action'] = [
            'url'  => wc_get_endpoint_url( 'print-shipping-label', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ),
            'name' => __( 'Print Shipping Label', 'usps-labels' )
        ];

        return $actions;
    }

    /**
     *
     */
    public function shipping_label_menu_item( $classes, $endpoint ) {
        global $wp;

        if ( isset( $wp->query_vars['print-shipping-label'] ) ) {
            if ( 'orders' === $endpoint ) {
                $classes[] = 'is-active';
            }
        }

        return $classes;
    }

    /**
     *
     */
    public function shipping_label_title( $title ) {
        global $wp;

        if ( in_the_loop() && isset($wp->query_vars['print-shipping-label'] ) ) {
            $title = __( 'Print Shipping Label', 'usps-labels' );
        }

        return $title;
    }

    /**
     *
     */
    function print_shipping_label( $order_id ) {
        WC_Shortcode_My_Account::edit_address('shipping');
    }
}
