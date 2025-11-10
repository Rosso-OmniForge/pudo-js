<?php

namespace Pudo\WooCommerce;

use ShippingData;
use WC_Order;
use Pudo\Common\Service\PudoShippingService;

use function WC;

/**
 *  Copyright: © 2025 The Courier Guy
 */
class Pudo_Shipping_Method extends \WC_Shipping_Method {



	const TITLE = 'The Courier Guy Locker';

	public $id;
	public $supports;
	public $title                    = null;
	public array $prohibitedProducts = array();
	public $method_description;
	public $instance_form_fields;
	public $enabled;
	public static ?\WC_Logger $wc_logger = null;
	public $method_title;

	private static ?bool $checkout_fields_added = null;
	private static array $parameters;
	private bool $pudoAvailable;
	private array $lockers;
	private string $optionsParams;
	private string $class_name;
	private array $pudoShippingTypes = array( 'd2l-pudo', 'l2l-pudo', 'l2d-pudo', 'd2d-pudo' );
	public const ORDER_SHIPPING_DATA = '_order_shipping_data';
	/**
	 * @var \Pudo\WooCommerce\PudoApi
	 */
	private PudoApi $pudoApi;
	private PudoShippingService $pudoShippingService;

	/**
	 * @param int $instance_id
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		parse_str( $_POST['post_data'] ?? '', $postData );
		$this->optionsParams = json_encode( ( $postData ) );
		$this->id            = 'pickup_dropoff';
		$this->class_name    = 'Pudo\WooCommerce\Pudo_Shipping_Method';
		$this->title         = self::TITLE;
		$this->enabled       = 'yes';
		$this->pudoAvailable = false;

		if ( ! empty( $instance_id ) ) {
			$this->title = 'The Courier Guy Locker';
		}
		$this->supports = array(
			'settings',
			'shipping-zones',
			'instance-settings',
		);

		$this->method_title       = __( self::TITLE );
		$this->method_description = __(
			'The Courier Guy Locker is a smart locker system designed to allow'
			. ' South Africans to send and receive parcels around the country.'
		);

		$this->pudoApi = new PudoApi();
		$this->lockers = $this->getPudoLockerOptions();

		$this->pudoShippingService = new PudoShippingService();

		if ( empty( $this->lockers ) ) {
			$this->lockers = array(
				array( 'CG54' => 'Sasol Rivonia Uplifted' ),
			);
		}

		$fields                     = Pudo_Shipping_Settings::overrideFormFieldsVariable( $this->lockers );
		$this->instance_form_fields = $fields;
		$this->settings             = $fields;

		add_action( 'woocommerce_update_options_shipping_methods', array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_shipping_methods', array( $this, 'add_pudo_shipping_method' ) );
		$this->enabled = 'yes';
		$this->init();

		self::$parameters = $this->instance_settings;

		if ( self::$wc_logger === null ) {
			self::$wc_logger = wc_get_logger();
		}

		if ( self::$checkout_fields_added === null ) {
			// Add custom html to checkout page
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_pudo_checkout_fields' ) );
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_pudo_address_fields' ) );
			self::$checkout_fields_added = true;
		}

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'updateShippingPropertiesOnOrder' ), 10, 2 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'addProhibitedWarnings' ), 10 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'addProhibitedWarnings' ), 5 );
		add_filter( 'woocommerce_package_rates', array( $this, 'pudo_rates_filter' ), 10, 1 );
	}

	/*
	 * Echos radios for shipping selection
	 *
	 * returns void
	 */
	function addPudoShippingRateOptions() {
		$opts                     = json_decode( $this->optionsParams, true );
		$sp                       = $opts['shipping_method'][0] ?? '';
		$free_ship                = self::$parameters['free_shipping'];
		$amount_for_free_shipping = self::$parameters['amount_for_free_shipping'];
		$subtotal                 = WC()->cart->subtotal;
		$html                     = '<tr><th></th><td>';
		if ( self::$parameters['pudo_source'] == 'street' ) {
			$html .= "<input type='radio' value='d2l-pudo' id='2l-pudo' name='shipping_method[0]' ";
		} else {
			$html .= "<input type='radio' value='l2l-pudo' id='2l-pudo' name='shipping_method[0]' ";
		}
		if ( $sp == 'd2l-pudo' || $sp == 'l2l-pudo' ) {
			$html .= ' checked ';
		}
		$sourceLiteral = 'L';
		if ( self::$parameters['pudo_source'] === 'street' ) {
			$sourceLiteral = 'D';
		}

		$html .= " class='update_totals_on_change'>";
		if ( ( $free_ship === 'yes' && $subtotal > $amount_for_free_shipping ) || ! isset( $opts[ "{$sourceLiteral}2LPrice" ] ) ) {
			$html .= '<label>Deliver to a Locker(The Courier Guy Locker)</strong></label>';
		} else {
			$html .= '<label>Deliver to a Locker(The Courier Guy Locker)</label>';
		}

		$html .= '</td></tr>';
		echo $html;
		// Check source type (If street, only allow to locker)
		if ( self::$parameters['pudo_source'] != 'street' ) {
			$html  = '<tr><th></th><td>';
			$html .= "<input type='radio' value='l2d-pudo' id='2d-pudo' name='shipping_method[0]' ";
			if ( $sp == 'l2d-pudo' ) {
				$html .= ' checked ';
			}
			$html .= "class='update_totals_on_change'>";
			if ( isset( $opts[ "{$sourceLiteral}2DPrice" ] ) ) {
				$html .= "<label>Deliver to a Door(The Courier Guy Locker) : <strong>R{$opts["{$sourceLiteral}2DPrice"]}</strong></label>";
			} else {
				$html .= '<label>Deliver to a Door(The Courier Guy Locker)</label>';
			}
			$html .= '</td></tr>';

			echo $html;
		} else {
			foreach ( $opts['d2drates'] as $ddrate ) {
				$html  = '<tr><th></th><td>';
				$html .= "<input type='radio' value='d2d-pudo,{$ddrate['id']},{$ddrate['cost']}' id='d2d-pudo,{$ddrate['id']},{$ddrate['cost']}' name='shipping_method[0]' ";
				if ( $sp == "d2d-pudo,{$ddrate['id']},{$ddrate['cost']}" ) {
					$html .= ' checked ';
				}
				$html .= "class='update_totals_on_change'>";
				$html .= "<label>Deliver to a Door({$ddrate['label']})  : <strong>R{$ddrate['cost']}</strong></label>";
				$html .= '</td></tr>';
				echo $html;
			}
		}
		if ( in_array( $sp, $this->pudoShippingTypes ) ) {
			$html  = '<tr><th></th><td>';
			$html .= "<input type='radio' value='no-pudo' id='no-pudo' name='shipping_method[0]' ";
			if ( $sp == 'no-pudo' ) {
				$html .= ' checked ';
			}
			$html .= "class='update_totals_on_change'>";
			$html .= '<label>Other Shipping</label>';
			$html .= '</td></tr>';
			echo $html;
		}

		if ( $opts['showModal'] === true ) {
			echo '<script>pudoShowModal()</script>';
		}
	}

	/**
	 * @return string (yes|no)
	 */
	public function checkShippingDebug() {
		$woocommerce_shipping_debug = \WC_Admin_Settings::get_option( 'woocommerce_shipping_debug_mode', 'no' );

		return $woocommerce_shipping_debug;
	}

	/**
	 * If one of PUDO methods selected on checkout filter out all non-pudo methods
	 *
	 * @param array $rates
	 *
	 * @return array
	 */
	public function pudo_rates_filter( array $rates ): array {
		$rateNames = array_keys( $rates );
		$hasPudo   = false;
		foreach ( $rateNames as $rateName ) {
			if ( strpos( $rateName, 'pickup_dropoff' ) !== false ) {
				$hasPudo = true;
				break;
			}
		}

		if ( $hasPudo ) {
			$isOk     = true;
			$postData = array();
			if ( ! empty( $_POST['post_data'] ) ) {
				parse_str( $_POST['post_data'], $postData );
				if ( $postData['shipping_method'][0] === 'l2l-pudo' && $postData['pudo-locker-origin'] === $postData['pudo-locker-destination'] ) {
					$isOk = false;
				}
			}

			$rates = array_filter(
				$rates,
				function ( $rate ) use ( $isOk ) {
					return ( $isOk && strpos( $rate->id, 'pickup_dropoff' ) !== false );
				}
			);
			uasort(
				$rates,
				function ( $a, $b ) {
					$a = (float) $a->cost;
					$b = (float) $b->cost;

					return $a == $b ? 0 : $a - $b;
				}
			);
		}

		return $rates;
	}

