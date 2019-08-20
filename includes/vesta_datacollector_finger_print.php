<?php
if (!isset($_SESSION)) {
    session_start();
}
//call wp_head hook for add script in head section
add_action('wp_head', 'add_javascript_data_collector');
/**
 * Method for data-collector
 *
 * @return enque script
 */
function add_javascript_data_collector()
{
    $vesta_payment_gateway_class_obj = new WC_Vesta_Payment_Gateway();
    //Get mercahant Account name
    $sandbox_mode = $vesta_payment_gateway_class_obj->test_mode;
   
    if($sandbox_mode === true){
        //test mode Data-collector URL
        $data_collector = $vesta_payment_gateway_class_obj->test_data_collector.'/fetch/an/SandboxID1288/ws/';
    }else{
        //production/live mode Data-collector URL
        $merchant_account_name = $vesta_payment_gateway_class_obj->vesta_payment_username;
        $data_collector = $vesta_payment_gateway_class_obj->live_data_collector.'/fetch/an/' .$merchant_account_name. '/ws/';
    }
	//session start method
    if (!empty($_SESSION['vesta_payment_session_tags']['WebSessionID'])) {
        wp_register_script('vesta-data-collector', $data_collector. $_SESSION['vesta_payment_session_tags']['WebSessionID'] . '/ep/vdccs.js', array('jquery'), null, false);
        wp_enqueue_script('vesta-data-collector');
    }else{
        $vesta_common_method = VestaCommonClass::get_instance();
        $get_random_value = $vesta_payment_gateway_class_obj->getToken(9);
        $endpoint = $vesta_payment_gateway_class_obj->vesta_payment_api_url . '/' . $vesta_payment_gateway_class_obj::SESSION_TAG;
        $WebSessionID_params = $vesta_payment_gateway_class_obj->setAccountInformation();
        $WebSessionID_params['TransactionID'] = $get_random_value;
        $webSessionResponse = $vesta_common_method->_sendRequest($endpoint, $WebSessionID_params);
        //set session varaible for vesta session tag
        $_SESSION['vesta_payment_session_tags'] = $webSessionResponse;
        //enqueue script for datacollector
        if(isset($_SESSION['vesta_payment_session_tags']['WebSessionID'])){
            wp_register_script('vesta-data-collector', $data_collector. $_SESSION['vesta_payment_session_tags']['WebSessionID'] . '/ep/vdccs.js', array('jquery'), null, false);
            wp_enqueue_script('vesta-data-collector');
        }
    }

}

add_action('woocommerce_before_checkout_form', 'vesta_payment_finger_print');

/**
 * Finger print
 *
 * @return enqueue script
 */
function vesta_payment_finger_print()
{
    if (isset($_SESSION['vesta_payment_session_tags'])) {
        ?>
        <!--Begin fingerprinting tags below-->
        <p style="background:url('https://fingerprint.ecustomerpayments.com/ThreatMetrixUIRedirector/fp/clear.png?org_id=<?= $_SESSION['vesta_payment_session_tags']['OrgID'] ?>&session_id=<?= $_SESSION['vesta_payment_session_tags']['WebSessionID'] ?>&m=1')"></p>
        <img src="https://fingerprint.ecustomerpayments.com/ThreatMetrixUIRedirector/fp/clear.png?org_id=<?= $_SESSION['vesta_payment_session_tags']['OrgID'] ?>&session_id=<?= $_SESSION['vesta_payment_session_tags']['WebSessionID'] ?>&m=2" alt="" />
        <?php 
            wp_register_script('vesta-data-collector-script', "https://fingerprint.ecustomerpayments.com/ThreatMetrixUIRedirector/fp/check.js?org_id=".$_SESSION['vesta_payment_session_tags']['OrgID']."&session_id=".$_SESSION['vesta_payment_session_tags']['WebSessionID']."" , array('jquery'), null, false);
            wp_enqueue_script('vesta-data-collector-script');
        ?>
        <object style="height:0px !important;" data="https://fingerprint.ecustomerpayments.com/ThreatMetrixUIRedirector/fp/fp.swf?org_id=<?= $_SESSION['vesta_payment_session_tags']['OrgID'] ?>&session_id=<?= $_SESSION['vesta_payment_session_tags']['WebSessionID'] ?>" type="application/x-shockwave-flash" width="1" height="1" id="obj_id">
            <param value="https://fingerprint.ecustomerpayments.com/ThreatMetrixUIRedirector/fp/fp.swf?org_id=<?= $_SESSION['vesta_payment_session_tags']['OrgID'] ?>&session_id=<?= $_SESSION['vesta_payment_session_tags']['WebSessionID'] ?>" name="movie" />
        </object>
        <!--End fingerprinting-->

        <?php
    }
}
