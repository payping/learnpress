<?php
/*
Plugin Name: LearnPress PayPing Gateway
Plugin URI: https://payping.ir
Description: PayPing payment gateway for LearnPress.
Author: Mahdi Sarani
Version: 1.1.0
Author URI: https://mahdisarani.ir
Text Domain: learnpress-payping
Domain Path: /languages/
*/

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

define( 'LP_Payping_uri', plugin_dir_url( __FILE__ ) );
define( 'LP_ADDON_PayPingPAYMENT_FILE', __FILE__ );
define( 'LP_ADDON_PayPingPAYMENT_VER', '1.1.0' );
define( 'LP_ADDON_PayPingPAYMENT_REQUIRE_VER', '1.1.0' );

/**-------------------------------------------------------------------------------------------------------------------------
 * change iranin currency and symbol in learnpress
 */
function payping_override_iranin_currency( $currencies ) {
	$currencies['IRR'] = __( 'Iranian rial(payping)', 'learnpress' );
	$currencies['IRt'] = __( 'Iranian Toman (payping)', 'learnpress' );
	return $currencies;
}

add_filter( 'learn-press/currencies', 'payping_override_iranin_currency' );

function payping_override_iranin_currency_symbol( $symbols ) {
	$symbols['IRR'] = 'ریال';
	$symbols['IRT'] = 'تومان';
	return $symbols;
}
add_filter( 'learn-press/currency-symbols', 'payping_override_iranin_currency_symbol' );

/**
 * Class LP_Addon_PayPingPayment_Preload
 */
class LP_Addon_PayPingPayment_Preload{

	/**
	 * LP_Addon_PayPingPayment_Preload constructor.
	 */
	public function __construct(){
		load_plugin_textdomain( 'learnpress-payping', false, basename( dirname(__FILE__) ) . '/languages' );
		add_action( 'learn-press/ready', array( $this, 'load' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Load addon
	 */
	public function load(){
		LP_Addon::load( 'LP_Addon_PayPingPayment', 'inc/load.php', __FILE__ );
		remove_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Admin notice
	 */
	public function admin_notices() {
		?>
        <div class="error">
            <p><?php echo wp_kses(
					sprintf(
						__( '<strong>%s</strong> addon version %s requires %s version %s or higher is <strong>installed</strong> and <strong>activated</strong>.', 'learnpress-payping' ),
						__( 'LearnPress PayPing Payment', 'learnpress-payping' ),
						LP_ADDON_PayPingPAYMENT_VER,
						sprintf( '<a href="%s" target="_blank"><strong>%s</strong></a>', admin_url( 'plugin-install.php?tab=search&type=term&s=learnpress' ), __( 'LearnPress', 'learnpress-payping' ) ),
						LP_ADDON_PayPingPAYMENT_REQUIRE_VER 
					),
					array(
						'a'      => array(
							'href'  => array(),
							'blank' => array()
						),
						'strong' => array()
					)
				); ?>
            </p>
        </div>
		<?php
	}
}

new LP_Addon_PayPingPayment_Preload();

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