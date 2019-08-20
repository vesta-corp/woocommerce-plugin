<?php
/**
 * Class for common method.
 *
 * @category Vesta_Payment
 * @package Vesta_Payment
 * @author Vesta corporation
 */
if (! class_exists('VestaCommonClass') ) :
    class VestaCommonClass
    {
        public static $instance;
        /**
         * get instance from this method
         *
         * @return void
         */ 
        public static function get_instance()
        {
            if (is_null(self::$instance) ) {
                self::$instance = new self;
            }

            return self::$instance;
        }
        /**
         * Curl to send request API
         *
         * @param  [type]  $endPoint
         * @param  [array] $data
         * @return json_data
         */
        public function _sendRequest( $endPoint = null, $data = null )
        {
            $args = array(
                'body' => $data,
                'timeout' => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'cookies' => array()
            );
             
            $response = wp_remote_post( $endPoint, $args );
            if ( is_wp_error( $response ) ) {
                $error = [
                    'errorResponse' => '404',
                    'message' => 'Something went wrong, Please check your config settings.'
                ];
                return $error;
             
            }
            else {
             
                parse_str($response["body"],$result);
                return $result;
             
            }
           
        }

        /**
         * save log in data base
         *
         * @return void
         */
        function save_vesta_payment_log( $code = null, $content = null, $order_id = null )
        {
            global $wpdb;
            $table_name = $wpdb->prefix . "vesta_payment_logs";
            $wpdb->insert($table_name, array( 'order_id' => $order_id, 'response_code' => $code,'response_text' => $content, 'created_on' => current_time('mysql') ), array( '%s','%s','%s' ));
        }

        /**
         * Method for apply woocommerce logger
         *
         * @param  [type] $message
         * @return void
         */
        public function vesta_logger( $message = null, $debug_data = null )
        {

            if (isset($debug_data) &&  'yes' ==  $debug_data ) {            
                $log = new WC_Logger();
                $log->add('wc_vesta_payment', json_encode($message));
            }
        }
    
        /**
         * Method for custom order Action
         *
         * @param  [type] $actions
         * @return void
         */
    
        function custom_wc_order_action( $actions )
        {

            if (is_array($actions) ) {
                $actions['vesta_capture_action'] = __('Authorize Capture');
            }

            return $actions;

        }
    
        /**
         * Method for get data from database table
         *
         * @return void
         */
        function get_all_token_data( $vesta_table = null, $condition = null )
        {
            global $wpdb;
            $table_name = $wpdb->prefix .$vesta_table;
            $results = $wpdb->get_results("SELECT * FROM {$table_name} WHERE user_id = ".$condition." order by is_default DESC", OBJECT);
            return $results;
        }

        /**
         * Method for get token meta
         *
         * @param  [type] $vesta_table
         * @param  [type] $condition
         * @return data_object
         */
        function get_token_meta( $vesta_table = null, $condition = null)
        {
            global $wpdb;
            $table_name = $wpdb->prefix .$vesta_table;
            $results = $wpdb->get_results("SELECT * FROM {$table_name} WHERE payment_token_id = ".$condition, OBJECT);
            return $results;
        }

        /**
         * Method for get token data count
         *
         * @param  [type] $vesta_table
         * @param  [type] $condition
         * @return row_count
         */
        function get_saved_token( $vesta_table = null, $condition = null )
        {
            global $wpdb;
            $table_name = $wpdb->prefix .$vesta_table;
            $results = $wpdb->get_results("SELECT * FROM {$table_name} WHERE token = ".$condition, OBJECT);
            return count($results);
        }

        /**
         * Method for get token row
         *
         * @param  [type] $vesta_table
         * @param  [type] $condition
         * @return void
         */
        function get_saved_token_value( $vesta_table = null, $condition = null )
        {
            global $wpdb;
            $table_name = $wpdb->prefix .$vesta_table;
            $results = $wpdb->get_row("SELECT * FROM {$table_name} WHERE token = ".$condition, OBJECT);
            return $results;
        }
    }
endif;
