<?php

namespace USPS_Labels;

use WC_Integration;

class Settings extends WC_Integration {
    /**
     * Init and hook in the integration.
     */
    public function __construct() {
        global $woocommerce;

        $this->id                 = 'usps_labels';
        $this->method_title       = __( 'USPS Labels', 'usps-labels' );
        $this->method_description = __( 'Enter your USPS API Credentials', 'usps-labels' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->username = $this->get_option( 'username' );
        $this->password = $this->get_option( 'password' );

        // Actions.
        add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
    }


    /**
     * Initialize integration settings form fields.
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'username' => array(
                'title'       => __( 'Username', 'usps-labels' ),
                'type'        => 'text',
                'description' => __( 'Enter your USPS Username.', 'usps-labels' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'password' => array(
                'title'       => __( 'Password', 'usps-labels' ),
                'type'        => 'password',
                'description' => __( 'Enter your USPS Password.', 'usps-labels' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
        );
    }

    public function admin_options() {
        parent::admin_options();

        // Get the USPS API credentials from the settings
        $username = $this->get_option( 'username' );
        $password = $this->get_option( 'password' );

        if ( ! $username || ! $password ) {
            return;
        }

        // Define the XML request payload with the USPS API credentials
        $xml_request = <<<XML
<USPSReturnsLabelRequest USERID="$username" PASSWORD="$password">
<Option/>
<Revision></Revision>
<ImageParameters>
<ImageType>PDF</ImageType>
</ImageParameters>
<CustomerFirstName>Cust First Name</CustomerFirstName>
<CustomerLastName>Cust Last Name</CustomerLastName>
<CustomerFirm>Customer Firm</CustomerFirm>
<CustomerAddress1/>
<CustomerAddress2>PO Box 100</CustomerAddress2>
<CustomerUrbanization/>
<CustomerCity>Washington</CustomerCity>
<CustomerState>DC</CustomerState>
<CustomerZip5>20260</CustomerZip5>
<CustomerZip4>1122</CustomerZip4>
<POZipCode>20260</POZipCode>
<AllowNonCleansedOriginAddr>false</AllowNonCleansedOriginAddr>
<RetailerATTN>ATTN: Retailer Returns Department</RetailerATTN>
<RetailerFirm>Retailer Firm</RetailerFirm>
<WeightInOunces>80</WeightInOunces>
<ServiceType>GROUND</ServiceType>
<Width>4</Width>
<Length>10</Length>
<Height>7</Height>
<Girth>2</Girth>
<Machinable>true</Machinable>
<CustomerRefNo>RMA%23: EE66GG87</CustomerRefNo>
<PrintCustomerRefNo>true</PrintCustomerRefNo>
<CustomerRefNo2> EF789UJK </CustomerRefNo2>
<PrintCustomerRefNo2>true</PrintCustomerRefNo2>
<SenderName>Sender Name for Email</SenderName>
<SenderEmail>senderemail@email.com</SenderEmail>
<RecipientName>Recipient of Email</RecipientName>
<RecipientEmail>recipientemail@email.com</RecipientEmail>
<TrackingEmailPDF>true</TrackingEmailPDF>
<ExtraServices>
<ExtraService></ExtraService>
</ExtraServices>
</USPSReturnsLabelRequest>
XML;

        // URL-encode the XML string and append it to the USPS API endpoint URL as a query parameter
        $api_endpoint = 'https://secure.shippingapis.com/ShippingAPI.dll';
        $api_url = add_query_arg( array(
            'API' => 'USPSReturnsLabel',
            'XML' => urlencode( $xml_request ),
        ), $api_endpoint );

        // Send a GET request to the USPS API to generate the label
        $response = wp_remote_get( $api_url );

        // Check for errors in the API response
        if ( is_wp_error( $response ) ) {
            echo '<p>' . esc_html__( 'Error generating label:', 'usps-labels' ) . ' ' . esc_html( $response->get_error_message() ) . '</p>';
            return;
        }

        // Parse the API response to get the PDF data
        $body = wp_remote_retrieve_body( $response );
        $xml = simplexml_load_string( $body );

        echo '<hr>';
        echo '<h2>' . esc_html__( 'Example USPS Return Label', 'usps-labels' ) . '</h2>';

        // Display the PDF in an iframe using a data URI
        if($pdf_data = (string) $xml->LabelImage) {
            echo "<iframe src='data:application/pdf;base64," . $pdf_data . "' width='100%' height='300px'></iframe>";
            return;
        }

        // Check for errors in the XML response
        if ($xml->getName() === 'Error') {
            $errorNumber = (string) $xml->Number;
            $errorDescription = (string) $xml->Description;
            // $errorSource = (string) $xml->Source;

            $errorMessage = "
                Error Number: $errorNumber <br>
                Description: $errorDescription <br>
            ";

            echo '<p>' . esc_html__( 'Error generating label:', 'usps-labels' ) . '</p>';
            echo '<p>' . $errorMessage . '</p>';
            return;
        }

        // Unknown error
        echo '<p>' . esc_html__( 'Unknown error', 'usps-labels'  ) . '</p>';
    }
}