<?php

namespace Pudo\WooCommerce;

use Pudo\Common\Processor\APIProcessor;
use WP_Error;

/**
 *  Copyright: © 2025 The Courier Guy
 */
class PudoApi {


	/**
	 * @var array
	 */
	public array $lockers;
	private string $accountKey;
	private string $apiURL;

	private const DEFAULT_LOCKER = array(
		'CG54' => array(
			'code'          => 'CG54',
			'name'          => 'Sasol Rivonia Uplifted',
			'latitude'      => '-26.049703',
			'longitude'     => '28.059084',
			'openinghours'  => array(
				array(
					'day'        => 'Monday',
					'open_time'  => '08:00:00',
					'close_time' => '17:00:00',
				),
				array(
					'day'        => 'Tuesday',
					'open_time'  => '08:00:00',
					'close_time' => '17:00:00',
				),
				array(
					'day'        => 'Wednesday',
					'open_time'  => '08:00:00',
					'close_time' => '17:00:00',
				),
				array(
					'day'        => 'Thursday',
					'open_time'  => '08:00:00',
					'close_time' => '17:00:00',
				),
				array(
					'day'        => 'Friday',
					'open_time'  => '08:00:00',
					'close_time' => '17:00:00',
				),
				array(
					'day'        => 'Saturday',
					'open_time'  => '08:00:00',
					'close_time' => '13:00:00',
				),
				array(
					'day'        => 'Sunday',
					'open_time'  => '08:00:00',
					'close_time' => '13:00:00',
				),
				array(
					'day'        => 'Public Holidays',
					'open_time'  => '08:00:00',
					'close_time' => '13:00:00',
				),
			),
			'address'       => '375 Rivonia Rd, Rivonia, Sandton, 2191, South Africa',
			'type'          => array(
				'id'   => 2,
				'name' => 'Locker',
			),
			'place'         => array(
				'placeNumber' => '',
				'town'        => 'Sandton',
				'postalCode'  => '2191',
			),
			'lstTypesBoxes' => array(
				array(
					'id'     => 3,
					'name'   => 'V4-L',
					'type'   => '13',
					'width'  => 41,
					'height' => 41,
					'length' => 60,
					'weight' => 15,
				),
				array(
					'id'     => 4,
					'name'   => 'V4-S',
					'type'   => '11',
					'width'  => 41,
					'height' => 8,
					'length' => 60,
					'weight' => 5,
				),
				array(
					'id'     => 5,
					'name'   => 'V4-XS',
					'type'   => '10',
					'width'  => 17,
					'height' => 8,
					'length' => 60,
					'weight' => 2,
				),
				array(
					'id'     => 6,
					'name'   => 'V4-M',
					'type'   => '12',
					'width'  => 41,
					'height' => 19,
					'length' => 60,
					'weight' => 10,
				),
				array(
					'id'     => 7,
					'name'   => 'V4-XL',
					'type'   => '14',
					'width'  => 41,
					'height' => 69,
					'length' => 60,
					'weight' => 20,
				),
			),
		),
	);

	public function __construct() {
		$this->apiURL     = ( get_option( 'pudo_api_url' ) ) ? get_option(
			'pudo_api_url'
		) : 'https://sandbox.api-pudo.co.za';
		$this->accountKey = get_option( 'pudo_account_key' ) ?? '';
		try {
			$this->lockers = $this->getAllLockers();
		} catch ( \Exception $exception ) {
			die( $exception->getMessage() );
		}
	}

	/**
	 * Make async booking request
	 *
	 * @param string $data
	 *
	 * @return array|Exception|string|WP_Error
	 */
	public function bookingRequest( string $data ): WP_Error|Exception|array|string {
		return $this->callPudoApi( 'booking_request', $data );
	}

	/**
	 * @param string $data
	 *
	 * @return WP_Error|PromiseInterface|Exception|array|string|Response
	 */
	public function getRates( string $data ): WP_Error|PromiseInterface|Exception|array|string|Response {
		return $this->callPudoApi( 'get_rates', $data );
	}

