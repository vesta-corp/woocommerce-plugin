<?php
/**
 * vesta payment setting page for admin
 */

if (!defined('ABSPATH')) {
    exit;
}
//unset the session varaible
if (!isset($_SESSION)) {
    session_start();
}
//include_once vesta_common_class.php;
require_once plugin_dir_path(__FILE__) . 'vesta_common_class.php';
require_once plugin_dir_path(__FILE__) . 'vesta_payment_risk_xml.php';
require_once plugin_dir_path(__FILE__) . 'XmlValidator.php';

/**
 * vesta class extend to Woocommerce
 */
class WC_Vesta_Payment_Gateway extends WC_Payment_Gateway
{
    //define APIs AS constant
    const SESSION_TAG            = 'GetSessionTags';
    const CHARGE_PAYMENT_REQUEST = 'ChargePaymentRequest';
    const DISPOSITION            = 'Disposition';
    const REVERSE_PAYMENT_REQUEST = 'ReversePaymentRequest';
    const ACCOUNT_NUMBER_TO_PERMANENT_TOKEN = 'AccountNumberToPermanentToken';
    const CHARGE_AUTH_CAPTURE = 'charge_auth_captured';
    const CHARGE_AUTH_ONLY = 'charge_auth_only';

    protected $xmlValidator;
    /**
     * constructor method
     */
    function __construct()
    {

        $this->xmlValidator	= XmlValidator::getXmlValidatorInstance();
        $this->id                                 = "wc_vesta_payment";
        $this->method_title                     = __("Vesta Payment", 'wc_vesta_payment');
        $this->method_description                 = __("Vesta Payment Gateway is a service provider allowing merchants to accept credit card.", 'wc_vesta_payment');
        $this->title                             = __($this->get_option('title'), 'wc_vesta_payment');
        //$this->icon                            = plugins_url('../assets/images/maestro.png', __FILE__ ) ; 
        $this->has_fields                         = true;
        $this->supports                         = array('default_credit_card_form', 'products',  'refunds', 'tokenization');
        $this->init_form_fields();
        $this->init_settings();
        $this->test_mode                        = 'yes' === $this->get_option('test_mode');
        $this->vesta_payment_username           = $this->test_mode ? $this->get_option('vesta_payment_username') : $this->get_option('vesta_production_username');
        $this->vesta_payment_password           = $this->test_mode ? $this->get_option('vesta_payment_password') : $this->get_option('vesta_production_password');
        $this->vesta_payment_api_url            = $this->test_mode ? rtrim($this->get_option('vesta_payment_api_url'), "/") : rtrim($this->get_option('vesta_production_api_url'), "/");
        $this->vesta_payment_authorize_only      = $this->get_option('vesta_payment_authorize_only');
        $this->vesta_payment_cardtypes           = $this->get_option('vesta_payment_cardtypes');
        $this->vesta_payment_merchant_routingId = $this->test_mode ? $this->get_option('vesta_payment_merchant_routingId') : $this->get_option('vesta_production_merchant_routingId');
        $this->description                       = $this->get_option('description');
        $this->debug                            = $this->get_option('debug');
        $this->save_card                        = $this->get_option('save_card');
        $this->vesta_production_username        = $this->get_option('vesta_production_username');
        $this->test_data_collector              = $this->get_option('vesta_payment_data_collector_url');
        $this->live_data_collector              = $this->get_option('vesta_production_data_collector_url');
        //$this->vesta_production_password        = $this->get_option('vesta_production_password');
        //$this->vesta_production_api_url         = $this->get_option('vesta_production_api_url');


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_thankyou', array($this, 'unset_session_after_order'));
    }

