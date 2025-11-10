<?php

/**
 * Plugin Name: The Courier Guy Locker Shipping for WooCommerce
 * Description: The Courier Guy Locker Woocommerce Shipping functionality.
 * Author: The Courier Guy
 * Author URI: https://www.thecourierguy.co.za/
 * Version: 1.5.1
 * Plugin Slug: wp-plugin-pudo-for-wc
 * Text Domain: pudo-for-wc
 * WC requires at least: 5.0
 * WC tested up to: 10.1.2
 *
 * WP tested up to: 6.8
 */

use Pudo\WooCommerce\Pudo_Shipping_Method;
use Pudo\WooCommerce\PudoApi;

/**
 *  Copyright: © 2025 The Courier Guy
 */
// Ensure the WP Absolute path is defined
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Include Pudo-Core
require_once 'Includes/ls-framework-custom/Core/PudoPluginDependencies.php';
require_once 'Includes/ls-framework-custom/Core/PudoPlugin.php';
require_once 'Includes/ls-framework-custom/Core/PudoPostType.php';

require_once 'vendor/autoload.php';

// Register and activation of plugin
// Dependent on WooCommerce being installed
register_activation_hook( __FILE__, 'pudo_plugin_activated' );
add_action( 'admin_init', 'pudo_plugin_registered' );

// Shipping actions

function pudo_plugin_activated() {
	add_option( 'pudoMsg', '1', 0, true );
}

