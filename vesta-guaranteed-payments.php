<?php
/**
 * Plugin Name: Vesta Guaranteed Payments
 * Description: Vesta's Guaranteed Payments guarantees funds for all accepted CNP transactions with zero liability for fraud-related chargebacks. Leveraging decades of experience in supporting billions of payments, our core solution is proven to drive revenue for the world's largest brands.
 * version: 1.0.0
 * author: Vesta Corporation.
 * Text Domain: wc_vesta_payment
 * Domain Path: /languages
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $_SESSION ) ) {
	session_start();
}

/**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function vesta_wocommerce_missing_wc_notice() {
	 echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Vesta payment gateway requires WooCommerce to be installed and active. You can download %s here.', 'vesta-woocommerce-payment' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}
add_action( 'plugins_loaded', 'vesta_woocommerce_payment_init' );

/**
 * Method call when plugin initialize
 *
 * @return void
 */
function vesta_woocommerce_payment_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'vesta_wocommerce_missing_wc_notice' );
		return;
	}
	$domain = 'wc_vesta_payment';
	$mo_file = WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . get_locale() . '.mo';

	load_textdomain( $domain, $mo_file ); 
	load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	// add script and css files
	add_action( 'wp_enqueue_scripts', 'add_vesta_payment_style' );
	function add_vesta_payment_style() {
		wp_enqueue_style( 'vesta_payment_css', plugins_url( '/vesta-guaranteed-payments/assets/css/vesta_payment_styles.css', dirname( __FILE__ ) ) );
		wp_enqueue_style( 'vesta_google_font', 'https://fonts.googleapis.com/css?family=Roboto' );
		wp_enqueue_script( 'vesta_payment_js', plugins_url( '/vesta-guaranteed-payments/assets/js/creditCardValidator.js', dirname( __FILE__ ) ) );
		wp_enqueue_script( 'vesta_token_js', plugins_url( '/vesta-guaranteed-payments/assets/js/vestatoken-1.0.3.js', dirname( __FILE__ ) ) );
		wp_register_script( 'vesta_pay', plugins_url( '/vesta-guaranteed-payments/assets/js/vesta_pay.js', dirname( __FILE__ ) ), array( 'jquery', 'vesta_token_js' ) );
		wp_enqueue_script( 'vesta_custom', plugins_url( '/vesta-guaranteed-payments/assets/js/vesta_custom.js', dirname( __FILE__ ) ) );

	}
	add_action('admin_enqueue_scripts', 'vesta_payment_enqueue');
	function vesta_payment_enqueue() {
		wp_enqueue_script( 'vesta_custom', plugins_url( '/vesta-guaranteed-payments/assets/js/vesta_admin_custom.js', dirname( __FILE__ ) ) );	
	}
	// Include files
	include plugin_dir_path( __FILE__ ) . 'includes/vesta_payment_config.php';
	include plugin_dir_path( __FILE__ ) . 'includes/vesta_payment_capture.php';
	include plugin_dir_path( __FILE__ ) . 'includes/vesta_datacollector_finger_print.php';

	add_filter( 'woocommerce_payment_gateways', 'add_vesta_payment_gateway' );

	/**
	 * Register payment method
	 *
	 * @param  [type] $methods
	 * @return void
	 */
	function add_vesta_payment_gateway( $methods ) {
		$methods[] = 'WC_Vesta_Payment_Gateway';
		return $methods;
	}

	// Add custom action links
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_vesta_payment_action_links' );

	/**
	 * call method for action link
	 *
	 * @param  [type] links
	 * @return void
	 */
	function wc_vesta_payment_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_vesta_payment' ) . '">' . __( 'Settings', 'wc_vesta_payment' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	// add menu for vesta payment log
	add_action( 'admin_menu', 'vesta_payment_log_menu_page' );

	/**
	 * Method for add menu
	 *
	 * @return void
	 */
	function vesta_payment_log_menu_page() {
		add_menu_page( 'Vesta Payment Logs', 'Vesta Payment Logs', 'edit_posts', 'vesta-payment-logs', 'vesta_payment_log_list_page' );
	}

	/**
	 * Call function for payment log menu
	 *
	 * @return void
	 */
	function vesta_payment_log_list_page() {
		include plugin_dir_path( __FILE__ ) . 'includes/vesta_payment_logs.php';
		$vesta_payment_logs = new VestaPaymentLog();
		$vesta_payment_logs->prepare_items();
		$message = '';
		$get_variable = filter_input_array(INPUT_GET);
		if ( 'delete' === $vesta_payment_logs->current_action() ) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf( __( 'Logs deleted: %d', 'wc_vesta_payment' ), count( $get_variable['id'] ) ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<div id="icon-users" class="icon32"></div>
			<h2><?php echo  __('Vesta Payment Logs','wc_vesta_payment'); ?></h2>
		<?php echo $message; ?>
			<form id="events-filter" method="get">
				<input type="hidden" name="page" value="<?php echo $get_variable['page']; ?>" />
		<?php $vesta_payment_logs->display(); ?>
			</form>
		</div>
		<?php
	}

}

/**
* Hook activated on plugin activated
*/
register_activation_hook( __FILE__, 'vesta_payment_plugin_create_db' );

/**
 * Call back method for register activation hook
 *
 * @return void
 */
function vesta_payment_plugin_create_db() {
	 global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$vesta_payment_log_table = $wpdb->prefix . 'vesta_payment_logs';

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$vesta_payment_log_table}'" ) != $vesta_payment_log_table ) {
		$sql = "CREATE TABLE $vesta_payment_log_table (
			id int(11) NOT NULL AUTO_INCREMENT,
            order_id int(11) NOT NULL,
            response_code varchar(256) NOT NULL,
            response_text varchar(256) DEFAULT NULL,
            created_on datetime NOT NULL,
            UNIQUE KEY id (id)
	) $charset_collate;";
		dbDelta( $sql );
	}
}

// uninstall hook
register_uninstall_hook( __FILE__, 'Vesta_payment_uninstall' );

/**
 * Method call on vesta plugin get uninstall.
 */
function Vesta_payment_uninstall() {
	include_once plugin_dir_path( __FILE__ ) . 'uninstall.php';
}
