<?php
/**
 * PayPing payment gateway class.
 *
 * @author   Mahdi Sarani
 * @package  LearnPress/PayPing-Payment/Classes
 * @version  3.0.0
 */

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'LP_Gateway_PayPing_Payment' ) ) {
	/**
	 * Class LP_Gateway_PayPing_Payment.
	 */
	class LP_Gateway_PayPing_Payment extends LP_Gateway_Abstract {

		/**
		 * @var LP_Settings
		 */
		public $settings;

		/**
		 * @var array
		 */
		private $form_data = array();

		/**
		 * @var string
		 */
		private $sendUrl = 'https://api.payping.ir/v3/pay';
		
		/**
		 * @var string
		 */
		private $gatewayUrl = 'https://api.payping.ir/v3/pay/start';
		
		/**
		 * @var string
		 */
		private $verifyUrl = 'https://api.payping.ir/v3/pay/verify';
		
		/**
		 * @var null
		 */
		protected $order = null;

		/**
		 * @var null
		 */
		protected $posted = null;

		/**
		 * Request TransId
		 *
		 * @var string
		 */
		protected $transId = null;
		
		/**
		 * @var string
		 */
		public $id = 'payping-payment';

		/**
		 * @var string
		 */
		private $token = null;
		
		/**
		 * @var string
		 */
		private $Debug_Mode = null;
		
		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			parent::__construct();

			$this->icon               = LP_ADDON_PAYPING_PAYMENT_URL.'assets/images/logo.png';
			$this->method_title       = __( 'پرداخت پی‌پینگ', 'learnpress-payping-payment' );
			$this->method_description = __( 'پرداخت آنلاین به وسیله کلیه کارت‌های عضو شتاب', 'learnpress-payping-payment' );

			// Get settings
			$this->title        = $this->settings->get( 'title', $this->method_title );
			$this->description  = $this->settings->get( 'description', $this->method_description );
			$this->token        = $this->settings->get( "payping_token" );
			$this->Debug_Mode   = $this->settings->get( "Debug_Mode" );

			if ( did_action( 'learn_press/payping-payment-add-on/loaded' ) ) {
				return;
			}
			
			// web hook
			if( did_action( 'init' ) ){
				$this->register_web_hook();
			} else {
				add_action( 'init', array( $this, 'register_web_hook' ) );
			}
			add_action( 'learn_press_web_hooks_processed', array( $this, 'web_hook_process_payping' ) );

			add_action("learn-press/before-checkout-order-review", array( $this, 'error_message' ));
			
			add_filter( 'learn-press/payment-gateway/' . $this->id . '/available', array(
				$this,
				'payping_payment_available'
			) );

			do_action( 'learn_press/payping-payment-add-on/loaded' );
			
		}
		
		/**
		 * Check gateway available.
		 *
		 * @return bool
		 */
		public function payping_payment_available() {
			if ( LP()->settings->get( "{$this->id}.enable" ) != 'yes' ) {
				return false;
			}

			return true;
		}

		protected function _get( $name ) {
			return LP()->settings->get( $this->id . '.' . $name );
		}
		
		/**
		 * Register web hook.
		 *
		 * @return array
		 */
		public function register_web_hook() {
			learn_press_register_web_hook( 'payping', 'learn_press_payping', 10, 2 );
		}

		/**
		 * Admin payment settings.
		 *
		 * @return array
		 */
		public function get_settings() {
			if ( version_compare( LEARNPRESS_VERSION, '4.0.0-beta-0', '>=' ) ) {
				return apply_filters( 'learn-press/gateway-payment/payping-payment/settings',
					array(
						array(
							'type' => 'title',
						),
						array(
							'title'   => __( 'فعالسازی', 'learnpress-payping-payment' ),
							'id'      => '[enable]',
							'default' => 'no',
							'type'    => 'checkbox'
						),
						array(
							'title'   => __( 'عنوان درگاه', 'learnpress-payping-payment' ),
							'id'      => '[title]',
							'default' => $this->title,
							'type'    => 'text',
						),
						array(
							'title'   => __( 'توضیحات', 'learnpress-payping-payment' ),
							'id'      => '[description]',
							'default' => $this->description,
							'type'    => 'textarea',
						),
						array(
							'title'   => __( 'توکن', 'learnpress-payping-payment' ),
							'id'      => '[payping_token]',
							'default' => $this->token,
							'type'    => 'text',
						),
						array(
							'title'   => __( 'حالت اشکال زدایی', 'learnpress-payping-payment' ),
							'id'      => '[Debug_Mode]',
							'default' => 'no',
							'type'    => 'checkbox',
							'desc'    => __( 'این حالت فقط در زمان اشکال زدایی فعال شود!' ),
						),
						array(
							'type' => 'sectionend',
						),
					)
				);
			} else {
				return apply_filters( 'learn-press/gateway-payment/payping-payment/settings',
					array(
						array(
							'title'   => __( 'فعالسازی', 'learnpress-payping-payment' ),
							'id'      => '[enable]',
							'default' => 'no',
							'type'    => 'yes-no'
						),
						array(
							'title'      => __( 'عنوان', 'learnpress-payping-payment' ),
							'id'         => '[title]',
							'default'    => $this->title,
							'type'       => 'text',
							'visibility' => array(
								'state'       => 'show',
								'conditional' => array(
									array(
										'field'   => '[enable]',
										'compare' => '=',
										'value'   => 'yes'
									)
								)
							)
						),
						array(
							'title'      => __( 'توضیحات', 'learnpress-payping-payment' ),
							'id'         => '[description]',
							'default'    => $this->description,
							'type'       => 'textarea',
							'editor'     => array( 'textarea_rows' => 5 ),
							'visibility' => array(
								'state'       => 'show',
								'conditional' => array(
									array(
										'field'   => '[enable]',
										'compare' => '=',
										'value'   => 'yes'
									)
								)
							)
						),
						array(
							'title'      => __( 'توکن', 'learnpress-payping-payment' ),
							'id'         => '[payping_token]',
							'default'    => $this->token,
							'type'       => 'text',
							'visibility' => array(
								'state'       => 'show',
								'conditional' => array(
									array(
										'field'   => '[enable]',
										'compare' => '=',
										'value'   => 'yes'
									)
								)
							)
						),
						array(
							'title'      => __( 'اشکال زدایی', 'learnpress-payping-payment' ),
							'id'         => '[Debug_Mode]',
							'default'    => 'no',
							'type'       => 'yes-no',
							'desc'       => __( 'زمانیکه قصد اشکال زدایی دارید فعال شود.' ),
							'visibility' => array(
								'state'       => 'show',
								'conditional' => array(
									array(
										'field'   => '[enable]',
										'compare' => '=',
										'value'   => 'yes'
									)
								)
							)
						)
					)
				);
			}
		}

		/**
		 * Payment form.
		 */
		public function get_payment_form() {
			return LP()->settings->get( $this->id . '.description' );
		}

		/**
		 * Error message.
		 *
		 * @return array
		 */
		public function error_message(){
			if( ! session_id() ){ session_start(); }
			if(isset($_SESSION['payping_error']) && intval($_SESSION['payping_error']) === 1) {
				$_SESSION['payping_error'] = 0;
				$template = learn_press_locate_template( 'payment-error.php', learn_press_template_path() . '/addons/payping-payment/', LP_ADDON_Payping_PAYMENT_TEMPLATE );
				include $template;
			}
		}
		
		/**
		 * Process the payment and return the result
		 *
		 * @param $order_id
		 *
		 * @return array
		 * @throws Exception
		 */
		
		/**
		 * Get form data.
		 *
		 * @return array
		 */
		public function get_form_data(){
			
			if ( $this->order ) {
				$user            = learn_press_get_current_user();
				$currency_code = learn_press_get_currency()  ;
				if ( $currency_code == 'IRR' || $currency_code == 'irr' ){
					$amount = $this->order->get_total() / 10;
				}else{
					$amount = $this->order->get_total();
				}

				$this->form_data = array(
					'amount'      => $amount,
					'currency'    => strtolower( learn_press_get_currency() ),
					'token'       => $this->token,
					'description' => sprintf( __("ثبت‌نام در دوره %s","learnpress-payping"), $user->get_data( 'email' ) ),
					'customer'    => array(
						'name'          => $user->get_data( 'display_name' ),
						'billing_email' => $user->get_data( 'email' ),
					),
					'errors'      => isset( $this->posted['form_errors'] ) ? $this->posted['form_errors'] : ''
				);
				
			}
			
			return $this->form_data;
		}
		
		/**
		 * Validate form fields.
		 *
		 * @return bool
		 * @throws Exception
		 * @throws string
		 */
		public function validate_fields(){
			$posted        = learn_press_get_request( 'learn-press-payping' );
			$email   = !empty( $posted['email'] ) ? $posted['email'] : "";
			$mobile  = !empty( $posted['mobile'] ) ? $posted['mobile'] : "";
			$description = !empty( $posted['description'] ) ? $posted['description'] : "";
			$error_message = array();
			$this->posted = $posted;
			return $error_message ? false : true;
		}
		
		public function process_payment( $order ){
			$this->order = learn_press_get_order( $order );
			$payping = $this->send();
			
			$gateway_url = $this->gatewayUrl;
			
			$json = array(
				'result'   => $payping ? 'success' : 'fail',
				'redirect'   => $payping ? $gateway_url : ''
			);

			return $json;
		}
		
		/**
		 * Send.
		 *
		 * @return bool|object
		 */
		public function send(){
			if( $this->get_form_data() ){
				
				$amount = $this->form_data['amount'];
				$payerIdentity = $this->form_data['customer']['billing_email'];
				$payerName = $this->form_data['customer']['name'];
				$description = $this->form_data['description'];
				$clientRefId = $this->order->get_id();
				
				$currency = $this->form_data['currency'];
				$TokenCode = $this->form_data['token'];
				$returnSlug = LP()->settings->get('checkout_endpoints'. '.lp_order_received');
				$returnUrl = get_site_url().'/'.$returnSlug.'/?'.learn_press_get_web_hook( 'payping' ).'=1&order_id='.$this->order->get_id();
				//$returnUrl = learn_press_get_page_link( 'checkout' ).'?'.learn_press_get_web_hook( 'payping' ).'=1&order_id='.$this->order->get_id();
				
				
				
				
				
				$params = array(
                        'PayerName'     => $payerName,
                        'Amount'        => $amount,
                        'PayerIdentity' => $payerIdentity,
                        'ReturnUrl'     => $returnUrl,
                        'clientRefId'   => (string) $clientRefId,
                        'Description'   => $description,
						'NationalCode'	=> ''
                );
				$args = array(
                    'body' => json_encode( $params, true ),
                    'timeout' => '45',
                    'redirection' => '5',
                    'httpsversion' => '1.0',
                    'blocking' => true,
	                'headers' => array(
					  'X-Platform'         => 'LearnPress',
        		  	  'X-Platform-Version' => '4.1.0',
		              'Authorization' => 'Bearer '.$TokenCode,
		              'Content-Type'  => 'application/json',
		              'Accept' => 'application/json'
		              ),
                    'cookies' => array()
                );
				
				$PayResponse = wp_remote_post( $this->sendUrl, $args );
				
				$body = wp_remote_retrieve_body( $PayResponse );
				$body =  json_decode( $body, true );
				$code = wp_remote_retrieve_response_code( $PayResponse );
				
				$order_id = $this->order->get_id();
				$payment_code = isset($body['paymentCode']) ? sanitize_text_field($body['paymentCode']) : '';

				
				if( is_wp_error( $PayResponse ) ){
					return false;
				}else{
					if( $code === 200 ){
						if ( isset( $payment_code ) && ( $payment_code != '' )) {
								update_post_meta( $order_id, 'paymentCode', $payment_code );
								$this->gatewayUrl = sprintf( '%s/%s', $this->gatewayUrl, $payment_code );
								return true;
						}else{
							return false;
						}
					}else{
						return false;
					}
				}
			}
			return false;
		}
		
				/**
		 * Handle a web hook
		 *
		 */
		public function web_hook_process_payping(){
			$request = $_REQUEST;
			
			if( isset($request['learn_press_payping']) && intval($request['learn_press_payping']) === 1 ){
				
				$order = LP_Order::instance( $request['order_id'] );
				$currency_code = learn_press_get_currency();
				if( $currency_code == 'IRR' || $currency == 'irr' ){
					$amount = $order->get_total() / 10;
				}else{
					$amount = $order->get_total();
				}
				$Amount = $amount;
				
				$raw_data = isset( $_REQUEST['data'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['data'] ) ) : '';
				$responseData = json_decode( $raw_data, true ) ?: [];
				$status          = isset( $_REQUEST['status'] ) ? absint( $_REQUEST['status'] ) : null;
				$order_id = $order->get_id();

				$user_id  = $order ? (int) $order->get_user_id() : 0;
				if ( $user_id ) {
					$user = get_userdata( $user_id );
					$slug = $user ? $user->user_nicename : '';
				}
				$raw_url = $order->get_view_order_url();
				if ( $slug ) {
					$final_url = str_replace( '/lp-profile//', '/lp-profile/' . $slug . '/', $raw_url );
				} else {
					$profile_base = trailingslashit( learn_press_get_page_link( 'profile' ) );
					$final_url    = trailingslashit( $profile_base ) . 'order-details/' . $order->get_id() . '/';
				}
				if (isset($status) && ($status === 0)) {
					$msg = sprintf(
						'پرداخت ناموفق بود. کاربر از پرداخت در درگاه انصراف داده است. کد پیگیری: %s',
						isset($responseData['paymentCode']) ? sanitize_text_field($responseData['paymentCode']) : '-'
					);
					$order->add_note($msg);
					$order->set_customer_note($msg);
					$order->update_status( 'cancelled' );
					$order->save();
					
					wp_safe_redirect( $final_url );
					exit;
				} else {
					
					$savedPaymentCode    = get_post_meta( $order_id, 'paymentCode', true );
					$paymentRefId  = isset( $responseData['paymentRefId'] ) ? sanitize_text_field( $responseData['paymentRefId'] ) : null;
					$CardNumber      = isset( $responseData['cardNumber']   ) ? sanitize_text_field( $responseData['cardNumber']   ) : '-';
					$paymentCode      = isset( $responseData['paymentCode']   ) ? sanitize_text_field( $responseData['paymentCode']   ) : '-';
					
					if ( $savedPaymentCode != $paymentCode ) {
						$msg = 'کد پرداخت ذخیره شده با کد دریافتی از درگاه مطابقت ندارد.';
						$order->add_note($msg);
						$order->set_customer_note($msg);
						$order->update_status( 'failed' );
						$order->save();

						wp_safe_redirect( $final_url );
						exit;
					} else {
						$data = [
							'PaymentRefId' => $paymentRefId,
							'paymentCode'  => $savedPaymentCode,
							'Amount'       => $Amount,
						];
						$args = array(
							'body' => json_encode($data),
							'timeout' => '45',
							'redirection' => '5',
							'httpsversion' => '1.0',
							'blocking' => true,
							'headers' => array(
							'Authorization' => 'Bearer ' . $this->token,
							'Content-Type'  => 'application/json',
							'Accept' => 'application/json'
							),
						'cookies' => array()
						);
						$response = wp_remote_post( $this->verifyUrl, $args);
						$body = wp_remote_retrieve_body( $response );
						$code = wp_remote_retrieve_response_code( $response );
						var_dump($body);
						if( $code === 409 ) {
							$dataObj = json_decode($body);
							$metaDataCode = isset($dataObj->metaData->code) ? (int) $dataObj->metaData->code : null;
							if ($metaDataCode === 110) {
								$msg = sprintf(
									'این پرداخت قبلا تایید شده است و پرداخت موفق بوده است. کد پیگیری:  %s',
									isset($paymentRefId) ? sanitize_text_field($paymentRefId) : '-'
								);
								$order->add_note($msg);
								$order->set_customer_note($msg);
								$order->update_status( 'completed' );
								update_post_meta( $order->get_id(), 'PayPing_Refid', $paymentRefId );
								$this->payment_complete( $order, ( !empty( $paymentRefId ) ? $paymentRefId : '' ), __( 'Payment has been successfully completed', 'learnpress-payping' ) );
								$order->save();

								wp_safe_redirect( $final_url );
								exit;
							} else {
								$msg = sprintf(
									'مشکلی در تایید پرداخت به وجود آمده است. لطفا به مدیر سایت اطلاع دهید. شماره پیگیری:  %s',
									isset($paymentRefId) ? sanitize_text_field($paymentRefId) : '-'
								);
								$order->add_note($msg);
								$order->set_customer_note($msg);
								$order->update_status( 'failed' );
								$order->save();

								wp_safe_redirect( $final_url );
								exit;
							}
						} elseif ($code === 200) {
							$msg = sprintf(
								'پرداخت با موفقیت انجام شد. کد پیگیری:  %s',
								isset($paymentRefId) ? sanitize_text_field($paymentRefId) : '-'
							);
							$order->add_note($msg);
							$order->set_customer_note($msg);
							$order->update_status( 'completed' );
							update_post_meta( $order->get_id(), 'PayPing_Refid', $paymentRefId );
							$this->payment_complete( $order, ( !empty( $paymentRefId ) ? $paymentRefId : '' ), __( 'Payment has been successfully completed', 'learnpress-payping' ) );
							$order->save();

							wp_safe_redirect( $final_url );
							exit;
						} else {
							$msg = sprintf(
								'مشکلی در تایید پرداخت به وجود آمده است. در صورت کسر وجه مبلغ به صورت خودکار به حساب شما بازخواهد گشت. لطفا به مدیر سایت اطلاع دهید. شماره پیگیری:  %s',
								isset($paymentRefId) ? sanitize_text_field($paymentRefId) : '-'
							);
							$order->add_note($msg);
							$order->set_customer_note($msg);
							$order->update_status( 'failed' );
							$order->save();

							wp_safe_redirect( $final_url );
							exit;
						}
					}
				}
				
			}
		}
	}
}