	/**
	 * @param $orderId
	 * @param $data
	 *
	 * @return void
	 */
	public function updateShippingPropertiesOnOrder( $orderId, $data ) {
		$sp = WC()->session->get( 'pudo-shipping-method', 'no-pudo' );

		$originLockerName      = filter_var( $_POST['pudo-locker-origin-name'], FILTER_SANITIZE_STRING ) ?? '';
		$destinationLockerName = filter_var( $_POST['pudo-locker-destination-name'], FILTER_SANITIZE_STRING ) ?? '';

		$order = wc_get_order( $orderId );

		if ( WC()->session ) {
			$pudoMethod = filter_var( $_POST['shipping_method'][0], FILTER_SANITIZE_STRING );
			if ( $originLockerName ) {
				$lockerOrigin = filter_var(
					$_POST['pudo-locker-origin'],
					FILTER_SANITIZE_STRING
				) . ':' . $originLockerName;
			} else {
				$lockerOrigin = filter_var(
					$_POST['pudo-locker-origin'],
					FILTER_SANITIZE_STRING
				) . ':' . $originLockerName;
			}

			if ( $destinationLockerName ) {
				$lockerDestination = filter_var(
					$_POST['pudo-locker-destination'],
					FILTER_SANITIZE_STRING
				) . ':' . $destinationLockerName;
			} else {
				$lockerDestination = filter_var( $_POST['pudo-locker-destination'], FILTER_SANITIZE_STRING );
			}
			$usePudo = false;

			if ( substr( $sp, 0, strlen( $this->id ) ) == $this->id ) {
				$usePudo = $this->checkPudoShipment();
			}
		}
		if ( $usePudo ) {
			$_SESSION['pudo-shipping-method'] = '';
			$order->update_meta_data( self::ORDER_SHIPPING_DATA, json_encode( $sp ) );
			$order->update_meta_data( 'pudo_method', $sp );
			$order->add_meta_data( 'pudo_status', 'none', true );
			$order->update_meta_data( 'pudo_ship_to_different_address', $data['ship_to_different_address'] );
			$spArr = explode( ':', $sp );
			// Determine type and mansage order fields
			if ( isset( $spArr[3] ) && $spArr[3] == 'D2L' ) {
				$order->update_meta_data( 'pudo_locker_destination', $lockerDestination );
			} elseif ( isset( $spArr[3] ) && $spArr[3] == 'L2D' ) {
				$order->update_meta_data( 'pudo_locker_origin', $lockerOrigin );
				$order->update_meta_data( 'pudo_locker_destination', 'none' );
			} elseif ( isset( $spArr[3] ) && $spArr[3] == 'L2L' ) {
				$order->update_meta_data( 'pudo_locker_origin', $lockerOrigin );
				$order->update_meta_data( 'pudo_locker_destination', $lockerDestination );
			} elseif ( isset( $spArr[3] ) && $spArr[3] == 'D2D' ) {
				$order->update_meta_data( 'pudo_locker_origin', 'none' );
				$order->update_meta_data( 'pudo_locker_destination', 'none' );
			}
		}
		$order->save_meta_data();
	}

	/**
	 * @param string $method
	 *
	 * @return false|string
	 */
	private static function isPudoMethod( string $method ) {
		return str_contains( $method, 'pickup_dropoff' );
	}

	/**
	 * hack to force shipping calculation on pudo selection change
	 *
	 * @param array $fields
	 *
	 * @return array
	 * @return array
	 */
	public function add_pudo_address_fields( array $fields ): array {
		if ( ! empty( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $postData );
			$fields[0]['destination']['pudo-select']             = $postData['shipping_method'][0] ?? 'deliver_to_locker';
			$fields[0]['destination']['pudo-locker-origin']      = $postData['pudo-locker-origin'] ?? '';
			$fields[0]['destination']['pudo-locker-destination'] = $postData['pudo-locker-destination'] ?? '';
		} elseif ( ! empty( $_POST['shipping_method'] ) ) {
			$fields[0]['destination']['pudo-select']             = $_POST['shipping_method'][0] ?? 'deliver_to_locker';
			$fields[0]['destination']['pudo-locker-origin']      = $_POST['pudo-locker-origin'] ?? '';
			$fields[0]['destination']['pudo-locker-destination'] = $_POST['pudo-locker-destination'] ?? '';
		} else {
			$fields[0]['destination']['pudo-select'] = 'deliver_to_locker';
		}

		return $fields;
	}

	/**
	 * @return void
	 */
	public function init() {
		// Load the settings API
		$this->init_form_fields();
		$this->init_settings();
		$this->init_instance_settings();

		// Save admin settings
		add_action( 'woocommerce_update_options_shipping_methods', array( $this, 'process_admin_options' ) );

		add_filter( 'woocommerce_no_shipping_available_html', array( $this, 'custom_no_shipping_message' ), 1 );
	}

	public function custom_no_shipping_message( $message ) {
		return $this->pudoAvailable ? 'Choose The Courier Guy Locker option' : $message;
	}

	/**
	 * @return \WC_Logger|null
	 */
	public static function get_wc_logger(): ?\WC_Logger {
		return self::$wc_logger;
	}

	/**
	 * Add this method to the WC Shopping Methods
	 *
	 * @param array $methods
	 *
	 * @return mixed
	 */
	public function add_pudo_shipping_method( array $methods ): array {
		$methods[ $this->id ] = 'Pudo\WooCommerce\Pudo_Shipping_Method';

		return $methods;
	}

	/**
	 * @param array $package
	 *
	 * @throws \Exception
	 */
	public function calculate_shipping( $package = array() ) {
		// Check if any of the products are prohibited
		$this->prohibitedProducts = $this->isPudoProductProhibited( $package );
		if ( ! empty( $this->prohibitedProducts ) ) {
			return;
		}

		// Gather environmental variables
		$settings         = $this->instance_settings;
		self::$parameters = $settings;
		$postData         = array();

		// Parse postdata
		if ( ! empty( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $postData );
		}

		// Gather shipping method value
		$sp = $postData['shipping_method'][0] ?? '';

		// Begin validation

		// If shipping is local_pickup, set value to no-pudo
		if ( str_contains( $sp, 'local_pickup' ) ) {
			$postData['pudo-select'] = 'no-pudo';
		}

		// Get box size for cart
		list($boxes, $sortedBoxClass) = $this->getPudoBoxOptions();

		$freeShippingFinal = false;
		// Free shipping product settings
		$product_free_shipping = false;
		if ( isset( $package['contents'] ) ) {
			foreach ( $package['contents'] as $product ) {
				$pfs = get_post_meta( $product['product_id'], 'product_free_shipping_pudo', true );
				if ( $pfs === 'on' || $pfs === 'yes' ) {
					$product_free_shipping = true;
				}
			}
		}

		// Free shipping global settings
		$free_ship                = $this->get_instance_option( 'free_shipping' );
		$amount_for_free_shipping = $this->get_instance_option( 'amount_for_free_shipping' );
		if ( isset( $package['cart_subtotal'] ) ) {
			$subtotal = (float) $package['cart_subtotal'];
		} else {
			$subtotal = 0;
		}

		if ( ( ( $free_ship === 'yes' || $free_ship === 'on' ) && $subtotal >= $amount_for_free_shipping )
			|| $product_free_shipping ) {
			$freeShippingFinal = true;
		}

		$apiPayload        = new Pudo_Api_Payload();
		$settings['boxes'] = $boxes;
		if ( isset( $package['contents'] ) ) {
			$parcels = $apiPayload->getContentsPayload( $settings, $package['contents'] );
		} else {
			$parcels = array();
		}

		if ( count( $parcels ) !== 1 ) {
			$this->pudoAvailable = false;

			return;
		}

		$this->pudoAvailable = true;

		wc_get_logger()->log( 'info', $sp );

		// If there is post data available
		if ( ! empty( $_POST['post_data'] ) ) {
			$shippingDetails = array(
				'name'           => $postData['billing_first_name'],
				'email'          => $postData['billing_email'],
				'mobile_number'  => $postData['billing_phone'],
				'street_address' => filter_var(
					$_POST['s_address'] ?? $postData['shipping_address_1'],
					FILTER_SANITIZE_FULL_SPECIAL_CHARS
				),
				'city'           => filter_var(
					$_POST['s_city'] ?? $postData['shipping_city'],
					FILTER_SANITIZE_FULL_SPECIAL_CHARS
				),
				'code'           => filter_var(
					$_POST['s_postcode'] ?? $postData['shipping_postcode'],
					FILTER_SANITIZE_FULL_SPECIAL_CHARS
				),
				'zone'           => filter_var(
					$_POST['s_state'] ?? $postData['shipping_state'],
					FILTER_SANITIZE_FULL_SPECIAL_CHARS
				),
				'country'        => filter_var(
					$_POST['s_country'] ?? $postData['shipping_country'],
					FILTER_SANITIZE_FULL_SPECIAL_CHARS
				),
			);

			$lockerData = array(
				'pudo-source-locker'      => $postData['pudo-locker-origin'],
				'pudo-destination-locker' => $postData['pudo-locker-destination'],
			);

			if ( empty( $lockerData['pudo-destination-locker'] ) ) {
				$lockerData['pudo-destination-locker'] = $this->pudoApi->getDefaultLockerCode();
			}

			// Get the fit index (Index of type of delivery package)
			$fittedBox = $sortedBoxClass[ $parcels[0]['fitIndex'] ];

			$rate = $this->buildPudoRate(
				$sp,
				$fittedBox,
				$settings,
				$shippingDetails,
				$lockerData,
				$parcels,
				$freeShippingFinal
			);
			// Add shipping options
			add_action(
				'woocommerce_review_order_before_order_total',
				array( $this, 'addPudoShippingRateOptions' ),
				10,
				2
			);

			if ( strpos( $sp, 'd2d-pudo' ) === 0 ) {
				$d2drate = explode( ',', $sp );
				$rateId  = "pickup_dropoff:$d2drate[1]:2:D2D:" . $fittedBox['name'];
				$rate    = array(
					'id'       => $rateId,
					'label'    => "The Courier Guy Locker D2D - $d2drate[1]",
					'cost'     => $d2drate[2],
					'calc_tax' => 'per_order',
					'taxes'    => array(
						1 => 0,
					),
				);
				$sp      = 'd2d-pudo';

				// Define shipping  ID
				WC()->session->set( 'pudo-shipping-method', $rateId );
			}
			// Identify if shipping price and shipping method is to be applied
			if ( in_array( $sp, $this->pudoShippingTypes ) ) {
				$this->add_rate( $rate );
			}
		} else {
			$rate = array(
				'id'       => 'pickup_dropoff:ECO:2:D2D',
				'label'    => 'Update address on checkout to see The Courier Guy Locker rates',
				'calc_tax' => 'per_order',
				'taxes'    => array(
					1 => 0,
				),
			);

			$this->add_rate( $rate );
		}
	}

