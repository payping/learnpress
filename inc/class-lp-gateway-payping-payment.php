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
		private $sendUrl = 'https://api.payping.io/v2/pay';
		
		/**
		 * @var string
		 */
		private $gatewayUrl = 'https://api.payping.io/v2/pay/gotoipg';
		
		/**
		 * @var string
		 */
		private $verifyUrl = 'https://api.payping.io/v2/pay/verify';
		
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
			learn_press_register_web_hook( 'payping', 'learn_press_payping' );
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
					$amount = $this->order->order_total / 10;
				}else{
					$amount = $this->order->order_total;
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
			return $error ? false : true;
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
				$payerIdentity = $this->form_data['billing_email'];
				$payerName = $this->form_data['name'];
				$description = $this->form_data['description'];
				$clientRefId = $this->order->get_id();
				
				$currency = $this->form_data['currency'];
				$TokenCode = $this->form_data['token'];
				
				$returnUrl = get_site_url().'/?'.learn_press_get_web_hook( 'payping' ).'=1&order_id='.$this->order->get_id();
				
				
				$params = array(
                        'amount'        => $amount,
                        'returnUrl'     => $returnUrl,
                        'payerIdentity' => $payerIdentity,
                        'payerName'     => $payerName,
                        'clientRefId'   => $clientRefId,
                        'description'   => $description
                );
				$args = array(
                    'body' => json_encode( $params, true ),
                    'timeout' => '45',
                    'redirection' => '5',
                    'httpsversion' => '1.0',
                    'blocking' => true,
	                'headers' => array(
		              'Authorization' => 'Bearer '.$TokenCode,
		              'Content-Type'  => 'application/json',
		              'Accept' => 'application/json'
		              ),
                    'cookies' => array()
                );

				$PayResponse = wp_remote_post( $this->sendUrl, $args );
        		$ResponseXpId = wp_remote_retrieve_headers( $PayResponse )['x-paypingrequest-id'];
				if( is_wp_error( $PayResponse ) ){
					return false;
				}else{
					$code = wp_remote_retrieve_response_code( $PayResponse );
					if( $code === 200 ){
						if ( isset( $PayResponse["body"] ) && $PayResponse["body"] != '' ) {
							$CodePay = wp_remote_retrieve_body( $PayResponse );
							$CodePay =  json_decode( $CodePay, true );
							if( isset( $CodePay ) && $CodePay != '' ){
								$this->gatewayUrl = sprintf( '%s/%s', $this->gatewayUrl, $CodePay["code"] );
								return true;
							}else{
								wp_redirect( sprintf( '%s/%s', $this->gatewayUrl, $CodePay["code"] ) );
								exit;
							}
							return false;
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
					$amount = $order->order_total / 10;
				}else{
					$amount = $order->order_total;
				}
				$Amount = $amount;
				$refid = $_POST['refid'];
				$data = array('refId' => $refid, 'amount' => $Amount);
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
//				$XPP_ID = $response["headers"]["x-paypingrequest-id"];
				if( is_wp_error($response) ){
					wp_redirect(esc_url( learn_press_get_page_link( 'checkout' ) ));
				}else{
					$code = wp_remote_retrieve_response_code( $response );
					if( $code === 200 && isset( $refid ) && $refid != '' ){
						$request['transId'] = $refid;
						$this->payment_status_completed( $order, $request );
						wp_redirect(esc_url( $this->get_return_url( $order ) ));
						exit();
					}else{
						wp_redirect(esc_url( learn_press_get_page_link( 'checkout' )  ));
						exit();
					}
					exit();
				}
				exit();
			}
		}
		
		/**
		 * Handle a completed payment
		 *
		 * @param LP_Order
		 * @param request
		 */
		protected function payment_status_completed( $order, $request ){

			// order status is already completed
			if( $order->has_status( 'completed' ) ){
				exit;
			}

			$this->payment_complete( $order, ( !empty( $request['transId'] ) ? $request['transId'] : '' ), __( 'Payment has been successfully completed', 'learnpress-payping' ) );
			update_post_meta( $order->get_id(), 'PayPing_Refid', $request['transId'] );
		}

		/**
		 * Handle a pending payment
		 *
		 * @param  LP_Order
		 * @param  request
		 */
		protected function payment_status_pending( $order, $request ){
			$this->payment_status_completed( $order, $request );
		}

		/**
		 * @param        LP_Order
		 * @param string $txn_id
		 * @param string $note - not use
		 */
		public function payment_complete( $order, $trans_id = '', $note = '' ){
			$order->payment_complete( $trans_id );
		}
		
		/* */
	}
}
