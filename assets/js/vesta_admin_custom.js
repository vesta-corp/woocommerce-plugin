jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe admin functions.
	 */
	var wc_vesta_admin = {
		isTestMode: function() {
			return $( '#woocommerce_wc_vesta_payment_test_mode' ).is( ':checked' );
		},
		getUsername: function() {
			if ( wc_vesta_admin.isTestMode() ) {
				return $( '#woocommerce_wc_vesta_payment_vesta_payment_username' ).val();
			} else {
				return $( '#woocommerce_wc_vesta_payment_vesta_production_username' ).val();
			}
        },
        

            /**
	* Initialize.
	*/
	init: function() {
        $( document.body ).on( 'change', '#woocommerce_wc_vesta_payment_test_mode', function() {
            var test_account_name = $( '#woocommerce_wc_vesta_payment_vesta_payment_username' ).parents( 'tr' ).eq( 0 ),
            test_password = $( '#woocommerce_wc_vesta_payment_vesta_payment_password' ).parents( 'tr' ).eq( 0 ),
            test_api_url = $( '#woocommerce_wc_vesta_payment_vesta_payment_api_url' ).parents( 'tr' ).eq( 0 ),
            test_data_collector = $( '#woocommerce_wc_vesta_payment_vesta_payment_data_collector_url' ).parents( 'tr' ).eq( 0 ),
            test_merchant_routing = $( '#woocommerce_wc_vesta_payment_vesta_payment_merchant_routingId' ).parents( 'tr' ).eq( 0 ),
            test_sandbox_title = $( '#woocommerce_wc_vesta_payment_sandbox_section' ),
            live_account_name = $( '#woocommerce_wc_vesta_payment_vesta_production_username' ).parents( 'tr' ).eq( 0 ),
            live_password = $( '#woocommerce_wc_vesta_payment_vesta_production_password' ).parents( 'tr' ).eq( 0 ),
            live_api_url = $( '#woocommerce_wc_vesta_payment_vesta_production_api_url' ).parents( 'tr' ).eq( 0 ),
            live_data_collector = $( '#woocommerce_wc_vesta_payment_vesta_production_data_collector_url' ).parents( 'tr' ).eq( 0 ),
            live_merchant_routing = $( '#woocommerce_wc_vesta_payment_vesta_production_merchant_routingId' ).parents( 'tr' ).eq( 0 ),
            live_sandbox_title = $( '#woocommerce_wc_vesta_payment_production_section' );
            
            if ( $( this ).is( ':checked' ) ) {
                test_account_name.show().find("input").attr("required", true);
                test_password.show().find("input").attr("required", true);
                test_api_url.show().find("input").attr("required", true);
                test_data_collector.show().find("input").attr("required", true);
                test_merchant_routing.show().find("input").attr("required", true);
                test_merchant_routing.show().find("input").attr("required", true);
                test_sandbox_title.show().find("input").attr("required", true);

                live_account_name.hide().find("input").attr("required", false);
                live_password.hide().find("input").attr("required", false);
                live_api_url.hide().find("input").attr("required", false);
                live_data_collector.hide().find("input").attr("required", false);
                live_merchant_routing.hide().find("input").attr("required", false);
                live_sandbox_title.hide().find("input").attr("required", false);
            } else {
                test_account_name.hide().find("input").attr("required", false);
                test_password.hide().find("input").attr("required", false);
                test_api_url.hide().find("input").attr("required", false);
                test_data_collector.hide().find("input").attr("required", false);
                test_merchant_routing.hide().find("input").attr("required", false);
                test_sandbox_title.hide().find("input").attr("required", false);

                live_account_name.show().find("input").attr("required", true);
                live_password.show().find("input").attr("required", true);
                live_api_url.show().find("input").attr("required", true);
                live_data_collector.show().find("input").attr("required", true);
                live_merchant_routing.show().find("input").attr("required", true);
                live_sandbox_title.show().find("input").attr("required", true);
            }
        } );

        $( '#woocommerce_wc_vesta_payment_test_mode' ).change();

    }

    };


    wc_vesta_admin.init();
} );