	/**
	 *
	 * @return bool
	 */
	public function checkPudoShipment(): bool {
		if ( isset( $_POST['shipping_method'] ) ) {
			$shippingMethod = $_POST['shipping_method'];

			// If it's an array, get the first element, otherwise use the string directly
			$method = is_array( $shippingMethod ) ? $shippingMethod[0] : $shippingMethod;

			// Check if 'pudo' is in the method
			return str_contains( $method, 'pudo' );
		}

		return false;
	}

	/**
	 * This method is called to build the UI for custom shipping setting of type 'pudo_override_per_service'.
	 * This method must be overridden as it is called by the parent class WC_Settings_API.
	 *
	 * @param $key
	 * @param $data
	 *
	 * @return string
	 * @uses WC_Settings_API::get_custom_attribute_html()
	 * @uses WC_Shipping_Method::get_option()
	 * @uses WC_Settings_API::get_field_key()
	 * @uses WC_Settings_API::get_tooltip_html()
	 * @uses WC_Settings_API::get_description_html()
	 */
	public function generate_pudo_override_per_service_html( $key, $data ) {
		$field_key      = $this->get_field_key( $key );
		$defaults       = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);
		$data           = wp_parse_args( $data, $defaults );
		$overrideValue  = $this->get_option( $key );
		$overrideValues = json_decode( $overrideValue, true );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="
				<?php
				echo esc_attr( $field_key );
				?>
				_select">
					<?php
					echo wp_kses_post( $data['title'] );
					?>
					<?php
					echo $this->get_tooltip_html( $data ); // WPCS: XSS ok.
					?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>
					<?php
					echo wp_kses_post( $data['title'] );
					?>
					</span></legend>
					<select class="select
					<?php
					echo esc_attr( $data['class'] );
					?>
					" style="
					<?php
					echo esc_attr( $data['css'] );
					?>
							"
						<?php
						disabled( $data['disabled'], true );
						?>
						<?php
						echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok.
						?>
					>
						<option value="">Select a Service</option>
						<?php
						$prefix = ' - ';
						if ( $field_key == 'woocommerce_pudo_price_rate_override_per_service' ) {
							$prefix = ' - R ';
						}
						?>
						<?php
						foreach ( (array) $data['options'] as $option_key => $option_value ) :
							?>
							<option value="
							<?php
							echo esc_attr( $option_key );
							?>
							" data-service-label="
							<?php
							echo esc_attr( $option_value );
							?>
">
								<?php
								echo esc_attr(
									$option_value
								);
								?>
								<?php
								echo ( ! empty( $overrideValues[ $option_key ] ) ) ? $prefix . $overrideValues[ $option_key ] : '';
								?>
								</option>
							<?php
						endforeach;
						?>
					</select>
					<?php
					foreach ( (array) $data['options'] as $option_key => $option_value ) :
						?>
						<span style="display:none;" class="
						<?php
						echo esc_attr( $data['class'] );
						?>
						-span-
						<?php
						echo $option_key;
						?>
						">
							<?php
							$class = '';
							$style = '';
							if ( $field_key == 'woocommerce_pudo_price_rate_override_per_service' ) {
								$class = 'wc_input_price ';
								$style = ' style="width: 90px !important;" ';
								?>
								<span style="position:relative; top:8px; padding:0 0 0 10px;">R </span>
								<?php
							}
							?>
							<input data-service-id="
							<?php
							echo esc_attr( $option_key );
							?>
							" class="
							<?php
							echo $class;
							?>
							input-text regular-input
							<?php
							echo esc_attr( $data['class'] );
							?>
-input"
									type="text"
									<?php
									echo $style;
									?>
									value="
							<?php
							echo isset( $overrideValues[ $option_key ] ) ? $overrideValues[ $option_key ] : '';
							?>
							"/>
						</span>
						<?php
					endforeach;
					?>
					<?php
					echo $this->get_description_html( $data ); // WPCS: XSS ok.
					?>
					<input type="hidden" name="
					<?php
					echo esc_attr( $field_key );
					?>
					" value="
					<?php
					echo esc_attr( $overrideValue );
					?>
					"/>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param string          $selectedMethod
	 * @param $sortedBoxClass
	 * @param $settings
	 * @param $shippingDetails
	 * @param $lockerData
	 * @param $parcels
	 * @param bool            $freeShipping
	 *
	 * @return array
	 */
	private function buildPudoRate(
		string $selectedMethod,
		$fittedBox,
		$settings,
		$shippingDetails,
		$lockerData,
		$parcels,
		$freeShipping = false
	): array {
		$postData = array();

		parse_str( $_POST['post_data'], $postData );

		// Define tax rates dependent on woocommerce on config
		$taxes_enabled = get_option( 'woocommerce_calc_taxes' ) ?? 'true';
		if ( $taxes_enabled && $settings['tax_status'] === 'taxable' ) {
			$taxRate = $tax[1]->tax_rate ?? 0.0;
		}

		// Get the fit index (Index of type of delivery package)
		$lockerData['fittedBox'] = $fittedBox;

		$total    = 0.00;
		$rateCode = '';

		if ( $settings['pudo_source'] === 'street' ) {
			unset( $this->pudoShippingTypes[1] );
			unset( $this->pudoShippingTypes[2] );
		} else {
			unset( $this->pudoShippingTypes[0] );
			unset( $this->pudoShippingTypes[3] );
		}

		foreach ( $this->pudoShippingTypes as $pudoShippingType ) {
			$iteratedMethod = strtoupper( substr( $pudoShippingType, 0, 3 ) );

			// pudo-destination-locker
			$pudoDestinationLocker = $lockerData['pudo-destination-locker'];
			if ( isset( $this->pudoApi->lockers[ $pudoDestinationLocker ] ) &&
				isset( $this->pudoApi->lockers[ $pudoDestinationLocker ]['type'] ) &&
				isset( $this->pudoApi->lockers[ $pudoDestinationLocker ]['type']['name'] )
			) {
				$pudoDestinationLockerTypeName = $this->pudoApi->lockers[ $pudoDestinationLocker ]['type']['name'];
			} else {
				$pudoDestinationLockerTypeName = '';
			}

			// pudo-destination-locker
			$pudoSourceLocker = $lockerData['pudo-source-locker'];
			if ( isset( $this->pudoApi->lockers[ $pudoSourceLocker ] ) &&
				isset( $this->pudoApi->lockers[ $pudoSourceLocker ]['type'] ) &&
				isset( $this->pudoApi->lockers[ $pudoSourceLocker ]['type']['name'] )
			) {
				$pudoSourceLockerTypeName = $this->pudoApi->lockers[ $pudoSourceLocker ]['type']['name'];
			} else {
				$pudoSourceLockerTypeName = '';
			}

			if ( $iteratedMethod === 'D2L' ) {
				$sourceLetter      = 'D';
				$destinationLetter = $pudoDestinationLockerTypeName === 'Kiosk' ? 'K' : 'L';
			}

			if ( $iteratedMethod === 'L2D' ) {
				$sourceLetter      = $pudoSourceLockerTypeName === 'Kiosk' ? 'K' : 'L';
				$destinationLetter = 'D';
			}

			if ( $iteratedMethod === 'D2D' ) {
				$sourceLetter      = 'D';
				$destinationLetter = 'D';
			}

			if ( $iteratedMethod === 'L2L' ) {
				$sourceLetter      = $pudoSourceLockerTypeName === 'Kiosk' ? 'K' : 'L';
				$destinationLetter = $pudoDestinationLockerTypeName === 'Kiosk' ? 'K' : 'L';
			}

			$iteratedRateCode = $sourceLetter . '2' . $destinationLetter . explode(
				'-',
				$fittedBox['name']
			)[1] . ' - ECO';

			$shippingData      = new ShippingData( $iteratedMethod, false );
			$apiRequestBuilder = $shippingData->initializeAPIRequestBuilder(
				$shippingDetails,
				$settings,
				$lockerData,
				$iteratedMethod
			);
			$checkoutRates     = $this->pudoApi->getRates( json_encode( $apiRequestBuilder->buildRatesRequest() ) );

			$checkoutRates = json_decode( $checkoutRates['body'], true );

			if ( isset( $checkoutRates['rates'] ) ) {
				foreach ( $checkoutRates['rates'] as $rateData ) {
					$serviceLevel = $rateData['service_level'];

					if ( $serviceLevel['code'] === $iteratedRateCode ) {
						$price         = $rateData['rate'];
						$iteratedPrice = $freeShipping ? 0.00 : $price;

						$postData[ $iteratedMethod . 'Price' ] = $iteratedPrice;

						if ( $selectedMethod === $pudoShippingType ) {
							$selectedMethod = $iteratedMethod;
							$total          = $iteratedPrice;
							$rateCode       = $iteratedRateCode;
						}
						break;
					}
				}
			} elseif ( $selectedMethod === $pudoShippingType ) {
				$selectedMethod = $iteratedMethod;
			}
		}

		// Work out shipping price
		if ( $taxes_enabled == 'yes' ) {
			// calculate tax
			$tax      = $total * $taxRate / ( 100.0 + $taxRate );
			$tax      = (float) ( ( (int) ( 100 * $tax ) ) / 100 );
			$shipping = $total - $tax;
		} else {
			$shipping = $total;
		}

		$rateLabel = "The Courier Guy Locker  $rateCode";

		$serviceLevelCode = str_replace( ' ', '', $rateCode );

		$this->processShippingRates( $rateLabel, $shipping, $serviceLevelCode );

		// Define shipping  ID
		$shippingMethodId = "$this->id:$rateCode:$this->instance_id:$selectedMethod";
		// Define rate
		$rate = array(
			'id'       => $shippingMethodId,
			'label'    => $rateLabel . ( $freeShipping ? ' - FREE' : '' ),
			'cost'     => $shipping,
			'calc_tax' => 'per_order',
		);
		// More tax checks -_-
		if ( $taxes_enabled == 'yes' ) {
			$rate['taxes'] = array( 1 => $tax );
		} else {
			$rate['taxes'] = array( 1 => 0 );
		}

		$postData['d2drates'] = $this->addD2DRates( $settings, $shippingDetails, $lockerData, $freeShipping );

		// If pudo locker has been selected, set a modal controller (Will show the map)
		if ( $selectedMethod === 'L2L' || $selectedMethod === 'D2L' ) {
			$postData['showModal'] = true;
		} else {
			$postData['showModal'] = false;
		}

		// Set the rate, set global variable to keep passed post variable in context
		$postData['rate']            = $rate['cost'];
		$orderLockerSize             = $fittedBox;
		$postData['orderLockerSize'] = $orderLockerSize;
		$this->optionsParams         = json_encode( $postData );

		WC()->session->set( 'pudo-shipping-method', $shippingMethodId );

		return $rate;
	}

