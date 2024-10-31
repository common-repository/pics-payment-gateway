<?php
/*
Plugin Name: Pics Payment Gateway
Description: Pics Payment Gateway allows you to accept payment on your Woocommerce store via Visa, MasterCard, AMEX, eZcash, mCash & Internet banking services.
Version: 1.0.0
Author: Vertical Tech Solutions (Private) Limited
Author URI: https://pos.pics.lk
*/

add_action('plugins_loaded', 'woocommerce_gateway_pics_init', 0);
define('pics_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_pics_init() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Gateway class
     */
    class WC_Gateway_Pics extends WC_Payment_Gateway {

        const SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR = -1;

        const SUB_PROCESS_ERR_UNKNOWN = "Unknown error";

        /**
         * Make __construct()
         **/
        public function __construct(){

            $this->id 					= 'pics';
//			$this->icon 				= '';
            $this->method_title 		= 'Pics';
            $this->method_description	= 'The eCommerce Payment Service Provider of Sri Lanka';

            // Checkout has fields?
            $this->has_fields 			= false;

            $this->init_form_fields();
            $this->init_settings();

            $supports_array				= array('subscriptions', 'products');
            $this->supports				= $supports_array;

            // Special settings if gateway is on Test Mode
            $test_title			= '';
            $test_description	= '';

            if ( $this->settings['test_mode'] == 'yes' ) {
                $test_title 		= '';
                $test_description 	= '<br/><br/>(Sandbox Mode is Active. You will not be charged.)<br/>';
            }

            if ( $this->settings['onsite_checkout'] == 'yes'){
                $this->onsite_checkout_enabled = true;
            }
            else{
                $this->onsite_checkout_enabled = false;
            }

            // Title as displayed on Frontend
            $this->title 			= $this->settings['title'].$test_title;
            // Description as displayed on Frontend
            $this->description 		= $this->settings['description'].$test_description;
            $this->merchant_id 		= $this->settings['merchant_id'];
            $this->secret 		    = $this->settings['secret'];
            // Define the Redirect Page.
            $this->redirect_page	= $this->settings['redirect_page'];

            $this->msg['message']	= '';
            $this->msg['class'] 	= '';

            add_action('init', array(&$this, 'check_pics_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_pics_response')); //update for woocommerce >2.0
            add_action('woocommerce_gateway_icon', array($this, 'modify_gateway_icon_css'), 10, 2);

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
            }
            add_action('woocommerce_receipt_pics', array(&$this, 'receipt_page'));
        }

        function modify_gateway_icon_css($icon_html, $payment_gateway_id){
            if($payment_gateway_id != $this->id){
                return $icon_html;
            }

            $new_css = 'class="ph-logo-style" src';
            $icon_html = preg_replace('/(src){1}/', $new_css, $icon_html);

            return $icon_html;
        }

        function get_pics_live_url(){
            return 'https://payme.pics.lk/payments/v1/thirdParty/raiseInvoice';
        }

        function get_pics_sandbox_url(){
            return 'https://st-payme.verticaltechsolutions.com/payments/v1/thirdParty/raiseInvoice';
        }

        /**
         * Initiate Form Fields in the Admin Backend
         **/
        function init_form_fields(){

            $this->form_fields = array(
                // Activate the Gateway
                'enabled' => array(
                    'title' 			=> __('Enable/Disable', 'woo_pics'),
                    'type' 			=> 'checkbox',
                    'label' 			=> __('Enable PICS', 'woo_pics'),
                    'default' 		=> 'yes',
                    'description' 	=> 'Show in the Payment List as a payment option'
                ),
                // Title as displayed on Frontend
                'title' => array(
                    'title' 			=> __('Title', 'woo_pics'),
                    'type'			=> 'text',
                    'default' 		=> __('Pics', 'woo_pics'),
                    'description' 	=> __('This controls the title which the user sees during checkout.', 'woo_pics'),
                    'desc_tip' 		=> true
                ),
                // Description as displayed on Frontend
                'description' => array(
                    'title' 			=> __('Description:', 'woo_pics'),
                    'type' 			=> 'textarea',
                    'default' 		=> __('Pay by Visa, MasterCard, AMEX, eZcash, mCash or Internet Banking via Pics.', 'woo_pics'),
                    'description' 	=> __('This controls the description which the user sees during checkout.', 'woo_pics'),
                    'desc_tip' 		=> true
                ),
                // LIVE Key-ID
                'merchant_id' => array(
                    'title' 		=> __('Merchant ID', 'woo_pics'),
                    'type' 			=> 'text',
                    'description' 	=> __('Your Pics Merchant ID'),
                    'desc_tip' 		=> true
                ),
                // LIVE Key-Secret
                'secret' => array(
                    'title' 			=> __('Secret Key', 'woo_pics'),
                    'type' 			=> 'text',
                    'description' 	=> __('Secret word you set in your Pics Account'),
                    'desc_tip' 		=> true
                ),
                // Mode of Transaction
                'test_mode' => array(
                    'title'         => __('Sandbox Mode', 'woo_pics'),
                    'type'          => 'checkbox',
                    'label'         => __('Enable Sandbox Mode', 'woo_pics'),
                    'default'       => 'yes',
                    'description'   => __('Pics sandbox can be used to test payments', 'woo_pics'),
                    'desc_tip' 		=> true
                ),
                // Page for Redirecting after Transaction
                'redirect_page' => array(
                    'title' 			=> __('Return Page'),
                    'type' 			=> 'select',
                    'options' 		=> $this->pics_get_pages('Select Page'),
                    'description' 	=> __('Page to redirect the customer after payment', 'woo_pics'),
                    'desc_tip' 		=> true
                )
            );
        }

        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
        public function admin_options(){
            echo '<h3>'.__('Pics', 'woo_pics').'</h3>';
            echo '<p>'.__('WooCommerce Payment Plugin of Pics Payment Gateway, The Digital Payment Service Provider of Sri Lanka').'</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * Availability Check
         *
         * You can hook into this
         */
        public function is_available(){
            if (parent::is_available()){
                $_avail = apply_filters('pics_filter_is_available', true);
                return $_avail;
            }
            else{
                return false;
            }
        }

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
        function payment_fields(){
            if( $this->description ) {
                echo wpautop( wptexturize( $this->description ) );
            }
        }

        /**
         * Receipt Page
         **/
        function receipt_page($order){
            if ($this->onsite_checkout_enabled){
                echo '<p><strong>' . __('Thank you for your order.', 'woo_pics').'</strong>
				</br>' . __('Click the below button to checkout with Pics.', 'woo_pics').'
				</p>';
            }
            else{
                echo '<p><strong>' . __('Thank you for your order.', 'woo_pics').'</strong><br/>' . __('The payment page will open soon.', 'woo_pics').'</p>';
            }
            echo $this->generate_pics_form($order);
        }

        /**
         * Generate button link
         **/
        function generate_pics_form($order_id){
            global $woocommerce;
            $order = new WC_Order( $order_id );

            $redirect_url = $order->get_checkout_order_received_url();

            // Redirect URL : For WooCoomerce 2.0
            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                $notify_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
            }

            $productinfo = "Order $order_id";

            $txnid = $order_id.'_'.date("ymds");

            $effective_merchant_id = apply_filters('pics_filter_merchant_id', $this->merchant_id);
            $effective_merchant_secret = apply_filters('pics_filter_merchant_secret', $this->secret, $order_id, $effective_merchant_id);
            $effective_test_mode = apply_filters('pics_filter_test_mode', $this->settings['test_mode'], $effective_merchant_id);

            $pics_args = array(
                'merchant_uuid' => $effective_merchant_id,
//                'return_url' => $redirect_url,

                'first_name' => $order -> get_billing_first_name(),
                'last_name' => $order -> get_billing_last_name(),
                'email' => $order -> get_billing_email(),
                'phone' => $order -> get_billing_phone(),
                'address' => $order->get_billing_address_1() . (
                    ($order->get_billing_address_2() != "") ? ', ' . $order->billing_address_2 : ''
                    ),
                'city' => $order -> get_billing_city(),
                'country' => $order -> get_billing_country(),
                'currency' => get_woocommerce_currency(),
                'invoice_amount' => ($order->get_total()),
                'invoice_number' => $order_id,
                "invoiced_at" => (new DateTime())->format('Y-m-d H:i:s'),
                "back_reference_key" => bin2hex(random_bytes(50)),
                "operation_mode" => $this->settings['test_mode'] == 'yes' ? "sandbox" : "live"
            );

            $signature = md5($pics_args['merchant_uuid'] . strtoupper(md5($effective_merchant_secret)) . $pics_args['invoice_amount'] . $pics_args['invoice_number'] . $pics_args['invoiced_at'] . $pics_args['back_reference_key']);
            $pics_args['signature'] = $signature;

            $subscription_process_status = null;
            $subscription_err = null;

            if ($subscription_process_status == self::SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR){
                $target_err_text = self::SUB_PROCESS_ERR_UNKNOWN;
                if (!empty($subscription_err)){
                    $target_err_text = $subscription_err;
                }

                return sprintf(
                    '<ul class="woocommerce-error" role="alert"><li><b>Cannot Process Payment</b><br>%s</li></ul>',
                    $target_err_text);
            }

            if (!$this->onsite_checkout_enabled){
                $pics_args_array = array();
                foreach($pics_args as $key => $value){
                    $pics_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }

                $effective_url = "";
                if ($effective_test_mode == 'yes'){
                    $effective_url = $this->get_pics_sandbox_url();
                }
                else{
                    $effective_url = $this->get_pics_live_url();
                }

                return '<form id="payment-form" action="'. $effective_url .'">'. implode('', $pics_args_array) .'<input type="submit" class="button-alt" id="submit_pics_payment_form" value="'.__('Pay via PICS', 'woo_pics').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woo_pics').'</a></form>
                        <script type="text/javascript">
					    jQuery("#payment-form").submit(function(e) {
					        e.preventDefault();
                            var form = jQuery(this);
                            var url = form.attr("action");
                            var data = form.serialize();
					   
                            jQuery.ajax({
                                type : "POST",
                                url : url,
                                data : data,
                                success : function(response) {
                                if(response.code == 200){
                                    window.location = response.data.payment_link;
                                }else{
                                    alert(\'Please Check PICS Configuration!\');
                                }
                                }				        
                            })
					    })
    					</script>   
                        ';
            }
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);

            if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
                $checkout_payment_url = $order->get_checkout_payment_url( true );
            } else {
                $checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
            }

            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                    'order',
                    $order->id,
                    add_query_arg(
                        'key',
                        $order->order_key,
                        $checkout_payment_url
                    )
                )
            );
        }

        /**
         * Check for valid gateway server callback
         **/
        function check_pics_response(){
            global $woocommerce;

            $merchant_id = $this->merchant_id;
            $merchant_secret = $this->secret;
            $invoice_number = sanitize_text_field($_GET['invoice_number']);
            $invoice_amount = sanitize_text_field($_GET['invoice_amount']);
            $currency = sanitize_text_field($_GET['currency']);
            $payment_status = sanitize_text_field($_REQUEST['payment_status']);
            $transaction_id = sanitize_text_field($_REQUEST['transaction_id']);
            $signature = sanitize_text_field($_GET['signature']);

            if ($invoice_number && $transaction_id) {
                $order_id = $invoice_number;
                if ($order_id != '') {
                    try {
                        $order = new WC_Order( $order_id );

                        $localSignature = strtoupper( md5(
                            $merchant_id
                            . $invoice_number
                            . $invoice_amount
                            . $currency
                            . strtoupper(md5($merchant_secret))
                        ) );

                        if ($localSignature == $signature) {
                            $status = $payment_status;

                            if ($status == "2") {
                                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                                $this->msg['class'] = 'woocommerce-message';

                                if ($order->status == 'processing') {
                                    $order->add_order_note('PICS Payment ID: '.esc_attr($transaction_id));
                                } else {
                                    $order->payment_complete();
                                    $order->add_order_note('Pics payment successful.<br/>Pics Payment ID: '.esc_attr($transaction_id));
                                    $woocommerce->cart->empty_cart();
                                }
                            } else if ($status == "0") {
                                $this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order through eMail";
                                $this->msg['class'] = 'woocommerce-info';
                                $order->add_order_note('PICS payment status is pending<br/>PICS Payment ID: '.esc_attr($transaction_id));
                                $order->update_status('on-hold');
                                $woocommerce->cart->empty_cart();
                            } else {
                                $this->msg['class'] = 'woocommerce-error';
                                $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order->add_order_note('Transaction ERROR. Status Code: '. $status);
                            }
                        } else {
                            $this->msg['class'] = 'error';
                            $this->msg['message'] = "Security Error. Illegal access detected.";
                            $order->add_order_note('Checksum ERROR: '.json_encode($_REQUEST));
                        }

                    } catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
                }
            }
        }

        /**
         * Get Page list from WordPress
         **/
        function pics_get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_post($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        private function convert_timestamp_readable($ts){
            $dt = new DateTime("@$ts");
            $format = 'Y-m-d H:i:s e';
            return $dt->format($format);
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_gateway_pics_gateway($methods) {
        $methods[] = 'WC_Gateway_Pics';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_pics_gateway' );

}

/**
 * 'Settings' link on plugin page
 **/
add_filter( 'plugin_action_links', 'pics_add_action_plugin', 10, 5 );
function pics_add_action_plugin( $actions, $plugin_file ) {
    static $plugin;

    if (!isset($plugin))
        $plugin = plugin_basename(__FILE__);
    if ($plugin == $plugin_file) {
        $settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_pics">' . __('Settings') . '</a>');
        $actions = array_merge($settings, $actions);
    }

    return $actions;
}
