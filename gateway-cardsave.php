<?php
/*
Plugin Name: WooCommerce CardSave Redirect Gateway
Plugin URI: http://www.cardsave.net
Description: Extends WooCommerce. Provides a CardSave Redirect gateway for WooCommerce.
Version: 1.0
Author: CardSave Online
Author URI: http://www.cardsave.net
*/

/*  Copyright 2011  CardSave Online  (email : ecomm@cardsave.net) 

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
add_action('plugins_loaded', 'init_your_gateway', 0);
 
function init_your_gateway() {
 
    if ( ! class_exists( 'woocommerce_payment_gateway' ) ) { return; }
	
	class woocommerce_cardsave extends woocommerce_payment_gateway {
			
		public function __construct() { 
			global $woocommerce;
			
			$this->id			= 'CardSave';
			$this->method_title = __('CardSave', 'woothemes');
			$this->logo 		= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/cardsave.gif';
			$this->has_fields 	= false;
			$this->liveurl 		= 'https://mms.cardsaveonlinepayments.com/Pages/PublicPages/PaymentForm.aspx';
			
			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->title 				= $this->settings['title'];
			$this->description 			= $this->settings['description'];
			$this->merchantid 			= $this->settings['merchantid'];
			$this->merchantpass			= $this->settings['merchantpass'];		
			$this->merchantpsk 			= $this->settings['merchantpsk'];
			
			$this->transactionDate		= date('Y-m-d H:i:s O');
			
			$this->preauthonly 	= ($this->settings['preauthonly'] == "yes" ? "PREAUTH" : "SALE");
			
			$this->CV2Mandatory 		= ($this->settings['CV2Mandatory'] == "yes" ? "TRUE" : "FALSE");
			$this->Address1Mandatory 	= ($this->settings['Address1Mandatory'] == "yes" ? "TRUE" : "FALSE");
			$this->CityMandatory 		= ($this->settings['CityMandatory'] == "yes" ? "TRUE" : "FALSE");
			$this->PostCodeMandatory 	= ($this->settings['PostCodeMandatory'] == "yes" ? "TRUE" : "FALSE");
			$this->StateMandatory 		= ($this->settings['StateMandatory'] == "yes" ? "TRUE" : "FALSE");
			$this->CountryMandatory 	= ($this->settings['CountryMandatory'] == "yes" ? "TRUE" : "FALSE");
			
			$this->AmexAccepted 	= ($this->settings['AmexAccepted'] == "yes" ? "TRUE" : "FALSE");
			
			if ($this->AmexAccepted == "TRUE") {
				$this->icon 			= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/cardsave-logos-with-amex.png';	
			} else {
				$this->icon 			= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/cardsave-logos-no-amex.png';	
			}
			
			// Actions
			add_action('init', array(&$this, 'check_cardsave_response'));
			add_action('valid-cardsave-request', array(&$this, 'successful_request'));
			add_action('woocommerce_receipt_CardSave', array(&$this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
			add_action('woocommerce_thankyou_CardSave', array(&$this, 'thankyou_page'));
		} 
		
		/**
		 * Initialise Gateway Settings Form Fields
		 */
		function init_form_fields() {
		
			$this->form_fields = array(
				'enabled' => array(
								'title' => __( '<b>Enable/Disable:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Enable CardSave Redirect Payment Module.', 'woothemes' ), 
								'default' => 'yes'
							), 
				'title' => array(
								'title' => __( '<b>Title:</b>', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'The title which the user sees during checkout.', 'woothemes' ), 
								'default' => __( 'Credit/Debit Card', 'woothemes' )
							),
				'description' => array(
								'title' => __( '<b>Description:</b>', 'woothemes' ), 
								'type' => 'textarea', 
								'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ), 
								'default' => __('Pay securely by Credit or Debit card through CardSave\'s Secure Servers.', 'woothemes')
							),
				'merchantid' => array(
								'title' => __( '<b>Merchant ID:</b>', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Please enter your Merchant ID as provided by CardSave.', 'woothemes' ), 
								'default' => ''
							),
				'merchantpass' => array(
								'title' => __( '<b>Merchant Password:</b>', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Please enter your Merchant Password as provided by CardSave.', 'woothemes' ), 
								'default' => ''
							),
				'merchantpsk' => array(
								'title' => __( '<b>Pre-Shared Key:</b>', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Please enter your Pre-Shared Key as shown in the CardSave MMS.', 'woothemes' ), 
								'default' => ''
							),
				'preauthonly' => array(
								'title' => __( '<b>"PreAuth" Payment Only:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Tick to obtain Authorisation for the payment only (you intend to manually collect the payment via the MMS).', 'woothemes' ), 
								'default' => 'no'
							),
				'AmexAccepted' => array(
								'title' => __( '<b>Accept American Express?</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Only tick if you have an American Express MID associated with your CardSave gateway account.', 'woothemes' ), 
								'default' => 'no'
							),
				'CV2Mandatory' => array(
								'title' => __( '<b>CV2 Mandatory:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( ' ', 'woothemes' ), 
								'default' => 'yes'
							), 
				'Address1Mandatory' => array(
								'title' => __( '<b>Address 1 Mandatory:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( ' ', 'woothemes' ), 
								'default' => 'yes'
							), 
				'CityMandatory' => array(
								'title' => __( '<b>City Mandatory:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( ' ', 'woothemes' ), 
								'default' => 'yes'
							), 
				'PostCodeMandatory' => array(
								'title' => __( '<b>Post Code Mandatory:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( ' ', 'woothemes' ), 
								'default' => 'yes'
							), 
				'StateMandatory' => array(
								'title' => __( '<b>State Mandatory:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( ' ', 'woothemes' ), 
								'default' => 'yes'
							),
				'CountryMandatory' => array(
								'title' => __( '<b>Country Mandatory:</b>', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( ' ', 'woothemes' ), 
								'default' => 'yes'
							)
				);
		
		} // End init_form_fields()
		
		/**
		 * Admin Panel Options 
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {

			?>
			<p><img src="<?=$this->logo;?>" /></p>
			<h3>CardSave Redirect Payments</h3>
			<p><b>Accept payments from Credit/Debit cards through the CardSave Payment Gateway.</b><br />The customer will be redirected to a secure CardSave hosted page to enter their card details.</p>
			<p><a href="https://mms.cardsaveonlinepayments.com/" target="_blank">CardSave Merchant Management System (MMS)</a><br />
			<a href="http://www.cardsave.net/" target="_blank">CardSave Website</a></p>
			<p><b>Module Version:</b> 1.0<br />
			<b>Module Date:</b> 12 October 2011</p>
			<table class="form-table">
			<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
			<?php
		} // End admin_options()
		
		/**
		 * There are no payment fields for cardsave, but we want to show the description if set.
		 **/
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
		
		/**
		 * Generate the cardsave button link
		 **/
		public function generate_cardsave_form( $order_id ) {
			global $woocommerce;
			
			$order = &new woocommerce_order( $order_id );
			
			$cardsave_adr = $this->liveurl . '?';
			
			$shipping_name = explode(' ', $order->shipping_method);	
			
			$suppcurr = array(
				'USD' => '840',
				'EUR' => '978',
				'CAD' => '124',
				'JPY' => '392',
				'GBP' => '826',
				'AUD' => '036'
			);
			
			if (in_array(get_option('woocommerce_currency'),array_keys($suppcurr))) {
				$this->currency = $suppcurr[get_option('woocommerce_currency')];
			} else {
				$this->currency = 'GBP';
			}
			
			$countriesArray = array(
				'AL' => '8',
				'DZ' => '12',
				'AS' => '16',
				'AD' => '20',
				'AO' => '24',
				'AI' => '660',
				'AG' => '28',
				'AR' => '32',
				'AM' => '51',
				'AW' => '533',
				'AU' => '36',
				'AT' => '40',
				'AZ' => '31',
				'BS' => '44',
				'BH' => '48',
				'BD' => '50',
				'BB' => '52',
				'BY' => '112',
				'BE' => '56',
				'BZ' => '84',
				'BJ' => '204',
				'BM' => '60',
				'BT' => '64',
				'BO' => '68',
				'BA' => '70',
				'BW' => '72',
				'BR' => '76',
				'BN' => '96',
				'BG' => '100',
				'BF' => '854',
				'BI' => '108',
				'KH' => '116',
				'CM' => '120',
				'CA' => '124',
				'CV' => '132',
				'KY' => '136',
				'CF' => '140',
				'TD' => '148',
				'CL' => '152',
				'CN' => '156',
				'CO' => '170',
				'KM' => '174',
				'CG' => '178',
				'CD' => '180',
				'CK' => '184',
				'CR' => '188',
				'CI' => '384',
				'HR' => '191',
				'CU' => '192',
				'CY' => '196',
				'CZ' => '203',
				'DK' => '208',
				'DJ' => '262',
				'DM' => '212',
				'DO' => '214',
				'EC' => '218',
				'EG' => '818',
				'SV' => '222',
				'GQ' => '226',
				'ER' => '232',
				'EE' => '233',
				'ET' => '231',
				'FK' => '238',
				'FO' => '234',
				'FJ' => '242',
				'FI' => '246',
				'FR' => '250',
				'GF' => '254',
				'PF' => '258',
				'GA' => '266',
				'GM' => '270',
				'GE' => '268',
				'DE' => '276',
				'GH' => '288',
				'GI' => '292',
				'GR' => '300',
				'GL' => '304',
				'GD' => '308',
				'GP' => '312',
				'GU' => '316',
				'GT' => '320',
				'GN' => '324',
				'GW' => '624',
				'GY' => '328',
				'HT' => '332',
				'VA' => '336',
				'HN' => '340',
				'HK' => '344',
				'HU' => '348',
				'IS' => '352',
				'IN' => '356',
				'ID' => '360',
				'IR' => '364',
				'IQ' => '368',
				'IE' => '372',
				'IL' => '376',
				'IT' => '380',
				'JM' => '388',
				'JP' => '392',
				'JO' => '400',
				'KZ' => '398',
				'KE' => '404',
				'KI' => '296',
				'KP' => '408',
				'KR' => '410',
				'KW' => '414',
				'KG' => '417',
				'LA' => '418',
				'LV' => '428',
				'LB' => '422',
				'LS' => '426',
				'LR' => '430',
				'LY' => '434',
				'LI' => '438',
				'LT' => '440',
				'LU' => '442',
				'MO' => '446',
				'MK' => '807',
				'MG' => '450',
				'MW' => '454',
				'MY' => '458',
				'MV' => '462',
				'ML' => '466',
				'MT' => '470',
				'MH' => '584',
				'MQ' => '474',
				'MR' => '478',
				'MU' => '480',
				'MX' => '484',
				'FM' => '583',
				'MD' => '498',
				'MC' => '492',
				'MN' => '496',
				'MS' => '500',
				'MA' => '504',
				'MZ' => '508',
				'MM' => '104',
				'NA' => '516',
				'NR' => '520',
				'NP' => '524',
				'NL' => '528',
				'AN' => '530',
				'NC' => '540',
				'NZ' => '554',
				'NI' => '558',
				'NE' => '562',
				'NG' => '566',
				'NU' => '570',
				'NF' => '574',
				'MP' => '580',
				'NO' => '578',
				'OM' => '512',
				'PK' => '586',
				'PW' => '585',
				'PA' => '591',
				'PG' => '598',
				'PY' => '600',
				'PE' => '604',
				'PH' => '608',
				'PN' => '612',
				'PL' => '616',
				'PT' => '620',
				'PR' => '630',
				'QA' => '634',
				'RE' => '638',
				'RO' => '642',
				'RU' => '643',
				'RW' => '646',
				'SH' => '654',
				'KN' => '659',
				'LC' => '662',
				'PM' => '666',
				'VC' => '670',
				'WS' => '882',
				'SM' => '674',
				'ST' => '678',
				'SA' => '682',
				'SN' => '686',
				'SC' => '690',
				'SL' => '694',
				'SG' => '702',
				'SK' => '703',
				'SI' => '705',
				'SB' => '90',
				'SO' => '706',
				'ZA' => '710',
				'ES' => '724',
				'LK' => '144',
				'SD' => '736',
				'SR' => '740',
				'SJ' => '744',
				'SZ' => '748',
				'SE' => '752',
				'CH' => '756',
				'SY' => '760',
				'TW' => '158',
				'TJ' => '762',
				'TZ' => '834',
				'TH' => '764',
				'TG' => '768',
				'TK' => '772',
				'TO' => '776',
				'TT' => '780',
				'TN' => '788',
				'TR' => '792',
				'TM' => '795',
				'TC' => '796',
				'TV' => '798',
				'UG' => '800',
				'UA' => '804',
				'AE' => '784',
				'GB' => '826',
				'US' => '840',
				'UY' => '858',
				'UZ' => '860',
				'VU' => '548',
				'VE' => '862',
				'VN' => '704',
				'VG' => '92',
				'VI' => '850',
				'WF' => '876',
				'EH' => '732',
				'YE' => '887',
				'ZM' => '894',
				'ZW' => '716'
			);
			
			if (in_array($order->billing_country,array_keys($countriesArray))) {
				$this->countryISO = $countriesArray[$order->billing_country];
			} else {
				$this->countryISO = "";
			}			
			
			$HashDigest="PreSharedKey=" . $this->merchantpsk;
			$HashDigest=$HashDigest . '&MerchantID=' . $this->merchantid;
			$HashDigest=$HashDigest . '&Password=' . $this->merchantpass;
			$HashDigest=$HashDigest . '&Amount=' . $order->order_total*100;
			$HashDigest=$HashDigest . '&CurrencyCode=' . $this->currency;
			$HashDigest=$HashDigest . '&OrderID=' . $order_id;
			$HashDigest=$HashDigest . '&TransactionType=' . $this->preauthonly;
			$HashDigest=$HashDigest . '&TransactionDateTime=' . $this->transactionDate;
			$HashDigest=$HashDigest . '&CallbackURL=' . add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))));
			$HashDigest=$HashDigest . '&OrderDescription=' . "";
			$HashDigest=$HashDigest . '&CustomerName=' . $order->billing_first_name . " " . $order->billing_last_name;
			$HashDigest=$HashDigest . '&Address1=' . $order->billing_address_1;
			$HashDigest=$HashDigest . '&Address2=' . $order->billing_address_2;
			$HashDigest=$HashDigest . '&Address3=' . $order->billing_address_3;
			$HashDigest=$HashDigest . '&Address4=' . $order->billing_address_4;
			$HashDigest=$HashDigest . '&City=' . $order->billing_city;
			$HashDigest=$HashDigest . '&State=' . $order->billing_state;
			$HashDigest=$HashDigest . '&PostCode=' . $order->billing_postcode;
			$HashDigest=$HashDigest . '&CountryCode=' . $this->countryISO;
			$HashDigest=$HashDigest . "&CV2Mandatory=" . $this->CV2Mandatory;
			$HashDigest=$HashDigest . "&Address1Mandatory=" . $this->Address1Mandatory;
			$HashDigest=$HashDigest . "&CityMandatory=" . $this->CityMandatory;
			$HashDigest=$HashDigest . "&PostCodeMandatory=" . $this->PostCodeMandatory;
			$HashDigest=$HashDigest . "&StateMandatory=" . $this->StateMandatory;
			$HashDigest=$HashDigest . "&CountryMandatory=" . $this->CountryMandatory;
			$HashDigest=$HashDigest . "&ResultDeliveryMethod=" . 'SERVER';
			$HashDigest=$HashDigest . "&ServerResultURL=" . trailingslashit(get_bloginfo('wpurl')).'?cardsave_listener=cardsave_server_callback';
			$HashDigest=$HashDigest . "&PaymentFormDisplaysResult=" . 'FALSE';
			$HashDigest=$HashDigest . "&ServerResultURLCookieVariables=" . '';
			$HashDigest=$HashDigest . "&ServerResultURLFormVariables=" . '';
			$HashDigest=$HashDigest . "&ServerResultURLQueryStringVariables=" . '';
			
			$HashDigest=sha1($HashDigest);
			
			$cardsave_args = array(
				'HashDigest' 			=> $HashDigest,
				'MerchantID'			=> $this->merchantid,
				'Amount' 				=> $order->order_total*100,
				'CurrencyCode' 			=> $this->currency,
				'OrderID' 				=> $order_id,
				'TransactionType' 		=> $this->preauthonly,
				'TransactionDateTime' 	=> $this->transactionDate,
				'CallbackURL' 			=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))),
				'OrderDescription' 		=> "",
				'CustomerName' 			=> $order->billing_first_name . " " . $order->billing_last_name,
				'Address1' 				=> $order->billing_address_1,
				'Address2' 				=> $order->billing_address_2,
				'Address3' 				=> "",
				'Address4' 				=> "",
				'City' 					=> $order->billing_city,
				'State' 				=> $order->billing_state,
				'PostCode' 				=> $order->billing_postcode,
				'CountryCode' 				=> $this->countryISO,
				'CV2Mandatory' 				=> $this->CV2Mandatory,
				'Address1Mandatory' 		=> $this->Address1Mandatory,
				'CityMandatory' 			=> $this->CityMandatory,
				'PostCodeMandatory' 		=> $this->PostCodeMandatory,
				'StateMandatory' 			=> $this->StateMandatory,
				'CountryMandatory' 			=> $this->CountryMandatory,
				'ResultDeliveryMethod' 		=> "SERVER",
				'ServerResultURL' 			=> trailingslashit(get_bloginfo('wpurl')).'?cardsave_listener=cardsave_server_callback',
				'PaymentFormDisplaysResult' 			=> "FALSE",
				'ServerResultURLCookieVariables' 		=> "",
				'ServerResultURLFormVariables' 			=> "",
				'ServerResultURLQueryStringVariables' 	=> ""
			);
					
			$cardsave_args_array = array();

			foreach ($cardsave_args as $key => $value) {
				$cardsave_args_array[] = '<input type="hidden" name="'.$key.'" value="'. $value .'" />';
			}
			
			return '<form action="'.$cardsave_adr.'" method="post" id="cardsave_payment_form">
					' . implode('', $cardsave_args_array) . '
					<input type="submit" class="button-alt" id="submit_cardsave_payment_form" value="'.__('Pay via cardsave', 'woothemes').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
					<script type="text/javascript">
						jQuery(function(){
							jQuery("body").block(
								{ 
									message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to CardSave to make payment.', 'woothemes').'", 
									overlayCSS: 
									{ 
										background: "#fff", 
										opacity: 0.6 
									},
									css: { 
										padding:        20, 
										textAlign:      "center", 
										color:          "#555", 
										border:         "3px solid #aaa", 
										backgroundColor:"#fff", 
										cursor:         "wait",
										lineHeight:		"32px"
									} 
								});
							jQuery("#submit_cardsave_payment_form").click();
						});
					</script>
				</form>';
			
		}
		
		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {
			
			$order = &new woocommerce_order( $order_id );
			
			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			);
			
		}
		
		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {
			
			echo '<p>'.__('Thank you for your order, please click the button below to pay with cardsave.', 'woothemes').'</p>';
			
			echo $this->generate_cardsave_form( $order );
			
		}
		
		/**
		* Check for valid CardSave server callback
		**/
		function check_cardsave_response() {
			global $woocommerce;
			
			if (isset($_GET['cardsave_listener']) && $_GET['cardsave_listener'] == 'cardsave_server_callback') {
			
				$cardsave_response = array (
					'szHashDigest' => "",
					'szOutputMessage' => "",
					'boErrorOccurred' => false,
					'nStatusCode' => 30,
					'szMessage' => "",
					'nPreviousStatusCode' => 0,
					'szPreviousMessage' => "",
					'szCrossReference' => "",
					'nAmount' => 0,
					'nCurrencyCode' => 0,
					'szOrderID' => "",
					'szTransactionType' => "",
					'szTransactionDateTime' => "",
					'szOrderDescription' => "",
					'szCustomerName' => "",
					'szAddress1' => "",
					'szAddress2' => "",
					'szAddress3' => "",
					'szAddress4' => "",
					'szCity' => "",
					'szState' => "",
					'szPostCode' => "",
					'nCountryCode' => ""
				);
				
				$szOutputMessage = "";
				$boErrorOccurred = false;
				$nOutputProcessedOK = 0;
				
		
				try
					{
						// hash digest
						if (isset($_POST["HashDigest"]))
						{
							$cardsave_response['szHashDigest'] = $_POST["HashDigest"];
						}
		
						// transaction status code
						if (!isset($_POST["StatusCode"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [StatusCode] not received";
							$boErrorOccurred = true;
						}
						else
						{
							if ($_POST["StatusCode"] == "")
							{
								$cardsave_response['nStatusCode'] = null;
							}
							else
							{
								$cardsave_response['nStatusCode'] = intval($_POST["StatusCode"]);
							}
						}
						// transaction message
						if (!isset($_POST["Message"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [Message] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szMessage'] = $_POST["Message"];
						}
						// status code of original transaction if this transaction was deemed a duplicate
						if (!isset($_POST["PreviousStatusCode"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [PreviousStatusCode] not received";
							$boErrorOccurred = true;
						}
						else
						{
							if ($_POST["PreviousStatusCode"] == "")
							{
								$cardsave_response['nPreviousStatusCode'] = null;
							}
							else
							{
								$cardsave_response['nPreviousStatusCode'] = intval($_POST["PreviousStatusCode"]);
							}
						}
						// status code of original transaction if this transaction was deemed a duplicate
						if (!isset($_POST["PreviousMessage"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [PreviousMessage] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szPreviousMessage'] = $_POST["PreviousMessage"];
						}
						// cross reference of transaction
						if (!isset($_POST["CrossReference"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [CrossReference] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szCrossReference'] = $_POST["CrossReference"];
						}
						// amount (same as value passed into payment form - echoed back out by payment form)
						if (!isset($_POST["Amount"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [Amount] not received";
							$boErrorOccurred = true;
						}
						else
						{
							if ($_POST["Amount"] == null)
							{
								$cardsave_response['nAmount'] = null;
							}
							else
							{
								$cardsave_response['nAmount'] = intval($_POST["Amount"]);
							}
						}
						// currency code (same as value passed into payment form - echoed back out by payment form)
						if (!isset($_POST["CurrencyCode"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [CurrencyCode] not received";
							$boErrorOccurred = true;
						}
						else
						{
							if ($_POST["CurrencyCode"] == null)
							{
								$cardsave_response['nCurrencyCode'] = null;
							}
							else
							{
								$cardsave_response['nCurrencyCode'] = intval($_POST["CurrencyCode"]);
							}
						}
						// order ID (same as value passed into payment form - echoed back out by payment form)
						if (!isset($_POST["OrderID"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [OrderID] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szOrderID'] = $_POST["OrderID"];
						}
						// transaction type (same as value passed into payment form - echoed back out by payment form)
						if (!isset($_POST["TransactionType"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [TransactionType] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szTransactionType'] = $_POST["TransactionType"];
						}
						// transaction date/time (same as value passed into payment form - echoed back out by payment form)
						if (!isset($_POST["TransactionDateTime"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [TransactionDateTime] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szTransactionDateTime'] = $_POST["TransactionDateTime"];
						}
						// order description (same as value passed into payment form - echoed back out by payment form)
						if (!isset($_POST["OrderDescription"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [OrderDescription] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$szOrderDescription = $_POST["OrderDescription"];
						}
						// customer name (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["CustomerName"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [CustomerName] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szCustomerName'] = $_POST["CustomerName"];
						}
						// address1 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["Address1"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [Address1] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szAddress1'] = $_POST["Address1"];
						}
						// address2 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["Address2"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [Address2] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szAddress2'] = $_POST["Address2"];
						}
						// address3 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["Address3"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [Address3] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szAddress3'] = $_POST["Address3"];
						}
						// address4 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["Address4"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [Address4] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szAddress4'] = $_POST["Address4"];
						}
						// city (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["City"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [City] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szCity'] = $_POST["City"];
						}
						// state (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["State"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [State] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szState'] = $_POST["State"];
						}
						// post code (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["PostCode"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [PostCode] not received";
							$boErrorOccurred = true;
						}
						else
						{
							$cardsave_response['szPostCode'] = $_POST["PostCode"];
						}
						// country code (not necessarily the same as value passed into payment form - as the customer can change it on the form)
						if (!isset($_POST["CountryCode"]))
						{
							$szOutputMessage = $szOutputMessage . "Expected variable [CountryCode] not received";
							$boErrorOccurred = true;
						}
						else
						{
							if ($_POST["CountryCode"] == "")
							{
								$cardsave_response['nCountryCode'] = null;
							}
							else
							{
								$cardsave_response['nCountryCode'] = intval($_POST["CountryCode"]);
							}
						}
					}
				catch (Exception $e)
				{
					$boErrorOccurred = true;
					$szOutputMessage = "Error";
					if (isset($_POST["Message"]))
					{
						$cardsave_response['szOutputMessage'] = $_POST["Message"];
					}
				}
			
				// The nOutputProcessedOK should return 0 except if there has been an error talking to the gateway or updating the website order system.
				// Any other process status shown to the gateway will prompt the gateway to send an email to the merchant stating the error.
				// The customer will also be shown a message on the hosted payment form detailing the error and will not return to the merchants website.
				$nOutputProcessedOK = 0;
				
				if (is_null($cardsave_response['nStatusCode']))
				{
					$nOutputProcessedOK = 30;		
				}
				
				if ($boErrorOccurred == true)
				{
					$nOutputProcessedOK = 30;
				}
				
				// Calculate a hash to check against the passed hash to check the values passed are legitimate.		
				$calchash="PreSharedKey=" . $this->merchantpsk;
				$calchash=$calchash . '&MerchantID=' . $_POST["MerchantID"];
				$calchash=$calchash . '&Password=' . $this->merchantpass;
				$calchash=$calchash . '&StatusCode=' . $_POST["StatusCode"];
				$calchash=$calchash . '&Message=' . $_POST["Message"];
				$calchash=$calchash . '&PreviousStatusCode=' . $_POST["PreviousStatusCode"];
				$calchash=$calchash . '&PreviousMessage=' . $_POST["PreviousMessage"];
				$calchash=$calchash . '&CrossReference=' . $_POST["CrossReference"];
				$calchash=$calchash . '&Amount=' . $_POST["Amount"];
				$calchash=$calchash . '&CurrencyCode=' . $_POST["CurrencyCode"];
				$calchash=$calchash . '&OrderID=' . $_POST["OrderID"];
				$calchash=$calchash . '&TransactionType=' . $_POST["TransactionType"];
				$calchash=$calchash . '&TransactionDateTime=' . $_POST["TransactionDateTime"];
				$calchash=$calchash . '&OrderDescription=' . $_POST["OrderDescription"];
				$calchash=$calchash . '&CustomerName=' . $_POST["CustomerName"];
				$calchash=$calchash . '&Address1=' . $_POST["Address1"];
				$calchash=$calchash . '&Address2=' . $_POST["Address2"];
				$calchash=$calchash . '&Address3=' . $_POST["Address3"];
				$calchash=$calchash . '&Address4=' . $_POST["Address4"];
				$calchash=$calchash . '&City=' . $_POST["City"];
				$calchash=$calchash . '&State=' . $_POST["State"];
				$calchash=$calchash . '&PostCode=' . $_POST["PostCode"];
				$calchash=$calchash . '&CountryCode=' . $_POST["CountryCode"];
				$calchash = sha1($calchash);
				
				if ($calchash != $cardsave_response['szHashDigest']) {
					$nOutputProcessedOK = 30; 
					$szOutputMessage = $szOutputMessage . "Hashes did not match";
				} 
				
				if($nOutputProcessedOK == 30) {
					echo("StatusCode=".$nOutputProcessedOK."&Message=".$szOutputMessage);
				} else {
					do_action("valid-cardsave-request", $cardsave_response);
				}
			}
		}		
			
		/**
		 * Server callback was valid, process callback (update order as passed/failed etc).
		 **/
		function successful_request($cardsave_response) {
			global $woocommerce;
			
			$nOutputProcessedOK = 0;
			$szOutputMessage = "";
			
			if (isset($cardsave_response)) {
			
				try {
					switch ($cardsave_response['nStatusCode']) {
						// transaction authorised
						case 0:
							$transauthorised = true;
							break;
						// card referred (treat as decline)
						case 4:
							$transauthorised = false;
							break;
						// transaction declined
						case 5:
							$transauthorised = false;
							break;
						// duplicate transaction
						case 20:
							// need to look at the previous status code to see if the
							// transaction was successful
							if ($cardsave_response['nPreviousStatusCode'] == 0)
							{
								// transaction authorised
								$transauthorised = true;
							}
							else
							{
								// transaction not authorised
								$transauthorised = false;
							}
							break;
						// error occurred
						case 30:
							$transauthorised = false;
							break;
						default:
							$transauthorised = false;
							break;
					}
				
					if ($transauthorised == true) {
						// put code here to update/store the order with the a successful transaction result
						$order_id 	  	= $cardsave_response['szOrderID'];
						$order 			= new woocommerce_order( (int) $order_id );
										
						if ($order->status !== 'completed') {
							if ($order->status == 'processing') {
								// This is the second call - do nothing
							} else {
								$order->payment_complete();
								//Add admin order note
								$order->add_order_note('CardSave Payment: SUCCESSFUL<br>'. $cardsave_response['szMessage'] .'<br>Transaction Cross Reference: ' . $cardsave_response['szCrossReference']);								
								//Add customer order note
								$order->add_order_note('Payment Successful - ' . $cardsave_response['szMessage'],'customer');
								// Empty the Cart
								$woocommerce->cart->empty_cart();
							}
						}
					} else {
						// put code here to update/store the order with the a failed transaction result
						$order_id 	  	= $cardsave_response['szOrderID'];
						$order 			= new woocommerce_order( (int) $order_id );					
						$order->update_status('failed');
						//Add admin order note
						$order->add_order_note('CardSave Payment: FAILED<br>Failure Message: '. $cardsave_response['szMessage'] .'<br>Transaction Cross Reference: ' . $cardsave_response['szCrossReference']);
						//Add customer order note
						$order->add_order_note('Payment Failed - ' . $cardsave_response['szMessage'],'customer');
					}
				} catch (Exception $e) {
					$nOutputProcessedOK = 30;
					$szOutputMessage = "Error updating website system, please ask the developer to check code";
				}
				//echo string back for gateway to read - this confirms the script ran correctly.
				echo("StatusCode=".$nOutputProcessedOK."&Message=".$szOutputMessage);
			}
		}	
		
		function thankyou_page () {
			global $woocommerce;
			
			//grab the order ID from the querystring
			$order_id 	  	= $_GET['OrderID'];
			//lookup the order details
			$order 			= new woocommerce_order( (int) $order_id );
			
			//check the status of the order
			if ($order->status == 'processing') {
				//display additional success message
				echo "<p>Your payment for ". woocommerce_price($order->order_total) ." was successful. The authorisation code has been recorded, <a href=\"/?page_id=10&order=". $order_id ."\">click here to view your order</a></p>";
			} else {
				//display additional failed message
				echo "<br>&nbsp;<p>For further information on why your order might have failed, <a href=\"/?page_id=10&order=". $order_id ."\">click here to view your order</a>.</p>";
			}
		}		
	}
}

/**
 * Add the gateway to WooCommerce
 **/
function add_cardsave_gateway( $methods ) {
	$methods[] = 'woocommerce_cardsave'; return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_cardsave_gateway' );
