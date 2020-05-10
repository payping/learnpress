<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Gateway_PayPing' ) ) {
	/**
	 * Class LP_Gateway_PayPing
	 */
	class LP_Gateway_PayPing extends LP_Gateway_Abstract {

		/**
		 * @var array
		 */
		private $form_data = array();

		/**
		 * @var string
		 */
		private $sendUrl = 'https://payping.ir/v2/pay';
		
		/**
		 * @var string
		 */
		private $gatewayUrl = 'https://payping.ir/v2/pay/gotoipg/';
		
		/**
		 * @var string
		 */
		private $verifyUrl = 'https://payping.ir/v2/pay/verify';
		
		/**
		 * @var string
		 */
		private $token = null;

		/**
		 * @var array|null
		 */
		protected $settings = null;

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
		 * LP_Gateway_PayPing constructor.
		 */
		public function __construct() {
			$this->id = 'payping';

			$this->method_title       =  __( 'درگاه پرداخت پی‌پینگ', 'learnpress-payping' );;
			$this->method_description = __( 'ایجاد پرداخت با پی‌پینگ.', 'learnpress-payping' );
			$this->icon               = '';

			// Get settings
			$this->title       = LP()->settings->get( "{$this->id}.title", $this->method_title );
			$this->description = LP()->settings->get( "{$this->id}.description", $this->method_description );

			$settings = LP()->settings;

			// Add default values for fresh installs
			if ( $settings->get( "{$this->id}.enable" ) ) {
				$this->settings                     = array();
				$this->settings['token']        = $settings->get( "{$this->id}.token" );
			}
			
			$this->token = $this->settings['token'];
			
			
			if ( did_action( 'learn_press/payping-add-on/loaded' ) ) {
				return;
			}

			// check payment gateway enable
			add_filter( 'learn-press/payment-gateway/' . $this->id . '/available', array(
				$this,
				'payping_available'
			), 10, 2 );

			do_action( 'learn_press/payping-add-on/loaded' );

			parent::__construct();
			
			// web hook
			if ( did_action( 'init' ) ) {
				$this->register_web_hook();
			} else {
				add_action( 'init', array( $this, 'register_web_hook' ) );
			}
			add_action( 'learn_press_web_hooks_processed', array( $this, 'web_hook_process_payping' ) );
			
			add_action("learn-press/before-checkout-order-review", array( $this, 'error_message' ));
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

			return apply_filters( 'learn-press/gateway-payment/payping/settings',
				array(
					array(
						'title'   => __( 'فعالسازی', 'learnpress-payping' ),
						'id'      => 'enable',
						'default' => 'no',
						'type'    => 'yes-no'
					),
					array(
						'type'       => 'text',
						'title'      => __( 'عنوان', 'learnpress-payping' ),
						'default'    => __( 'PayPing', 'learnpress-payping' ),
						'id'         => 'title',
						'class'      => 'regular-text',
						'visibility' => array(
							'state'       => 'show',
							'conditional' => array(
								array(
									'field'   => 'enable',
									'compare' => '=',
									'value'   => 'yes'
								)
							)
						)
					),
					array(
						'type'       => 'textarea',
						'title'      => __( 'توضیحات', 'learnpress-payping' ),
						'default'    => __( 'پرداخت با پی‌پینگ', 'learnpress-payping' ),
						'id'         => 'description',
						'editor'     => array(
							'textarea_rows' => 5
						),
						'css'        => 'height: 50px;',
						'visibility' => array(
							'state'       => 'show',
							'conditional' => array(
								array(
									'field'   => 'enable',
									'compare' => '=',
									'value'   => 'yes'
								)
							)
						)
					),
					array(
						'title'      => __( 'توکن پی‌پینگ', 'learnpress-payping' ),
						'id'         => 'token',
						'type'       => 'text',
						'visibility' => array(
							'state'       => 'show',
							'conditional' => array(
								array(
									'field'   => 'enable',
									'compare' => '=',
									'value'   => 'yes'
								)
							)
						)
					)
				)
			);
		}

		/**
		 * Error message.
		 *
		 * @return array
		 */
		public function error_message() {
			if( ! session_id() ){ session_start(); }
			if(isset($_SESSION['payping_error']) && intval($_SESSION['payping_error']) === 1) {
				$_SESSION['payping_error'] = 0;
				$template = learn_press_locate_template( 'payment-error.php', learn_press_template_path() . '/addons/payping-payment/', LP_ADDON_Payping_PAYMENT_TEMPLATE );
				include $template;
			}
		}
		
		/**
		 * @return mixed
		 */
		public function get_icon() {
			if ( empty( $this->icon ) ) {
				$this->icon = LP_Payping_uri . '/assets/img/logo.png';
			}
			return parent::get_icon();
		}

		/**
		 * Check gateway available.
		 *
		 * @return bool
		 */
		public function payping_available() {
			if ( LP()->settings->get( "{$this->id}.enable" ) != 'yes' ) {
				return false;
			}
			return true;
		}
		
		/**
		 * Get form data.
		 *
		 * @return array
		 */
		public function get_form_data() {
			if ( $this->order ) {
				$user            = learn_press_get_current_user();
				$currency_code = learn_press_get_currency()  ;
				if ($currency_code == 'IRR') {
					$amount = $this->order->order_total ;
				} else {
					$amount = $this->order->order_total * 10;
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
		public function validate_fields() {
			$posted        = learn_press_get_request( 'learn-press-payping' );
			$email   = !empty( $posted['email'] ) ? $posted['email'] : "";
			$mobile  = !empty( $posted['mobile'] ) ? $posted['mobile'] : "";
			$description = !empty( $posted['description'] ) ? $posted['description'] : "";
			$error_message = array();
			$this->posted = $posted;
			return $error ? false : true;
		}
		
		/**
		 * PayPing payment process.
		 *
		 * @param $order
		 *
		 * @return array
		 * @throws string
		 */
		public function process_payment( $order ) {
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
				
				$returnUrl = get_site_url() . '/?' . learn_press_get_web_hook( 'payping' ) . '=1&order_id='.$this->order->get_id();
				
				$params = array(
                        'amount'        => $amount,
                        'returnUrl'     => $returnUrl,
                        'payerIdentity' => $payerIdentity,
                        'payerName'     => $payerName,
                        'clientRefId'   => $clientRefId,
                        'description'   => $description
                );
				$args['body'] = json_encode( $params, true );
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
							var_dump( $CodePay );
							wp_redirect( sprintf( '%s/%s', $this->gatewayUrl, $CodePay["code"] ) );
							exit;
							
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
		public function web_hook_process_payping() {
			$request = $_REQUEST;
			
			if(isset($request['learn_press_payping']) && intval($request['learn_press_payping']) === 1) {
				$token = $_GET['token'];
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->verifyUrl);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "token=".$this->token."&token=$token");
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				$result = curl_exec($ch);
				curl_close($ch);
				$result = json_decode($result);
				
				$order = LP_Order::instance( $request['order_id'] );
				$currency_code = learn_press_get_currency();
				if ($currency_code == 'IRR') {
					$amount = $order->order_total;
				} else {
					$amount = $order->order_total * 10 ;
				}
				
				if(intval($result->status) === 1 && $result->amount ==  $amount) {
					$this->authority = intval($_GET['Authority']);
					$this->payment_status_completed($order , $request);
					wp_redirect(esc_url( $this->get_return_url( $order ) ));
					exit();
				}
				if(!isset($_SESSION))
					session_start();
				$_SESSION['payping_error'] = 1;
				wp_redirect(esc_url( learn_press_get_page_link( 'checkout' )  ));
				exit();
			}
		}
		
		/**
		 * Handle a completed payment
		 *
		 * @param LP_Order
		 * @param request
		 */
		protected function payment_status_completed( $order, $request ) {

			// order status is already completed
			if ( $order->has_status( 'completed' ) ) {
				exit;
			}

			$this->payment_complete( $order, ( !empty( $request['transId'] ) ? $request['transId'] : '' ), __( 'Payment has been successfully completed', 'learnpress-payping' ) );

		}

		/**
		 * Handle a pending payment
		 *
		 * @param  LP_Order
		 * @param  request
		 */
		protected function payment_status_pending( $order, $request ) {
			$this->payment_status_completed( $order, $request );
		}

		/**
		 * @param        LP_Order
		 * @param string $txn_id
		 * @param string $note - not use
		 */
		public function payment_complete( $order, $trans_id = '', $note = '' ) {
			$order->payment_complete( $trans_id );
		}
	}
}
