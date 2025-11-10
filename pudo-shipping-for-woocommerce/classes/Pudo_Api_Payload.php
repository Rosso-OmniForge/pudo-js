<?php

namespace Pudo\WooCommerce;

/**
 *  Copyright: © 2025 The Courier Guy
 */
class Pudo_Api_Payload {


	public static $r1;
	public static $j;
	protected static $log;
	public $globalFactor = 50;

	private array $lengthFactors = array(
		'cm' => 10,
		'mm' => 1,
	);

	/**
	 * @param int $globalFactor
	 */
	public function set_global_factor( int $globalFactor ): void {
		$this->globalFactor = $globalFactor;
	}

	/**
	 * @param array $parameters
	 * @param array $items
	 *
	 * @return array
	 */
	public function getContentsPayload( $parameters, $items ) {
		$logging = $parameters['usemonolog'] === 'yes';
		if ( $logging && ! self::$log ) {
			self::$log = wc_get_logger();
		}

		$waybillDescriptionOverride = isset( $parameters['remove_waybill_description'] ) && $parameters['remove_waybill_description'] === 'yes';
		self::$r1                   = $r2 = array();

		/** Get the standard parcel sizes
		 * At least one must be set or default to standard size
		 */
		list($globalParcels, $defaultProduct, $globalFlyer) = $this->getGlobalParcels( $parameters );

		/**
		 * Get products per item and store for efficiency
		 */
		$all_items = $this->getAllItems( $items, $defaultProduct );
		unset( $items );

		/**
		 * Items that don't fit into any of the defined parcel sizes
		 * are each passed as a lumped item with their own dimension and mass
		 *
		 * Now check if there are items that don't fit into any box
		 */
		list($tooBigItems, $fittingItems, $fitsFlyer) = $this->getFittingItems(
			$all_items,
			$globalParcels,
			$globalFlyer
		);

		if ( empty( $fittingItems ) && ! $fitsFlyer ) {
			return array();
		}

		// Up to here we have three arrays of products - single pack items, too big items and fitting items. No longer need all_items
		unset( $all_items );

		// Handle the non-fitting items next
		// Single pack sizes
		self::$j = $this->fitToobigItems( $tooBigItems, $waybillDescriptionOverride, self::$j );
		unset( $tooBigItems );

		$this->poolIfPossible( $fittingItems );

		/** Now the fitting items
		 * We have to fit them into parcels
		 * The idea is to minimise the total number of parcels - cf Talent 2020-09-09
		 */
		$conLoad = new Pudo_Api_Content_Payload( $parameters, $fittingItems, $globalParcels, $logging, self::$log );

		$r2 = $conLoad->calculate_multi_fitting_items_advanced();

		unset( $fittingItems );

		foreach ( $r2 as $itemm ) {
			self::$r1[] = $itemm;
		}

		return self::$r1;
	}

	/**
	 * Get the standard parcel sizes
	 * At least one must be set or default to standard size
	 *
	 * @param $parameters
	 *
	 * @return array
	 */
	private function getGlobalParcels( $parameters ) {
		$globalParcells = $parameters['boxes'];

		// Get a default product size to use where dimensions are not configured
		$globalParcelCount = count( $globalParcells );
		if ( $globalParcelCount === 1 ) {
			$defaultProduct = $globalParcells[0];
		} elseif ( $globalParcelCount === 0 ) {
			$defaultProduct = null;
		} else {
			$defaultProduct = $globalParcells[1];
		}

		$globalFlyer = $globalParcells[0] ?? null;

		// Order the global parcels by largest dimension ascending order
		if ( count( $globalParcells ) > 1 ) {
			usort(
				$globalParcells,
				function ( $a, $b ) {
					if ( $a[0] === $b[0] ) {
						return 0;
					}

					return ( $a[0] < $b[0] ) ? -1 : 1;
				}
			);
		}

		return array(
			$globalParcells,
			$defaultProduct,
			$globalFlyer,
		);
	}

