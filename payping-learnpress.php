<?php
/**
 * Plugin Name: PayPing Learnpress
 * Plugin URI: https://payping.ir
 * Description: درگاه پرداخت پی پینگ برای افزونه Learnpress
 * Author: Mahdi Sarani
 * Version: 4.1.0
 * Author URI: https://mahdisarani.ir
 * Tags: learnpress, payping
 * Text Domain: learnpress-payping
 * Domain Path: /languages/
 * Requires Plugins: learnpress
 * Require_LP_Version: 3.0.0
 * License: GPLv3 or later
 *
 * @package LearnPress-PayPing-Payment
 */

defined( 'ABSPATH' ) || exit;

define( 'LP_ADDON_PAYPING_PAYMENT_FILE', plugin_dir_path(__FILE__) );
define( 'LP_ADDON_PAYPING_PAYMENT_URL', plugin_dir_url( __FILE__ ) );


/**
 * Class LP_Addon_PayPing_Payment_Preload
 */
class LP_Addon_PayPing_Payment_Preload {
	/**
	 * @var array
	 */
	public static $addon_info = array();

	/**
	 * LP_Addon_PayPing_Payment_Preload constructor.
	 */
	public function __construct() {
		// Set Base name plugin.
		define( 'LP_ADDON_PAYPING_PAYMENT_BASENAME', plugin_basename( LP_ADDON_PAYPING_PAYMENT_FILE ) );

		// Set version addon for LP check .
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		self::$addon_info = get_file_data(
			LP_ADDON_PAYPING_PAYMENT_FILE,
			array(
				'Name'               => 'Plugin Name',
				'Require_LP_Version' => 'Require_LP_Version',
				'Version'            => 'Version',
			)
		);

		define( 'LP_ADDON_PAYPING_PAYMENT_VER', self::$addon_info['Version'] );
		define( 'LP_ADDON_PAYPING_PAYMENT_REQUIRE_VER', self::$addon_info['Require_LP_Version'] );

		// Check LP activated .
		if ( ! is_plugin_active( 'learnpress/learnpress.php' ) ) {
			add_action( 'admin_notices', array( $this, 'show_note_errors_require_lp' ) );

			deactivate_plugins( LP_ADDON_PAYPING_PAYMENT_BASENAME );

			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}

			return;
		}

		// Sure LP loaded.
		add_action( 'learn-press/ready', array( $this, 'load' ) );
	}

	/**
	 * Load addon
	 */
	public function load() {
		LP_Addon::load( 'LP_Addon_PayPing_Payment', 'inc/load.php', __FILE__ );
	}

	public function show_note_errors_require_lp() {
		?>
		<div class="notice notice-error">
			<p><?php echo( 'Please active <strong>LP version ' . LP_ADDON_PAYPING_PAYMENT_REQUIRE_VER . ' or later</strong> before active <strong>' . self::$addon_info['Name'] . '</strong>' ); ?></p>
		</div>
		<?php
	}
}

new LP_Addon_PayPing_Payment_Preload();
add_action('init', 'PayPingGateway_session_start', 1);
function PayPingGateway_session_start(){
   if( ! session_id() ){
	  session_start();
   }
}

add_action('wp_logout', 'PayPingGateway_session_end');
function PayPingGateway_session_end(){
   if( session_id() ){
     $_SESSION = [];
     session_destroy();
   }
}