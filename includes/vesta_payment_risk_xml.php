<?php 
/**
 * Class for common method.
 *
 * @package Vesta Payment
 */
if (! class_exists('VestaRiskXML') ) :
    class VestaRiskXML
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
         * Method for get xml data
         *
         * @param [type] $order
         * @param [type] $items
         * @return void
         */
        public function get_risk_information_XML( $order = null, $items = null, $account_number_indicator = null )
        {
            if(!function_exists('is_plugin_active') ) {            
                include_once ABSPATH . 'wp-admin/includes/plugin.php';            
            }
            $isPdof = ($account_number_indicator == 3) ? "true" : "false";
            // Get customer register date
            $udata = get_userdata( get_current_user_id() );
            $registered = $udata->user_registered;
            $created_DTM = isset($registered) ? $registered : current_time('mysql');
            $current_user = wp_get_current_user();
            $account_email = isset($current_user->user_email) ? $current_user->user_email : $order->billing_email;
            $account_first_name = isset($current_user->user_firstname) ? $current_user->user_firstname : $order->billing_first_name;
            $account_last_name = isset($current_user->user_lastname) ? $current_user->user_lastname : $order->billing_last_name;
            
            $billing_address_1 = get_user_meta( $current_user->ID, 'billing_address_1', true );
            $billing_address_2 = get_user_meta( $current_user->ID, 'billing_address_2', true );
            $billing_city = get_user_meta( $current_user->ID, 'billing_city', true );
            $billing_country = get_user_meta( $current_user->ID, 'billing_country', true );
            $billing_postcode = get_user_meta( $current_user->ID, 'billing_postcode', true );
            $billing_region = get_user_meta( $current_user->ID, 'billing_state', true );
            $billing_phone_no = get_user_meta( $current_user->ID, 'billing_phone', true );
            
            $account_address_1 = ($billing_address_1 != '') ? $billing_address_1 : $order->billing_address_1;
            $account_address_2 = ($billing_address_2 != '') ? $billing_address_2 : $order->billing_address_2;
            $account_city = ($billing_city != '') ? $billing_city : $order->billing_city;
            $account_country = ($billing_country != '') ? $billing_country : $order->billing_country;
            $account_postcode = ($billing_postcode != '') ? $billing_postcode : $order->billing_postcode;
            $account_region = ($billing_region != '') ? $billing_region : $order->billing_state;
            $account_phone = ($billing_phone_no != '') ? $billing_phone_no : $order->billing_phone;

            $customer_ip = get_post_meta( $order->id, '_customer_ip_address', true );
            $riskXML  = '';
            
            $phone_code = $this->get_country_code($order->shipping_country);
            $billing_phone = $this->getPaddedPhone($phone_code.$account_phone);
            $riskXML.='<?xml version="1.0" encoding="UTF-8"?>
				<RiskInformation version="2.0">
				<Transaction>
                <Purchaser>
					<Account>
                        <AccountID>'.$current_user->ID.'</AccountID>
                        <CreatedDTM>'.$this->getConvertedTime( $created_DTM ).'</CreatedDTM>
                        <isEmailVerified>false</isEmailVerified>
						<Email>'.$account_email.'</Email>
						<FirstName>'.htmlentities(str_replace('%', "&percnt;", $account_first_name), ENT_QUOTES, "UTF-8").'</FirstName>
						<LastName>'.htmlentities(str_replace('%', "&percnt;", $account_last_name), ENT_QUOTES, "UTF-8").'</LastName>
						<AddressLine1>'.htmlentities(str_replace('%', "&percnt;", $account_address_1), ENT_QUOTES, "UTF-8").'</AddressLine1>';
                        if(!empty($account_address_2) ) {
                                $riskXML.='<AddressLine2>'.htmlentities(str_replace('%', "&percnt;", $account_address_2), ENT_QUOTES, "UTF-8").'</AddressLine2>';
                        }
                        $riskXML.='<City>'.htmlentities(str_replace('%', "&percnt;", $account_city), ENT_QUOTES, "UTF-8").'</City>
                        <CountryCode>'.htmlentities(str_replace('%', "&percnt;", $account_country), ENT_QUOTES, "UTF-8").'</CountryCode>
                        <PostalCode>'.htmlentities(str_replace('%', "&percnt;", $account_postcode), ENT_QUOTES, "UTF-8").'</PostalCode>
                        <Region>'.htmlentities(str_replace('%', "&percnt;", $account_region), ENT_QUOTES, "UTF-8").'</Region>
                        <PhoneNumber>'.$billing_phone.'</PhoneNumber>
                    </Account>
                </Purchaser>
                <Channel>
                        <IPAddress>'.$order->customer_ip_address.'</IPAddress>
                        <VestaChannelCode>WEB</VestaChannelCode>
                </Channel>';
                foreach ( $order->get_used_coupons() as $coupon_name ) {
                    $coupon_post_obj     = get_page_by_title($coupon_name, OBJECT, 'shop_coupon');
                    $coupon_id             = $coupon_post_obj->ID;
                    $coupons_obj         = new WC_Coupon($coupon_id);
                    if($coupons_obj->discount_type == 'fixed_cart'){
                        $coupon_type[] = 'fixed_cart';
                    } 
                }

                foreach ( $order->get_used_coupons() as $coupon_name ) {
                    $coupon_post_obj     = get_page_by_title($coupon_name, OBJECT, 'shop_coupon');
                    $coupon_id             = $coupon_post_obj->ID;
                    $coupons_obj         = new WC_Coupon($coupon_id);
 
                    if (in_array("fixed_cart", $coupon_type)){
                        $discount_field = '<Discount>'.$coupons_obj->get_amount().'</Discount>';
                    }else{
                        //$amount = $coupons_obj->get_amount();
                        $discount_field = "";
                    }
                    $coupon_desc = htmlentities(str_replace('%', "&percnt;", $coupons_obj->get_description()), ENT_QUOTES, "UTF-8");
                    $riskXML .= '<Promotion>
                                    <Code>'.htmlentities(str_replace('%', "&percnt;", $coupons_obj->get_code()), ENT_QUOTES, "UTF-8").'</Code>
                                    <Description>'. $coupon_desc .'</Description>';
                                    $riskXML .= $discount_field;
                                    $riskXML .='</Promotion>';
                }
                $riskXML.='<TimeStamp>'.$this->getConvertedTime($order->order_date).'</TimeStamp>
                <MerchantOrderID>'.$order->id.'</MerchantOrderID>
                <Billing>
                    <BillingPhoneNumber>'.$billing_phone.'</BillingPhoneNumber>
                    <Email>'.$order->billing_email.'</Email>
                    <PaymentDetails>
                        <isPDOF>'.$isPdof.'</isPDOF>
                    </PaymentDetails>
                </Billing>';
            $riskXML.= $this->get_shopping_cart_XML($order, $items);
            $riskXML.='</Transaction>
            </RiskInformation>';
            //echo "<pre>"; print_r($riskXML); echo "</pre>"; die('hihi');
            $fileName = "vesta_payment_risk_info.xml";
            $myfile = fopen($fileName, "w") or die("Unable to create Risk XML!");
            fwrite( $myfile, $riskXML );
            fclose($myfile);
            
            return $fileName;    
        }

        /**
         * Method for get cart data
         *
         * @param [type] $order
         * @param [type] $items
         * @return void
         */
        public function get_shopping_cart_XML($order = null, $items)
        {
            $shipping_address_2 = ($order->shipping_address_2 != null) ? $order->shipping_address_2 : '';
            foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
                // Get the data in an unprotected array
                $shipping_item_data = $shipping_item_obj->get_data();
                $shipping_data_name         = $shipping_item_data['name'];
                $shipping_data_total        = $shipping_item_data['total'];
            }
            $phone_code = $this->get_country_code($order->shipping_country);
            if ($order->shipping_first_name == $order->billing_first_name && $order->shipping_last_name == $order->billing_last_name && $order->shipping_address_1 == $order->billing_address_1 &&
            $order->shipping_postcode == $order->billing_postcode) {
                $billing_email_same = $order->billing_email;
                $billing_phone_same = $this->getPaddedPhone($phone_code.$order->billing_phone);
             }else{
                $billing_email_same = "";
                $billing_phone_same = "";
             }
            $riskXML  = '';
            $riskXML.= '<ShoppingCart DeliveryCount="1">
                <Delivery LineItemCount="' . count($order->get_items()) . '">
                    <DeliveryInfo>
                        <DeliveryMethod>PhysicalShipping</DeliveryMethod>
                        <ShippingClass>'.htmlentities(str_replace('%', "&percnt;", $shipping_data_name), ENT_QUOTES, "UTF-8").'</ShippingClass>';
                        $riskXML .= '<ShippingCost>'.$shipping_data_total.'</ShippingCost>
                        <Company>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_company), ENT_QUOTES, "UTF-8").'</Company>
                        <FirstName>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_first_name), ENT_QUOTES, "UTF-8").'</FirstName>
                        <LastName>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_last_name), ENT_QUOTES, "UTF-8").'</LastName>
                        <AddressLine1>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_address_1), ENT_QUOTES, "UTF-8").'</AddressLine1>
                        <AddressLine2>'.htmlentities(str_replace('%', "&percnt;", $shipping_address_2), ENT_QUOTES, "UTF-8").'</AddressLine2>
                        <City>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_city), ENT_QUOTES, "UTF-8").'</City>
                        <Region>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_state), ENT_QUOTES, "UTF-8").'</Region>
                        <PostalCode>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_postcode), ENT_QUOTES, "UTF-8").'</PostalCode>
                        <CountryCode>'.htmlentities(str_replace('%', "&percnt;", $order->shipping_country), ENT_QUOTES, "UTF-8").'</CountryCode>
                    </DeliveryInfo>';
            $riskXML.= $this->get_cart_items($items, $order);
            $riskXML.= '</Delivery>
                </ShoppingCart>';

            return     $riskXML;
        }

        /**
         * Method for item xml
         *
         * @param [type] $items
         * @param [type] $order
         * @return void
         */
        public function get_cart_items($items, $order)
        {
        
            $productItemXml     = '';
            $items = $order->get_items();
            foreach($items as $item) {
                //the product id, always, no matter what type of product
                $product_id = $item->get_product_id();
                //a default
                $variation_id = false;
                $product_variation_id = $item['variation_id'];

                //check if this is a variation using is_type
                if( $product_variation_id ) {
                    $product = wc_get_product( $product_variation_id );
                }else{
                    $product = new WC_Product($item['product_id']);
                }
                $terms = get_the_terms( $product_id, 'product_cat' );
                $productItemXml .='<LineItem>
			<ProductCode>'.$product_id.'</ProductCode>
			<ProductDescription>'.substr(strip_tags($product->get_description()), 0, 30).'</ProductDescription>
			<UnitPrice>'.$product->get_price().'</UnitPrice>
            <Quantity>'.$item['quantity'].'</Quantity>
            <DiverseCart>
                <SKU>'.$product->get_sku().'</SKU>
                <ProductType>'.$product->get_type().'</ProductType>';
            $productItemXml .= $this->getCategoryXml($terms);
            $productItemXml .= '</DiverseCart>';
            //for sale product discount
            if($product->is_on_sale()){
                $regular_price = $product->get_regular_price();
                $sale_price = $product->get_sale_price();
                $discount = ($regular_price - $sale_price) * $item['quantity'];
                $productItemXml .='
                    <Promotion>
					    <Discount>'.$discount.'</Discount>
					</Promotion>';
            }

                $productItemXml .='</LineItem>';
            }
            return $productItemXml;
        }

        /**
         * Get product category
         * @param [type] $product
         * @return array
         */
        public function getCategoryXml($terms = null) {
            $infoData = '';
            foreach ( $terms as $term ) {
                // Categories by slug
                $product_cat_slug = $term->slug;
                $infoData .= "<Category>".$product_cat_slug."</Category>";
				$infoData .= $this->getSubCategoryXml($term->term_id);
            }
            return $infoData;

        }
        function getSubCategoryXml($parent_cat_ID = null) {
           $infoData = '';
            $args = array(
               'hierarchical' => 1,
               'show_option_none' => '',
               'hide_empty' => 0,
               'parent' => $parent_cat_ID,
               'taxonomy' => 'product_cat'
            );
          $subcats = get_categories($args);
            echo '<ul class="wooc_sclist">';
              foreach ($subcats as $sc) {
                $infoData .= "<SubCategory>".$sc->name."</SubCategory>";
              }
            return $infoData;
        }
        /**
         * Get phone with 15 digit
         *
         * @param [type] $telephone
         * @return phone number
         */
        public function getPaddedPhone($telephone = null)
        {
            $newTelephone = preg_replace("/\D/", "", $telephone);
            if (strlen($newTelephone) != 15 && strlen($newTelephone) < 15) {
                return str_pad($newTelephone, 15, '0', STR_PAD_LEFT);
            } else {
                return $newTelephone;
            }
        }

        /**
         * Get ISO-8601 timestamp.
         * @return timestamp
         */
        public function getConvertedTime($time = null)
        {
            return date('Y-m-d\TH:i:s\Z', strtotime($time));
        }

        /**
         * Get country phone code
         *
         * @param [type] $country_code
         * @return phone_code
         */
        public function get_country_code($country_code = null){
            $countryArray = array(
                'AD'=>array('name'=>'ANDORRA','code'=>'376'),
                'AE'=>array('name'=>'UNITED ARAB EMIRATES','code'=>'971'),
                'AF'=>array('name'=>'AFGHANISTAN','code'=>'93'),
                'AG'=>array('name'=>'ANTIGUA AND BARBUDA','code'=>'1268'),
                'AI'=>array('name'=>'ANGUILLA','code'=>'1264'),
                'AL'=>array('name'=>'ALBANIA','code'=>'355'),
                'AM'=>array('name'=>'ARMENIA','code'=>'374'),
                'AN'=>array('name'=>'NETHERLANDS ANTILLES','code'=>'599'),
                'AO'=>array('name'=>'ANGOLA','code'=>'244'),
                'AQ'=>array('name'=>'ANTARCTICA','code'=>'672'),
                'AR'=>array('name'=>'ARGENTINA','code'=>'54'),
                'AS'=>array('name'=>'AMERICAN SAMOA','code'=>'1684'),
                'AT'=>array('name'=>'AUSTRIA','code'=>'43'),
                'AU'=>array('name'=>'AUSTRALIA','code'=>'61'),
                'AW'=>array('name'=>'ARUBA','code'=>'297'),
                'AZ'=>array('name'=>'AZERBAIJAN','code'=>'994'),
                'BA'=>array('name'=>'BOSNIA AND HERZEGOVINA','code'=>'387'),
                'BB'=>array('name'=>'BARBADOS','code'=>'1246'),
                'BD'=>array('name'=>'BANGLADESH','code'=>'880'),
                'BE'=>array('name'=>'BELGIUM','code'=>'32'),
                'BF'=>array('name'=>'BURKINA FASO','code'=>'226'),
                'BG'=>array('name'=>'BULGARIA','code'=>'359'),
                'BH'=>array('name'=>'BAHRAIN','code'=>'973'),
                'BI'=>array('name'=>'BURUNDI','code'=>'257'),
                'BJ'=>array('name'=>'BENIN','code'=>'229'),
                'BL'=>array('name'=>'SAINT BARTHELEMY','code'=>'590'),
                'BM'=>array('name'=>'BERMUDA','code'=>'1441'),
                'BN'=>array('name'=>'BRUNEI DARUSSALAM','code'=>'673'),
                'BO'=>array('name'=>'BOLIVIA','code'=>'591'),
                'BR'=>array('name'=>'BRAZIL','code'=>'55'),
                'BS'=>array('name'=>'BAHAMAS','code'=>'1242'),
                'BT'=>array('name'=>'BHUTAN','code'=>'975'),
                'BW'=>array('name'=>'BOTSWANA','code'=>'267'),
                'BY'=>array('name'=>'BELARUS','code'=>'375'),
                'BZ'=>array('name'=>'BELIZE','code'=>'501'),
                'CA'=>array('name'=>'CANADA','code'=>'1'),
                'CC'=>array('name'=>'COCOS (KEELING) ISLANDS','code'=>'61'),
                'CD'=>array('name'=>'CONGO, THE DEMOCRATIC REPUBLIC OF THE','code'=>'243'),
                'CF'=>array('name'=>'CENTRAL AFRICAN REPUBLIC','code'=>'236'),
                'CG'=>array('name'=>'CONGO','code'=>'242'),
                'CH'=>array('name'=>'SWITZERLAND','code'=>'41'),
                'CI'=>array('name'=>'COTE D IVOIRE','code'=>'225'),
                'CK'=>array('name'=>'COOK ISLANDS','code'=>'682'),
                'CL'=>array('name'=>'CHILE','code'=>'56'),
                'CM'=>array('name'=>'CAMEROON','code'=>'237'),
                'CN'=>array('name'=>'CHINA','code'=>'86'),
                'CO'=>array('name'=>'COLOMBIA','code'=>'57'),
                'CR'=>array('name'=>'COSTA RICA','code'=>'506'),
                'CU'=>array('name'=>'CUBA','code'=>'53'),
                'CV'=>array('name'=>'CAPE VERDE','code'=>'238'),
                'CX'=>array('name'=>'CHRISTMAS ISLAND','code'=>'61'),
                'CY'=>array('name'=>'CYPRUS','code'=>'357'),
                'CZ'=>array('name'=>'CZECH REPUBLIC','code'=>'420'),
                'DE'=>array('name'=>'GERMANY','code'=>'49'),
                'DJ'=>array('name'=>'DJIBOUTI','code'=>'253'),
                'DK'=>array('name'=>'DENMARK','code'=>'45'),
                'DM'=>array('name'=>'DOMINICA','code'=>'1767'),
                'DO'=>array('name'=>'DOMINICAN REPUBLIC','code'=>'1809'),
                'DZ'=>array('name'=>'ALGERIA','code'=>'213'),
                'EC'=>array('name'=>'ECUADOR','code'=>'593'),
                'EE'=>array('name'=>'ESTONIA','code'=>'372'),
                'EG'=>array('name'=>'EGYPT','code'=>'20'),
                'ER'=>array('name'=>'ERITREA','code'=>'291'),
                'ES'=>array('name'=>'SPAIN','code'=>'34'),
                'ET'=>array('name'=>'ETHIOPIA','code'=>'251'),
                'FI'=>array('name'=>'FINLAND','code'=>'358'),
                'FJ'=>array('name'=>'FIJI','code'=>'679'),
                'FK'=>array('name'=>'FALKLAND ISLANDS (MALVINAS)','code'=>'500'),
                'FM'=>array('name'=>'MICRONESIA, FEDERATED STATES OF','code'=>'691'),
                'FO'=>array('name'=>'FAROE ISLANDS','code'=>'298'),
                'FR'=>array('name'=>'FRANCE','code'=>'33'),
                'GA'=>array('name'=>'GABON','code'=>'241'),
                'GB'=>array('name'=>'UNITED KINGDOM','code'=>'44'),
                'GD'=>array('name'=>'GRENADA','code'=>'1473'),
                'GE'=>array('name'=>'GEORGIA','code'=>'995'),
                'GH'=>array('name'=>'GHANA','code'=>'233'),
                'GI'=>array('name'=>'GIBRALTAR','code'=>'350'),
                'GL'=>array('name'=>'GREENLAND','code'=>'299'),
                'GM'=>array('name'=>'GAMBIA','code'=>'220'),
                'GN'=>array('name'=>'GUINEA','code'=>'224'),
                'GQ'=>array('name'=>'EQUATORIAL GUINEA','code'=>'240'),
                'GR'=>array('name'=>'GREECE','code'=>'30'),
                'GT'=>array('name'=>'GUATEMALA','code'=>'502'),
                'GU'=>array('name'=>'GUAM','code'=>'1671'),
                'GW'=>array('name'=>'GUINEA-BISSAU','code'=>'245'),
                'GY'=>array('name'=>'GUYANA','code'=>'592'),
                'HK'=>array('name'=>'HONG KONG','code'=>'852'),
                'HN'=>array('name'=>'HONDURAS','code'=>'504'),
                'HR'=>array('name'=>'CROATIA','code'=>'385'),
                'HT'=>array('name'=>'HAITI','code'=>'509'),
                'HU'=>array('name'=>'HUNGARY','code'=>'36'),
                'ID'=>array('name'=>'INDONESIA','code'=>'62'),
                'IE'=>array('name'=>'IRELAND','code'=>'353'),
                'IL'=>array('name'=>'ISRAEL','code'=>'972'),
                'IM'=>array('name'=>'ISLE OF MAN','code'=>'44'),
                'IN'=>array('name'=>'INDIA','code'=>'91'),
                'IQ'=>array('name'=>'IRAQ','code'=>'964'),
                'IR'=>array('name'=>'IRAN, ISLAMIC REPUBLIC OF','code'=>'98'),
                'IS'=>array('name'=>'ICELAND','code'=>'354'),
                'IT'=>array('name'=>'ITALY','code'=>'39'),
                'JM'=>array('name'=>'JAMAICA','code'=>'1876'),
                'JO'=>array('name'=>'JORDAN','code'=>'962'),
                'JP'=>array('name'=>'JAPAN','code'=>'81'),
                'KE'=>array('name'=>'KENYA','code'=>'254'),
                'KG'=>array('name'=>'KYRGYZSTAN','code'=>'996'),
                'KH'=>array('name'=>'CAMBODIA','code'=>'855'),
                'KI'=>array('name'=>'KIRIBATI','code'=>'686'),
                'KM'=>array('name'=>'COMOROS','code'=>'269'),
                'KN'=>array('name'=>'SAINT KITTS AND NEVIS','code'=>'1869'),
                'KP'=>array('name'=>'KOREA DEMOCRATIC PEOPLES REPUBLIC OF','code'=>'850'),
                'KR'=>array('name'=>'KOREA REPUBLIC OF','code'=>'82'),
                'KW'=>array('name'=>'KUWAIT','code'=>'965'),
                'KY'=>array('name'=>'CAYMAN ISLANDS','code'=>'1345'),
                'KZ'=>array('name'=>'KAZAKSTAN','code'=>'7'),
                'LA'=>array('name'=>'LAO PEOPLES DEMOCRATIC REPUBLIC','code'=>'856'),
                'LB'=>array('name'=>'LEBANON','code'=>'961'),
                'LC'=>array('name'=>'SAINT LUCIA','code'=>'1758'),
                'LI'=>array('name'=>'LIECHTENSTEIN','code'=>'423'),
                'LK'=>array('name'=>'SRI LANKA','code'=>'94'),
                'LR'=>array('name'=>'LIBERIA','code'=>'231'),
                'LS'=>array('name'=>'LESOTHO','code'=>'266'),
                'LT'=>array('name'=>'LITHUANIA','code'=>'370'),
                'LU'=>array('name'=>'LUXEMBOURG','code'=>'352'),
                'LV'=>array('name'=>'LATVIA','code'=>'371'),
                'LY'=>array('name'=>'LIBYAN ARAB JAMAHIRIYA','code'=>'218'),
                'MA'=>array('name'=>'MOROCCO','code'=>'212'),
                'MC'=>array('name'=>'MONACO','code'=>'377'),
                'MD'=>array('name'=>'MOLDOVA, REPUBLIC OF','code'=>'373'),
                'ME'=>array('name'=>'MONTENEGRO','code'=>'382'),
                'MF'=>array('name'=>'SAINT MARTIN','code'=>'1599'),
                'MG'=>array('name'=>'MADAGASCAR','code'=>'261'),
                'MH'=>array('name'=>'MARSHALL ISLANDS','code'=>'692'),
                'MK'=>array('name'=>'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF','code'=>'389'),
                'ML'=>array('name'=>'MALI','code'=>'223'),
                'MM'=>array('name'=>'MYANMAR','code'=>'95'),
                'MN'=>array('name'=>'MONGOLIA','code'=>'976'),
                'MO'=>array('name'=>'MACAU','code'=>'853'),
                'MP'=>array('name'=>'NORTHERN MARIANA ISLANDS','code'=>'1670'),
                'MR'=>array('name'=>'MAURITANIA','code'=>'222'),
                'MS'=>array('name'=>'MONTSERRAT','code'=>'1664'),
                'MT'=>array('name'=>'MALTA','code'=>'356'),
                'MU'=>array('name'=>'MAURITIUS','code'=>'230'),
                'MV'=>array('name'=>'MALDIVES','code'=>'960'),
                'MW'=>array('name'=>'MALAWI','code'=>'265'),
                'MX'=>array('name'=>'MEXICO','code'=>'52'),
                'MY'=>array('name'=>'MALAYSIA','code'=>'60'),
                'MZ'=>array('name'=>'MOZAMBIQUE','code'=>'258'),
                'NA'=>array('name'=>'NAMIBIA','code'=>'264'),
                'NC'=>array('name'=>'NEW CALEDONIA','code'=>'687'),
                'NE'=>array('name'=>'NIGER','code'=>'227'),
                'NG'=>array('name'=>'NIGERIA','code'=>'234'),
                'NI'=>array('name'=>'NICARAGUA','code'=>'505'),
                'NL'=>array('name'=>'NETHERLANDS','code'=>'31'),
                'NO'=>array('name'=>'NORWAY','code'=>'47'),
                'NP'=>array('name'=>'NEPAL','code'=>'977'),
                'NR'=>array('name'=>'NAURU','code'=>'674'),
                'NU'=>array('name'=>'NIUE','code'=>'683'),
                'NZ'=>array('name'=>'NEW ZEALAND','code'=>'64'),
                'OM'=>array('name'=>'OMAN','code'=>'968'),
                'PA'=>array('name'=>'PANAMA','code'=>'507'),
                'PE'=>array('name'=>'PERU','code'=>'51'),
                'PF'=>array('name'=>'FRENCH POLYNESIA','code'=>'689'),
                'PG'=>array('name'=>'PAPUA NEW GUINEA','code'=>'675'),
                'PH'=>array('name'=>'PHILIPPINES','code'=>'63'),
                'PK'=>array('name'=>'PAKISTAN','code'=>'92'),
                'PL'=>array('name'=>'POLAND','code'=>'48'),
                'PM'=>array('name'=>'SAINT PIERRE AND MIQUELON','code'=>'508'),
                'PN'=>array('name'=>'PITCAIRN','code'=>'870'),
                'PR'=>array('name'=>'PUERTO RICO','code'=>'1'),
                'PT'=>array('name'=>'PORTUGAL','code'=>'351'),
                'PW'=>array('name'=>'PALAU','code'=>'680'),
                'PY'=>array('name'=>'PARAGUAY','code'=>'595'),
                'QA'=>array('name'=>'QATAR','code'=>'974'),
                'RO'=>array('name'=>'ROMANIA','code'=>'40'),
                'RS'=>array('name'=>'SERBIA','code'=>'381'),
                'RU'=>array('name'=>'RUSSIAN FEDERATION','code'=>'7'),
                'RW'=>array('name'=>'RWANDA','code'=>'250'),
                'SA'=>array('name'=>'SAUDI ARABIA','code'=>'966'),
                'SB'=>array('name'=>'SOLOMON ISLANDS','code'=>'677'),
                'SC'=>array('name'=>'SEYCHELLES','code'=>'248'),
                'SD'=>array('name'=>'SUDAN','code'=>'249'),
                'SE'=>array('name'=>'SWEDEN','code'=>'46'),
                'SG'=>array('name'=>'SINGAPORE','code'=>'65'),
                'SH'=>array('name'=>'SAINT HELENA','code'=>'290'),
                'SI'=>array('name'=>'SLOVENIA','code'=>'386'),
                'SK'=>array('name'=>'SLOVAKIA','code'=>'421'),
                'SL'=>array('name'=>'SIERRA LEONE','code'=>'232'),
                'SM'=>array('name'=>'SAN MARINO','code'=>'378'),
                'SN'=>array('name'=>'SENEGAL','code'=>'221'),
                'SO'=>array('name'=>'SOMALIA','code'=>'252'),
                'SR'=>array('name'=>'SURINAME','code'=>'597'),
                'ST'=>array('name'=>'SAO TOME AND PRINCIPE','code'=>'239'),
                'SV'=>array('name'=>'EL SALVADOR','code'=>'503'),
                'SY'=>array('name'=>'SYRIAN ARAB REPUBLIC','code'=>'963'),
                'SZ'=>array('name'=>'SWAZILAND','code'=>'268'),
                'TC'=>array('name'=>'TURKS AND CAICOS ISLANDS','code'=>'1649'),
                'TD'=>array('name'=>'CHAD','code'=>'235'),
                'TG'=>array('name'=>'TOGO','code'=>'228'),
                'TH'=>array('name'=>'THAILAND','code'=>'66'),
                'TJ'=>array('name'=>'TAJIKISTAN','code'=>'992'),
                'TK'=>array('name'=>'TOKELAU','code'=>'690'),
                'TL'=>array('name'=>'TIMOR-LESTE','code'=>'670'),
                'TM'=>array('name'=>'TURKMENISTAN','code'=>'993'),
                'TN'=>array('name'=>'TUNISIA','code'=>'216'),
                'TO'=>array('name'=>'TONGA','code'=>'676'),
                'TR'=>array('name'=>'TURKEY','code'=>'90'),
                'TT'=>array('name'=>'TRINIDAD AND TOBAGO','code'=>'1868'),
                'TV'=>array('name'=>'TUVALU','code'=>'688'),
                'TW'=>array('name'=>'TAIWAN, PROVINCE OF CHINA','code'=>'886'),
                'TZ'=>array('name'=>'TANZANIA, UNITED REPUBLIC OF','code'=>'255'),
                'UA'=>array('name'=>'UKRAINE','code'=>'380'),
                'UG'=>array('name'=>'UGANDA','code'=>'256'),
                'US'=>array('name'=>'UNITED STATES','code'=>'1'),
                'UY'=>array('name'=>'URUGUAY','code'=>'598'),
                'UZ'=>array('name'=>'UZBEKISTAN','code'=>'998'),
                'VA'=>array('name'=>'HOLY SEE (VATICAN CITY STATE)','code'=>'39'),
                'VC'=>array('name'=>'SAINT VINCENT AND THE GRENADINES','code'=>'1784'),
                'VE'=>array('name'=>'VENEZUELA','code'=>'58'),
                'VG'=>array('name'=>'VIRGIN ISLANDS, BRITISH','code'=>'1284'),
                'VI'=>array('name'=>'VIRGIN ISLANDS, U.S.','code'=>'1340'),
                'VN'=>array('name'=>'VIET NAM','code'=>'84'),
                'VU'=>array('name'=>'VANUATU','code'=>'678'),
                'WF'=>array('name'=>'WALLIS AND FUTUNA','code'=>'681'),
                'WS'=>array('name'=>'SAMOA','code'=>'685'),
                'XK'=>array('name'=>'KOSOVO','code'=>'381'),
                'YE'=>array('name'=>'YEMEN','code'=>'967'),
                'YT'=>array('name'=>'MAYOTTE','code'=>'262'),
                'ZA'=>array('name'=>'SOUTH AFRICA','code'=>'27'),
                'ZM'=>array('name'=>'ZAMBIA','code'=>'260'),
                'ZW'=>array('name'=>'ZIMBABWE','code'=>'263')
            );
            
            foreach($countryArray as $key => $value){
                if($key == $country_code){
                    return $value['code'];
                }
            }  
            
        }
    
    }
endif;