	/**
	 * @param $settings
	 * @param $shippingDetails
	 * @param $lockerData
	 * @param $freeShipping
	 *
	 * @return array
	 */
	public function addD2DRates( $settings, $shippingDetails, $lockerData, $freeShipping ): array {
		$shippingData      = new ShippingData( 'D2D', false );
		$apiRequestBuilder = $shippingData->initializeAPIRequestBuilder(
			$shippingDetails,
			$settings,
			$lockerData,
			'D2D'
		);
		$d2lLockerRates    = $this->pudoApi->getRates( json_encode( $apiRequestBuilder->buildRatesRequest() ) );

		$lockerRates = json_decode( $d2lLockerRates['body'], true );
		$rates       = array();
		if ( isset( $lockerRates['rates'] ) ) {
			foreach ( $lockerRates['rates'] as $rateData ) {
				$serviceLevel = $rateData['service_level'];

				$rateLabel = 'The Courier Guy Locker ' . $serviceLevel['name'];
				$ratePrice = $rateData['rate'];

				$this->processShippingRates( $rateLabel, $ratePrice, $serviceLevel['code'] );

				$rate = array(
					'id'       => $serviceLevel['code'],
					'label'    => $rateLabel . ( $freeShipping ? ' - FREE' : '' ),
					'cost'     => $freeShipping ? 0.00 : $ratePrice,
					'calc_tax' => 'per_order',
				);

				$rates[] = $rate;
			}
		}

		return $rates;
	}

	/**
	 * @param $rateLabel
	 * @param $ratePrice
	 * @param $serviceLevelCode
	 *
	 * @return void
	 */
	public function processShippingRates( &$rateLabel, &$ratePrice, $serviceLevelCode ): void {
		$options = array(
			'label_overrides' => json_decode( $this->get_instance_option( 'label_override_per_service' ), true ),
			'price_overrides' => json_decode( $this->get_instance_option( 'price_rate_override_per_service' ), true ),
		);

		$this->pudoShippingService->applyRateOverrides( $options, $rateLabel, $ratePrice, $serviceLevelCode );
	}

	/**
	 * @param $fields
	 *
	 * @return array
	 */
	public function add_pudo_checkout_fields( $fields ) {
		$methods = \WC_Shipping_Zones::get_zones();
		foreach ( $methods as $method ) {
			foreach ( $method['shipping_methods'] as $key => $item ) {
				if ( is_a( $item, $this->class_name ) ) {
					$this->instance_id = $key;
				}
			}
		}

		$this->init_instance_settings();

		$this->pudoApi = new PudoApi();
		$lockerz       = $this->lockers;

		$useSourceLocker = $this->instance_settings['pudo_source'] === 'locker';
		$sourceLocker    = $this->instance_settings['pudo_locker_name'];

		$sourceLockers = array_filter(
			$lockerz,
			function ( $key ) use ( $sourceLocker ) {
				return $key === $sourceLocker;
			},
			ARRAY_FILTER_USE_KEY
		);

		$destinationLockers = array_filter(
			$lockerz,
			function ( $key ) use ( $sourceLocker ) {
				return $key !== $sourceLocker;
			},
			ARRAY_FILTER_USE_KEY
		);

		$destinationLockers = array( 'none' => 'None' ) + $destinationLockers;

		$fields['billing']['pudo-locker-origin']      = array(
			'label'    => 'Origin Locker',
			'type'     => 'select',
			'options'  => $sourceLockers,
			'priority' => 5,
			'class'    => array( 'address-field' ),
		);
		$fields['billing']['pudo-locker-origin-name'] = array(
			'label'    => 'Origin Locker Name',
			'type'     => 'text',
			'priority' => 5,
			'class'    => 'hide',
		);

		$fields['billing']['pudo-locker-destination'] = array(
			'label'    => 'Destination Locker',
			'type'     => 'text',
			'priority' => 5,
			'class'    => 'hide',
			'required' => false,
		);

		$fields['billing']['pudo-locker-destination-name'] = array(
			'label'    => 'Destination Locker Name',
			'type'     => 'text',
			'priority' => 5,
			'class'    => 'hide',
			'required' => true,
		);

		if ( get_option( 'pudo_use_osm_map' ) !== 'true' ) {
			$fields['billing']['pudo-locker-destination']['type']    = 'select';
			$fields['billing']['pudo-locker-destination']['options'] = $destinationLockers;
		}

		return $fields;
	}

	/**
	 * @return array
	 */
	protected function getPudoLockerOptions(): array {
		$lockersx = array();
		foreach ( $this->pudoApi->lockers as $locker ) {
			$lockersx[ $locker['code'] ] = $locker['name'];
		}

		return $lockersx;
	}

	/**
	 * @return array
	 */
	protected function getPudoBoxOptions(): array {
		$boxes = array();
		if ( isset( $this->instance_settings['pudo_locker_name'] ) ) {
			$locker = $this->pudoApi->lockers[ $this->instance_settings['pudo_locker_name'] ] ?? null;
			if ( ! $locker ) {
				throw new \Exception( 'Configured source locker does not exist.' );
			}
			$sortedBoxClass = $locker['lstTypesBoxes'];

			usort(
				$sortedBoxClass,
				function ( $a, $b ) {
					$a1 = array( $a['width'], $a['height'], $a['length'] );
					rsort( $a1 );
					$b1 = array( $b['width'], $b['height'], $b['length'] );
					rsort( $b1 );
					if ( $r0 = ( $a1[0] <=> $b1[0] ) ) {
						return $r0;
					}
					if ( $r1 = ( $a1[1] <=> $b1[1] ) ) {
						return $r1;
					}

					return $a1[2] <=> $b1[2];
				}
			);
			foreach ( $sortedBoxClass as $box ) {
				$b = array( $box['width'] * 10, $box['height'] * 10, $box['length'] * 10 );
				// Sort each box largest to smallest dimension
				rsort( $b );
				$b['volume']    = $b[0] * $b[1] * $b[2];
				$b['name']      = $box['name'];
				$b['type']      = $box['type'];
				$b['maxWeight'] = (float) $box['weight'];
				$boxes[]        = $b;
			}
		}

		return array( $boxes, $sortedBoxClass );
	}

	// Admin actions

	/**
	 * Adds create PUDO order action to order metabox
	 *
	 * @param $actions
	 *
	 * @return mixed
	 */
	public static function addSendCollectionActionToOrderMetaBox( $actions ) {
		$orderId      = sanitize_text_field( $_GET['post'] );
		$order        = wc_get_order( $orderId );
		$shouldNotAdd = true;
		$hasOrder     = $order->get_meta( 'pudo_collectionNumber', true ) !== '';

		$hasShippingMethod = false;
		$shippingMethods   = json_decode( $order->get_meta( self::ORDER_SHIPPING_DATA, true ), true );
		foreach ( $shippingMethods ?? array() as $vendor_id => $method ) {
			$ordered      = $order->get_meta( 'pudo_order_id' . "_$vendor_id", true ) !== '';
			$shouldNotAdd = $shouldNotAdd && $ordered;
			if ( self::isPudoMethod( $method ) ) {
				$hasShippingMethod = true;
			}
		}
		if ( ! $shouldNotAdd && $hasShippingMethod ) {
			if ( ! $hasOrder ) {
				$actions['pudo_send_collection'] = __( 'Send Order to The Courier Guy Locker', 'woocommerce' );
			} else {
				// Disabled until print waybill functionality determined
				$actions['pudo_print_label']   = __( 'Print The Courier Guy Locker Label', 'woocommerce' );
				$actions['pudo_print_waybill'] = __( 'Print The Courier Guy Locker Waybill', 'woocommerce' );
			}
		}

		return $actions;
	}


