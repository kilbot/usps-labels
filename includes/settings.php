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
            'merchant_account_number' => array(
                'title'       => __( 'Merchant Account Code', 'usps-labels' ),
                'type'        => 'text',
                'description' => __( 'Enter your USPS Merchant Account Code', 'usps-labels' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'mid' => array(
                'title'       => __( 'MID', 'usps-labels' ),
                'type'        => 'password',
                'description' => __( 'Enter your USPS Mailer Identifier.', 'usps-labels' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'merchandise_description' => array(
                'title'       => __( 'Merchandise Description', 'usps-labels' ),
                'type'        => 'textarea',
                'description' => __( 'Describe the merchandise being shipped.', 'usps-labels' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
        );
    }

    public function admin_options() {
        parent::admin_options();

        // Get the USPS API credentials from the settings
        $merchant_account_number = $this->get_option( 'merchant_account_number' );
        $mid = $this->get_option( 'mid' );
        $merchandise_description = $this->get_option( 'merchandise_description' );

        if ( ! $merchant_account_number || ! $mid ) {
            return;
        }

        // Define the XML request payload with the USPS API credentials
        $xml_request = <<<XML
<ExternalReturnLabelRequest>
<CustomerName>Nash Rambler</CustomerName> 
<CustomerAddress1>475 L’Enfant Plaza SW</CustomerAddress1> 
<CustomerAddress2>Rm 5411 </CustomerAddress2> 
<CustomerCity>Washington</CustomerCity> 
<CustomerState>DC</CustomerState> 
<CustomerZipCode>20260</CustomerZipCode> 
<MerchantAccountCode>$merchant_account_number</MerchantAccountCode> 
<MID>$mid</MID> 
<LabelDefinition>4X6</LabelDefinition> 
<ServiceTypeCode>020</ServiceTypeCode> 
<MerchandiseDescription>$merchandise_description</MerchandiseDescription> 
<InsuranceAmount></InsuranceAmount> 
<AddressOverrideNotification>true</AddressOverrideNotification> 
<PackageInformation></PackageInformation> 
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
        $api_url = add_query_arg( array(
            // 'API' => 'USPSReturnsLabel',
            'externalReturnLabelRequest' => urlencode( $xml_request ),
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
        if ( isset($xml->ReturnLabel) && $pdf_data = (string) $xml->ReturnLabel ) {
            echo "<iframe src='data:application/pdf;base64," . $pdf_data . "' width='100%' height='600px'></iframe>";
            return;
        }

        // Check for errors in the XML response
        elseif ( $xml->getName() === 'Error' ) {
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

            // Added: Check if LabelImage is missing in XML response
        elseif ( ! isset( $xml->ReturnLabel ) ) {
            echo '<p>' . esc_html__('Error: ReturnLabel is missing in the response', 'usps-labels') . '</p>';
            return;
        }

        // Unknown error
        echo '<p>' . esc_html__( 'Unknown error', 'usps-labels'  ) . '</p>';
    }
}