	/**
	 * @param int $bookingID
	 *
	 * @return array|Exception|string|WP_Error
	 */
	public function getWaybill( int $bookingID ): WP_Error|Exception|array|string {
		$data = "/$bookingID?api_key=$this->accountKey";

		return $this->callPudoApi( 'get_waybill', $data );
	}

	/**
	 * @param int $bookingID
	 *
	 * @return array|Exception|string|WP_Error
	 */
	public function getLabel( int $bookingID ): WP_Error|Exception|array|string {
		$data = "/$bookingID?api_key=$this->accountKey";

		return $this->callPudoApi( 'get_label', $data );
	}

	/**
	 * @return array|Exception|string|WP_Error
	 */
	public function getLockerRates(): WP_Error|Exception|array|string {
		return $this->callPudoApi( 'checkout_rates' );
	}

	/**
	 * @return array
	 */
	public function getAllLockers(): array {
		$logger        = wc_get_logger();
		$context       = array( 'source' => 'pudo-for-wc' );
		$mappedLockers = get_transient( "pudo_Lockers_$this->apiURL" );
		if ( ! $mappedLockers ) {
			$mappedLockers = array();
			$response      = $this->callPudoApi( 'get_all_lockers' );
			if ( is_array( $response ) && ! empty( $response['response'] ) && $response['response']['code'] === 200 ) {
				if ( ! is_string( $response['body'] ) ) {
					$logger->error(
						( 'mapLockers: Input is not a string, received type: ' . gettype( $response['body'] ) ),
						$context
					);

					return $mappedLockers;
				}

				json_decode( $response['body'], true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$logger->error( 'mapLockers: JSON decoding failed: ' . json_last_error_msg(), $context );

					return $mappedLockers;
				}

				$mappedLockers = APIProcessor::mapLockers( $response['body'] );
				set_transient( "pudo_Lockers_$this->apiURL", $mappedLockers, 24 * 60 * 60 );
			} else {
				$mappedLockers = array();
			}
		}

		return $mappedLockers;
	}

	/**
	 * @param $transactionId
	 *
	 * @return WP_Error|Exception|array|string
	 */
	public function bookingConfirmation( $transactionId ): WP_Error|Exception|array|string {
		return $this->callPudoApi( 'bookingConfirmation', $transactionId );
	}

	/**
	 * @param string $method
	 * @param null   $content
	 *
	 * @return WP_Error|Exception|array|string
	 */
	private function callPudoApi( string $method, $content = null ): WP_Error|Exception|array|string {
		$apiProcessor = new APIProcessor( $this->apiURL );
		$request      = $apiProcessor->getRequest( $method, $content );
		$url          = $request['url'];
		$type         = $request['type'];

		$response = '';

		try {
			if ( $type === 'GET' ) {
				$response = wp_remote_get(
					$url,
					array(
						'headers' => array(
							'Authorization' => "Bearer $this->accountKey",
						),
						'timeout' => 30,
					)
				);
			} elseif ( $type === 'POST' ) {
				$response = wp_remote_post(
					$url,
					array(
						'headers'     => array(
							'Content-Type'  => 'application/json; charset=utf-8',
							'Authorization' => "Bearer $this->accountKey",
						),
						'body'        => $content,
						'method'      => 'POST',
						'data_format' => 'body',
						'timeout'     => 30,
					)
				);
			}
		} catch ( \Exception $exception ) {
			return new Exception( $exception->getMessage() );
		}

		return $response;
	}

	public function getDefaultLockerCode(): string {
		return current( self::DEFAULT_LOCKER )['code'];
	}

	public function getBoxInfo( $boxName ): array {
		$boxes = current( self::DEFAULT_LOCKER )['lstTypesBoxes'];
		foreach ( $boxes as $box ) {
			if ( $box['name'] === $boxName ) {
				return $box;
			}
		}

		return reset( $boxes );
	}
}