    /**
     * craete option
     *
     * @return void
     */
    public function admin_options()
    {
        ?>
    <h3><?php _e('Vesta Payment Gateway', 'wc_vesta_payment'); ?></h3>
    <p><?php _e('Vesta Payment is a payment gateway service provider allowing merchants to accept credit card payment.', 'wc_vesta_payment'); ?></p>
    <table class="form-table">
        <?php $this->generate_settings_html(); ?>
    </table>
<?php

}

/**
 * Method for add config field in admin
 *
 * @return void
 */
public function init_form_fields()
{
    $this->form_fields = array(
        'enabled' => array(
             'title'        => __('Enable / Disable', 'wc_vesta_payment'),
             'label'        => __('Enable this payment gateway', 'wc_vesta_payment'),
            'type'        => 'checkbox',
             'default'    => 'no',
         ),
         'title' => array(
             'title'        => __('Title', 'wc_vesta_payment'),
             'type'        => 'text',
             'desc_tip'    => __('Payment title of checkout process.', 'wc_vesta_payment'),
             'default'    => __('Vesta Payment Gateway', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'description' => array(
             'title'        => __('Description', 'wc_vesta_payment'),
             'type'        => 'textarea',
             'desc_tip'    => __('Payment title of checkout process.', 'wc_vesta_payment'),
             'default'    => __('Make payment through credit card with vesta gateway.', 'wc_vesta_payment'),
             'css'        => 'max-width:400px;'
         ),
         'vesta_payment_authorize_only' => array(
             'title'       => __('Payment action', 'wc_vesta_payment'),
             'type'        => 'select',
             'class'       => 'wc-enhanced-select',
             'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'wc_vesta_payment'),
             'default'     => 'yes',
             'desc_tip'    => true,
             'options'     => array(
                 'no'          => __('Authorize and Capture', 'wc_vesta_payment'),
                 'yes' => __('Authorize Only', 'wc_vesta_payment'),
             ),
         ),
        'test_mode' => array(
            'title'        => __('Enable test mode', 'wc_vesta_payment'),
            'label'        => __('Enable the sandbox/test mode', 'wc_vesta_payment'),
            'type'        => 'checkbox',
            'default'    => 'yes',
        ),
        'vesta_payment_username' => array(
            'title'        => __('Test Account Name', 'wc_vesta_payment'),
            'type'        => 'password',
            'desc_tip'    => __('This is the account name provided by vesta when you signed up for an account.', 'wc_vesta_payment'),
            'autocomplete'        => 'off',
            'custom_attributes' => array(
                'required' => __('required', 'wc_vesta_payment')
            ),
        ),
         'vesta_payment_password' => array(
             'title'        => __('Test Password', 'wc_vesta_payment'),
             'type'        => 'password',
             'desc_tip'    => __('This is the password provided by vesta when you signed up for an account.', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_payment_api_url' => array(
             'title'        => __('Test API URL', 'wc_vesta_payment'),
             'type'        => 'text',
             'desc_tip'    => __('This is the API URL provided by Vesta when you signed up for an account.', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_payment_data_collector_url' => array(
             'title'        => __('Test Data Collector', 'wc_vesta_payment'),
             'type'        => 'text',
             'default'   => 'https://riskcsproxy.ecustomersupport.com/DCCSProxy/Service',
             'desc_tip'    => __('This is the data-collector URL provided by Vesta when you signed up for an account.', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_payment_merchant_routingId' => array(
             'title'        => __('Test Merchant Routing ID', 'wc_vesta_payment'),
             'type'        => 'text',
             'desc_tip'    => __('This is the Merchant Routing ID provided by Vesta when you signed up for an account.', 'wc_vesta_payment'),
             'default' => 'FULL-ACQUIRING-SVC',
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
        'vesta_production_username' => array(
            'title'        => __('Live Account Name', 'wc_vesta_payment'),
            'type'        => 'password',
            'desc_tip'    => __('This is the account name provided by vesta when you signed up for an account.', 'wc_vesta_payment'),
            'autocomplete'        => 'off',
            'custom_attributes' => array(
                'required' => __('required', 'wc_vesta_payment')
            ),
        ),
         'vesta_production_password' => array(
             'title'        => __('Live Password', 'wc_vesta_payment'),
             'type'        => 'password',
             'desc_tip'    => __('This is the password provided by vesta when you signed up for an account.', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_production_api_url' => array(
             'title'        => __('Live API URL', 'wc_vesta_payment'),
             'type'        => 'text',
             'desc_tip'    => __('This is the API URL provided by Vesta when you signed up for an account.', 'wc_vesta_payment'),
             'default'     => 'https://vsafe1.ecustomerpayments.com/GatewayV4Proxy/Service/',
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_production_data_collector_url' => array(
             'title'        => __('Live Data Collector', 'wc_vesta_payment'),
             'type'        => 'text',
             'default'   => 'https://collectorsvc.ecustomersupport.com/DCCSProxy/Service',
             'desc_tip'    => __('This is the data-collector URL provided by Vesta when you signed up for an account.', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_production_merchant_routingId' => array(
             'title'        => __('Live Merchant Routing ID', 'wc_vesta_payment'),
             'type'        => 'text',
             'desc_tip'    => __('This is the Merchant Routing ID provided by Vesta when you signed up for an account.', 'wc_vesta_payment'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'vesta_payment_cardtypes' => array(
             'title'    => __('Accepted Cards', 'wc_vesta_payment'),
             'type'     => 'multiselect',
            'class'    => 'chosen_select',
             'css'      => 'width: 350px;',
             'desc_tip' => __('Select the card types to accept.', 'wc_vesta_payment'),
             'options'  => array(
                'mastercard'       => 'MasterCard',
                 'visa'             => 'Visa',
                 'discover'         => 'Discover',
                 'amex'                => 'American Express',
                 'jcb'               => 'JCB',
                 'dinersclub'       => 'Dinners Club',
             ),
             'default' => array('mastercard', 'visa', 'discover', 'amex'),
             'custom_attributes' => array(
                 'required' => __('required', 'wc_vesta_payment')
             ),
         ),
         'save_card' => array(
             'title' => __('Saved Card', 'wc_vesta_payment'),
             'type' => 'checkbox',
             'label' => __('Saved Card', 'wc_vesta_payment'),
             'default' => 'no',
             'description' => sprintf(__('Saved card , If you want save card for future payment, then checked the box.'))
         ),
         'debug' => array(
             'title' => __('Debug Log', 'wc_vesta_payment'),
             'type' => 'checkbox',
             'label' => __('Enable log', 'wc_vesta_payment'),
             'default' => 'no',
             'description' => sprintf(__('Log , inside <code>uploads/wc-logs/wc_vesta_payment-%s.txt</code>', 'wc_paymentwing'), sanitize_file_name(wp_hash('wing')))
        )
    );
}

/**
 * woocommerce payment_fields method
 *
 * @return void
 */
public function payment_fields()
{
    // ok, let's display some description before the payment form
    if ($this->description) {
        // display the description with <p> tags etc.
        echo wpautop(wp_kses_post($this->description));
    }

    // I will echo() the form, but you can close PHP tags and print it directly in HTML
    echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" >';

    // Add this action hook if you want your custom gateway to support it
    do_action('woocommerce_credit_card_form_start', $this->id);

    // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
    echo '  <div class="card-bounding vestaCard_bounding" id="Card_bound">

        <aside>Card Number</aside>
        <div class="card-container">
          <!--- ".card-type" is a sprite used as a background image with associated classes for the major card types, providing x-y coordinates for the sprite --->
          <div class="card-type"></div>
          <input name="vesta_ccNo" id="vesta_ccNo" value="" placeholder="0000 0000 0000 0000" onkeyup="$cc.validate(event)" maxlength="22" onPaste="pasted(event)" />
          <!-- The checkmark ".card-valid" used is a custom font from icomoon.io --->
          <div class="card-valid">&#xea10;</div>
        </div>
    
        <div class="card-details clearfix">
    
          <div class="expiration">
            <aside>Expiration Date</aside>
            <input name="vesta_expiry" value="" onkeyup="$cc.expiry.call(this,event)" maxlength="7" placeholder="mm/yyyy" />
          </div>
    
          <div class="cvv">
            <aside>CVV</aside>
            <input name="vesta_cvv" value="" maxlength="4" placeholder="xxx"/>
            <input type="hidden" name="temp_vesta_token" id="temp_vesta_token" value="" />
          </div>
    
        </div>';
    if (is_user_logged_in()) {
        $payment_method_page = is_add_payment_method_page();
        if ('yes' == $this->save_card && $payment_method_page == 0) {
            echo '<div>
                <div class="vesta_save_card">
                    <input type="checkbox" name="vesta_save_token" id="vesta_save_token" value="1"/>' . " Save payment information to my account for future purchases." . '
                </div>
            </div>';
        }
        // Render the saved card by customer
        $current_user = get_current_user_id();
        $vesta_common_method = VestaCommonClass::get_instance();
        $wc_token_table = 'woocommerce_payment_tokens';
        $token_data = $vesta_common_method->get_all_token_data($wc_token_table, $current_user);
        foreach ($token_data as $data) {
            $token_id = $data->token_id;
            $token = WC_Payment_Tokens::get($token_id);
            $vesta_card_type = $token->get_card_type();
            $vesta_card_exp_year = $token->get_expiry_year();
            $vesta_card_last4 = $token->get_last4();
            $vesta_card_exp_month = $token->get_expiry_month();
            echo '<input type="radio" name="vesta_payment_token" id="vesta_payment_token" value="' . $data->token . '"> ' . $vesta_card_type . ' ending in ' . $vesta_card_last4 . ' (expires ' . $vesta_card_exp_month . '/' . $vesta_card_exp_year . ')</br>';
        }
        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id());
    }

    echo '</div>';
    do_action('woocommerce_credit_card_form_end', $this->id);

    echo '<div class="clear"></div></fieldset>';
}

public function payment_scripts()
{
    // we need JavaScript to process a token only on cart/checkout pages, right?
    if (!is_cart() && !is_checkout()) {
        return;
    }

    // if our payment gateway is disabled, we do not have to enqueue JS too
    if ('no' === $this->enabled) {
        return;
    }
    // no reason to enqueue JavaScript if API keys are not set
    if (empty($this->vesta_payment_username) && empty($this->vesta_payment_password)) {
        return;
    }
    
    if($this->test_mode === true){
        wp_localize_script(
            'vesta_pay',
            'vesta_tokanization_params',
            array(
                'vesta_payment_username' => $this->vesta_payment_username
            )
        );
    }else{
        wp_localize_script(
            'vesta_pay',
            'vesta_tokanization_params',
            array(
                'vesta_production_username' => $this->vesta_production_username
            )
        ); 
    }

    wp_enqueue_script('vesta_pay');
}

public function validate_fields()
{
    $get_post_var = filter_input_array(INPUT_POST);
    if (!empty($get_post_var['vesta_payment_token']) && isset($get_post_var['vesta_payment_token'])) {
        return true;
    } else {
        if (empty($get_post_var['vesta_ccNo'])) {
            wc_add_notice('Credit Card Number required!', 'error');
            return false;
        }
        if (empty($get_post_var['vesta_expiry'])) {
            wc_add_notice('Credit Card Expiry required!', 'error');
            return false;
        }
        $exp_date     = explode("/", sanitize_text_field($get_post_var['vesta_expiry']));
        $exp_month    = str_replace(' ', '', $exp_date[0]);
        $exp_year     = str_replace(' ', '', $exp_date[1]);
        $exp_year_last2 = (strlen($exp_year) == 4) ? substr($exp_year, 2, 2) : $exp_year;

        $expires = \DateTime::createFromFormat('my', $exp_month . $exp_year_last2);
        $now     = new \DateTime();

        if ($expires < $now || strlen($exp_year) != 4) {
            wc_add_notice('Please enter correct Credit Card Expiration Date !', 'error');
            return false;
        }
        if (empty($get_post_var['vesta_cvv'])) {
            wc_add_notice('Credit Card CVV Number required!', 'error');
            return false;
        }
        return true;
    }
}

/**
 * get_icon function.
 *
 * @access public
 * @return string
 */
public function get_icon()
{
    $icon = '<div class="vesta_card_img">';
    foreach ($this->vesta_payment_cardtypes as $name) {
        $url = plugins_url( "vesta-guaranteed-payments/assets/images/{$name}.png", _FILE_ );
        $icon .= '<img title="' . ucfirst($name) . '" src="' . esc_url($url) . '" alt="' . ucfirst($name) . '"/>';
    }
    $icon .= "</div>";
    return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
}


/**
 * Method to get credit card type
 *
 * @param  [type] $number
 * @return void
 */
function get_card_type($number)
{
    $number = preg_replace('/[^\d]/', '', $number);
    if (preg_match('/^3[47][0-9]{13}$/', $number)) {
        return 'amex';
    } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
        return 'dinersclub';
    } elseif (preg_match('/^6(?:011\d{12}|5\d{14}|4[4-9]\d{13}|22(?:1(?:2[6-9]|[3-9]\d)|[2-8]\d{2}|9(?:[01]\d|2[0-5]))\d{10})$/', $number)) {
        return 'discover';
    } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
        return 'jcb';
    } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
        return 'mastercard';
    } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
        return 'visa';
    } else {
        return 'unknown card';
    }
}
/**
 * Undocumented function
 *
 * @param  [type] $order_id
 * @return void
 */
function process_payment($order_id)
{
    $wc_order     = new WC_Order($order_id);
    $vesta_common_method = VestaCommonClass::get_instance();
    $get_post_var       = filter_input_array(INPUT_POST);
    $cardtype = $this->get_card_type(sanitize_text_field(str_replace(' ', '', $get_post_var['temp_vesta_token'])));
    if (!empty($get_post_var['vesta_payment_token'])) {
        $account_number_param = $get_post_var['vesta_payment_token'];
        $wc_token_table = 'woocommerce_payment_tokens';
        $token_data = $vesta_common_method->get_saved_token_value($wc_token_table, $account_number_param);

        $token = WC_Payment_Tokens::get($token_data->token_id);
        $vesta_card_exp_year = $token->get_expiry_year();
        $exp_month    = $token->get_expiry_month();
        $exp_year_last2 = (strlen($vesta_card_exp_year) == 4) ? substr($vesta_card_exp_year, 2, 2) : $vesta_card_exp_year;
        $cvc          = "";
        //Disable saved card if merchant not accepting a particular card type
        if (!in_array($token->get_card_type(), $this->vesta_payment_cardtypes)) {
            wc_add_notice('Merchant do not support accepting in ' . $token->get_card_type(),  $notice_type = 'error');
            return array(
                'result'   => 'success',
                'redirect' => WC()->cart->get_checkout_url(),
            );
            die;
        }
        $account_number_indicator = "3";
        $transaction_type = "5";
    } else {
        if (!in_array($cardtype, $this->vesta_payment_cardtypes)) {
            wc_add_notice('Merchant do not support accepting in ' . $cardtype,  $notice_type = 'error');
            return array(
                'result'   => 'success',
                'redirect' => WC()->cart->get_checkout_url(),
            );
            die;
        }
        $account_number_param = sanitize_text_field(str_replace(' ', '', $get_post_var['temp_vesta_token']));
        $exp_date     = explode("/", sanitize_text_field($get_post_var['vesta_expiry']));
        $exp_month    = str_replace(' ', '', $exp_date[0]);
        $exp_year     = str_replace(' ', '', $exp_date[1]);
        $exp_year_last2 = (strlen($exp_year) == 4) ? substr($exp_year, 2, 2) : $exp_year;
        $cvc          = sanitize_text_field($get_post_var['vesta_cvv']);
        $account_number_indicator = "2";
        $transaction_type = "2";
    }
    $get_random_value = $this->getToken(9);
    if (empty($_SESSION['vesta_payment_session_tags'])) {
        $endpoint = $this->vesta_payment_api_url . '/' . self::SESSION_TAG;
        $WebSessionID_params = $this->setAccountInformation();
        $WebSessionID_params['TransactionID'] = $get_random_value;

        $webSessionResponse = $vesta_common_method->_sendRequest($endpoint, $WebSessionID_params);
        //Curl error response code
        if ($webSessionResponse['errorResponse'] == 404) {
            $vesta_common_method->save_vesta_payment_log($webSessionResponse['errorResponse'], $webSessionResponse['message'], $order_id);
            $vesta_common_method->vesta_logger('Login Failed with vesta payment gateway.', $this->debug);
            $vesta_common_method->vesta_logger(print_r($webSessionResponse, true));
            wc_add_notice('Something went wrong, please contact your service provider.', $notice_type = 'error');
            return false;
        }
        //set session varaible for vesta session tag
        $_SESSION['vesta_payment_session_tags'] = $webSessionResponse;
    }

    $session_data = $_SESSION['vesta_payment_session_tags'];
    if (!empty($session_data) && is_array($session_data) && isset($session_data['ResponseCode']) &&  $session_data['ResponseCode'] == 0) {

        $prepareParams = $this->setVestaOrderDataForAPI($wc_order, $account_number_indicator, $transaction_type);
        $prepareParams['TransactionID'] = $get_random_value;
        $prepareParams['WebSessionID']  = $session_data['WebSessionID'];
        $prepareParams['AccountNumber'] = $account_number_param;
        if (!empty($cvc)) {
            $prepareParams['CVV'] = $cvc;
        }
        $prepareParams['ExpirationMMYY'] = $exp_month . $exp_year_last2;
        $chargePaymentResponseData = $this->getChargePaymentRequest($prepareParams);
        //logger method
        $this->vesta_transaction_logger($chargePaymentResponseData);

        if ((0 == $chargePaymentResponseData['ResponseCode'] && $chargePaymentResponseData['PaymentStatus'] == 10) || (0 == $chargePaymentResponseData['ResponseCode'] && $chargePaymentResponseData['PaymentStatus'] == 52) || (0 == $chargePaymentResponseData['ResponseCode'] && $chargePaymentResponseData['PaymentStatus'] == 3) || (0 == $chargePaymentResponseData['ResponseCode'] && $chargePaymentResponseData['PaymentStatus'] == 1)) {
            //store card if permanent token get from getChargePayment
            if (isset($chargePaymentResponseData['PermanentToken']) && $chargePaymentResponseData['PaymentStatus'] == 10 || isset($chargePaymentResponseData['PermanentToken']) && $chargePaymentResponseData['PaymentStatus'] == 52) {
                
                $wc_token_table = 'woocommerce_payment_tokens';
                $vesta_token_data = $vesta_common_method->get_saved_token($wc_token_table, $chargePaymentResponseData['PermanentToken']);
                if (isset($vesta_token_data) && $vesta_token_data > 0) { } else {
                    
                    $token = new WC_Payment_Token_CC();
                    $token->set_token($chargePaymentResponseData['PermanentToken']); // Token comes from payment processor
                    $token->set_gateway_id('wc_vesta_payment');
                    $token->set_last4($chargePaymentResponseData['CardLast4']);
                    $token->set_expiry_year($exp_year);
                    $token->set_expiry_month($exp_month);
                    $token->set_card_type($cardtype);
                    $token->set_user_id($wc_order->get_customer_id());
                    // Save the new token to the database
                    $token->save();
                }
            }
            switch ($chargePaymentResponseData['PaymentStatus']) {
                case 10:
                    $wc_order->add_order_note(__('Vesta Payment Completed', 'wc_vesta_payment'));
                    //$wc_order->payment_complete(chargePaymentResponseData['PaymentID']);
                    $capture_payment_message = sprintf(__('Vesta payment charge authorized and captured (Charge ID: %s). Process order to take payment, or refund to remove the capture payment.', 'wc_vesta_payment'), $chargePaymentResponseData['PaymentID']);
                    $wc_order->update_status('completed', $capture_payment_message);
                    WC()->cart->empty_cart();
                    $this->vesta_add_meta($order_id, $chargePaymentResponseData, $get_random_value);
                    add_post_meta($order_id, '_vesta_payment_charge_status', self::CHARGE_AUTH_CAPTURE);
                    update_post_meta($order_id, '_transaction_id', $get_random_value);
                    $this->vesta_transaction_logger($chargePaymentResponseData);
                    $capture_message = __('Payment Authorize and Capture Success', 'wc_vesta_payment');
                    $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['ResponseCode'], $capture_message, $order_id);
                    //unset or set session variable
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($wc_order),
                    );
                    break;
                case 52:
                    $wc_order->add_order_note(__('Vesta Payment Processing', 'wc_vesta_payment'));
                    $authorized_message = sprintf(__('Vesta payment charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'wc_vesta_payment'), $chargePaymentResponseData['PaymentID']);
                    $wc_order->update_status('on-hold', $authorized_message);
                    WC()->cart->empty_cart();
                    $this->vesta_add_meta($order_id, $chargePaymentResponseData, $get_random_value);
                    add_post_meta($order_id, '_vesta_payment_charge_status', self::CHARGE_AUTH_ONLY);
                    update_post_meta($order_id, '_transaction_id', $get_random_value);
                    $this->vesta_transaction_logger($chargePaymentResponseData);
                    $auth_message = __('Payment Authorize Success', 'wc_vesta_payment');
                    $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['ResponseCode'], $auth_message, $order_id);
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($wc_order),
                    );
                    break;
                case 3:
                    // Get charge Payment Response failed due to vsafe denied.
                    $this->vesta_transaction_logger($chargePaymentResponseData);
                    $decline_message = __('Fraud denied. This can occur if our platform has determined that this transaction is too risky to process, or the customer is not reliable.', 'wc_vesta_payment');
                    $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['ResponseCode'], $decline_message, $order_id);
                    $vesta_common_method->vesta_logger(print_r($chargePaymentResponseData, true));
                    wc_add_notice(__('Your transaction has been declined and your card will not be charged. Please verify that the information you entered matches what your bank has on file or use a different credit or debit card.<br><br>Please note that your bank may place an \"authorization hold\" on your account for the full attempted transaction amount until they process this decline. It takes most banks 1-2 business days to remove the pending amount, please contact your bank if you have questions about their authorization hold reversal procedure.', 'wc_vesta_payment'), $notice_type = 'error');
                    return false;
                    break;
                case 1:
                    // Get charge Payment Response failed due to bank decline. 
                    $this->vesta_transaction_logger($chargePaymentResponseData);
                    $decline_message = __('Bank denied.', 'wc_vesta_payment');
                    $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['ResponseCode'], $decline_message, $order_id);
                    $vesta_common_method->vesta_logger(print_r($chargePaymentResponseData, true));
                    wc_add_notice( __('Your transaction has been declined by the card issuer, you will not be charged. Please contact the card issuer with additional questions or use another credit card or debit card.', 'wc_vesta_payment'), $notice_type = 'error' );
                    return false;
                    break;
                default:
                    $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['ResponseCode'], 'Transaction not success.Please conract with service provider', $order_id);
                    wc_add_notice('Something went wrong, Please try again or contact your service provider', $notice_type = 'error');
                    return false;
            }
        } else {
            // Get charge Payment Response failed
            if (isset($chargePaymentResponseData['errorResponse'])) {
                $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['errorResponse'], $chargePaymentResponseData['message'], $order_id);
            } else {
                // When Mercahnt Add wrong "Merchant routing id/FRD-ALL-GUAR" 
                if($chargePaymentResponseData['PaymentStatus'] == 51)
                {
                    $chargePaymentResponseData['ResponseText'] = 'Something is wrong. Please Check Vesta payment configuration setting.';
                    $chargePaymentResponseData['ResponseCode'] = '404';
                }
                $vesta_common_method->save_vesta_payment_log($chargePaymentResponseData['ResponseCode'], $chargePaymentResponseData['ResponseText'], $order_id);
            }
            $vesta_common_method->vesta_logger(print_r($chargePaymentResponseData, true));
            wc_add_notice('Something went wrong, Please try again or contact your service provider', $notice_type = 'error');
            return false;
        }
    } else if (isset($session_data['ResponseCode']) &&  $session_data['ResponseCode'] == 1001) {
        $this->vesta_log_msg($session_data, $order_id);
    } else {
        $this->vesta_log_msg($session_data, $order_id);
    }
}