// Autoload plugin classes (Include classes)
spl_autoload_register(
	function ( $class ) {
		$parts      = explode( '\\', $class );
		$class_path = plugin_dir_path( __FILE__ ) . 'classes/';
		$file_path  = $class_path . end( $parts ) . '.php';
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

// Initialise the plugin
wc_pudo_shipping_init();
/**
 * initialize shipping plugin function
 *
 * @param \WC_Order $order
 */
function wc_pudo_shipping_init() {
	require_once __DIR__ . '/classes/Product.php';

	add_action( 'woocommerce_shipping_init', 'initiate_pudo_shipping_method' );
	add_action( 'wp_enqueue_scripts', 'register_pudo_js_resources' );
	add_action( 'admin_enqueue_scripts', 'register_pudo_js_resources' );

	// Create PUDO orders in backend
	add_filter(
		'manage_edit-shop_order_columns',
		array(
			Pudo_Shipping_Method::class,
			'addCollectionActionAndPrintWaybillToOrderList',
		),
		20
	);

	// Use the getter function to get order ID
	function wc_add_order_meta_box_action( $actions, $order ) {
		if ( ! $order || ! str_contains( strtolower( $order->get_meta( 'pudo_method' ) ), 'pickup_dropoff' ) ) {
			return $actions;
		}
		$meta           = $order->get_meta_data();
		$orderGenerated = false;

		foreach ( $meta as $m ) {
			$orderMetaData = $m->get_data();
			// Check that a transaction id is present

			if ( $orderMetaData['key'] === 'pudo_booking_id' ) {
				$orderGenerated = true;
			}
		}

		if ( $orderGenerated ) {
			$actions['wc_custom_order_action_label']  = __( 'Print The Courier Guy Locker Label', 'pudo' );
			$actions['wc_custom_order_action_waybil'] = __( 'Print The Courier Guy Locker Waybill', 'pudo' );
		} else {
			$actions['wc_custom_order_action_pudo'] = __( 'Create The Courier Guy Locker Shipment', 'pudo' );
		}

		return $actions;
	}

	add_action( 'woocommerce_order_actions', 'wc_add_order_meta_box_action', 10, 2 );

	/**
	 * Add an order note when custom action is clicked
	 * Add a flag on the order to show it's been run
	 *
	 * @param \WC_Order $order
	 */
	function wc_print_waybill_from_order( $order ) {
		Pudo_Shipping_Method::printWaybillFromOrder( $order );
	}

	/**
	 * Add an order note when custom action is clicked
	 * Add a flag on the order to show it's been run
	 *
	 * @param \WC_Order $order
	 */
	function wc_print_label_from_order( $order ) {
		Pudo_Shipping_Method::printLabelFromOrder( $order );
	}

	// Add custom order action in "Order Meta box" (Select drop down on right hand side)
	add_action( 'woocommerce_order_action_wc_custom_order_action_waybil', 'wc_print_waybill_from_order' );
	add_action( 'woocommerce_order_action_wc_custom_order_action_label', 'wc_print_label_from_order' );
	/** On processing a pudo order request
	 *
	 * @param $order
	 *
	 * @return void
	 */
	function wc_process_order_meta_box_action( $order ) {
		custom_form_endpoint_handler();
	}

	add_action( 'admin_notices', 'custom_display_admin_message' );

	function custom_display_admin_message() {
		global $pagenow;

		// Check if we are on the post editing page and the post type is "shop_order."
		if ( $pagenow === 'post.php' && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'shop_order' ) {
			// Get the post ID from the URL parameter 'post'.
			$post_id = absint( $_GET['post'] );

			// Check if the post meta "pudo_status" is not 'none'.
			$pudo_status = get_post_meta( $post_id, 'pudo_status', true );

			if ( $pudo_status == 'Booking confirmed' ) {
				$message     = 'The Courier Guy Locker ' . $pudo_status . ' for Order ' . $post_id;
				$noticeClass = 'success';
				echo '<div class="notice notice-' . $noticeClass . ' is-dismissible"><p>' . $message . '</p></div>';
			} elseif ( $pudo_status !== 'none' && $pudo_status !== 'booking confirmed' ) {
				$message     = $pudo_status;
				$noticeClass = 'error';
				echo '<div class="notice notice-' . $noticeClass . '"><p>' . $message . '</p></div>';
			}
		}
	}

	// Add custom actions to Woocommerce "Order meta box" (Order box on right hand side)
	add_action( 'woocommerce_order_action_wc_custom_order_action_pudo', 'wc_process_order_meta_box_action' );

	// Add custom column to woocommerce orders grid
	add_action(
		'manage_shop_order_posts_custom_column',
		array( Pudo_Shipping_Method::class, 'collectActionAndPrintWaybillOnOrderlistContent' ),
		20,
		2
	);

	// Add javascript for orders list
	add_action( 'admin_head', array( Pudo_Shipping_Method::class, 'addCustomJavascriptForOrderList' ) );
	add_action( 'admin_head', array( Pudo_Shipping_Method::class, 'display_pudo_notice' ) );
	// Add createShipmentFromOrder Hook
	add_action(
		'woocommerce_order_action_pudo_send_collection',
		array(
			Pudo_Shipping_Method::class,
			'createShipmentFromOrder',
		),
		10,
		1
	);

	// Add print waybill hook (Only displayed if order already requested against api)
	add_action(
		'woocommerce_order_action_pudo_print_waybill',
		array(
			Pudo_Shipping_Method::class,
			'printWaybillFromList',
		),
		10,
		1
	);

	// Make function to print pudo waybill available
	add_action( 'admin_post_print_pudo_waybill', array( Pudo_Shipping_Method::class, 'printWaybillFromList' ) );

	// Submit from Orders Grid function
	add_action(
		'wp_ajax_submit_pudo_collection_from_listing_page',
		array( Pudo_Shipping_Method::class, 'setCollectionFromOrderListingPage' )
	);
}

/**
 * Return instance of shipping method
 *
 * @param $settings
 *
 * @return Pudo_Shipping_Method
 */
function initiate_pudo_shipping_method( $settings ) {
	return new Pudo_Shipping_Method();
}

/**
 * Ensure the woocommerce plugin is installed and enabled
 *
 * @return bool
 */
function pudo_wc_is_installed() {
	// Fetch array containing active plugins from table wp_options
	$active_plugins = get_option( 'active_plugins' );
	// Check If woocommerce/woocommerce.php is not found in array
	if ( ! in_array( 'woocommerce/woocommerce.php', $active_plugins ) ) {
		return false;
	} else {
		register_setting( 'pudo_woocommerce', 'dismissed-pudo_disclaimer' );
		if ( get_option( 'pudoMsg', false ) ) {
			echo '
            <div class="updated notice notice-the-courier-guy is-dismissible" data-notice="tcg_disclaimer">
            <p><strong>Pudo Shipping</strong></p>
            <p>Parcel sizes are based on your packaging structure. The plugin will compare the cart’s total
                    dimensions against “Flyer”, “Medium” and “Large” parcel sizes to determine the best fit. The
                    resulting calculation will be submitted to The Pudo API using the parcel’s dimensions.
                    <strong>By downloading and using this plugin, you accept that incorrect ‘Parcel Size’ settings</strong></p>
            </div>
            ';
			delete_option( 'pudoMsg' );
		}

		return true;
	}
}

/**
 * Ensure the plugin is registered else return a admin notice
 *
 * @return void
 */
function pudo_plugin_registered() {
	$active_plugins = get_option( 'active_plugins' );

	if ( pudo_wc_is_installed() ) {
		$active_plugins[] = plugin_basename( __FILE__ );
	} else {
		add_action( 'admin_notices', 'addInvalidPluginNotice' );
		deactivate_plugins( plugin_basename( __FILE__ ) );
		unset( $_GET['activate'] );
	}
}

/**
 * Shipping debug alert
 *
 * @return void
 */
function woocom_shipping_debug_check() {
	$pudo                       = new Pudo_Shipping_Method();
	$woocommerce_shipping_debug = $pudo->checkShippingDebug();
	if ( $woocommerce_shipping_debug == 'yes' ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				_e(
					'Please note PUDO Cannot run whilst Woocommerce Shipping Debug is enabled',
					'sample-text-domain'
				);
				?>
			</p>
		</div>
		<?php
	}
}

/**
 * Admin notice for invalid plugin (Add to body)
 *
 * @return void
 */
function addInvalidPluginNotice() {
	echo getInvalidPluginNotice();
}

/**
 * Return the actual notice
 *
 * @return string
 */
function getInvalidPluginNotice() {
	return <<<NOTICE
    <div id="message" class="error">
    <p>WooCommerce is required for this plugin</p>
    </div>
    NOTICE;
}

/**
 * Include JS/CSS Resources for plugin into global scope
 *
 * @return void
 */
function register_pudo_js_resources() {
	$pluginData    = get_plugin_data( __FILE__ );
	$pluginVersion = $pluginData['Version'];
	// Inclusion of css
	wp_enqueue_script( 'pudo_js', plugins_url( '/dist/js/pudo.js', __FILE__ ), array( 'jquery' ), $pluginVersion, true );

	$pudoApi = new PudoApi();
	wp_localize_script(
		'pudo_js',
		'pudo_params',
		array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'wc_custom_nonce' ),
			'markersJSON' => $pudoApi->lockers,
		)
	);
	// Only enqueue PUDO admin JS if on shipping settings tab
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'shipping' ) {
		wp_enqueue_script(
			'pudo_admin_js',
			plugins_url( '/dist/js/pudo_admin.js', __FILE__ ),
			array( 'jquery' ),
			$pluginVersion
		);
	} else {
		wp_register_script(
			'pudo_admin_js',
			plugins_url( '/dist/js/pudo_admin.js', __FILE__ ),
			array( 'jquery' ),
			$pluginVersion
		);
	}
	wp_enqueue_style( 'pudo_css', plugins_url( '/dist/css/pudo.css', __FILE__ ), array(), $pluginVersion );
	wp_enqueue_style( 'pudo-leaflet-css', 'https://unpkg.com/leaflet@1.4.0/dist/leaflet.css' );
	wp_enqueue_style(
		'pudo-leaflet-marker-cluster-css-default',
		'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.0/MarkerCluster.Default.min.css'
	);
	wp_enqueue_style(
		'pudo-leaflet-marker-cluster-css',
		'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.4.1/MarkerCluster.css'
	);

	wp_enqueue_style(
		'pudo-font-awesome-css',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css'
	);
	wp_enqueue_style( 'pudo-geosearch-css', 'https://unpkg.com/leaflet-geosearch@3.0.0/dist/geosearch.css' );
	wp_enqueue_style( 'pudo-font-datagrid-css', 'https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css' );

	wp_enqueue_script( 'pudo-poppover-js', 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js' );
	wp_enqueue_script( 'pudo-leaflet-js', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.8.0/leaflet.js' );
	wp_enqueue_script(
		'pudo_leaflet_marker_cluster-js',
		'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.4.1/leaflet.markercluster.js',
		array( 'jquery' )
	);
	wp_enqueue_script(
		'pudo-bootstrap-bundle-js',
		'https://cdn.jsdelivr.net/npm/bootstrap@4.4.0/dist/js/bootstrap.bundle.min.js'
	);
	wp_enqueue_script( 'pudo-bootbox-js', 'https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.5.3/bootbox.min.js' );
	wp_enqueue_script( 'pudo-geosearch-js', 'https://unpkg.com/leaflet-geosearch@3.0.0/dist/geosearch.umd.js' );
	wp_enqueue_script( 'pudo-geosearch-datatable-js', 'https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js' );
	wp_enqueue_script(
		'pudo-jquery-loader',
		'https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js'
	);
}

/**
 * Register settings
 *
 * @return void
 */
function pudoWooCommerceSettings() {
	register_setting( 'pudo_woocommerce', 'pudo_account_key' );
	register_setting( 'pudo_woocommerce', 'pudo_api_url' );
	register_setting( 'pudo_woocommerce', 'pudo_account' );
	register_setting( 'pudo_woocommerce', 'pudo_osm_email' );
	register_setting( 'pudo_woocommerce', 'pudo_use_osm_map' );
}

function set_shipping_method() {
	check_ajax_referer( 'wc_custom_nonce', 'nonce' );

	if ( isset( $_POST['shipping_method'] ) ) {
		$shipping_method = sanitize_text_field( $_POST['shipping_method'] );
		WC()->session->set( 'chosen_shipping_methods', array( $shipping_method ) );
		WC()->cart->calculate_totals();
		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
}

add_action( 'wp_ajax_set_shipping_method', 'set_shipping_method' );
add_action( 'wp_ajax_nopriv_set_shipping_method', 'set_shipping_method' );

// Register settings in WP
add_action( 'admin_init', 'pudoWooCommerceSettings' );

/**
 * Plugin Action Links : Apply merger of links (Custom pudo settings link on plugin card)
 *
 * @param $links
 *
 * @return mixed
 */
function pudo_wc_add_plugin_settings_link( $links ) {
	$url           = admin_url() . '/admin.php?page=pudo-woocommerce-config';
	$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html( 'Settings' ) . '</a>';
	$links[]       = $settings_link;

	return $links;
}

// Add filter for plugin link on plugin card
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pudo_wc_add_plugin_settings_link' );

/**
 * Settings Form
 *
 * @return void
 */
function pudo_render_plugin_settings_page() {
	// adding check to see if in the admin page, and then loading this html

	$apiURL        = ( get_option( 'pudo_api_url' ) ) ? get_option(
		'pudo_api_url'
	) : '';
	$apiKey        = ( get_option( 'pudo_account_key' ) ) ? get_option(
		'pudo_account_key'
	) : '';
	$pudoID        = get_option( 'pudo_account' ) ?? '';
	$pudoOSMEmail  = get_option( 'pudo_osm_email' ) ?? '';
	$pudoUseOSMMap = get_option( 'pudo_use_osm_map' ) ?? '';
	// $pudoDisplayShippingLabel = get_option('pudo_display_shipping_zone_match_label') ?? '';

	$template = plugin_dir_path( __FILE__ ) . 'templates/settings-form.php';
	if ( file_exists( $template ) ) {
		include $template;
	}
}

/**
 * Add settings page
 *
 * @return void
 */
function wc_pudo_add_settings_page() {
	add_submenu_page(
		'woocommerce',
		'The Courier Guy Locker Account',
		'The Courier Guy Locker Account',
		'manage_options',
		'pudo-woocommerce-config',
		'pudo_render_plugin_settings_page',
		1.0
	);
}

// Add action to WP environment
add_action( 'admin_menu', 'wc_pudo_add_settings_page' );
// Add checkout values hook
add_action( 'woocommerce_checkout_update_order_review', 'pudo_save_checkout_values', 9999 );

// Action to delete selected pudo shipping method session after checkout
add_action( 'woocommerce_checkout_order_processed', 'pudo_delete_session_after_checkout' );
function pudo_delete_session_after_checkout( $order_id ) {
	// Get the WooCommerce session object
	$wc_session = WC()->session;

	$wc_session->set( 'pudo-shipping-method', null );
}

/**
 * Define function for checkout hook callback
 *
 * @param $posted_data
 *
 * @return void
 */
function pudo_save_checkout_values( $posted_data ) {
	parse_str( $posted_data, $output );
	WC()->session->set( 'checkout_data', $output );
}

// Checkout get value hook
add_filter( 'woocommerce_checkout_get_value', 'pudo_get_saved_checkout', 9999, 2 );
/**
 * Get saved checkout values (WP Session)
 *
 * @param $value
 * @param $index
 *
 * @return int|mixed
 */
function pudo_get_saved_checkout( $value, $index ) {
	$data = WC()->session->get( 'checkout_data' );
	if ( ! $data || empty( $data[ $index ] ) ) {
		return $value;
	}

	return is_bool( $data[ $index ] ) ? (int) $data[ $index ] : $data[ $index ];
}

// Ship to different address checkbox on "Checkout" page
add_filter( 'woocommerce_ship_to_different_address_checked', 'pudo_get_saved_ship_to_different' );

/**
 * Check if ship to different address is check or not
 *
 * @param $checked
 *
 * @return mixed|true
 */
function pudo_get_saved_ship_to_different( $checked ) {
	$data = WC()->session->get( 'checkout_data' );
	if ( ! $data || empty( $data['ship_to_different_address'] ) ) {
		return $checked;
	}

	return true;
}

/**
 * Render Order locker update
 *
 * @param $orderId
 *
 * @return void
 */
function pudo_render_locker_select_page( $orderId ) {
	$order            = wc_get_order( $orderId );
	$pudoLockerOrigin = explode( ':', $order->get_meta( 'pudo_locker_origin' ) );
	$originCode       = $pudoLockerOrigin[0];
	$OriginName       = $pudoLockerOrigin[1];

	$lockers       = Pudo_Shipping_Method::getPudoLockers( 0 );
	$originOptions = '';

	foreach ( $lockers as $l ) {
		if ( $originCode == $l->id ) {
			$originOptions .= "<option value='$l->id:$l->name' selected>$l->name</option>";
		} else {
			$originOptions .= "<option value='$l->id:$l->name'>$l->name</option>";
		}
	}
	$token              = wp_create_nonce();
	$_SESSION['_token'] = $token;
	$template           = plugin_dir_path( __FILE__ ) . 'templates/change-locker-form.php';
	if ( file_exists( $template ) ) {
		include $template;
	}
	exit();
}

function generateToken() {
	// Generate a random token using a secure method
	$token = bin2hex( random_bytes( 32 ) );

	// Store the token in the user's session
	$_SESSION['_token'] = $token;

	return $token;
}

/** Endpoint handler
 *
 * @return void
 */
function custom_form_endpoint_handler() {
	session_start();
	if ( ! isset( $_SESSION['_token'] ) ) {
		$_SESSION['_token'] = generateToken();
	}

	if ( ! isset( $_POST['post_ID'] ) ) {
		return;
	}

	$orderId = $_POST['post_ID'];

	$order = wc_get_order( $orderId );

	$pudoLockerOrigin = explode( ':', $order->get_meta( 'pudo_locker_origin' ) );
	$lockerOriginCode = $pudoLockerOrigin[0] ?? '';

	$pudoLockerDestination = explode( ':', $order->get_meta( 'pudo_locker_destination' ) );
	$lockerDestinationCode = $pudoLockerDestination[0] ?? '';

	$pudoMethodArray = explode( ':', $order->get_meta( 'pudo_method' ) );

	$orderServiceLevelCode = isset( $pudoMethodArray[1] ) ? $pudoMethodArray[1] : '';

	$pudoMethod = isset( $pudoMethodArray[3] ) ? strtolower( $pudoMethodArray[3] ) : '';

	$pudoApi            = new PudoApi();
	$lockers            = $pudoApi->getAllLockers();
	$token              = wp_create_nonce();
	$redirectBackUrl    = get_admin_order_url( $orderId );
	$_SESSION['_token'] = $token;
	$template           = plugin_dir_path( __FILE__ ) . 'templates/change-locker-form.php';
	if ( file_exists( $template ) ) {
		include $template;
		exit();
	}
}

add_action( 'template_redirect', 'custom_form_endpoint_handler' );

function get_admin_order_url( $order_id ) {
	// Ensure the order exists
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return false; // Return false if order doesn't exist
	}

	return admin_url( "post.php?post={$order_id}&action=edit" );
}

/**
 * Generate iframe form
 *
 * @return void
 */
function pudoGetRates(): void {
	if ( ! isset( $_POST['pudoPost'] ) ) {
		return;
	}

	$pudoPost = sanitize_post( $_POST['pudoPost'] );

	$shippingData = new ShippingData( $pudoPost['method'], true );

	$data = array(
		'pudo-source-locker'      => $pudoPost['collectionAddress'],
		'pudo-destination-locker' => $pudoPost['deliveryAddress'],
	);

	$order = wc_get_order( $pudoPost['orderID'] );

	$instanceId = 0;
	foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
		$instanceId = $item->get_instance_id();
	}

	$pudoShippingMethod = new Pudo_Shipping_Method( $instanceId );
	$settings           = $pudoShippingMethod->instance_settings;

	$pudoApi = new PudoApi();

	$orderArray = WCOrderUtility::convertOrderToArray( $order );

	$apiRequestBuilder = $shippingData->initializeAPIRequestBuilder(
		$orderArray,
		$settings,
		$data,
		$pudoPost['method']
	);

	$rates = $pudoApi->getRates( json_encode( $apiRequestBuilder->buildRatesRequest() ) )['body'];

	echo wp_json_encode(
		array(
			'success' => true,
			'rates'   => $rates,
		)
	);

	wp_die();
}

