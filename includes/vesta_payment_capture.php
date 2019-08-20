<?php
/*Capture Charge*/

function vesta_capture_meta_box($order_id)
{
    global $post;
    $chargestatus = get_post_meta($post->ID, '_vesta_payment_charge_status', true);

    if ($chargestatus == 'charge_auth_only') {
            add_meta_box(
                'vesta_capture_chargeid',
                __('Capture authorized transaction', 'wc_vesta_payment'),
                'vesta_capture_meta_box_callback',
                'shop_order',
                'side',
                'default'
            );
    }
}
//wordpress hook for add meta box
add_action('add_meta_boxes', 'vesta_capture_meta_box');

/**
 * Metabox call function
 *
 * @param  [type] $post
 * @return void
 */
function vesta_capture_meta_box_callback( $post )
{
    echo '<select name="_vesta_charge_payment" style="width:222px">
    <option value="">Choose an action...</option>
    <option value="1">Payment Capture</option>
    <option value="2">Payment Void</option>
    </select>';
}
// Woocommerce hook for save updated order
add_action("woocommerce_saved_order_items", "vesta_capture_meta_box_action", 10, 2);

/**
 * Call back function for save order
 *
 * @param  [type] $order_id
 * @param  [type] $items
 * @return void
 */
function vesta_capture_meta_box_action( $order_id, $items )
{
    if (isset($items['_vesta_charge_payment']) && (1 == $items['_vesta_charge_payment'] ) ) {
            $wc_order = new WC_Order($order_id);
            $transaction_id = get_post_meta($order_id, '_transaction_id', true);
            $vesta_transaction_id = get_post_meta($order_id, '_vesta_payment_transaction_id', true);
            $vesta_payment_id = get_post_meta($order_id, '_vesta_payment_id', true);
            $disposition_comment = 'Vesta Payment Capture by Admin';

            $amount = $wc_order->order_total;
            $deposion_params = [
                "TransactionID" => $vesta_transaction_id,
                "PaymentID" => $vesta_payment_id,
                "Amount" => $amount,
                "DisposionComment" => $disposition_comment,
                "DispositionType" => "1",
            ];
            if (class_exists('WC_Vesta_Payment_Gateway')) {
                    $vesta_payment_gateway_class_obj = new WC_Vesta_Payment_Gateway();
            }
            $final_disposion_params = array_merge($vesta_payment_gateway_class_obj->setAccountInformation(), $deposion_params);
            $endPoint     = $vesta_payment_gateway_class_obj->vesta_payment_api_url . '/' . $vesta_payment_gateway_class_obj::DISPOSITION;
            $vesta_common_method = VestaCommonClass::get_instance();
            $disposition_response_data = $vesta_common_method->_sendRequest($endPoint, $final_disposion_params);

            if (0 == $disposition_response_data['ResponseCode']) {
                // Logger and log section    
                $logger_data = "Authorize capture payment = Success , ";
                $logger_data .= 'ResponseCode = ' . $disposition_response_data['ResponseCode'] . ' , ';
                $logger_data .= 'PaymentID = ' . $disposition_response_data['PaymentID'] . ' , ';
                $logger_data .= 'PaymentGuaranteeStatus = ' . $disposition_response_data['PaymentGuaranteeStatus'] . ' , ';
                $logger_data .= 'PaymentStatus = ' . $disposition_response_data['PaymentStatus'] . ' , ';
                $vesta_common_method->vesta_logger($logger_data, $vesta_payment_gateway_class_obj->debug);
                $vesta_common_method->save_vesta_payment_log($disposition_response_data['ResponseCode'], esc_html('Authorize payment capture success'), $order_id);
                // update payment status
                update_post_meta($order_id, '_vesta_payment_charge_status', 'charge_auth_captured');
                $capture_message = sprintf(__('Vesta payment charge captured (Charge ID: %s). Please update order status accordingly.', 'wc_vesta_payment'), $vesta_transaction_id);
                $wc_order->add_order_note(__($capture_message, 'wc_vesta_payment'));
                return;
            } else {

                $logger_data = "Authorize capture payment = Failed , ";
                $logger_data .= 'ResponseCode = ' . $disposition_response_data['ResponseCode'] . ' , ';
                $logger_data .= 'ResponseText = ' . $disposition_response_data['ResponseText'] . ' , ';
                $vesta_common_method->vesta_logger($logger_data, $vesta_payment_gateway_class_obj->debug);
                $vesta_common_method->save_vesta_payment_log($disposition_response_data['ResponseCode'], $disposition_response_data['ResponseText'], $order_id);
            }
    }
    // code block for void authorize payment
    if (isset($items['_vesta_charge_payment']) && ( 2 == $items['_vesta_charge_payment'] ) ) {
        $wc_order = new WC_Order($order_id);
        $transaction_id = get_post_meta($order_id, '_transaction_id', true);
        $vesta_transaction_id = get_post_meta($order_id, '_vesta_payment_transaction_id', true);
        $vesta_payment_id = get_post_meta($order_id, '_vesta_payment_id', true);
        $amount = $wc_order->order_total;
        if (class_exists('WC_Vesta_Payment_Gateway') ) {
            $vesta_payment_gateway_class_obj = new WC_Vesta_Payment_Gateway();
        }
        $void_request_data = [
            "TransactionID"     => $vesta_transaction_id,
            "PaymentID"         => $vesta_payment_id,
            "Amount"             => $amount
        ];
        $void_payment_request = array_merge($vesta_payment_gateway_class_obj->setAccountInformation(), $void_request_data);
        $endPoint     = $vesta_payment_gateway_class_obj->vesta_payment_api_url . '/' . $vesta_payment_gateway_class_obj::REVERSE_PAYMENT_REQUEST;
        $vesta_common_method = VestaCommonClass::get_instance();
        $void_response_data = $vesta_common_method->_sendRequest($endPoint, $void_payment_request);
        //check response and write logs
        if(0 == $void_response_data['ResponseCode'] ) {
            if(10 == $void_response_data['PaymentStatus'] ) {
                update_post_meta($order_id, '_vesta_payment_charge_status', 'charge_voided');
                $logger_data = 'ResponseCode = '. $void_response_data['ResponseCode'].', ';
                $logger_data .= 'PaymentStatus = ' . $void_response_data['PaymentStatus'] . ', ';
                $logger_data .= 'PaymentAcquirerName = ' . $void_response_data['PaymentAcquirerName']. ', ';
                $logger_data .= 'ReversalAction = ' . $void_response_data['ReversalAction']. ', ';
                $logger_data .= 'PaymentID = ' . $void_response_data['PaymentID']. ',';
                $logger_data .= 'PaymentStatus = ' . $void_response_data['PaymentStatus']. ',';

                $vesta_common_method->vesta_logger($logger_data, $vesta_payment_gateway_class_obj->debug);
                $vesta_common_method->save_vesta_payment_log($void_response_data['ResponseCode'], esc_html('Payment voided Successfully'), $order_id); 
                $wc_order->add_order_note(__('Voided on '.date("d-M-Y h:i:s e"). ' with Payment ID = '.$void_response_data['PaymentID']. '. Please update order status accordingly.', 'wc_vesta_payment'));
                return true;
            }
            else if(1 == $void_response_data['PaymentStatus'] ) {
                $vesta_common_method->save_vesta_payment_log($void_response_data['ResponseCode'], 'Void or refund was rejected by the acquirer.', $order_id);
                $logger_data = 'ResponseCode = '. $void_response_data['ResponseCode'].', ';
                $logger_data .= 'PaymentStatus = ' . $void_response_data['PaymentStatus'] . ', ';
                $vesta_common_method->vesta_logger($logger_data, $vesta_payment_gateway_class_obj->debug);
                $vesta_common_method->vesta_logger(print_r($void_response_data, true));
                return false;
            }else if(4 == $void_response_data['PaymentStatus']  && 3 == $void_response_data['ReversalAction'] ) {
                $vesta_common_method->save_vesta_payment_log($void_response_data['ResponseCode'], esc_html('Authorization was successfully voided.'), $order_id);
                $logger_data = 'ResponseCode = '. $void_response_data['ResponseCode'].', ';
                $logger_data .= 'PaymentStatus = ' . $void_response_data['PaymentStatus'] . ', ';
                $vesta_common_method->vesta_logger($logger_data, $vesta_payment_gateway_class_obj->debug);
                $vesta_common_method->vesta_logger(print_r($void_response_data, true)); 
                $wc_order->add_order_note(__('Auth hold reversal on '.date("d-M-Y h:i:s e"). 'with Payment ID = '.$void_response_data['PaymentID'].'. Please update order status accordingly.', 'wc_vesta_payment'));
                //$wc_order->update_status('wc-cancelled');
                return true;
            }else if(6 == $void_response_data['PaymentStatus'] ) {
                $vesta_common_method->save_vesta_payment_log($void_response_data['ResponseCode'], 'Authorization communication error.', $order_id);
                $logger_data = 'ResponseCode = '. $void_response_data['ResponseCode'].', ';
                $logger_data .= 'PaymentStatus = ' . $void_response_data['PaymentStatus'] . ', ';
                $vesta_common_method->vesta_logger($logger_data, $vesta_payment_gateway_class_obj->debug);
                $vesta_common_method->vesta_logger(print_r($void_response_data, true));  
                return false;
            }else{
                $vesta_common_method->save_vesta_payment_log($void_response_data['ResponseCode'], 'Authorization communication error.', $order_id);
                $wc_order->add_order_note(__('There was an error in refunding the transaction on '.date("d-M-Y h:i:s e"), 'wc_vesta_payment'));
            return false;
            }
        } else {        
            $wc_order->add_order_note(__('There was an error in refunding the transaction on '.date("d-M-Y h:i:s e"), 'wc_vesta_payment'));
            return false;
        }
    }
}
