<?php

/**
 * Copyright: © 2025 The Courier Guy.
 *
 * @author The Courier Guy / Pudo
 */
$pudoPostType = new PudoPostType( 'product' );
$pudoPostType->addMetaBox(
	'The Courier Guy Locker Settings',
	array(
		'form_fields' => array(
			'product_free_shipping_pudo' => array(
				'display_name'  => 'Free Shipping',
				'property_type' => 'checkbox',
				'description'   => __( 'Enable free shipping for baskets including this product', 'woocommerce' ),
				'placeholder'   => '',
				'default'       => '0',
			),
			'product_prohibit_pudo'      => array(
				'display_name'  => 'Prohibit The Courier Guy Locker Shipping',
				'property_type' => 'checkbox',
				'description'   => __(
					'Enable to prohibit Pickup Dropoff shipping if cart contains this product',
					'woocommerce'
				),
				'placeholder'   => '',
				'default'       => '0',
			),
		),
	)
);
