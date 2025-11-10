<?php

namespace Pudo\WooCommerce;

/**
 *  Copyright: © 2025 The Courier Guy
 */
class Pudo_Api_Content_Payload {


	private $parameters;
	private $fittingItems;
	private $globalParcels;
	private $logging;
	private $log;

	private $r1;
	private $j;

	public function __construct( $parameters, $fittingItems, $globalParcels, $logging, $log ) {
		$this->parameters    = $parameters;
		$this->fittingItems  = $fittingItems;
		$this->globalParcels = $globalParcels;
		$this->logging       = $logging;
		$this->log           = $log;
		$this->r1            = Pudo_Api_Payload::$r1;
		$this->j             = Pudo_Api_Payload::$j;
	}

	/**
	 * @param $parcel
	 * @param $package
	 *
	 * @return mixed
	 */
	private static function getMaxPackingConfiguration(
		$parcel,
		$package
	) {
		$boxPermutations = array(
			array( 0, 1, 2 ),
			array( 0, 2, 1 ),
			array( 1, 0, 2 ),
			array( 1, 2, 0 ),
			array( 2, 1, 0 ),
			array( 2, 0, 1 ),
		);

		$maxItems = 0;
		foreach ( $boxPermutations as $permutation ) {
			$boxItems  = (int) ( $parcel[0] / $package[ $permutation[0] ] );
			$boxItems *= (int) ( $parcel[1] / $package[ $permutation[1] ] );
			$boxItems *= (int) ( $parcel[2] / $package[ $permutation[2] ] );
			$maxItems  = max( $maxItems, $boxItems );
		}

		return $maxItems;
	}

	private static function getActualPackingConfigurationAdvanced(
		$parcel,
		$package,
		$count
	) {
		$boxPermutations = array(
			array( 0, 1, 2 ),
			array( 0, 2, 1 ),
			array( 1, 0, 2 ),
			array( 1, 2, 0 ),
			array( 2, 1, 0 ),
			array( 2, 0, 1 ),
		);

		$usedHeight = $parcel[2];
		$useds      = array();
		foreach ( $boxPermutations as $permutation ) {
			$nl = (int) ( $parcel[0] / $package[ $permutation[0] ] );
			$nw = (int) ( $parcel[1] / $package[ $permutation[1] ] );
			$na = $nl * $nw;
			$h  = 0;
			if ( $na !== 0 ) {
				$h = ceil( $count / ( $nl * $nw ) ) * $package[ $permutation[2] ];
				if ( $h <= $usedHeight ) {
					$usedHeight = $h;
				}
			}
			$useds[] = array( $nl * $package[ $permutation[0] ], $nw * $package[ $permutation[1] ], $h );
		}

		$used = array();
		foreach ( $useds as $u ) {
			if ( $u[2] == $usedHeight ) {
				$used = $u;
				break;
			}
		}

		$remainingBoxes = array();

		$vb1 = array( $used[0], $used[1], $parcel[2] - $used[2] );
		rsort( $vb1 );
		$vb1['volume'] = $vb1[0] * $vb1[1] * $vb1[2];
		if ( $vb1['volume'] > 0 ) {
			$remainingBoxes[] = $vb1;
		}

		$vb2 = array( $parcel[0] - $used[0], $used[1], $parcel[2] );
		rsort( $vb2 );
		$vb2['volume'] = $vb2[0] * $vb2[1] * $vb2[2];
		if ( $vb2['volume'] > 0 ) {
			$remainingBoxes[] = $vb2;
		}

		$vb3 = array( $parcel[0], $parcel[1] - $used[1], $parcel[2] );
		rsort( $vb3 );
		$vb3['volume'] = $vb3[0] * $vb3[1] * $vb3[2];
		if ( $vb3['volume'] > 0 ) {
			$remainingBoxes[] = $vb3;
		}

		return $remainingBoxes;
	}

