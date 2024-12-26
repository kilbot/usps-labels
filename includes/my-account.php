<?php

namespace USPS_Labels;

use WC_Order;
use WC_Shortcode_My_Account;

class MyAccount {

    public function __construct() {
        add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_print_label_button' ), 10, 2 );
        add_filter( 'woocommerce_account_menu_item_classes', array( $this, 'shipping_label_menu_item' ), 10, 2 );
        add_action( 'woocommerce_account_print-shipping-label_endpoint', array( $this, 'print_shipping_label' ) );
        add_action( 'woocommerce_account_track-shipping_endpoint', array( $this, 'track_shipping' ) );
        add_filter( 'the_title', array( $this, 'shipping_label_title' ) );
    }

    /**
     * Add a button to the My Account page to print USPS label
     * @param array $actions Existing actions
     * @param WC_Order $order WooCommerce Order object
     * @return array Modified actions
     */
    function add_print_label_button( array $actions, WC_Order $order ) {
        $usps_tracking_values = get_post_meta( $order->get_ID(), 'usps_tracking', false );

        if ( ! empty( $usps_tracking_values ) ) {
            $actions['track-shipping'] = array(
                'url'  => wc_get_endpoint_url( 'track-shipping', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ),
                'name' => __( 'Track Shipping', 'usps-labels' )
            );
        } elseif ( $order->get_status() === 'processing') {
            $actions['print-shipping-label'] = array(
                'url'  => wc_get_endpoint_url( 'print-shipping-label', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ),
                'name' => __( 'Print Shipping Label', 'usps-labels' )
            );
        }

        return $actions;
    }

    /**
     *
     */
    public function shipping_label_menu_item( $classes, $endpoint ) {
        global $wp;

        if ( isset( $wp->query_vars['print-shipping-label'] ) || isset( $wp->query_vars['track-shipping'] ) ) {
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

        if ( in_the_loop() && isset($wp->query_vars['track-shipping'] ) ) {
            $title = __( 'Track Shipping', 'usps-labels' );
        }

        return $title;
    }

    /**
     *
     */
    function print_shipping_label( $order_id ) {
        // add shipping form to the page
        $this->print_shipping_address( $order_id );

        // Retrieve stored settings
        $settings = get_option('woocommerce_usps_labels_settings');

        // Get the merchant_account_number and mid from the settings array
        $merchant_account_number = $settings['merchant_account_number'] ?? null;
        $mid = $settings['mid'] ?? null;
        $merchandise_description = $settings['merchandise_description'] ?? null;


        // Fetch order details
        $order = wc_get_order($order_id);
        $shipping_address = $order->get_address('shipping');

        // Fetch WordPress admin details
        $admin_email = get_option('admin_email');
        if ( ! is_email( $admin_email ) ) {
            echo '<p>' . esc_html__('Error getting admin email.', 'usps-labels') . '</p>';
            return;
        }

        $admin_user = get_user_by('email', $admin_email);
        $admin_name = $admin_user->display_name;

        // Define the XML request payload with the USPS API credentials
        $xml_request = <<<XML
<ExternalReturnLabelRequest>
<CustomerName>{$shipping_address['first_name']} {$shipping_address['last_name']}</CustomerName>
<CustomerAddress1>{$shipping_address['address_1']}</CustomerAddress1>
<CustomerAddress2>{$shipping_address['address_2']}</CustomerAddress2>
<CustomerCity>{$shipping_address['city']}</CustomerCity>
<CustomerState>{$shipping_address['state']}</CustomerState>
<CustomerZipCode>{$shipping_address['postcode']}</CustomerZipCode>
<MerchantAccountCode>$merchant_account_number</MerchantAccountCode> 
<MID>$mid</MID> 
<LabelDefinition>4X6</LabelDefinition> 
<ServiceTypeCode>020</ServiceTypeCode> 
<MerchandiseDescription>{$merchandise_description}</MerchandiseDescription> 
<InsuranceAmount></InsuranceAmount> 
<AddressOverrideNotification>true</AddressOverrideNotification> 
<PackageInformation>{$order_id}</PackageInformation> 
<PackageInformation2></PackageInformation2> 
<CallCenterOrSelfService>Customer</CallCenterOrSelfService> 
<CompanyName></CompanyName> 
<Attention></Attention> 
<SenderName></SenderName>
<SenderEmail></SenderEmail>
<RecipientName></RecipientName>
<RecipientEmail></RecipientEmail>
<RecipientBCC></RecipientBCC>
</ExternalReturnLabelRequest>
XML;

        // URL-encode the XML string and append it to the USPS API endpoint URL as a query parameter
        $api_endpoint = 'https://returns.usps.com/services/GetLabel';
        $api_url = add_query_arg(array(
            'externalReturnLabelRequest' => urlencode($xml_request),
        ), $api_endpoint);

        // Send a GET request to the USPS API to generate the label
        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) {
            echo '<p>' . esc_html__('Error generating label:', 'usps-labels') . ' ' . esc_html($response->get_error_message()) . '</p>';
            return;
        }

        // Parse the API response to get the PDF data
        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);

