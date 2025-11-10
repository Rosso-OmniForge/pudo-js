<?php

class WCOrderUtility {



	/**
	 * Convert a WooCommerce Order object into an array
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public static function convertOrderToArray( WC_Order $order ): array {
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
}