	private function getAllItems( $items, $defaultProduct ) {
		$all_itemms = array();
		foreach ( $items as $item ) {
			$itm               = array();
			$item_variation_id = isset( $item['variation_id'] ) ? $item['variation_id'] : 0;
			$item_product_id   = isset( $item['product_id'] ) ? $item['product_id'] : 0;
			$itm['single']     = false;
			if ( $item_variation_id !== 0 ) {
				$product = new \WC_Product_Variation( $item_variation_id );
			} else {
				$product = new \WC_Product( $item_product_id );
			}
			$a                         = wc_format_dimensions( array( 1, 0, 0 ) );
			$unit                      = trim( substr( $a, 1 ) );
			$itm['item']               = $item;
			$itm['product']            = $product;
			$itm['dimensions']         = array();
			$itm['dimensions']['mass'] = $product->has_weight() ? wc_get_weight( $product->get_weight(), 'kg' ) : 0.001;
			$itm['has_dimensions']     = true;
			$itm['toobig']             = false;
			if ( $product->has_dimensions() ) {
				$itm['dimensions']['height'] = (int) ( $product->get_height() * $this->lengthFactors[ $unit ] );
				$itm['dimensions']['width']  = (int) ( $product->get_width() * $this->lengthFactors[ $unit ] );
				$itm['dimensions']['length'] = (int) ( $product->get_length() * $this->lengthFactors[ $unit ] );
			} else {
				$itm['dimensions']['height'] = 1;
				$itm['dimensions']['width']  = 1;
				$itm['dimensions']['length'] = 1;
			}
			$itmdimensionsheight = $itm['dimensions']['height'];
			$itmdimensionswidth  = $itm['dimensions']['width'];
			$itmdimensionslength = $itm['dimensions']['length'];
			$itm['volume']       = 0;
			if ( $itmdimensionsheight != 0 && $itmdimensionswidth != 0 && $itmdimensionslength != 0 ) {
				$itm['volume'] = intval( $itmdimensionsheight ) * intval( $itmdimensionswidth ) * intval(
					$itmdimensionslength
				);
			}
			$itm['slug']                = get_post( $item['product_id'] )->post_title;
			$all_itemms[ $item['key'] ] = $itm;
		}

		return $all_itemms;
	}

	private function getFittingItems( $all_items, $globalParcels, $globalFlyer ) {
		$tooBigItems  = array();
		$fittingItems = array();
		$fitsFlyer    = true;
		foreach ( $all_items as $key => $item ) {
			$fits      = $this->doesFitGlobalParcels( $item, $globalParcels );
			$fitsFlyer = $fitsFlyer && $this->doesFitParcel( $item, $globalFlyer );
			if ( ! $fits['fits'] || $item['toobig'] ) {
				$fitsFlyer           = false;
				$tooBigItems[ $key ] = $item;
			} else {
				$fittingItems[ $key ] = array(
					'item'  => $item,
					'index' => $fits['fitsIndex'],
				);
			}
		}

		// Order the fitting items with the biggest dimension first
		usort(
			$fittingItems,
			function ( $a, $b ) {
				$itema         = $a['item'];
				$itemb         = $b['item'];
				$producta_size = max(
					(int) $itema['dimensions']['length'],
					(int) $itema['dimensions']['width'],
					(int) $itema['dimensions']['height']
				);
				$productb_size = max(
					(int) $itemb['dimensions']['length'],
					(int) $itemb['dimensions']['width'],
					(int) $itemb['dimensions']['height']
				);
				if ( $producta_size === $productb_size ) {
					return 0;
				}

				return ( $producta_size < $productb_size ) ? 1 : -1;
			}
		);

		$f = array();
		foreach ( $fittingItems as $fitting_item ) {
			$f[ $fitting_item['item']['item']['key'] ] = array(
				'item'  => $fitting_item['item'],
				'index' => $fitting_item['index'],
			);
		}
		$fittingItems = $f;
		unset( $f );

		return array(
			$tooBigItems,
			$fittingItems,
			$fitsFlyer,
		);
	}

	private function fitToobigItems( $tooBigItems, $waybillDescriptionOverride, $j ) {
		foreach ( $tooBigItems as $tooBigItem ) {
			++$j;
			$item = $tooBigItem;

			$slug                 = $item['slug'];
			$entry                = array();
			$entry['item']        = $j;
			$entry['description'] = ! $waybillDescriptionOverride ? $slug : 'Item';
			$entry['pieces']      = $item['item']['quantity'];

			$dim         = array();
			$dim['dim1'] = (int) $item['dimensions']['length'];
			$dim['dim2'] = (int) $item['dimensions']['width'];
			$dim['dim3'] = (int) $item['dimensions']['height'];
			sort( $dim );

			$entry['dim1']    = $dim[0];
			$entry['dim2']    = $dim[1];
			$entry['dim3']    = $dim[2];
			$entry['actmass'] = $item['dimensions']['mass'];

			self::$r1[] = $entry;
		}

		return $j;
	}

