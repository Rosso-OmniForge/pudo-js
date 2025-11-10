<?php

namespace Pudo\WooCommerce;

use Pudo\Common\Service\PudoShippingService;
use StdClass;

/**
 *  Copyright: © 2025 The Courier Guy
 */
class Pudo_Shipping_Settings {


	const ADDRESS_LINE_1 = 'Address Line 1';
	const ADDRESS_LINE_2 = 'Address Line 2';
	const ADDRESS_LINE_3 = 'Address Line 3';

	/**
	 * Generate the fields for settings for the plugin
	 */
	public static function overrideFormFieldsVariable( array $lockers = array() ) {
		$fields['title'] = array(
			'title'   => __( 'Shipping Method Title' ),
			'type'    => 'text',
			'label'   => __( 'Shipping Method Title' ),
			'default' => 'The Courier Guy Locker',
		);

		$fields['tax_status'] = array(
			'title'       => __( 'Tax status', 'woocommerce' ),
			'type'        => 'select',
			'options'     => array(
				'taxable' => 'Taxable',
				'none'    => 'None',
			),
			'description' => __( 'VAT applies or not', 'woocommerce' ),
			'default'     => __( 'taxable', 'woocommerce' ),
		);

		// Pudo source
		$fields['pudo_source'] = array(
			'title'       => __( 'Select locker or address as source' ),
			'type'        => 'select',
			'options'     => array(
				'locker' => 'Locker',
				'street' => 'Street Address',
			),
			'label'       => __( 'Select locker or address as source' ),
			'description' => __(
				'If you do not see all the lockers below please save and submit a valid API key on The Courier Guy Locker Account page.',
				'woocommerce'
			),
			'desc_tip'    => false,
		);

		$fields['pudo_locker_name'] = array(
			'title'   => __( 'The Courier Guy Locker: Locker Source 1' ),
			'type'    => 'select',
			'options' => $lockers,
			'label'   => __( 'The Courier Guy Locker: Locker Source' ),
		);

		$fields['pudo_locker_name_2'] = array(
			'title'   => __( 'The Courier Guy Locker: Locker Source 2' ),
			'type'    => 'select',
			'options' => $lockers,
			'label'   => __( 'The Courier Guy Locker: Locker Source' ),
		);

		$fields['pudo_locker_name_3'] = array(
			'title'   => __( 'The Courier Guy Locker: Locker Source 3' ),
			'type'    => 'select',
			'options' => $lockers,
			'label'   => __( 'The Courier Guy Locker: Locker Source' ),
		);

		$fields['other_settings'] = array(
			'title' => __( 'Other Settings' ),
			'type'  => 'title',
			'label' => __( 'Other Settings' ),
		);

		$fields['remove_waybill_description'] = array(
			'title'       => __( 'Generic waybill description', 'woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'When enabled, a generic product description will be shown on the waybill.',
				'woocommerce'
			),
			'default'     => 'no',
		);

		$fields['usemonolog']               = array(
			'title'       => __( 'Enable WooCommerce Logging', 'woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Check this to enable WooCommerce logging for this plugin. Remember to empty out logs when done.',
				'woocommerce'
			),
			'default'     => __( 'no', 'woocommerce' ),
		);
		$fields['free_shipping']            = array(
			'title'       => __( 'Enable free shipping ', 'woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'This will enable free shipping over a specified amount', 'woocommerce' ),
			'default'     => 'no',
		);
		$fields['amount_for_free_shipping'] = array(
			'title'             => __( 'Amount for free Shipping', 'woocommerce' ),
			'type'              => 'number',
			'description'       => __( 'Enter the amount for free shipping when enabled', 'woocommerce' ),
			'default'           => '1000',
			'custom_attributes' => array(
				'min' => '0',
			),
		);

		$fields['label_override_per_service'] = array(
			'title'       => __( 'Label Override Per Service', 'woocommerce' ),
			'type'        => 'pudo_override_per_service',
			'description' => __(
				'These labels will override The Courier Guy Locker labels per service.',
				'woocommerce'
			) . '<br />' . __( 'Select a service to add or remove label override.', 'woocommerce' ),
			'options'     => PudoShippingService::getRateOptions(),
			'default'     => '',
			'class'       => 'pudo-override-per-service',
		);

		$fields['price_rate_override_per_service'] = array(
			'title'       => __( 'Price Rate Override Per Service', 'woocommerce' ),
			'type'        => 'pudo_override_per_service',
			'description' => __(
				'These prices will override The Courier Guy Locker rates per service.',
				'woocommerce'
			) . '<br />' . __(
				'Select a service to add or remove price rate override.',
				'woocommerce'
			),
			'options'     => PudoShippingService::getRateOptions(),
			'default'     => '',
			'class'       => 'pudo-override-per-service',
		);

		// Sender contact details
		$fields['sender_contact'] = array(
			'title'    => __( 'Name of Shop Contact' ),
			'type'     => 'text',
			'label'    => __( 'Name of Sender' ),
			'required' => true,
		);
		$fields['sender_email']   = array(
			'title'    => __( 'Email of Sender' ),
			'type'     => 'text',
			'label'    => __( 'Sender Email' ),
			'required' => true,
		);
		$fields['sender_phone']   = array(
			'title'    => __( 'Phone of Sender' ),
			'type'     => 'text',
			'label'    => __( 'Sender Phone' ),
			'required' => true,
		);

		// Shop Addresses
		$fields['shop_addresses'] = array(
			'title' => __( 'Shop Addresses (If Street Address selected as Source)' ),
			'type'  => 'title',
			'label' => __( 'Shop Addresses' ),
		);

		$fields['sender_addressline1'] = array(
			'title' => __( self::ADDRESS_LINE_1 ),
			'type'  => 'text',
			'label' => __( self::ADDRESS_LINE_1 ),
		);
		$fields['sender_addressline2'] = array(
			'title' => __( self::ADDRESS_LINE_2 ),
			'type'  => 'text',
			'label' => __( self::ADDRESS_LINE_2 ),
		);
		$fields['sender_addressline3'] = array(
			'title' => __( self::ADDRESS_LINE_3 ),
			'type'  => 'text',
			'label' => __( self::ADDRESS_LINE_3 ),
		);
		$fields['sender_city']         = array(
			'title' => __( 'City' ),
			'type'  => 'text',
			'label' => __( 'City' ),
		);
		$fields['sender_suburb']       = array(
			'title' => __( 'Suburb' ),
			'type'  => 'text',
			'label' => __( 'Suburb' ),
		);
		$fields['sender_postal_code']  = array(
			'title' => __( 'Postal Code' ),
			'type'  => 'text',
			'label' => __( 'Postal Code' ),
		);

		return $fields;
	}
}
