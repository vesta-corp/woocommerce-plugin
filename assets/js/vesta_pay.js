jQuery(function ($) {
    'use strict';

    
    var productionMerchantName = vesta_tokanization_params.vesta_production_username;
    if(!productionMerchantName){
        var merchantName = vesta_tokanization_params.vesta_payment_username;
        var tokenApiEndpoint = "https://vsafesandboxtoken.ecustomersupport.com/GatewayV4ProxyJSON/Service/ChargeAccountToTemporaryToken";
    }else{
        var merchantName = vesta_tokanization_params.vesta_production_username;
        var tokenApiEndpoint = "https://vsafe1token.ecustomerpayments.com/GatewayV4ProxyJSON/Service/ChargeAccountToTemporaryToken";
    }
    /**
     * Object to handle Vesta tokenization process.
     */
    var vesta_token_obj = {
        init: function () {
            vestatoken.init({ ServiceURL: tokenApiEndpoint, AccountName: merchantName });
        }
    };
	/**
	 * Object to handle Authnet payment forms.
	 */
    var vesta_payment = {
		/**
		 * Initialize event handlers and UI state.
		 */
        init: function () {
            // checkout page
            if ($('form.woocommerce-checkout').length) {
                this.form = $('form.woocommerce-checkout');
                //console.log(this.form);
            }

            $('form.woocommerce-checkout').on('checkout_place_order_wc_vesta_payment', this.onSubmit);

            // pay order page
            if ($('form#order_review').length) {
                this.form = $('form#order_review');
            }

            $('form#order_review').on('submit', this.onSubmit);

            // add payment method page
            //if ($('form#add_payment_method').length) {
            //    this.form = $('form#add_payment_method');
            //}
            //$('form#add_payment_method').on('submit', this.onSubmit);
        },
        onSubmit: function (e) {
            e.preventDefault();
            if(vesta_payment.isVestaChosen()){
                vesta_payment.block();
                var radioValue = jQuery("input[name='vesta_payment_token']:checked").val();
                if (radioValue) {
                    vesta_payment.successCallback('NoData');//save card case
                }else{
                    var ccNumber = document.getElementById('vesta_ccNo').value.replace(/ +/g, "");
                    vestatoken.getcreditcardtoken({
                        ChargeAccountNumber: ccNumber,
                        onSuccess: function (data) {
                            vesta_payment.successCallback(data);
                        },
                        onFailed: function (failure) {
                            vesta_payment.errorCallback(failure);
                        },
                        onInvalidInput: function (failure) {
                            vesta_payment.errorCallback(failure);
                        }
                    });
                }

            }
            return false;
        },
        successCallback: function (data) {
            vesta_payment.form.find('#temp_vesta_token').val(data.ChargeAccountNumberToken);
            vesta_payment.form.off('checkout_place_order_wc_vesta_payment', vesta_payment.onSubmit);
            vesta_payment.form.off('submit', vesta_payment.onSubmit);
            vesta_payment.form.submit();
        },
        errorCallback: function (data) {
            alert(data);
        },
        isVestaChosen: function () {
            var checked = $( '#payment_method_wc_vesta_payment' ).is( ':checked' );
            var checkedName = $( 'input[name="payment_method"]:checked' ).val();
            var Result = (checked && checkedName === "wc_vesta_payment") ? true : false;
            return Result;
        },
        block: function() {
			vesta_payment.form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
		},
    };
    vesta_payment.init();
    vesta_token_obj.init();
});