/**
 * Method for get random value
 *
 * @param  [type] $length
 * @return void
 */
public function getToken($length)
{
    $token = "";
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet .= "0123456789";
    $max = strlen($codeAlphabet); // edited

    for ($i = 0; $i < $length; $i++) {
        $token .= $codeAlphabet[random_int(0, $max - 1)];
    }
    return $token;
}

/**
 * Method for set vesta account info
 *
 * @return array
 */
public function setAccountInformation()
{
    return $requestParams = [
        "AccountName"     => $this->vesta_payment_username,
        "Password"         => $this->vesta_payment_password
    ];
}

/**
 * Method for prepare parameter for call
 *
 * @param  [type] $wc_order
 * @param  [type] $account_number_indicator
 * @return void
 */
public function setVestaOrderDataForAPI($wc_order, $account_number_indicator,$transaction_type)
{
    $vesta_risk_information_xml = VestaRiskXML::get_instance();
    $vesta_common_method = VestaCommonClass::get_instance();
    $items                 = WC()->cart->get_cart();
    if ('no' === $this->vesta_payment_authorize_only) {
        $autoDisposition = 1;
    } else {
        $autoDisposition = 0;
    }
    //validate xml with xsd
    $riskFileName = $vesta_risk_information_xml->get_risk_information_XML($wc_order, $items,$account_number_indicator);
    if($riskFileName == ""){
        $return_data['error_msg'] = "404";
        $return_data['message'] = __('XML XSD validation error, Could not get risk data!', 'wc_vesta_payment');
        $vesta_common_method->save_vesta_payment_log($return_data['error_msg'], $return_data['message'], $wc_order->id);
        wc_add_notice(__('XML XSD validation error.', 'wc_vesta_payment'), $notice_type = 'error');
        return false;
    }
    if(!$this->validateRiskXml($riskFileName)){
        $return_data['error_msg'] = "404";
        $return_data['message'] = __('XML XSD validation error, Could not get risk data!', 'wc_vesta_payment');
        $return_data['response'] = $this->xmlValidator->displayErrors();
        $vesta_common_method->save_vesta_payment_log($return_data['error_msg'], $return_data['message'], $wc_order->id);
        wc_add_notice(__('XML XSD validation error.', 'wc_vesta_payment'), $notice_type = 'error');
        return false;
    }
    $riskData = $this->getriskData($riskFileName);

    // check card token store or not
    $get_post_var       = filter_input_array(INPUT_POST);
    $is_save_token = $get_post_var['vesta_save_token'];
    if (1 == $is_save_token) {
        $store_card = 1;
    } else {
        $store_card = 0;
    }
    //get user account information

    $current_user = wp_get_current_user();
    $account_first_name = isset($current_user->user_firstname) ? $current_user->user_firstname : $wc_order->billing_first_name;
    $account_last_name = isset($current_user->user_lastname) ? $current_user->user_lastname : $wc_order->billing_last_name;
            
    $billing_address_1 = get_user_meta( $current_user->ID, 'billing_address_1', true );
    $billing_city = get_user_meta( $current_user->ID, 'billing_city', true );
    $billing_country = get_user_meta( $current_user->ID, 'billing_country', true );
    $billing_postcode = get_user_meta( $current_user->ID, 'billing_postcode', true );
    $billing_region = get_user_meta( $current_user->ID, 'billing_state', true );
    $billing_phone_no = get_user_meta( $current_user->ID, 'billing_phone', true );
            
    $account_address_1 = ($billing_address_1 != '') ? $billing_address_1 : $wc_order->billing_address_1;
    $account_city = ($billing_city != '') ? $billing_city : $wc_order->billing_city;
    $account_country = ($billing_country != '') ? $billing_country : $wc_order->billing_country;
    $account_postcode = ($billing_postcode != '') ? $billing_postcode : $wc_order->billing_postcode;
    $account_region = ($billing_region != '') ? $billing_region : $wc_order->billing_state;

    return $setApiData     = [
        "AccountName"               => $this->vesta_payment_username,
        "Password"                  => $this->vesta_payment_password,
        "PaymentDescriptor"         => "Woocommerce order id # {$wc_order->id}",
        "AccountHolderAddressLine1" => $account_address_1,
        "AccountHolderCity"         => $account_city,
        "AccountHolderRegion"       => $account_region,
        "AccountHolderPostalCode"   => $account_postcode,
        "AccountHolderCountryCode"  => $account_country,
        "AccountHolderFirstName"    => $account_first_name,
        "AccountHolderLastName"     => $account_last_name,
        "AccountNumberIndicator"    => $account_number_indicator,
        "Amount"                    => $wc_order->order_total,
        "AutoDisposition"           => $autoDisposition,
        "PaymentSource"             => "WEB",
        "MerchantRoutingID"         => $this->vesta_payment_merchant_routingId,
        "StoreCard"                 => $store_card,
        "TransactionType"           => $transaction_type,
        "RiskInformation"           => $riskData
    ];
}