	private function array_flatten( $array ) {
		$flat = array();
		foreach ( $array as $key => $value ) {
			array_push( $flat, $key );
			foreach ( $value as $val ) {
				array_push( $flat, $val );
			}
		}

		return array_unique( $flat );
	}

	/**
	 * Will attempt to pool items of same dimensions to produce
	 * better packing calculations
	 *
	 * Parameters are passed by reference, so modified in the function
	 *
	 * @param $fittingItems
	 * @param $items
	 */
	private function poolIfPossible( &$fittingItems ) {
		$pools = array();

		$fittings = array_values( $fittingItems );
		$nfit     = count( $fittings );
		for ( $i = 0; $i < $nfit; $i++ ) {
			$flat = $this->array_flatten( $pools );
			if ( ! in_array( $i, $flat ) ) {
				$pools[ $i ] = array();
			}
			for ( $jj = $i + 1; $jj < $nfit; $jj++ ) {
				if ( $fittings[ $i ]['item']['volume'] != $fittings[ $jj ]['item']['volume'] ) {
					continue;
				}
				if (
					$fittings[ $i ]['item']['dimensions']['height'] != $fittings[ $jj ]['item']['dimensions']['height']
					&& $fittings[ $i ]['item']['dimensions']['width'] != $fittings[ $jj ]['item']['dimensions']['width']
				) {
					continue;
				}
				$flat = $this->array_flatten( $pools );
				if ( ! in_array( $jj, $flat ) ) {
					$pools[ $i ][] = $jj;
				}
			}
		}

		if ( count( $pools ) == count( $fittingItems ) ) {
			return;
		}

		$fitted = array();

		foreach ( $pools as $k => $fit ) {
			$key            = $fittings[ $k ]['item']['item']['key'];
			$grp_name       = $fittings[ $k ]['item']['slug'];
			$grp_quantity   = (float) $fittings[ $k ]['item']['item']['quantity'];
			$grp_mass       = $fittings[ $k ]['item']['dimensions']['mass'] * $grp_quantity;
			$grp_dimensions = $fittings[ $k ]['item']['dimensions'];
			foreach ( $fit as $item ) {
				$grp_name     .= '.';
				$grp_mass     += $fittings[ $item ]['item']['dimensions']['mass'] * (float) $fittings[ $item ]['item']['item']['quantity'];
				$grp_quantity += $fittings[ $item ]['item']['item']['quantity'];
			}
			$fitted[ $key ]                               = $fittings[ $k ];
			$fitted[ $key ]['item']['slug']               = $grp_name;
			$fitted[ $key ]['item']['dimensions']         = $grp_dimensions;
			$fitted[ $key ]['item']['dimensions']['mass'] = $grp_mass / $grp_quantity;
			$fitted[ $key ]['item']['item']['quantity']   = $grp_quantity;
		}

		$fittingItems = $fitted;
	}

	/**
	 * @param $item
	 * @param $globalParcels
	 *
	 * @return array
	 */
	private function doesFitGlobalParcels( $item, $globalParcels ) {
		$globalParcelIndex = 0;
		$fits              = array();
		foreach ( $globalParcels as $globalParcel ) {
			$fits = $this->doesFitParcel( $item, $globalParcel );
			if ( $fits ) {
				break;
			}
			++$globalParcelIndex;
		}

		return array(
			'fits'      => $fits,
			'fitsIndex' => $globalParcelIndex,
		);
	}

	/**
	 * @param $item
	 * @param $parcel
	 *
	 * @return bool
	 */
	private function doesFitParcel( $item, $parcel ) {
		// Parcel now has volume as element - need to drop before sorting
		$parcel = $parcel ?? array();
		unset( $parcel['volume'] );

		if ( ! $parcel ) {
			return false;
		}

		rsort( $parcel );
		if ( $item['has_dimensions'] ) {
			$productDims    = array();
			$productDims[0] = $item['dimensions']['length'];
			$productDims[1] = $item['dimensions']['width'];
			$productDims[2] = $item['dimensions']['height'];
			rsort( $productDims );
			$fits = false;
			if (
				$productDims[0] <= $parcel[0]
				&& $productDims[1] <= $parcel[1]
				&& $productDims[2] <= $parcel[2]
			) {
				$fits = true;
			}
		} else {
			$fits = true;
		}

		return $fits;
	}
}