	/**
	 * Add Pudo Shipping column in admin orders
	 *
	 * @param $columns
	 *
	 * @return array
	 */
	public static function addCollectionActionAndPrintWaybillToOrderList( $columns ) {
		$reordered_columns = array();
		foreach ( $columns as $key => $column ) {
			$reordered_columns[ $key ] = $column;
			if ( $key == 'order_status' ) {
				$reordered_columns['pickup_dropoff_order'] = __( 'The Courier Guy Locker Shipping', 'theme_domain' );
			}
		}

		return $reordered_columns;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	private static function orderHasPudoMethod( WC_Order $order ) {
		$shippingMethods[] = json_decode( $order->get_meta( self::ORDER_SHIPPING_DATA ) );
		$hasPudo           = false;
		foreach ( $shippingMethods as $method ) {
			if ( self::isPudoMethod( $method ) ) {
				$hasPudo = true;
			}
		}

		return $hasPudo;
	}

	/**
	 * @param $column
	 * @param $orderId
	 *
	 * @return void
	 */
	public static function collectActionAndPrintWaybillOnOrderlistContent( $column, $orderId ) {
		if ( $column === 'pickup_dropoff_order' ) {
			$order           = wc_get_order( $orderId );
			$shippingMethods = get_post_meta( $orderId )['pudo_method'] ?? '';

			$hasOrder      = $order->get_meta( 'pudo_wayBillNumber', true ) !== '';
			$hasPudoMethod = false;

			if ( $shippingMethods ) {
				foreach ( $shippingMethods ?? array() as $key => $shippingMethod ) {
					if ( ( self::isPudoMethod( $shippingMethod ) ) ) {
						$hasPudoMethod = true;
						$waybill       = $order->get_meta( "pickup_dropoff_waybill_filename_$key", true );
						if ( $waybill !== '' ) {
							$hasOrder = true;
						}
					}
				}
				if ( $hasPudoMethod ) {
					$pudo_status = $order->get_meta( 'pudo_status', true );
					if ( ! $hasOrder ) {
						?>
						<a href="#" tcg_order_id_ol='
						<?php
						echo $orderId;
						?>
						' class='send-pudo-order_order-list'
							title='Send Order To The Courier Guy Locker'>
							<?php
							echo wc_help_tip( 'Send Order to The Courier Guy Locker' );
							?>
						</a>
						<?php
					} else {
						?>
						<a href='/wp-admin/admin-post.php?action=print_pudo_waybill&order_id=
						<?php
						echo $orderId;
						?>
						' class='print-pudo-waybill_order-list' title='Print Waybill'>
							<?php
							echo wc_help_tip( 'Print The Courier Guy Locker Waybill' );
							?>
						</a>
						<?php
					}
				}
			}
		}
	}

	public static function addCustomJavascriptForOrderList() {
		?>
		<script>
			jQuery(function () {
			jQuery(document.body).on('click', '.send-pudo-order_order-list', function (event) {
				event.preventDefault()
				let postId = jQuery(this).attr('tcg_order_id_ol')
				jQuery('<img alt ="The Courier Guy Locker" id=\'' + postId + '_ol_loader\' src=\'data:image/gif;base64,R0lGODlhHgAeAIQAAAQCBISChMTCxOTi5GxqbCQmJPTy9NTS1BQSFKyqrMzKzOzq7Pz6/DQ2NNza3BwaHLy+vAwKDJyanMTGxOTm5Hx+fCwqLPT29BQWFLSytMzOzOzu7Pz+/Nze3P///wAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQJCQAeACwAAAAAHgAeAAAF/qAnjiOzOJrCHUhDZAYpzx7TCRAkCIwG/ABMZUOTcSgTnVLA8QF/mASn6OE4lFie8/mrUD0LXG7Z5EIPVWPnAsaJBRprIfLEoBcd2QIyie0hCgtsIkcSBUF3OgslSQJ9YAMMRRcViTgTkh4dSwKDXyJ/SgM1YmOjnyKbpZgbWSqohAo5OxAGV1iLsCIDWBAOsm48uiIXbxAHSWMKw4QTpQoM0dLMItLW0heZzAzZ3AylgNQcssoaSxDasMVYGrdKFMy8sxAdG246E1OwDM5LG99jdOSBdevNlBtjBMSAFSbgKYA7/HTQN8MGmz04oI3AeDHjgAv6OFwY4ExhGwi5REYM8ANOgAoG5MZMuHhqxp97OhgYmHXvUREOCBPq4LCzJYSaRRYA46lzng4BRFAxIElmp7JIzDicOADtgoIDDgykkxECACH5BAkJACgALAAAAAAeAB4AhQQCBISChMTCxERCROTi5BweHKyqrPTy9NTS1BQSFGxqbDQ2NLy6vJSSlMzKzOzq7CQmJPz6/HR2dAwKDLSytNza3BwaHHx+fAQGBISGhMTGxOTm5CQiJKyurPT29BQWFLy+vJyanMzOzOzu7CwqLPz+/Hx6fNze3P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAb+QJRwOIw8KiJHCZFYKCgHonSKipwEIJBAEBEBvoDPZUSVljYarVpQ8oK/H0OpjCpV1FqGtv1+X+goD1hZa3x9YQh1ZicegVgCegIidhAYbx+JBhJSDyAaUZ0gDg+NQiUPDRCIKAaWHUVpAp+BBBFlHheZlgAWZCgnebJRgEOtbxlVg4QExEMSfQUeI2uic80oHhx9DHd5IA/XQw19Eg7BXOFCJ30DaYQO6aaqYAUR9vfxQiP7/PYe+PlK3LPnLgu8eCUKejL3CIStdB4agkBwZ5CADfEIZBlUYRoeJeEiaLAI4kAJi3qYXesGSZStK5EElGoWKo9KkVpkBjphbYpbFVCDHDwMpKVRJ1kEPFgr4YHASJ2CQPgaQgAUoUdKIpgj5AmUyilHSXI5sBESFg0zp5QAtlHPHrIt13wt82Dro7Eb84y6FsFpsBJk39VCOALJpAMORBAYMXRKEAAh+QQJCQAqACwAAAAAHgAeAIUEAgSEgoTEwsREQkTk4uQcHhysqqz08vTU0tQUEhS0trSUkpRsamw0NjTMyszs6uwkJiT8+vx0dnQMCgyMjoy0srTc2twcGhy8vrx8fnwEBgSEhoTExsTk5uQkIiSsrqz09vQUFhS8urycmpzMzszs7uwsKiz8/vx8enzc3tz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAG/kCVcDiMPCwkxwmRaDAqB6J0qoqkBBiMQBAhAb6AUKZElZ46HK1acPKCvyHDqaw6WdRakbb9fmfoKg9YWWt8fWEIdWYpIIFYAnoCJHYQGm8hiQYSUg8YHFGdGA4PjUInDwsQiCoGlh9FaQKfgQQRZSAZmZYAF2QqKXmyUYBDrW8bVYOEBMRDEn0FICVronPNKiAefSJ3eRgP10MLfRIOwVzhQil9A2mEDummqmAFEfb38UIl+/z2IPj5DvAT6C4LvHgntNEz9wiDrXTr3gy4M0hAh3gUyE3DoyTcgQt9MJyoqIfZNQbQbF2JJKBUMwN9KAiJEKtloBTWpliJYgyAXodhgbQ06iSLAAhrJ0AQ4LClkTEFUgiAIvRISQRzhDzx/EOFaEUtEQ5k2SICC4dGOYmcADZWzx6xkMZqMUnnAdZHXMSuETHqWoSlwU7oNVgLYQkkkw44IEGgxEMqQQAAIfkECQkALAAsAAAAAB4AHgCFBAIEhIKExMLEREJE5OLkrKqsHB4c9PL01NLUFBIUlJKUbGpstLa0NDY0jIqMzMrM7OrsJCYk/Pr8dHZ0DAoMtLK03NrcHBocvL68fH58BAYEhIaExMbEREZE5ObkrK6sJCIk9Pb0FBYUnJqcvLq8jI6MzM7M7O7sLCos/P78fHp83N7c////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABv5AlnA4lEAspkcKkWgsKgeidMqSrAQYjEAgMQG+AFHmRJWmPBytWpDygr+iQqrMSlnUWpK2/X5n6CwQWFlrfH1hCHVmKyGBWAJ6AiZ2ERpvIokFE1IQGBxRnRgPEI1CKRAKEYgsBZYfRWkCn4EEEmUhGZmWABdkLCt5slGAQ61vG1WDhATEQxN9BiEna6JzzSwhIH0kd3kYENdDCn0TD8Fc4UIrfQNphA/ppqpgBhL29/FCJ/v89iH4+Q7wE+guC7x4KbTRM/cIg610694MuDNIgId4JchNw6Mk3IELfTCkqKiH2bUF0GxdiSSgVLMCfUoIkRCrZaAV1qYcmKDrC1iIYYG0NOoki0AIa3YcgMTEyhIDKQRAEXqkxIJCMEwL/KFCtKKWLodW5SSSAlgWSGcNHXJADIK5NVzcvDHwtJkEArFIsJELwEAJl9dSnEAyCYGBDioc0gkCACH5BAkJAC0ALAAAAAAeAB4AhQQCBISChMTCxERCROTi5BweHKyqrPTy9NTS1BQSFJSSlGxqbDQyNLS2tIyKjMzKzOzq7CQmJPz6/HR2dAwKDLSytNza3BwaHLy+vHx+fAQGBISGhMTGxERGROTm5CQiJKyurPT29BQWFJyanDQ2NLy6vIyOjMzOzOzu7CwqLPz+/Hx6fNze3P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAb+wJZwOJRALKeHCpEgLSoHonTakrAEGIxAIDkBvgBRBkWVqjwcrVqg8oK/IoOq3FJZ1NqStv1+Z+gtEFhZa3x9YQh1ZiwhgVgCegIndhEabyKJBhNSEBgcUZ0YDxCNQioQChGILQaWIEVpAp+BBBJlIRmZlgAXZC0sebJRgEOtbxtVg4QExEMTfQUhKGuic80tIR99JXd5GBDXQwp9Ew/BXOFCLH0DaYQP6aaqYAUS9vfxQij7/PYh+PkO8BPoLgu8eCq00TP3CIOtdOveDLgzSICHeCbITcOjJNyBC30wqKioh9m1BdBsXYkkoFQzA31MCJEQqyWrCS6lHJig68tah2GBtDSCCaCAAhbW7DgAiYmVpQZSCEQx9oZBCAsKwTQ18IcK1TcJDrjp09SamWeHEoQY28cBMRAg31BYe6gA1GYoNhQAo5ZtARM5m4UoMWGAVQQFOqxwSCcIACH5BAkJADAALAAAAAAeAB4AhQQCBISChMTCxERCROTi5KSmpBweHPTy9JSSlNTS1LS2tCwuLBQSFGxqbIyKjMzKzOzq7KyurCQmJPz6/HR2dAwKDJyanNza3Ly+vDQ2NBwaHHx+fAQGBISGhMTGxERGROTm5KyqrCQiJPT29Ly6vDQyNBQWFIyOjMzOzOzu7LSytCwqLPz+/Hx6fJyenNze3P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAb+QJhwOJxALqgHK8HINFQHonQKm7wEGIxAMEEBvgDTJkWVskAerVrA8oK/phCrDGNd1FqStv1+b+gwEFhZa3x9YQl1Zi8jgVgCegIodhIcbyaJIRRSEBgeUZ0YDxCNQiwQCBKIMAUcHBFFaQKfgQQTZSMbmZYAGmQwL3mzUYBDIbxfHVWDhATFQxR9BiMpa6JzzzAjIn0kd3kYENlDCH0UD8Jc40IvfQNphA/rpqpgBhP4+fNCKf3+/ymIzTvgjyCDNwuwjZvAzV6GNxXErXuBDMCABn0szDthTkUfCaWeHTDQB8MIE33+ZMP45h6MDZcSZWv15gQ/lKs0hZRygEJbIpoARAgssIqmAQQvsNlxoCGnJQVEWOiCcazPggkJ6oHBxCpAmap9GIxw04erwqjRDlUYkeDQFwfFIjR9I5asPajPUnQg+aVuyxM7n40gQWHAArYGPrTAcKtMEAAh+QQJCQAyACwAAAAAHgAeAIUEAgSEgoTEwsREQkSkoqTk4uQcHhy0srT08vSUkpTU0tRkZmQsLiwUEhR0dnSMiozMysysqqzs6uwkJiS8urz8+vwMCgycmpzc2tw0NjQcGhx8fnwEBgSEhoTExsRERkSkpqTk5uQkIiS0trT09vRsamw0MjQUFhR8enyMjozMzsysrqzs7uwsKiy8vrz8/vycnpzc3tz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAG/kCZcDisSDAqyEvRyJQOCKJ0KqvEBC6XQFBRAb6A04ZFlb5CHq1a8PKCv6fIqyx7YdRairb9fm/oMhJYWWt8fWEKdWYOiYJ5Wip2ExxvJ4kRDlIEiIFZEBIkQy8SCROcIBwcK0MhJ3CNBRVlJBuXlAAaZDIOlYmAQxG3Xx0yCA19Kb9DvG8GJCt9DLLKMiQGfRQLfTDUQwl9DiZvHLrdBX0DFm8M3aIizSzx8VHtQvLyCPMS9PX58yzqwDCY0+7FOzAGMryxIKFeDHQl+lyolwLcgT4TQlFDcO2NCxKu/HSL2EzWhl7dUL1JJoNFSEsyMGmcgoCRDJUARPADcYqSUYEEMQjaeaCBUzAAI4i8qBVT2BdpCkyhBBGgzNE+DUi46QOT4KJDACyQUAAWwINfK4q+yboVYVJlLDp0BMC2WYqZ1EhQcDCAwVgDH1C4mEYlCAAh+QQJCQAwACwAAAAAHgAeAIUEAgSEgoTEwsREQkQkIiSkoqTk4uT08vQUEhSUkpRkZmQ0MjS0srTU0tR0dnSMiowsKiysqqzs6uz8+vwcGhwMCgycmpw8Ojy8urzc2tx8fnwEBgSEhoTMzsxERkQkJiSkpqTk5uT09vQUFhRsamw0NjS0trR8enyMjowsLiysrqzs7uz8/vwcHhycnpzc3tz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAG/kCYcDg8qEgLgqiBKJEYB6J0CpM4RoAsQtTJZkeaFVXKKiC82QoX/Y2wxjAWia1d0wEaOAy0oW+7dCMNcWQOgxF9XhsXIhkfiV+HDlIFAIIwiAAQLhJvcRIJH5aDfBsqQyFYo3saE2MiGqQVWRRiMA5ol3pDKpAAHDAHZ2gou0O4aC0iKmwprsYwIgRsGApsLtBDCWwOC2gbttkGbAOzXinZQyzTXi0r7+9R6ULw8AfxEvLz9/Er5lkpPGVbl6wEmgoS5r0gNweNhXkouDFg80FEtgMtqIlQ5SUPtIbtXGnINQgaH2L0VF2K4MAilQOG9iQioA/EqpMtErzwxCLDSAMKqzKZIMIiFiZfAJw1EEVyT4Axmdj8uXNJIFFkbNQ0uAPgwS4VQNFMZdNiqLEVHDLWAZSlBQqX2URgcDAgxZIWHk4IgDslCAAh+QQJCQAyACwAAAAAHgAeAIUEAgSEgoTEwsREQkTk4uQkIiSkoqT08vS0srQUEhTU0tRkZmQ0MjSUkpR0dnSMiozMyszs6uwsKiysqqz8+vy8urwcGhwMCgzc2tw8Ojx8fnwEBgSEhoTExsRERkTk5uQkJiSkpqT09vS0trQUFhRsamw0NjScnpx8enyMjozMzszs7uwsLiysrqz8/vy8vrwcHhzc3tz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAG/kCZcDg8tEqMgkgkUGFWFKJ0Kos4SIBsQnR4CV6vTixKJboMiaz6wgV/v51IWegqqe9bprvifcXmMiEbd1ptXnxwBzIuUi4OCjITg2obGUsQAnxgHYoTDlIGACSQkgASJxGMQiIfEF6dgy1DH1iikCEaZFMUMSKRkxYrQg53o4BEE4QcMgdpdynHQ8R3MCIthCy6xyIwhBULhCfRRA2EDgx3G8LjQgSEAxd3LOxDLgXUK/n5ivRC+voH9kXg1y/gvhXx1LBQxc4eNRN3LsihF+OdnTvi6KUwh4AQCF/jDlgg9EJELTUa2F1UAyOKhmKQxgl65q+WMU8gpxx4FGhSWgGCIWz1BACjQQxVLjA8GHlz0AgzGkhNUkhBAQhCxkIEKFOK0BYVhLIYY2hmGiE2CsJmeXCsxUg8IsASgvE02goO3QrJzQIjRc5xIio4GMBChAIYHlC80CYlCAAh+QQJCQAxACwAAAAAHgAeAIUEAgSEgoTEwsREQkTk4uSkoqQkIiT08vS0srQUEhTU0tRkZmSUkpQ0MjR0dnSMiozMyszs6uysqqz8+vy8urwcGhwMCgwsKizc2tw8Ojx8fnwEBgSEhoTExsRERkTk5uSkpqT09vS0trQUFhRsamycnpw0NjR8enyMjozMzszs7uysrqz8/vy8vrwcHhwsLizc3tz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAG/sCYcDg8rEgNQygkSGFUE6J0Gos4RoBsInRoCVqtDixKJbIKiazawgV/v51IWcgiqe9bppvibcHmMSAbd1ptXnxwBzEsUiwOCjESg2obGUsQAnxgHYoRf0QFACOQkgAXJRGMQiEfEF6dX3JCH1iikCAaZFMTMCFVXy0QZA53o4BEEW5gBDEHaXcox0MwwJkdEyuEL7rHEx2ZfSoLhCXSRB9gfTANdxsq5kMTb14pFncv8EMsEJoUECoAASrKJ2SCwYMHBEYYSDBgQHtqXqiCx8LAHRcm7liQBQ/GpCwD7Nwplw8FIQcICCmBd6ACoRYhaqnRAE+kGhdRNBSDZE4QaDQhKmoZk+DAF5UDjwJNMsAQhC2lAFwwgKGKBYYHLocOEmFGA6mPWbYpMADWGIgAZUoR2pKCUBZjE80Qc8tGgdssD46tcIknRFtCLrhKU8HBhRq2F1EYhReCgoMBL0IocOHhRAtuUoIAACH5BAkJADAALAAAAAAeAB4AhQQCBISChMTCxERGROTi5KSipCQiJPTy9LSytBQSFNTS1JSSlGRmZDQyNHR2dIyKjMzKzOzq7KyqrPz6/Ly6vBwaHAwKDCwqLNza3Dw6PHx+fAQGBISGhMTGxOTm5KSmpPT29LS2tBQWFJyenGxqbDQ2NHx6fIyOjMzOzOzu7KyurPz+/Ly+vBweHCwuLNze3P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAb+QJhwODyoSA0DCCRAYVITonQKizhEgGwCdGAJWKzOK0olrgqJrNrCBX+/nUhZuCKp71umm+JlveYwHxt3Wm1efHAHMCtSKw4KMBKDAJQbGUsQAnxgHYoRf0QFACKQkgAXIxGMQiAeEF6eX3JCHlijkB8aZFMTLyBVXywQZA6UlKSARBFuYAQwB2nGACfJQy/Bmh0TKtIALrvJEx2afSkM3SPVRB5gfS8N0hsp6kMTb14oFtIu9EMrEJsoQEhBkKCifkImKFx4wGCEgwgngFAIYoU+Yy5W0fvXTlgJaRZm0dMTDIUdaen6EWj35QWCbkroievz5QAIW8Y00HtxCMxuMBgapCFTt2yPMxgpbCGT4OAXlQOPgPnc9eFWoEEtFrxYtQLDgwpWI3yZ509DqUkYJygwgDYsqCmmum1B0e0YpDKO6gJgo0AvgAfJVICVNrduixDqUnBoYaywsRYnnI6k4GCACxAKWgwwwQKclCAAIfkECQkALgAsAAAAAB4AHgCFBAIEhIKExMLEREZE5OLkJCIkpKKk9PL0FBIU1NLUtLK0ZGZkNDI0lJKUdHZ0zMrM7OrsrKqs/Pr8HBocDAoMjI6MLCos3NrcvLq8PDo8fH58BAYEhIaExMbE5ObkpKak9Pb0FBYUbGpsNDY0nJ6cfHp8zM7M7O7srK6s/P78HB4cLC4s3N7cvL68////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABv5Al3A4PKBEjAIIJDBdThKidOqCOEKALAJ0aAlarQ4rSiWmDIismsIFf78dSFmYEqnvW6Yb422x5i4fG3dabV58cAcuKVIpDgkuEYMAlBsZSw8CfGAdihB/RAYAIZCSABYkEIxCIB4PXp5fckIeWKOQHxpkUxIsIFVfLQ9kDpSUpIBEEG5gBC4HacYAFclDLMGaHRIo0gAru8kSHZp9JwvdJNVEHmB9LAzSGyfqQxJvXiYU0iv0QykPmzA8OEGQoKJ+QiQoXHjAIISDCCWAUAgihT5jK1bR+9dO2AhpFGbR0xPMhB1p6foRaPeFhYJuSuiJ6/PlAAhbxjTQY3EIzHIwFxqkIVO3bI8zFydsIYvg4BeVXr8gBPsp5MOtQINUNGCxKgUIAuMERP0yz5+GUpMwSnwVLIyno1NMdUNwoAszOE6pOOpGaUuXYBjewC2DYkI3NiDuChMJ6AQHFcb8suxAAFw1EBgcDFiBKcGFA5aJBAEAIfkECQkAKQAsAAAAAB4AHgCFBAIEhIKExMLEZGZk5OLkJCIkpKKk9PL01NLUFBIUNDI0tLK0dHZ0zMrM7OrsrKqs/Pr8DAoMnJ6cLCos3NrcHBocPDo8fH58BAYEjI6MxMbEbGps5ObkpKak9Pb0FBYUNDY0vL68fHp8zM7M7O7srK6s/P78LC4s3N7c////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABv7AlHA4PJQ2ioLHIxhRSBCidJpyMD6AbMJzCAlCIQ0qSiWaDImsOsIFf78aR1lo2qjvW6Z7j5qnOhh3Wm1eYF4CHikmUiYMCCkPgWoYFksNX4YCB1V9RAYAH4+RABMSDotCHhwNXpsOX3JCHFigjx0XZFMQBK6FGmQMd6F+RA6FXwQpB2l3GcRDKIZgvyWCJ7nEEKxvIQcDghLPRByZIRQKdxgk4kMQhV4jEXcn7EMmrIYNJPv7m/VCEAIKPNDPgb9/AgXKU3MCFTsTGqRpAHEnQix2ejAhsHMnXD0C5SgsEKSEHQQN3Lp5oKXmArto0hpEuSDskbhX75KlIEFrmGcDFA6lQECRyNgXmUM61KriRQMBD6hMeCCAUhNTARcVXXhkbI+ABia0SQtTtNMUo5jeQOhyyNfBKSZgvvNiogumPTrnkMD3RsDaPYfWPdsV8U3dmASwPTPhgMKIERA8NBhBwIFiIkEAACH5BAkJACUALAAAAAAeAB4AhQQCBISChMTCxGRmZOTi5CQiJPTy9KSipNTS1BQSFDQyNMzKzHR2dOzq7Pz6/Ly+vAwKDJyenKyqrNza3BwaHDw6PAQGBIyOjMTGxGxqbOTm5CwuLPT29BQWFDQ2NMzOzHx6fOzu7Pz+/KyurNze3P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAb+wJJwODSMMooChyP4TEIOonRaajA6gGyCY3gIHg8MKUolig6JrBrCBX+/mEZZKMqo71ume0+alyQWd1ptXmBeAhwlIlIiDAh/gWoWFUsLX4YCBlV9RAcAHY+AABsRDYtCHBoLXpoNX3JCGlifoSCJVA4ErYUYZAx3oH5EDYVfBCUGaXcXwkMkhmC9I4IbZM0Oq28PBgOCEc1EGpgPEwp3FiHgQw6FXh8QdxvqQyKrhgsh+fma80IO/wAD/uvnTyAGaAsIijhoCMOHdgKsgdNzCcGEYg809CMw7smeBwtOXcOgbZsDhoU4NbvYbkEUEpe83Grmas+xEg7eZNokUopbAxK73ogkhqjKlwUEOJwSwYEAyaJEYQ3RZRSagJDYoIVppVIKMYxeHHQ5xGvmFBEwrXoR0SVmoZtzGjx1E3HsJQEC0l0jcVDoWDBIJTYT0WACApccFlg0IJhIEAAh+QQJCQAhACwAAAAAHgAeAIUEAgSEgoTEwsTk4uRkZmQkIiT08vTU0tSkoqQUEhTMyszs6uw0NjT8+vy8vrwMCgx8enzc2tysqqwEBgScnpzExsTk5uRsamwsLiz09vQUFhTMzszs7uw8Ojz8/vzc3tysrqz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAG/sCQcDg0gC6MQiYj2EQ4DaJ0GlpAEoBswmBwCByOyidKJXoQ2Kw20/W6K4uy0HNR2x9LsNv9kYckE3ZrXV9fXgIZIR5SHh8Gf4FqEx1LCoZgAo8LfUQLYYkSWRgUC4tCGRYKXppfcUINFV4VjxIQiVQNA5pvZB97s36dbl8DIQ2XxMFDvnphDRx7Dgqmyg2qhQ4GEZhgrspCA3pfEdeHZN8hTHsHsXoK6EMequ4N9fbwQ/b6+/iv+5fS+nloB6bChj0Czn1TZ+jAtj3evoXj9sTQFwUK/cBCCAUbmGLfHg5bxAzTLWWeLoGEVSjKpoz5HFVxg3FIyl3SBmQw5SHDSYAKAoAtaCVFVxWAAqZZa/apCsgpnoZhatBGADZgZRp5LOSBkDgvT8ssiGUxYVWaEeXkIuulazMBA2D68bAgwgGMGRQ4NEBtShAAIfkECQkAEAAsAAAAAB4AHgCEvL685OLk1NLU9PL0zMrM7Ors/Pr8xMbE3NrcxMLE5Obk9Pb0zM7M7O7s/P783N7c////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABf4gJI6jUSAM4QwHgTQGKc+Q8SQAkCTGoOeHR4xGcigOP1xilVMCDgWiyIH4WZe+pvYhhRRwuSTTqVxAHDLHw/y9MhwLArjJ5pIKz4E3RyiYSwoEOnptUSIGSAkHhAFDMzaEYARDD0kJf10ieFYBNXM5nZkiN1oHBg1XKqKHB2A4DVVWhqsQAWE6CIJKPLQiC04AAkhhBL1TgsQGysvGh8vPyguOvQbSy5/Fxg7IQAxJANOiv0kMsT8KxrZaL2QHaKuIwAMGnwl2orGuaKRNeqttYULR05GA0IN3j9bswTFpBJ5LXhgGWPAOToBWBSMCmCUiQKRbCVQY4AaETagZmyuA8ciy48ciKWrq6RgDEsBJIgV0NVmphWADeBfFZOHTSNsJAZPiCEAwT0oIADs=\'/>').insertAfter(jQuery('[tcg_order_id_ol=\'' + postId + '\']'))

				jQuery.ajax({
				type: 'post',
				url: '/wp-admin/admin-ajax.php',
				data: {
					action: 'submit_pudo_collection_from_listing_page',
					post_id: postId,
				},
				success: function (response) {
					response = JSON.parse(response)
					console.log(response)
					if (!response.success) {
					jQuery('#wpbody-content').prepend('<div class=\"error\"><p>' + response.result + '</p></div>')
					jQuery('html,body').animate({ scrollTop: 0 }, 'slow')
					location.replace('/wp-admin/change-locker?order_id=' + postId)
					} else {
					let waybillURL = '/wp-admin/admin-post.php?action=print_pudo_waybill&order_id=' + response.tcg_order_id_ol
					let elementId = '[tcg_order_id_ol=\'' + response.tcg_order_id_ol + '\']'
					jQuery(elementId).removeClass('send-pudo-order_order-list').addClass('print-pudo-waybill_order-list').attr('href', waybillURL)
					jQuery(elementId + ' > span.woocommerce-help-tip').attr('data-tip', 'Print The Courier Guy Locker Waybill')
					jQuery('#' + response.tcg_order_id_ol + '_ol_loader').remove()
					location.replace('/wp-admin/edit.php?post_type=shop_order&order_id=' + postId)
					// jQuery('#wpbody-content').prepend('<div class=\"notice notice-success\"><p>' + response.result + '</p></div>');
					}
				}
				})
			})
			})

		</script>
		<?php
	}

	public function add_custom_query_var() {
		// Add 'order_id' as a recognized query variable for shop_order post type
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'order_id';

				return $vars;
			}
		);
	}

	public static function display_pudo_notice() {
		global $pagenow;

		if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'shop_order' && isset( $_GET['order_id'] ) ) {
			$order_id    = absint( $_GET['order_id'] );
			$order       = wc_get_order( $order_id );
			$pudo_status = $order->get_meta( 'pudo_status' );
			if ( $pudo_status === 'Booking confirmed' ) {
				$message     = 'The Courier Guy Locker ' . $pudo_status . ' for Order ' . $order_id;
				$noticeClass = 'success';
			} else {
				$message     = $pudo_status;
				$noticeClass = 'error';
			}
			?>
			<script>
				jQuery(document).ready(function ($) {
				$('#wpbody-content').prepend('<div <?php echo 'class="notice notice-' . $noticeClass . '"><p>' . $message; ?></p></div>')
				})
			</script>
			<?php
		}
	}

	/**
	 * Place collection order from icon in orders table
	 *
	 * @return void
	 */
	public static function setCollectionFromOrderListingPage() {
		$orderId     = filter_var( $_POST['post_id'], FILTER_SANITIZE_NUMBER_INT );
		$order       = wc_get_order( $orderId );
		$klockers    = $order->get_meta( 'pudo_locker_origin', true );
		$pudo_status = $order->get_meta( 'pudo_status', true );
		// $locker starts with 'K', skip locker full check and create the order
		if ( strpos( $klockers, 'K' ) === 0 ) {
			self::createShipmentFromOrder( new WC_Order( $orderId ) );
			echo json_encode(
				array(
					'success'         => true,
					'result'          => $pudo_status,
					'tcg_order_id_ol' => $orderId,
				)
			);
		} else {
			$locker           = explode( ':', $order->get_meta( 'pudo_locker_origin', true ) )[0];
			$openLockerSpaces = self::getOpenLockerSpaces( $locker );
			$pudoMethod       = explode( ':', $order->get_meta( 'pudo_method', true ) )[1];
			$openSpaces       = json_decode( $openLockerSpaces, true )[0]['lstTypesBoxes'];
			foreach ( $openSpaces as $oa ) {
				if ( $oa['name'] == $pudoMethod ) {
					if ( $oa['free'] > 0 ) {
						self::createShipmentFromOrder( new WC_Order( $orderId ), $_POST );
						echo json_encode(
							array(
								'success'         => true,
								'result'          => 'Order sent to The Courier Guy Locker',
								'tcg_order_id_ol' => $orderId,
							)
						);
					} else {
						echo json_encode(
							array(
								'success'         => false,
								'result'          => 'Failed to send order, Locker full',
								'tcg_order_id_ol' => $orderId,
							)
						);
					}
				}
			}
		}
		exit;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public static function createShipmentFromOrder( WC_Order $order ): void {
		$data = array(
			'pudo-source-locker'      => $order->get_meta( 'pudo_locker_origin', true ),
			'pudo-destination-locker' => $order->get_meta( 'pudo_locker_destination', true ),
		);
		self::createShipment( $order, $data );
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	private static function convertOrderToArray( WC_Order $order ): array {
		return array(
			'name'            => $order->get_shipping_first_name(),
			'email'           => $order->get_billing_email(),
			'mobile_number'   => $order->get_billing_phone(),
			'street_address'  => $order->get_shipping_address_1(),
			'city'            => $order->get_shipping_city(),
			'code'            => $order->get_shipping_postcode(),
			'zone'            => $order->get_shipping_state(),
			'country'         => $order->get_shipping_country(),
			'entered_address' => "{$order->get_shipping_address_1()}, {$order->get_shipping_city()}, {$order->get_shipping_state()}, {$order->get_shipping_postcode()}, {$order->get_shipping_country()}",
		);
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	private static function createShipment( WC_Order $order, $data ): void {
		if ( ! self::orderHasPudoMethod( $order ) ) {
			return;
		}

		$settings = self::$parameters;

		$shippingMethods[] = json_decode( $order->get_meta( self::ORDER_SHIPPING_DATA ) );
		foreach ( $shippingMethods as $method ) {
			if ( ! self::isPudoMethod( $method ) ) {
				continue;
			}
			$methodParts = explode( ':', str_replace( '-FREE-SHIPPING', '', $method ) );

			$shippingData = new ShippingData( $methodParts[3], true );

			$instanceId = 0;
			foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
				$instanceId = $item->get_instance_id();
			}

			$pudoShippingMethod = new Pudo_Shipping_Method( $instanceId );
			$settings           = $pudoShippingMethod->instance_settings;

			$orderArray = self::convertOrderToArray( $order );

			$pudoApi = new PudoApi();

			$data['fittedBox'] = $pudoApi->getBoxInfo( $methodParts[4] );

			$apiRequestBuilder = $shippingData->initializeAPIRequestBuilder(
				$orderArray,
				$settings,
				$data,
				$methodParts[3]
			);

			$rates = $pudoApi->getRates( json_encode( $apiRequestBuilder->buildRatesRequest() ) )['body'];

			$rates = json_decode( $rates, true )['rates'];

			$response         = false;
			$boxSizeAvailable = false;
			foreach ( $rates as $rate ) {
				$serviceLevelCode = $rate['service_level']['code'];
				if ( $serviceLevelCode === $methodParts[1] ) {
					$bookingRequest   = $apiRequestBuilder->buildBookingRequest( $serviceLevelCode );
					$response         = $pudoApi->bookingRequest( json_encode( $bookingRequest ) );
					$boxSizeAvailable = true;
				}
			}

			if ( ! $boxSizeAvailable ) {
				$order->add_order_note(
					'The Courier Guy Locker: Locker size is not available at the moment.'
				);
				$order->update_meta_data( 'pudo_status', 'Could not confirm' );
			} elseif ( ! $response || is_wp_error( $response ) ) {
				$order->add_order_note(
					'The Courier Guy Locker: Something went wrong...'
				);
				$order->update_meta_data( 'pudo_status', 'Could not confirm' );
			} elseif ( $response['response']['code'] !== 200 ) {
				$order->add_order_note(
					'The Courier Guy Locker: Could not book - ' . $response['body']
				);
				$order->update_meta_data( 'pudo_status', 'Could not confirm' );
			} else {
				$bookingData = json_decode( $response['body'], true );

				if ( ! isset( $bookingData['id'] ) ) {
					$order->add_order_note(
						'The Courier Guy Locker: Could not book - Something went wrong...'
					);
					$order->update_meta_data( 'pudo_status', 'Could not confirm' );
				} else {
					$order->add_order_note( 'The Courier Guy Locker: Booking confirmed' );
					$order->update_meta_data( 'pudo_booking_id', $bookingData['id'] );
					$order->update_meta_data(
						'pudo_custom_tracking_reference',
						$bookingData['custom_tracking_reference']
					);
					$order->update_meta_data( 'pudo_status', 'Booking confirmed' );
				}
			}

			$order->save_meta_data();
		}
	}

	public static function getOriginLockerName( $index ) {
		$instanceSettings = ( new Pudo_Shipping_Method() )->instance_settings;

		return $instanceSettings[ 'pudo_locker_name_' . $index ];
	}

	/**
	 * Triggered by icon click in orders list
	 *
	 * @return void
	 */
	public static function printWaybillFromList() {
		$orderId = filter_var( $_REQUEST['order_id'], FILTER_SANITIZE_NUMBER_INT );
		self::printWaybillFromOrder( new WC_Order( $orderId ) );
	}

	/**
	 * Triggered by action in order detail or from above
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public static function printWaybillFromOrder( WC_Order $order ): void {
		$pudoApi = new PudoApi();

		$waybillResponse = $pudoApi->getWaybill( (int) $order->get_meta( 'pudo_booking_id' ) );

		if ( $waybillResponse['response']['code'] !== 200 ) {
			$order->add_order_note( 'The Courier Guy Locker: Could not fetch the waybill' );

			return;
		}

		$receiverEmail     = $order->get_billing_email();
		$pudoWaybillNumber = $order->get_meta( 'pudo_custom_tracking_reference' );

		$pdfData  = $waybillResponse['body'];
		$filename = $order->get_id() . '-' . $receiverEmail . '-' . $pudoWaybillNumber . '.pdf';

		header( 'Content-type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . strlen( $pdfData ) );
		header( 'Accept-Ranges: bytes' );
		echo $pdfData;
		exit();
	}

	/**
	 * Triggered by action in order detail or from above
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public static function printLabelFromOrder( WC_Order $order ): void {
		$pudoApi = new PudoApi();

		$labelResponse = $pudoApi->getLabel( (int) $order->get_meta( 'pudo_booking_id' ) );

		if ( $labelResponse['response']['code'] !== 200 ) {
			$order->add_order_note( 'The Courier Guy Locker: Could not fetch the label' );

			return;
		}

		$receiverEmail   = $order->get_billing_email();
		$pudoLabelNumber = $order->get_meta( 'pudo_custom_tracking_reference' );

		$pdfData  = $labelResponse['body'];
		$filename = $order->get_id() . '-' . $receiverEmail . '-' . $pudoLabelNumber . '.pdf';

		header( 'Content-type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . strlen( $pdfData ) );
		header( 'Accept-Ranges: bytes' );
		echo $pdfData;
		exit();
	}

	/**
	 * @param int $productId
	 *
	 * @return bool
	 */
	private function isPudoProhibited( int $productId ): bool {
		return get_post_meta( $productId, 'product_prohibit_pudo', true ) === 'on';
	}

	/**
	 * @param array $package
	 *
	 * @return array
	 */
	private function isPudoProductProhibited( array $package ): array {
		if ( ! isset( $package['contents'] ) ) {
			return array();
		}
		$prohibitedProducts = array();

		foreach ( $package['contents'] as $item ) {
			if ( $this->isPudoProhibited( $item['product_id'] ) ) {
				$prohibitedProducts[] = $item;
			}
		}

		return $prohibitedProducts;
	}

	/**
	 * @return void
	 */
	public function addProhibitedWarnings() {
		if ( empty( $this->prohibitedProducts ) ) {
			return;
		}
		$errorMessage = 'Products ';
		foreach ( $this->prohibitedProducts as $product ) {
			$errorMessage .= "{$product['data']->get_name()},";
		}
		$errorMessage = rtrim( $errorMessage, ',' ) . ' are prohibited from using The Courier Guy Locker checkout';
		$errors       = new \WP_Error();
		$errors->add( 'validation', $errorMessage );
		wc_print_notice( $errorMessage );
	}

	/** Returns lockers
	 *
	 * @param int $instanceId
	 *
	 * @return array
	 */
	public static function getPudoLockers( int $instanceId ) {
		$pudoApi = new PudoApi();

		return $pudoApi->lockers;
	}
}

