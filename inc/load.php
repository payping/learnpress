<?php
/**
 * Plugin load class.
 *
 * @author   Mahdi Sarani
 * @package  LearnPress/PayPing-Payment/Classes
 * @version  3.0.0
 */

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Addon_PayPing_Payment' ) ) {
	/**
	 * Class LP_Addon_PayPing_Payment.
	 */
	class LP_Addon_PayPing_Payment extends LP_Addon {
		/**
		 * Addon version
		 *
		 * @var string
		 */
		public $version = LP_ADDON_PAYPING_PAYMENT_VER;

		/**
		 * Require LP version
		 *
		 * @var string
		 */
		public $require_version = LP_ADDON_PAYPING_PAYMENT_REQUIRE_VER;

		/**
		 * Path file addon.
		 *
		 * @var string
		 */
		public $plugin_file = LP_ADDON_PAYPING_PAYMENT_FILE;

		/**
		 * LP_Addon_PayPing_Payment constructor.
		 */
		public function __construct() {
			parent::__construct();

			add_filter( 'learn-press/payment-methods', array( $this, 'add_payment' ) );
			add_filter( 'learn_press_payment_method', array( $this, 'add_payment' ) );
		}

		/**
		 * Add PayPing payment to payment system.
		 *
		 * @param $methods
		 *
		 * @return mixed
		 */
		public function add_payment( $methods ) {
			$methods['payping-payment'] = 'LP_Gateway_PayPing_Payment';

			return $methods;
		}


		/**
		 * Define constants.
		 */
		protected function _define_constants() {
			if ( ! defined( 'LP_ADDON_PAYPING_PAYMENT_PATH' ) ) {
				define( 'LP_ADDON_PAYPING_PAYMENT_PATH', LP_ADDON_PAYPING_PAYMENT_FILE );
			}
		}


		public function plugin_links() {
			$links = array(
				'settings' => '<a href="' . admin_url( 'admin.php?page=learn-press-settings&tab=payments&section=payping-payment' ) . '">' . __( 'تنظیمات',
						'learnpress-payping-payment' ) . '</a>'
			);

			return $links;
		}

		/**
		 * Include needed files
		 */
		protected function _includes() {
			require_once ( LP_ADDON_PAYPING_PAYMENT_PATH . "/inc/class-lp-gateway-payping-payment.php" );
		}

		public function plugin_url( $file = '' ) {
			return plugins_url( '/' . $file, LP_ADDON_PAYPING_PAYMENT_FILE );
		}
	}
}