/**
 * Get risk data
 *
 * @param [type] $file
 * @return string
 */
public function getriskData($file){
    return file_get_contents($file);
}

/**
 * Validate risk xml
 *
 * @param [type] $file
 * @return boolean
 */
public function validateRiskXml($file = NULL){
    return $this->xmlValidator->validateFeeds($file);
}
/**
 * getChargePaymentRequest function
 *
 * @param  [type] $prepareRequestData
 * @return array
 */
public function getChargePaymentRequest($prepareRequestData)
{
    $endPoint = $this->vesta_payment_api_url . '/' . self::CHARGE_PAYMENT_REQUEST;
    $vesta_common_method = VestaCommonClass::get_instance();
    $response_data = $vesta_common_method->_sendRequest($endPoint, $prepareRequestData);
    return $response_data;
}
/**
 * woocommerce refund process
 *
 * @param  [type] $order_id
 * @param  [type] $amount
 * @param  string $reason
 * @return void
 */
public function process_refund($order_id, $amount = null, $reason = '')
{
    try {
        $wc_order = new WC_Order($order_id);
        //check transaction id 
        if (!$wc_order->get_transaction_id()) {
            return new WP_Error('refund-error', sprintf(__('Order %s does not contain a transaction Id.', 'wc_vesta_payment'), $order_id));
        }

        $payment_status = get_post_meta($order_id, '_vesta_payment_charge_status', true);
        if ($payment_status == 'charge_auth_only') {
            $wc_order->add_order_note(__('There was an error in refunding the transaction on ' . date("d-M-Y h:i:s e"), 'wc_vesta_payment'));
            return new WP_Error('refund-error', sprintf(__('Refund cannot be performed for ‘Authorize Only’ transaction. Kindly void the transaction, to cancel the payment.', 'wc_vesta_payment'), $order_id));
        }

        $transaction_id = get_post_meta($order_id, '_transaction_id', true);
        $vesta_transaction_id = get_post_meta($order_id, '_vesta_payment_transaction_id', true);
        $vesta_payment_id = get_post_meta($order_id, '_vesta_payment_id', true);
        $refund_request_data = [
            "TransactionID"     => $vesta_transaction_id,
            "PaymentID"         => $vesta_payment_id,
            "Amount"             => $amount
        ];
        $reverse_payment_request = array_merge($this->setAccountInformation(), $refund_request_data);
        $vesta_common_method = VestaCommonClass::get_instance();
        $endPoint     = $this->vesta_payment_api_url . '/' . self::REVERSE_PAYMENT_REQUEST;
        $response_refund_data = $vesta_common_method->_sendRequest($endPoint, $reverse_payment_request);
        if (0 == $response_refund_data['ResponseCode']) {
            switch ($response_refund_data['PaymentStatus']) {
                case 10:
                    $payment_status = get_post_meta($order_id, '_vesta_payment_charge_status', true);
                    if ($payment_status == 'charge_auth_only') {
                        $refund_msg = __('Payment voided successfully', 'wc_vesta_payment');
                    } else {
                        $refund_msg = __('Payment refunded Successfully', 'wc_vesta_payment');
                    }
                    update_post_meta($order_id, '_vesta_payment_charge_status', 'charge_refunded');
                    $this->vesta_transaction_logger($response_refund_data);
                    $vesta_common_method->save_vesta_payment_log($response_refund_data['ResponseCode'],  $refund_msg, $order_id);
                    $wc_order->add_order_note(__('Refunded on ' . date("d-M-Y h:i:s e") . ' with Payment ID = ' . $response_refund_data['PaymentID'], 'wc_vesta_payment'));
                    if ($wc_order->order_total == $amount) {
                        $wc_order->update_status('wc-refunded');
                    }
                    return true;
                    break;
                case 1:
                    $vesta_common_method->save_vesta_payment_log($response_refund_data['ResponseCode'], 'Void or refund was rejected by the acquirer.', $order_id);
                    $this->vesta_transaction_logger($response_refund_data);
                    $vesta_common_method->vesta_logger(print_r($response_refund_data, true));
                    return false;
                    break;
                case 4:
                    $vesta_common_method->save_vesta_payment_log($response_refund_data['ResponseCode'], 'Authorization successfully voided.', $order_id);
                    $this->vesta_transaction_logger($response_refund_data);
                    $vesta_common_method->vesta_logger(print_r($response_refund_data, true));
                    $wc_order->add_order_note(__('Auth hold reversal on ' . date("d-M-Y h:i:s e") . 'with Payment ID = ' . $response_refund_data['PaymentID'], 'wc_vesta_payment'));
                    $wc_order->update_status('wc-cancelled');
                    return true;
                    break;
                case 6:
                    $vesta_common_method->save_vesta_payment_log($response_refund_data['ResponseCode'], 'Authorization communication error.', $order_id);
                    $this->vesta_transaction_logger($response_refund_data);
                    $vesta_common_method->vesta_logger(print_r($response_refund_data, true));
                    return false;
                    break;
                default:
                    $vesta_common_method->save_vesta_payment_log('404', 'Something went wrong! Please contact with service provider.', $order_id);
                    return false;
            }
        } else {
                $wc_order->add_order_note(__('There was an error in refunding the transaction on ' . date("d-M-Y h:i:s e"), 'wc_vesta_payment'));
                return new WP_Error('refund-error', sprintf(__('Order %s does not refunded! Please try again or contact service privider.', 'wc_vesta_payment'), $order_id));
        }
    } catch (Exception $exception) {
        $vesta_common_method = VestaCommonClass::get_instance();
        $vesta_common_method->vesta_logger($exception->getMessage());
        return new WP_Error('error', $exception->getMessage());
    }
}
/**
 * Method for unset session and create new web session.
 */
