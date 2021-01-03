<?php
/**
 * Plugin load class.
 *
 * @author   PayPing
 * @package  LearnPress/PayPing/Classes
 * @version  1.0.0
 */

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Addon_PayPingPayment' ) ) {
	/**
	 * Class LP_Addon_PayPingPayment
	 */
	class LP_Addon_PayPingPayment extends LP_Addon {

		/**
		 * @var string
		 */
		public $version = LP_ADDON_PayPingPAYMENT_VER;

		/**
		 * @var string
		 */
		public $require_version = LP_ADDON_PayPingPAYMENT_REQUIRE_VER;

		/**
		 * LP_Addon_PayPingPayment constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Define Learnpress PayPing.ir payment constants.
		 *
		 */
		protected function _define_constants() {
			define( 'LP_ADDON_PayPingPAYMENT_PATH', dirname( LP_ADDON_PayPingPAYMENT_FILE ) );
			define( 'LP_ADDON_PayPingPAYMENT_INC', LP_ADDON_PayPingPAYMENT_PATH . '/inc/' );
			define( 'LP_ADDON_PayPingPAYMENT_URL', plugin_dir_url( LP_ADDON_PayPingPAYMENT_FILE ) );
			define( 'LP_ADDON_PayPingPAYMENT_TEMPLATE', LP_ADDON_PayPingPAYMENT_PATH . '/templates/' );
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 */
		protected function _includes() {
			include_once LP_ADDON_PayPingPAYMENT_INC . 'class-lp-gateway-payping.php';
		}

		/**
		 * Init hooks.
		 */
		protected function _init_hooks() {
			// add payment gateway class
			add_filter( 'learn_press_payment_method', array( $this, 'add_payment' ) );
			add_filter( 'learn-press_payment-methods', array( $this, 'add_payment' ) );
		}

		/**
		 * Enqueue assets.
		 *
		 */
		protected function _enqueue_assets() {
			return;
			
			if (LP()->settings->get( 'learn_press_payping_enable' ) == 'yes' ) {
				$user = learn_press_get_current_user();

				learn_press_assets()->enqueue_script( 'learn-press-payping-payment', $this->get_plugin_url( 'assets/js/script.js' ), array() );
//				learn_press_assets()->enqueue_style( 'learn-press-payping', $this->get_plugin_url( 'assets/css/style.css' ), array() );

				$data = array(
					'plugin_url'  => plugins_url( '', LP_ADDON_PayPingPAYMENT_FILE )
				);
				wp_localize_script( 'learn-press-payping', 'learn_press_payping_info', $data );
			}
		}

		/**
		 * Add PayPing.ir to payment system.
		 *
		 * @param $methods
		 *
		 * @return mixed
		 */
		public function add_payment( $methods ) {
			$methods['payping'] = 'LP_Gateway_PayPing';
			return $methods;
		}

		/**
		 * Plugin links.
		 *
		 * @return array
		 */
		public function plugin_links() {
			$links[] = '<a href="' . admin_url( 'admin.php?page=learn-press-settings&tab=payments&section=payping' ) . '">' . __( 'تنظیمات', 'learnpress-payping' ) . '</a>';

			return $links;
		}
	}
}