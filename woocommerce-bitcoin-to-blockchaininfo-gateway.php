<?php
/**
 * Plugin Name: WooCommerce Bitcoin to Blockchain.Info Wallet Payment Gateway
 * Plugin URI: https://github.com/cameleater/woocommerce-bitcoin-to-blockchaininfo-gateway
 * Description: Allows customers to pay with Bitcoin at checkout into a Blockchain.Info wallet using unique wallet addresses for each order.
 * Author: cameleater
 * Author URI: https://github.com/cameleater/woocommerce-bitcoin-to-blockchaininfo-gateway
 * Version: 1.0.0
 * Text Domain: wc-gateway-bitcoin
 * Domain Path: /i18n/languages/
 *
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Bitcoin
 * @author    cameleater
 * @category  Admin
 * @copyright Copyright (c) 2015-2016, cameleater, and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */

defined( 'ABSPATH' ) or exit;



// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Gets unique bitcoin address from users xPub address.
 *
 * @param  string $address_to_pay takes in current address to make sure not to overwrite existing address
 * @return string $address a unique bitcoin address for current order
 */
function get_unique_bitcoin_address() {
		$json = file_get_contents("https://api.blockchain.info/v2/receive?key=828d4ee7-42d6-4746-ad81-4d2160604cc8&callback=http%3A%2F%2Fjros.co.nz&xpub=xpub6DJwm6RuCiyrHFMLujTZn47yGTSNVZXhz2k9pb3kWZqvq6dgVsDFsGMTw1NtfQ72uzNKmtMp9BLmzcmSu4Cgx2CA2mvVKsJhuu8Wycz8JXq");
		$response = json_decode($json);
		$address = $response->address;
		return $address;
}

/**
 * Used to create table in database to store order and payment details, should only get called on plugin activation.
 */
function table_install () {
   global $wpdb;

   $table_name = $wpdb->prefix . 'bitcoin_payment'; 
   
   $charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  order_id mediumint(9) UNIQUE NOT NULL,
	  address text NOT NULL,
	  price_btc text NOT NULL,
	  price_fiat text NOT NULL,
	  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
	
/**
 * Create table in database to store order and payment details, should only run on plugin activation.
 */
register_activation_hook( __FILE__, 'table_install' );


/**
 * Inserts row into bitcoin_payment table storing data of an order
 *
 * @param  string $order_id takes in ID of current order
 * @param string $price_btc the price in BTC of current order
 * @param string $price_fiat the fiat price of current order
 *
 */	
function table_insert_data($order_id, $price_btc, $price_fiat) {
	global $wpdb;
	
	$con=mysqli_connect("localhost","webitweb_wp96","S.1HYp@44k","webitweb_wp96");
	// Check connection
	if (mysqli_connect_errno())
	  {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	  }
		
	$table_name = $wpdb->prefix . 'bitcoin_payment';

	// Insert data into table if row with $order_id doesn't already exist.	
	$result = mysqli_query($con, "SELECT 1 FROM $table_name WHERE order_id='$order_id' LIMIT 1");
	if (mysqli_fetch_row($result)) {
		mysqli_close($con);
		return;
	} else {
		$address = get_unique_bitcoin_address();
		
		$wpdb->insert( 
			$table_name, 
			array( 
				'order_id' => $order_id, 
				'address' => $address, 
				'price_btc' => $price_btc, 
				'price_fiat' => $price_fiat,
				'time' => current_time( 'mysql' ),
			) 
		);
	}
	mysqli_close($con);
	return;
}

/**
 * Retrieve bitcoin address from table using $order_id
 *
 * @param string $order_id retrieves bitcoin address of current ID of order
 * @return array $row returns part of array that holds address of current order ID
 *
 */
function retrieve_bitcion_address( $order_id ) {
	global $wpdb;

	$con=mysqli_connect("localhost","webitweb_wp96","S.1HYp@44k","webitweb_wp96");
	// Check connection
	if (mysqli_connect_errno())
	  {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	  }	
	
	$table_name = $wpdb->prefix . 'bitcoin_payment';
	
	$result = mysqli_query($con, "SELECT address FROM $table_name WHERE order_id='$order_id' LIMIT 1");
	if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    return $row["address"];
    }
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + bitcoin gateway
 */
function wc_bitcoin_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Bitcoin';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_bitcoin_add_to_gateways' );


/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_bitcoin_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bitcoin_gateway' ) . '">' . __( 'Configure', 'wc-gateway-bitcoin' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_bitcoin_gateway_plugin_links' );


/**
 * Bitcoin to Blockchain.Info wallet Payment Gateway
 *
 * Allows customers to pay with Bitcoin at checkout into a Blockchain.Info wallet using unique wallet addresses for each order.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Bitcoin
 * @extends		WC_Payment_Gateway
 * @package		WooCommerce/Classes/Paymentameleater
 */
add_action( 'plugins_loaded', 'wc_bitcoin_gateway_init', 11 );