public function unset_session_after_order($order_id = null)
{
    if (isset($_SESSION['vesta_payment_session_tags'])) {
        unset($_SESSION['vesta_payment_session_tags']);
        // set new session
        $vesta_common_method = VestaCommonClass::get_instance();
        $endpoint = $this->vesta_payment_api_url . '/' . self::SESSION_TAG;
        $WebSessionID_params = $this->setAccountInformation();
        $WebSessionID_params['TransactionID'] = $this->getToken(9);
        $webSessionResponse['errorResponse'] = "";
        $webSessionResponse = $vesta_common_method->_sendRequest($endpoint, $WebSessionID_params);
        if (isset($webSessionResponse['errorResponse']) && $webSessionResponse['errorResponse'] == 404 && is_array($webSessionResponse)) {
            $vesta_common_method->save_vesta_payment_log($webSessionResponse['errorResponse'], $webSessionResponse['message'], $order_id);
            $vesta_common_method->vesta_logger('Login Failed with vesta payment gateway.', $this->debug);
            $vesta_common_method->vesta_logger(print_r($webSessionResponse, true));
            wc_add_notice('Something went wrong, please contact your service provider.', $notice_type = 'error');
            return false;
        }
        //set session varaible for vesta session tag
        $_SESSION['vesta_payment_session_tags'] = $webSessionResponse;
    }
}

