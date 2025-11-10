<?php

use Pudo\Common\Request\ApiRequestBuilder;

class ShippingData {


	private string $pudoMethod;
	private bool $isShipment;

	/**
	 * @param string $pudoMethod
	 * @param bool   $isShipment
	 */
	public function __construct( string $pudoMethod, bool $isShipment ) {
		$this->pudoMethod = $pudoMethod;
		$this->isShipment = $isShipment;
	}

	public const STANDARD_PARCEL = array(
		'submitted_length_cm' => '1',
		'submitted_width_cm'  => '1',
		'submitted_height_cm' => '1',
		'submitted_weight_kg' => '0.001',
		'parcel_description'  => 'Standard flyer',
	);

	public function buildCollectionDetails( $settings, $locker = null ): array {
		$collectionDetails = array();

		if ( $this->pudoMethod === 'L2L' || $this->pudoMethod === 'L2D' ) {
			$collectionDetails['terminal_id'] = $locker;
		} else {
			$address                              = $settings['sender_addressline1'];
			$collectionDetails['street_address']  = $settings['sender_addressline1'];
			$collectionDetails['local_area']      = $settings['sender_suburb'];
			$collectionDetails['city']            = $settings['sender_city'];
			$collectionDetails['code']            = $settings['sender_postal_code'];
			$collectionDetails['country']         = 'South Africa';
			$collectionDetails['entered_address'] = "{$settings['sender_addressline1']}, {$settings['sender_city']}, {$settings['sender_postal_code']}, {$settings['sender_postal_code']}, {'South Africa'}";
		}

		if ( $this->isShipment ) {
			$collectionDetails['name']          = $settings['sender_contact'];
			$collectionDetails['email']         = $settings['sender_email'];
			$collectionDetails['mobile_number'] = $settings['sender_phone'];
		}

		return $collectionDetails;
	}

	public function buildShippingDetails( $order, $locker = null ): array {
		$shippingDetails = array();
		if ( $this->pudoMethod === 'L2L' || $this->pudoMethod === 'D2L' ) {
			$shippingDetails['terminal_id'] = $locker;
		} else {
			$streetAddress  = $order['street_address'];
			$enteredAddress = "{$order['street_address']}, {$order['city']}, {$order['zone']}, {$order['zone']}, {$order['country']}";

			if ( str_contains( $streetAddress, ',' ) ) {
				$splitStreetAddress = explode( ',', $streetAddress );
				$streetAddress      = $splitStreetAddress[0];
				$suburb             = trim( $splitStreetAddress[1] );
			}

			$shippingDetails['lat']             = $order->shipping_address['latitude'] ?? '';
			$shippingDetails['lng']             = $order->shipping_address['longitude'] ?? '';
			$shippingDetails['street_address']  = $order['street_address'];
			$shippingDetails['suburb']          = $order['zone'];
			$shippingDetails['local_area']      = $order['city'];
			$shippingDetails['city']            = $order['city'];
			$shippingDetails['code']            = $order['code'];
			$shippingDetails['zone']            = $order['zone'];
			$shippingDetails['country']         = $order['country'];
			$shippingDetails['entered_address'] = $enteredAddress;
		}

		if ( $this->isShipment ) {
			$shippingDetails['name']          = $order['name'];
			$shippingDetails['email']         = $order['email'];
			$shippingDetails['mobile_number'] = $order['mobile_number'];
		}

		return $shippingDetails;
	}

	public function buildParcels( $data ): array {
		if ( isset( $data['fittedBox'] ) ) {
			$parcel = array(
				'submitted_length_cm' => $data['fittedBox']['length'],
				'submitted_width_cm'  => $data['fittedBox']['width'],
				'submitted_height_cm' => $data['fittedBox']['height'],
				'submitted_weight_kg' => $data['fittedBox']['weight'],
				'parcel_description'  => 'Standard flyer',
			);
		} else {
			$parcel = self::STANDARD_PARCEL;
		}

		return $parcel;
	}

	/**
	 * @param $order
	 * @param $service
	 * @param $data
	 * @param $method
	 *
	 * @return ApiRequestBuilder
	 */
	public function initializeAPIRequestBuilder( $order, $settings, $data, $method ): ApiRequestBuilder {
		$collectionLocker = null;
		if ( $method === 'L2L' || $method === 'L2D' ) {
			$collectionLocker = $data['pudo-source-locker'];
		}

		$shippingLocker = null;
		if ( $method === 'L2L' || $method === 'D2L' ) {
			$shippingLocker = $data['pudo-destination-locker'];
		}

		$shippingDetails   = $this->buildShippingDetails( $order, $shippingLocker );
		$collectionDetails = $this->buildCollectionDetails( $settings, $collectionLocker );
		$parcels           = $this->buildParcels( $data );

		return new ApiRequestBuilder( $shippingDetails, $collectionDetails, $method, $parcels );
	}
}