function wc_bitcoin_gateway_init() {
	
	

	class WC_Gateway_Bitcoin extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			
			$this->address_to_pay;
			$this->bitcoin_price;
			
			$this->id                 = 'bitcoin_gateway';
			$this->icon               = apply_filters('woocommerce_bitcoin_icon', '\images\bitcoin-logo.png');
			$this->has_fields         = false;
			$this->method_title       = __( 'Bitcoin to Blockchain.Info Wallet', 'wc-gateway-bitcoin' );
			$this->method_description = __( 'Allows bitcoin payments. Customers can send bitcoin to your Blockchain.Info wallet using unique wallet addresses for each order.', 'wc-gateway-bitcoin' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			$this->xpub 		= $this->get_option( 'xpub' );


			
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );			
		}
		
		
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			
			

			$this->form_fields = apply_filters( 'wc_bitcoin_form_fields', array(

				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-bitcoin' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Bitcoin Payments in checkout', 'wc-gateway-bitcoin' ),
					'default' => 'yes'
				),

				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-bitcoin' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-bitcoin' ),
					'default'     => __( 'Pay with Bitcoin', 'wc-gateway-bitcoin' ),
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-bitcoin' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-bitcoin' ),
					'default'     => __( 'Pay now with Bitcoin and your order will be confirmed after 3 confirmations.', 'wc-gateway-bitcoin' ),
					'desc_tip'    => true,
				),

				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-bitcoin' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the emails.', 'wc-gateway-bitcoin' ),
					'default'     => '<h2>Bitcoin Payment</h2><table style="border-color:black;border-width:3px;border-radius:3px !important;"><tr><td class="btc-table-data">Bitcoin Amount, please include the network fee so we receive this amount</td><td>' . $this->bitcoin_price . '</td></tr><tr><td style="border-top-color:black;">Bitcoin Address To Pay</td><td style="border-top-color:black;">' . $this->address_to_pay . '</td></tr></table>',
					'desc_tip'    => true,
				),
				
				'xpub' => array(
					'title'       => __( 'xPub address', 'wc-gateway-bitcoin' ),
					'type'        => 'textarea',
					'description' => __( 'Add your xPub address so the plugin will generate unique bitcoin addresses for each transaction.', 'wc-gateway-bitcoin' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}


		/**
		 * Output for the order received page.
		 * @param WC_Order $order
		 */
		public function thankyou_page( $order_id ) {
			// Get NZD in BTC
			$order = wc_get_order( $order_id );
			$nzd_price = $order->get_total();
			$this->bitcoin_price = file_get_contents("https://api.blockchain.info/tobtc?currency=NZD&value=" . $nzd_price);
			
			// Retrieves order data to print
			$this->address_to_pay = retrieve_bitcion_address( $order_id );
			
			// Print table showing bitcoin price and bitcoin wallet
			echo '<h2>Bitcoin Payment</h2><table style="border-color:black;border-width:3px;border-radius:3px !important;"><tr><td class="btc-table-data">Bitcoin Amount, please include the network fee so we receive this amount</td><td>' . $this->bitcoin_price . '</td></tr><tr><td style="border-top-color:black;">Bitcoin Address To Pay</td><td style="border-top-color:black;">' . $this->address_to_pay . '</td></tr></table>';			
		}


		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment) and add amount to be paid into address
			$order->update_status( 'on-hold', __( 'Awaiting Bitcoin confirmations', 'wc-gateway-bitcoin' ) );
			
			// Get NZD in BTC
			$nzd_price = $order->get_total();
			$this->bitcoin_price = file_get_contents("https://api.blockchain.info/tobtc?currency=NZD&value=" . $nzd_price);
			
			// Retrieves bitcoin address to print
			$this->address_to_pay = retrieve_bitcion_address( $order_id );
			
			// Add bitcoin price and address to order notes
			$price_and_address_note = $this->bitcoin_price . ' BTC should be deposited into ' . $this->address_to_pay;
			$order->add_order_note($price_and_address_note);
				
			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
		
		
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			
			// Get NZD in BTC
			//$order = wc_get_order( $order_id );
			$nzd_price = $order->get_total();
			$this->bitcoin_price = file_get_contents("https://api.blockchain.info/tobtc?currency=NZD&value=" . $nzd_price);
			
			// Gets ID of current order
			$order_id = trim( str_replace( '#', '', $order->get_order_number() ) );
			
			// Saves order ID, bitcoin price, fiat price, bitcoin address, and time of order in database
			table_insert_data($order_id, $this->bitcoin_price, $nzd_price);
			
			// Retrieves order data to print
			$this->address_to_pay = retrieve_bitcion_address( $order_id );
			
			// Prints bitcoin price and bitcoin address table into email
			echo '<h2>Bitcoin Payment</h2><table class="bitcoin-payment-table"><tr><td class="btc-table-data">Bitcoin Amount, please include the network fee so we receive this amount</td><td>' . $this->bitcoin_price . '</td></tr><tr><td>Bitcoin Address To Pay</td><td>' . $this->address_to_pay . '</td></tr></table>';
		}

  } // end \WC_Gateway_Bitcoin class
}