add_action( 'wp_ajax_pudo_get_rates', 'pudoGetRates' );
add_action( 'wp_ajax_nopriv_pudo_get_rates', 'pudoGetRates' );

function pudoCreateShipment(): void {
	$pudoPostData = sanitize_post( $_POST['pudoPostData'] );
	$result       = array();
	parse_str( $pudoPostData, $result );

	$orderID   = $result['orderID'];
	$order     = wc_get_order( $orderID );
	$orderData = WCOrderUtility::convertOrderToArray( $order );

	$method = strtoupper( $result['pudo-method'] );

	$shippingData = new ShippingData( $method, true );

	$instanceId = 0;
	foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
		$instanceId = $item->get_instance_id();
	}

	$pudoShippingMethod = new Pudo_Shipping_Method( $instanceId );
	$settings           = $pudoShippingMethod->instance_settings;

	$pudoApi = new PudoApi();

	$result['fittedBox'] = $pudoApi->getBoxInfo( $result['lockerSize'] );

	$apiRequestBuilder = $shippingData->initializeAPIRequestBuilder( $orderData, $settings, $result, $method );

	$requestBody = json_encode( $apiRequestBuilder->buildBookingRequest( $result['serviceLevelCode'] ) );

	$bookingResponse = $pudoApi->bookingRequest( $requestBody );

	$bookingData = json_decode( $bookingResponse['body'], true );

	if ( ! isset( $bookingData['id'] ) ) {
		$order->add_order_note(
			'The Courier Guy Locker: Could not book - Something went wrong...'
		);
		$order->update_meta_data( 'pudo_status', 'Could not confirm' );

		echo wp_json_encode(
			array(
				'success' => false,
				'result'  => $bookingData,
			)
		);
	} else {
		$order->add_order_note( 'The Courier Guy Locker: Booking confirmed' );
		$order->update_meta_data( 'pudo_booking_id', $bookingData['id'] );
		$order->update_meta_data(
			'pudo_custom_tracking_reference',
			$bookingData['custom_tracking_reference']
		);
		$order->update_meta_data( 'pudo_status', 'Booking confirmed' );
		echo wp_json_encode(
			array(
				'success' => true,
				'result'  => $bookingData,
			)
		);
	}

	$order->save_meta_data();

	wp_die();
}

add_action( 'wp_ajax_pudo_submit_shipment', 'pudoCreateShipment' );
add_action( 'wp_ajax_nopriv_pudo_submit_shipment', 'pudoCreateShipment' );
/** Create endpoint function override to receive locker update info
 *
 * @return void
 */
function custom_rewrite_endpoint() {
	add_rewrite_endpoint( 'change-locker-origin', EP_PERMALINK );
}

// Initialize the route function in wp scope
add_action( 'init', 'custom_rewrite_endpoint' );

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_pudo_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

add_action( 'before_woocommerce_init', 'woocommerce_pudo_declare_hpos_compatibility' );