public function add_payment_method()
{
    $vesta_common_method = VestaCommonClass::get_instance();
    try {
        $get_post_var       = filter_input_array(INPUT_POST);
        $cardtype = $this->get_card_type(sanitize_text_field(str_replace(' ', '', $get_post_var['vesta_ccNo'])));
        $account_number_param = sanitize_text_field(str_replace(' ', '', $get_post_var['vesta_ccNo']));
        $exp_date     = explode("/", sanitize_text_field($get_post_var['vesta_expiry']));
        $exp_month    = str_replace(' ', '', $exp_date[0]);
        $exp_year     = str_replace(' ', '', $exp_date[1]);
        $exp_year_last2 = (strlen($exp_year) == 4) ? substr($exp_year, 2, 2) : $exp_year;
        $cvc          = sanitize_text_field($get_post_var['vesta_cvv']);
        if (is_add_payment_method_page()) {
            $params = [
                "AccountName" => $this->vesta_payment_username,
                "AccountNumber" => $account_number_param,
                "AccountNumberIndicator" => 1,
                "Password" => $this->vesta_payment_password
            ];
            $endPoint = $this->vesta_payment_api_url . '/' . self::ACCOUNT_NUMBER_TO_PERMANENT_TOKEN;

            //call API for permanent token
            $token_response_data = $vesta_common_method->_sendRequest($endPoint, $params);
            $wc_token_table = 'woocommerce_payment_tokens';
            $vesta_token_data = $vesta_common_method->get_saved_token($wc_token_table, $token_response_data['PermanentToken']);
            if (isset($vesta_token_data) && $vesta_token_data > 0) {
                return array(
                    'result'   => 'failure',
                    'redirect' => wc_get_endpoint_url('payment-methods'),
                );
            } else {
                $token = new WC_Payment_Token_CC();
                $token->set_token($token_response_data['PermanentToken']); // Token comes from payment processor
                $token->set_gateway_id('wc_vesta_payment');
                $token->set_last4($token_response_data['CardLast4']);
                $token->set_expiry_year($exp_year);
                $token->set_expiry_month($exp_month);
                $token->set_card_type($cardtype);
                $token->set_user_id(get_current_user_id());
                // Save the new token to the database
                $token->save();
                return array(
                    'result'   => 'success',
                    'redirect' => wc_get_endpoint_url('payment-methods'),
                );
            }
        }
    } catch (Exception $exception) {
        return new WP_Error('error', $exception->getMessage());
        $vesta_common_method->vesta_logger($exception->getMessage());
    }
}