        echo '<h3>' . esc_html__('Shipping Label', 'usps-labels') . '</h3>';

        // Get the PDF data as a text string
        if ( isset($xml->ReturnLabel) && $pdf_data = (string) $xml->ReturnLabel ) {
            
            echo "<iframe src='data:application/pdf;base64," . $pdf_data . "' width='100%' height='930px'></iframe>";
        } else {
            echo $body;
            echo '<p>' . esc_html__('Error: ReturnLabel is missing in the response', 'usps-labels') . '</p>';
        }
    }

    /**
     * Print Shipping Form
     */
    function print_shipping_address( $order_id ) {
        $order = wc_get_order($order_id);

        if( ! $order ) {
            echo '<p>' . esc_html__('Error: Order not found!', 'usps-labels') . '</p>';
            return;
        }

        if ( isset( $_POST['save_address'] ) && isset( $_POST['woocommerce-edit-address-nonce'] ) ) {
            // Verify the nonce.
            if ( ! wp_verify_nonce( $_POST['woocommerce-edit-address-nonce'], 'woocommerce-edit_address' ) ) {
                wc_add_notice( __( 'Your session has expired. Please try again.', 'woocommerce' ), 'error' );
            }

            // Get the submitted values from $_POST and sanitize them.
            $shipping_first_name = sanitize_text_field( $_POST['shipping_first_name'] );
            $shipping_last_name = sanitize_text_field( $_POST['shipping_last_name'] );
            $shipping_company = sanitize_text_field( $_POST['shipping_company'] );
            $shipping_address_1 = sanitize_text_field( $_POST['shipping_address_1'] );
            $shipping_address_2 = sanitize_text_field( $_POST['shipping_address_2'] );
            $shipping_city = sanitize_text_field( $_POST['shipping_city'] );
            $shipping_state = sanitize_text_field( $_POST['shipping_state'] );
            $shipping_postcode = sanitize_text_field( $_POST['shipping_postcode'] );
            $shipping_country = sanitize_text_field( $_POST['shipping_country'] );

            // Update the order's shipping address.
            $order->set_shipping_first_name( $shipping_first_name );
            $order->set_shipping_last_name( $shipping_last_name );
            $order->set_shipping_company( $shipping_company );
            $order->set_shipping_address_1( $shipping_address_1 );
            $order->set_shipping_address_2( $shipping_address_2 );
            $order->set_shipping_city( $shipping_city );
            $order->set_shipping_state( $shipping_state );
            $order->set_shipping_postcode( $shipping_postcode );
            $order->set_shipping_country( $shipping_country );

            // Save the order.
            $order->save();

        } else {
            // Populate the form with the order's shipping details.
            $_POST['shipping_first_name'] = $order->get_shipping_first_name();
            $_POST['shipping_last_name'] = $order->get_shipping_last_name();
            $_POST['shipping_company'] = $order->get_shipping_company();
            $_POST['shipping_address_1'] = $order->get_shipping_address_1();
            $_POST['shipping_address_2'] = $order->get_shipping_address_2();
            $_POST['shipping_city'] = $order->get_shipping_city();
            $_POST['shipping_state'] = $order->get_shipping_state();
            $_POST['shipping_postcode'] = $order->get_shipping_postcode();
            $_POST['shipping_country'] = $order->get_shipping_country();
        }
        
        WC_Shortcode_My_Account::edit_address('shipping');
    }

    /**
     *
     */
    function track_shipping( $order_id ) {
        $usps_tracking_values = get_post_meta( $order_id, 'usps_tracking', false );

        if ( !empty( $usps_tracking_values ) ) {

            // Loop through and echo each tracking number
            foreach ( $usps_tracking_values as $tracking_number ) {
                echo '<div>';
                echo '<h4 style="margin-top:0">Tracking Info for ' . $tracking_number . '</h4>';
                echo '<p><strong>Summary:</strong> Your item is out for delivery.</p>';
                echo '<p><strong>Expected Delivery:</strong> January 5, 2023, 3:00 pm</p>';
                echo '<p>';
                echo '<strong>Details:</strong>';
                echo '<li>January 1, 2023, 2:59 pm - Delivered, In/At Mailbox - SOMEWHERE, DC 20500</li>';
                echo '<li>January 1, 2023, 8:12 am - Out for Delivery - SOMEWHERE, DC 20500</li>';
                echo '<li>January 1, 2023, 7:12 am - Sorting Complete - SOMEWHERE, DC 20500</li>';
                echo '</ul>';
                echo '</p>';
            }
        } else {
            echo "No USPS tracking numbers found.";
        }
    }
}