	public function calculate_single_fitting_items_packing( &$r1, &$j ) {
		$parameters_in    = $this->parameters;
		$fittingItems_in  = $this->fittingItems;
		$globalParcels_in = $this->globalParcels;

		$waybillDescriptionOverride = isset( $parameters_in['remove_waybill_description'] ) && $parameters_in['remove_waybill_description'] === 'yes';

		// Handle with existing code which works
		foreach ( $fittingItems_in as $fittingItem ) {
			$item     = $fittingItem['item'];
			$quantity = $item['item']['quantity'];
			// Fit them into boxes
			$pdims = array(
				$item['dimensions']['length'],
				$item['dimensions']['width'],
				$item['dimensions']['height'],
			);

			$nitems = $fittingItem['item']['item']['quantity'];

			// Calculate how many items will fit into each parcel
			$fits = array();
			foreach ( $globalParcels_in as $k => $global_parcel ) {
				$fits[ $k ] = self::getMaxPackingConfiguration( $global_parcel, $pdims );
			}
			asort( $fits );

			$bestFitIndex = 0;

			foreach ( $fits as $k => $fit ) {
				if ( $fit >= $nitems ) {
					$bestFitIndex = $k;
					break;
				}
			}

			$itemsPerBox = $fits[ $bestFitIndex ];

			if ( $itemsPerBox === 0 ) {
				$itemsPerBox = max( $fits );
			}

			$nboxes = (int) ceil( $nitems / $itemsPerBox );

			for ( $box = 1; $box <= $nboxes; $box++ ) {
				++$j;
				$entry                = array();
				$slug                 = $fittingItem['item']['slug'];
				$entry['item']        = $j;
				$entry['description'] = ! $waybillDescriptionOverride ? $slug : 'Item';
				if ( $quantity >= $itemsPerBox ) {
					$entry['actmass'] = $itemsPerBox * $item['dimensions']['mass'];
					$quantity        -= $itemsPerBox;
				} else {
					$entry['actmass'] = $quantity * $item['dimensions']['mass'];
				}
				$entry['pieces'] = 1; // Each box counts as one piece
				$entry['dim1']   = $globalParcels_in[ $bestFitIndex ][0];
				$entry['dim2']   = $globalParcels_in[ $bestFitIndex ][1];
				$entry['dim3']   = $globalParcels_in[ $bestFitIndex ][2];
				$r1[]            = $entry;
			}
		}
	}

	/**
	 * @return array|mixed
	 */
	public function calculate_multi_fitting_items_advanced(): ?array {
		$fittingItems_in  = $this->fittingItems;
		$globalParcels_in = $this->globalParcels;

		$fits = array();

		foreach ( $fittingItems_in as $fittingItem ) {
			$pdims = array(
				$fittingItem['item']['dimensions']['length'],
				$fittingItem['item']['dimensions']['width'],
				$fittingItem['item']['dimensions']['height'],
			);
			foreach ( $globalParcels_in as $k => $global_parcel ) {
				$fits[ $k ][ $fittingItem['item']['slug'] ] = self::getMaxPackingConfiguration( $global_parcel, $pdims );
			}

			$globalParcels_in = array_values( $globalParcels_in );
		}

		$tcgPackages = array();

		if ( count( $fittingItems_in ) === 1 ) {
			$fits = array_filter(
				$fits,
				function ( $a ) {
					return array_values( $a )[0] > 0;
				}
			);
		}

		foreach ( $fits as $fitIndex => $fit ) {
			$remainingItems = $this->fittingItems;
			$results        = array();
			$anyItemsLeft   = true;
			while ( $anyItemsLeft ) {
				list($r2, $anyItemsLeft, $remainingItems) = $this->fitItemsInRealBoxes(
					$remainingItems,
					$fits,
					(int) $fitIndex
				);
				if ( $r2 !== null ) {
					$r2[0]['fitIndex'] = $r2['fitIndex'];
					$results[]         = $r2[0];
				}
			}
			if ( count( $results ) === 1 ) {
				$boxIndex = $results[0]['fitIndex'];
				while ( $results[0]['actmass'] > $globalParcels_in[ $boxIndex ]['maxWeight'] ) {
					if ( ++$boxIndex > 4 ) {
						return array();
					}
				}
				$results[0]['fitIndex'] = $boxIndex;

				return $results;
			}
			$tcgPackages[ $fitIndex ] = $results;
		}

		usort(
			$tcgPackages,
			function ( $a, $b ) {
				if ( count( $a ) === count( $b ) ) {
					$avol = 0.0;
					foreach ( $a as $value ) {
						$avol += $this->packVol( $value );
					}
					$bvol = 0.0;
					foreach ( $b as $value ) {
						$bvol += $this->packVol( $value );
					}

					return $avol <=> $bvol;
				}

				return count( $a ) <=> count( $b );
			}
		);

		return $tcgPackages[0] ?? array();
	}

	/**
	 * @param array $package
	 *
	 * @return float
	 */
	private function packVol( array $package ): float {
		return (float) $package['dim1'] * (float) $package['dim2'] * (float) $package['dim3'];
	}