/**
 * Method for show/ save log message
 *
 * @param [type] $webSessionResponse
 * @param [type] $order_id
 * @return void
 */
public function vesta_log_msg($webSessionResponse = null, $order_id = null)
{
    $vesta_common_method = VestaCommonClass::get_instance();
    if (isset($webSessionResponse['errorResponse'])) {
        $vesta_common_method->save_vesta_payment_log($webSessionResponse['errorResponse'], $webSessionResponse['message'], $order_id);
    } else {
        $vesta_common_method->save_vesta_payment_log($webSessionResponse['ResponseCode'], $webSessionResponse['ResponseText'], $order_id);
    }
    $vesta_common_method->vesta_logger('Login Failed with vesta payment gateway.', $this->debug);
    $vesta_common_method->vesta_logger(print_r($webSessionResponse, true), $this->debug);
    wc_add_notice('Something went wrong, Please contact your service provider.', $notice_type = 'error');
    return false;
}

/**
 * Method for save response as meta for vesta payment
 *
 * @param [type] $order_id
 * @param [type] $chargePaymentResponseData
 * @param [type] $get_random_value
 * @return void
 */
public function vesta_add_meta($order_id = null, $chargePaymentResponseData = null, $get_random_value = null)
{
    add_post_meta($order_id, '_vesta_payment_id', $chargePaymentResponseData['PaymentID']);
    add_post_meta($order_id, '_vesta_payment_transaction_id', $get_random_value);
    add_post_meta($order_id, '_vesta_payment_last4', $chargePaymentResponseData['CardLast4']);
    add_post_meta($order_id, '_vesta_payment_AcquirerAVSResponseCode', $chargePaymentResponseData['AcquirerAVSResponseCode']);
    add_post_meta($order_id, '_vesta_payment_AcquirerResponseCodeText', $chargePaymentResponseData['AcquirerResponseCodeText']);
    add_post_meta($order_id, '_vesta_payment_AcquirerCVVResponseCode', $chargePaymentResponseData['AcquirerCVVResponseCode']);
}

/**
 * method for charge payment log
 *
 * @param [type] $chargePaymentResponseData
 * @return void
 */
public function vesta_transaction_logger($chargePaymentResponseData = null)
{
    $vesta_common_method = VestaCommonClass::get_instance();
    $logger_data = $chargePaymentResponseData;
    // Logger and log section 
    $vesta_common_method->vesta_logger($logger_data, $this->debug);
}
public function update_order_status($wc_order,$capture_message = null){
    //$wc_order->update_status('completed', $capture_message);
    set_status( 'completed', $capture_message  );
}
}
