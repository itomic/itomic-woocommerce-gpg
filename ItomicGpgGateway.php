<?php

class ItomicGpgGateway extends WC_Payment_Gateway {

    // Setup our Gateway's id, description and other values
    function __construct() {

        // The global ID for this Payment method
        $this->id = "itomic_gpg_gateway";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __( "GPG Payment", 'itomic-gpg-gateway' );

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __( "GPG Payment Payment Gateway Plug-in for WooCommerce", 'itomic-gpg-gateway' );

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __( "GPG Payment", 'itomic-gpg-gateway' );

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // Supports the default credit card form
        $this->supports = array( 'default_credit_card_form' );

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
        
        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }
        
        // Lets check for SSL
        add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );

        add_filter( 'woocommerce_credit_card_form_fields', array($this, 'credit_card_form_fields'), 10, 2 ); 
        
        // Save settings
        if ( is_admin() ) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }       

    } // End __construct()

        // define the woocommerce_credit_card_form_fields callback 
    function credit_card_form_fields( $default_fields, $id ) { 
        // make filter magic happen here... 
        $cards = array();
        $extra = array();

        foreach ($this->settings['cards'] as $icon){
            $cards[] = '<img src="'.plugin_dir_url(__FILE__).'icons/'.$icon.'.png" width="100">';
        }

        if(!empty($cards)){
            $extra['card-icon'] = '<p class="form-row form-row-wide">'.implode('', $cards).'</p>';
        }
        
        $extra['card-name-field'] = '<p class="form-row form-row-wide">
            <label for="' . esc_attr( $id ) . '-card-name">' . __( 'Name on card', 'itomic-gpg-gateway') . ' <span class="required">*</span></label>
            <input id="' . esc_attr( $id ) . '-card-name" class="input-text wc-credit-card-form-card-name" type="text" maxlength="20" autocomplete="off" placeholder="" name="' . $this->id . '-card-name' . '" style="font-size: 1.5em; padding: 8px;" />
        </p>';

        return array_merge($extra, $default_fields); 
    }
             
    // add the filter 
    

    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'     => __( 'Enable / Disable', 'itomic-gpg-gateway' ),
                'label'     => __( 'Enable this payment gateway', 'itomic-gpg-gateway' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'title' => array(
                'title'     => __( 'Title', 'itomic-gpg-gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Payment title the customer will see during the checkout process.', 'itomic-gpg-gateway' ),
                'default'   => __( 'Credit card', 'itomic-gpg-gateway' ),
            ),
            'description' => array(
                'title'     => __( 'Description', 'itomic-gpg-gateway' ),
                'type'      => 'textarea',
                'desc_tip'  => __( 'Payment description the customer will see during the checkout process.', 'itomic-gpg-gateway' ),
                'default'   => __( 'Pay securely using your credit card.', 'itomic-gpg-gateway' ),
                'css'       => 'max-width:350px;'
            ),
            'cards' => array(
                'title'     => __( 'Credit Cards', 'itomic-gpg-gateway' ),
                'label'     => __( 'Credit Cards', 'itomic-gpg-gateway' ),
                'type'      => 'multiselect',
                'options'   => array("visa" => "Visa", "mc" => "Master Card", "amex" => "AMEX")
            ),
            'recipient_id' => array(
                'title'     => __( 'Recipient email, aka gpg key', 'itomic-gpg-gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Email used with the gpg key.', 'itomic-gpg-gateway' ),
            ),
            'gpg_path' => array(
                'title'     => __( 'System path to gpg', 'itomic-gpg-gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Normally /usr/bin/gpg', 'itomic-gpg-gateway' ),
                'default'   => __( '/usr/bin/gpg', 'itomic-gpg-gateway' ),
            ),
            'gpg_options' => array(
                'title'     => __( 'System path to gpg', 'itomic-gpg-gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Example --batch --always-trust --no-permission-warning ', 'itomic-gpg-gateway' ),
                'default'   => __( '--batch --always-trust --no-permission-warning ', 'itomic-gpg-gateway' ),
            ),
            'gpg_config_path' => array(
                'title'     => __( 'Config path to gpg', 'itomic-gpg-gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Normally /home/username/.gnupg', 'itomic-gpg-gateway' ),
            ),
        );      
    }

    /**
     * [card_to_type description]
     * @param  [type] $card_number [description]
     * @return [type]              [description]
     */
    protected function card_to_type($card_number, $default = 'UNKNOWN')
    {
        $firstNum = substr($card_number, 0, 1);
        
        if($firstNum == 3){
            return 'amex';
        } 

        if($firstNum == 4){
            return 'visa';
        } 

        if($firstNum == 5){
            return 'mc';
        }

        return $default;
    }
    
    // Submit payment and handle response
    public function process_payment( $order_id ) {
        global $woocommerce;
        
        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );
        

        //build up our email
        //$amount = number_format($customer_order->order_total, 2);
        $name = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
        $amount = $customer_order->order_total;
        $receiptid = $customer_order->get_order_number();


        $card_number = $this->input_post('itomic_gpg_gateway-card-number', '');
        $cart_type = $this->card_to_type($card_number);
        $cart_type = strtoupper($cart_type);

        $emailText = "
        Below are the Credit Card Details for this order:
        
        Order Number: $receiptid
        Order from: {$name}
        ------------------------
        Card Type : {$card_type}
        Name on Card : {$_POST['itomic_gpg_gateway-card-name']}
        Card Number : {$_POST['itomic_gpg_gateway-card-number']}
        Expiry (month/year) : {$_POST['itomic_gpg_gateway-card-expiry']}
        Security Code : {$_POST['itomic_gpg_gateway-card-cvc']}
        Amount to bill : \\$ {$amount}
        ------------------------

        ";

        $result = $this->encrypt($emailText);

        if($result !== false){

            $mail_result = wp_mail(
                $this->recipient_id,
                "Credit Card Details for {$name} -- ENCRYPTED INFORMATION --",
                $result
            );

            if($mail_result){
                // Payment has been successful
                $customer_order->add_order_note(
                    __('Encrypted credit card email sent.', 
                       'itomic-gpg-gateway' 
                    ));
                                                     
                // Mark order as Paid
                $customer_order->payment_complete();

                // Empty the cart (Very important step)
                $woocommerce->cart->empty_cart();

                // Redirect to thank you page
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $customer_order ),
                );

            } else {
                // Transaction was not succesful
                // Add notice to the cart
                wc_add_notice('Error sending email notification, please contact us if you continue to have issues.', 'error' );
                // Add note to the order for your reference
                $customer_order->add_order_note( 'Error: could not send encrypt message');                
            }

        } else {
            // Transaction was not succesful
            // Add notice to the cart
            wc_add_notice('Error processing your card, please contact us if you continue to have issues.', 'error' );
            // Add note to the order for your reference
            $customer_order->add_order_note( 'Error: could not encrypt message');
        }
            

    }
    
    // Validate fields
    public function validate_fields() {

        $card_name = $this->input_post('itomic_gpg_gateway-card-name', false);
        $card_number = $this->input_post('itomic_gpg_gateway-card-number', false);
        $expiry_date = $this->input_post('itomic_gpg_gateway-card-expiry', false); 
        $cvc = $this->input_post('itomic_gpg_gateway-card-cvc', false);

        // name on card
        if(!$card_name){
            
            wc_add_notice(__(
                '<strong>Name on card</strong> is required', 
                'woocommerce'), 
            'error');
        }

        // card number
        if(!$card_number){
            wc_add_notice(
                __('<strong>Card number</strong> is required', 
                   'woocommerce'), 
            'error');
        } else {

            $cart_type = $this->card_to_type($card_number, false);

            if(empty($card_type) || !in_array($card_type, $this->settings['cards']))
                wc_add_notice(
                    __('<strong>Card number</strong> is not accepted', 
                       'woocommerce' ), 
                'error');
        }

        // expiry date
        if(empty($expiry_date)){

            wc_add_notice(
                __('<strong>Credit card expiry</strong> is required', 
                   'woocommerce'), 
            'error');

        } else {

            list($month, $year) = explode("/", $expiry_date);

            if(!empty($month) && !empty($year) && $month <= 12){

                $getExpiry = DateTime::createFromFormat('y-m-d', $year . '-' . $month . '-01');
                $getExpiry->modify('last day of this month');
                $today = new DateTime("now");

                if($today > $getExpiry){

                    wc_add_notice(
                        __('<strong>Credit card is expired</strong>', 
                           'woocommerce' ), 
                    'error');
                }

            }else{
                 wc_add_notice(
                    __('<strong>Credit card expiry</strong> is not valid', 
                       'woocommerce' ), 
                'error' );
            }
        }

        // Card Code
        if(!$cvc){

            wc_add_notice(
                __('<strong>Card code</strong> is required',
                   'woocommerce'), 
            'error' );

        }else{

            if(strlen($cvc) < 3){
                wc_add_notice(
                    __('<strong>Card code</strong> is not valid', 
                        'woocommerce' ), 
                'error');
            }
        }

        return true;
    }
    
    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if( $this->enabled == "yes" ) {
            if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
            }
        }       
    }

    /**
     * Get Input
     * @param  string  $name    
     * @param  boolean $default 
     * @return mixed           
     */
    protected function input_post($name, $default = false){

        if(empty($_POST[$name])){
            return $default;
        }

        return $_POST[$name];
    }

    /**
     * Encrypts the data (in $data). Returns the datatype 'false' on error
     * @return string | false on error
     */
    protected function encrypt($data) {

        $email = escapeshellcmd($this->recipient_id);

        $command = array();
        $command[] = "echo \"$data\" | ";
        $command[] = escapeshellcmd($this->gpg_path);
        $command[] = escapeshellcmd($this->gpg_options);
        $command[] = " --homedir ".escapeshellcmd($this->gpg_config_path);
        $command[] = "-a -r \"{$email}\" -e 2>&1";

        $run = implode(' ', $command);

        $encryptedData = shell_exec($run);


        if (is_array($encryptedData)) {
            $encryptedData = implode("\n", $encryptedData);
        }

        if (!$encryptedData) {
            return false;
        }

        return $encryptedData;

    }


} // End of ItomicGpgGateway