	/**
	 * @param $items
	 * @param $fits
	 * @param int   $boxndx
	 *
	 * @return array|null
	 */
	private function fitItemsInRealBoxes( $items, $fits, int $boxndx = 0 ): ?array {
		$items1 = array_values( $items );

		$parameters_in    = $this->parameters;
		$globalParcels_in = $this->globalParcels;

		foreach ( $fits as $fitKey => $fit ) {
			if ( (int) $fitKey < $boxndx ) {
				unset( $fits[ $fitKey ] );
			}
		}

		$waybillDescriptionOverride = isset( $parameters_in['remove_waybill_description'] ) && $parameters_in['remove_waybill_description'] === 'yes';

		$anyItemsLeft = true;
		$jj           = $this->j;
		$key          = 0;
		++$jj;
		$entry  = array();
		$boxKey = null;

		for ( $key = 0; $key < count( $items1 ); $key++ ) {
			$item = $items1[ $key ];
			if ( $item['item']['item']['quantity'] == 0 ) {
				continue;
			}
			$slug   = $item['item']['slug'];
			$boxKey = ! $boxKey ? $this->getBoxKey( $fits, $slug, $item['item']['item']['quantity'] ) : null;
			$box    = $globalParcels_in[ $boxKey ];

			$entry['item']        = $jj;
			$entry['description'] = ! $waybillDescriptionOverride ? $slug : 'Item';
			$entry['pieces']      = 1;
			$entry['dim1']        = $globalParcels_in[ $boxKey ][0];
			$entry['dim2']        = $globalParcels_in[ $boxKey ][1];
			$entry['dim3']        = $globalParcels_in[ $boxKey ][2];
			$entry['actmass']     = 0;

			// Calculate how many can be added
			$pdims    = array(
				$item['item']['dimensions']['length'],
				$item['item']['dimensions']['width'],
				$item['item']['dimensions']['height'],
			);
			$maxItems = self::getMaxPackingConfiguration( $box, $pdims );
			if ( $maxItems == 0 ) {
				return null;
			}
			$nItemsToAdd = min( $maxItems, $item['item']['item']['quantity'] );

			// Put nItemsToAdd into the box
			$entry['actmass']                           += $nItemsToAdd * $item['item']['dimensions']['mass'];
			$items1[ $key ]['item']['item']['quantity'] -= $nItemsToAdd;

			// Calculate the remaining boxes content
			$vboxes = self::getActualPackingConfigurationAdvanced(
				$box,
				$pdims,
				$nItemsToAdd
			);

			// These are now virtual boxes - maximum three
			for ( $vboxi = 0; $vboxi < count( $vboxes ); $vboxi++ ) {
				$this->fitItemsInVbox( $vboxes[ $vboxi ], $items1, $entry );
			}
			break;
		}
		$r2[]           = $entry;
		$r2['fitIndex'] = $boxKey;
		$itemsRemaining = 0;
		foreach ( $items1 as $item1 ) {
			$itemsRemaining += $item1['item']['item']['quantity'];
		}
		$anyItemsLeft = $itemsRemaining > 0;
		$this->j      = $jj;

		return array( $r2, $anyItemsLeft, array_values( $items1 ) );
	}


	private function fitItemsInVbox( $vbox, &$items1, &$entry ) {
		for ( $itemi = 0; $itemi < count( $items1 ); $itemi++ ) {
			$itemvb = $items1[ $itemi ];
			if ( $itemvb['item']['item']['quantity'] == 0 ) {
				continue;
			}

			// Calculate how many can be added
			$pdims    = array(
				$itemvb['item']['dimensions']['length'],
				$itemvb['item']['dimensions']['width'],
				$itemvb['item']['dimensions']['height'],
			);
			$maxItems = self::getMaxPackingConfiguration( $vbox, $pdims );
			if ( $maxItems == 0 ) {
				continue;
			}

			// Else put the items into this virtual box
			$nitems = min(
				$maxItems,
				$itemvb['item']['item']['quantity']
			);

			$items1[ $itemi ]['item']['item']['quantity'] -= $nitems;
			$entry['actmass']                             += $nitems * $itemvb['item']['dimensions']['mass'];

			// Calculate the remaining vboxes content
			$vboxes = self::getActualPackingConfigurationAdvanced(
				$vbox,
				$pdims,
				$nitems
			);

			for ( $vbi = 0; $vbi < count( $vboxes ); $vbi++ ) {
				$this->fitItemsInVbox( $vboxes[ $vbi ], $items1, $entry );
			}
			break;
		}
	}

	private function getBoxKey(
		$fits,
		$slug,
		$itemCount
	) {
		$fitsSlug = 0;
		foreach ( $fits as $key => $fit ) {
			$fitsSlug = $key;
			if ( $fit[ $slug ] >= $itemCount ) {
				break;
			}
		}

		return $fitsSlug;
	}
}
