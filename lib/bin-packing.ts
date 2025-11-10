/**
 * Pudo Bin Packing Algorithm
 * Ported from PHP WooCommerce Plugin
 * 
 * This complex algorithm determines the smallest box size needed
 * for a given set of cart items using advanced 3D bin-packing.
 */

import type {
  CartItem,
  BoxSize,
  ProcessedItem,
  BinPackingResult,
  DEFAULT_BOX_SIZES,
} from './types';

interface FittingItem {
  item: ProcessedItem;
  index: number;
}

interface FittingItemMap {
  [key: string]: FittingItem;
}

/**
 * Length conversion factors
 */
const LENGTH_FACTORS: { [unit: string]: number } = {
  cm: 10,
  mm: 1,
};

/**
 * Main function to calculate bin packing payload
 */
export function getContentsPayload(
  items: CartItem[],
  globalParcels: BoxSize[],
  waybillDescriptionOverride: boolean = false
): BinPackingResult[] {
  const r1: BinPackingResult[] = [];
  let j = 0;

  // Get default product size
  const [parcels, defaultProduct, globalFlyer] = getGlobalParcels(globalParcels);

  // Process all items
  const allItems = getAllItems(items);

  // Categorize items
  const [tooBigItems, fittingItems, fitsFlyer] = getFittingItems(
    allItems,
    parcels,
    globalFlyer
  );

  if (Object.keys(fittingItems).length === 0 && !fitsFlyer) {
    return [];
  }

  // Handle non-fitting items (too big for any box)
  j = fitTooBigItems(tooBigItems, waybillDescriptionOverride, j, r1);

  // Pool similar items for better efficiency
  const pooledItems = poolIfPossible(fittingItems);

  // Calculate fitting items with advanced algorithm
  const contentPayload = new ContentPayload(pooledItems, parcels);
  const r2 = contentPayload.calculateMultiFittingItemsAdvanced();

  return [...r1, ...r2];
}

/**
 * Process global parcels (box sizes)
 */
function getGlobalParcels(globalParcels: BoxSize[]): [BoxSize[], BoxSize | null, BoxSize | null] {
  const parcelCount = globalParcels.length;
  let defaultProduct: BoxSize | null = null;
  
  if (parcelCount === 1) {
    defaultProduct = globalParcels[0];
  } else if (parcelCount > 1) {
    defaultProduct = globalParcels[1];
  }

  const globalFlyer = globalParcels[0] ?? null;

  // Sort by largest dimension ascending
  if (globalParcels.length > 1) {
    globalParcels.sort((a, b) => a.length - b.length);
  }

  return [globalParcels, defaultProduct, globalFlyer];
}

/**
 * Convert cart items to processed items with dimensions
 */
function getAllItems(items: CartItem[]): ProcessedItem[] {
  return items.map((item) => {
    const dimensions = item.dimensions;
    const volume =
      dimensions.height * dimensions.width * dimensions.length;

    return {
      item,
      dimensions: {
        height: dimensions.height * 10, // Convert cm to mm
        width: dimensions.width * 10,
        length: dimensions.length * 10,
        weight: dimensions.weight,
      },
      volume,
      slug: item.name,
      hasDimensions: true,
      tooBig: false,
      single: false,
    };
  });
}

/**
 * Categorize items as fitting or too big
 */
function getFittingItems(
  allItems: ProcessedItem[],
  globalParcels: BoxSize[],
  globalFlyer: BoxSize | null
): [ProcessedItem[], FittingItemMap, boolean] {
  const tooBigItems: ProcessedItem[] = [];
  const fittingItems: FittingItemMap = {};
  let fitsFlyer = true;

  allItems.forEach((item, index) => {
    const fits = doesFitGlobalParcels(item, globalParcels);
    const itemFitsFlyer = globalFlyer ? doesFitParcel(item, globalFlyer) : false;
    fitsFlyer = fitsFlyer && itemFitsFlyer;

    if (!fits.fits || item.tooBig) {
      fitsFlyer = false;
      tooBigItems.push(item);
    } else {
      fittingItems[item.item.productId] = {
        item,
        index: fits.fitsIndex,
      };
    }
  });

  // Sort fitting items by largest dimension first
  const sortedFittingItems: FittingItemMap = {};
  Object.entries(fittingItems)
    .sort(([, a], [, b]) => {
      const sizeA = Math.max(
        a.item.dimensions.length,
        a.item.dimensions.width,
        a.item.dimensions.height
      );
      const sizeB = Math.max(
        b.item.dimensions.length,
        b.item.dimensions.width,
        b.item.dimensions.height
      );
      return sizeB - sizeA;
    })
    .forEach(([key, value]) => {
      sortedFittingItems[key] = value;
    });

  return [tooBigItems, sortedFittingItems, fitsFlyer];
}

/**
 * Handle items too big for standard boxes
 */
function fitTooBigItems(
  tooBigItems: ProcessedItem[],
  waybillDescriptionOverride: boolean,
  j: number,
  r1: BinPackingResult[]
): number {
  tooBigItems.forEach((item) => {
    j++;
    const dimensions = [
      item.dimensions.length,
      item.dimensions.width,
      item.dimensions.height,
    ].sort((a, b) => a - b);

    r1.push({
      item: j,
      description: !waybillDescriptionOverride ? item.slug : 'Item',
      pieces: item.item.quantity,
      dim1: dimensions[0] / 10, // Convert mm back to cm
      dim2: dimensions[1] / 10,
      dim3: dimensions[2] / 10,
      actmass: item.dimensions.weight,
    });
  });

  return j;
}

/**
 * Pool items with same dimensions for better packing
 */
function poolIfPossible(fittingItems: FittingItemMap): FittingItemMap {
  const pools: { [key: number]: number[] } = {};
  const fittings = Object.values(fittingItems);
  const nfit = fittings.length;

  // Build pools of similar items
  for (let i = 0; i < nfit; i++) {
    const flat = arrayFlatten(pools);
    if (!flat.includes(i)) {
      pools[i] = [];
    }

    for (let jj = i + 1; jj < nfit; jj++) {
      if (fittings[i].item.volume !== fittings[jj].item.volume) {
        continue;
      }
      if (
        fittings[i].item.dimensions.height !== fittings[jj].item.dimensions.height ||
        fittings[i].item.dimensions.width !== fittings[jj].item.dimensions.width
      ) {
        continue;
      }
      const flatNow = arrayFlatten(pools);
      if (!flatNow.includes(jj)) {
        pools[i].push(jj);
      }
    }
  }

  if (Object.keys(pools).length === Object.keys(fittingItems).length) {
    return fittingItems;
  }

  // Merge pooled items
  const fitted: FittingItemMap = {};
  Object.entries(pools).forEach(([k, fit]) => {
    const idx = parseInt(k);
    const key = fittings[idx].item.item.productId;
    let grpName = fittings[idx].item.slug;
    let grpQuantity = fittings[idx].item.item.quantity;
    let grpMass = fittings[idx].item.dimensions.weight * grpQuantity;
    const grpDimensions = { ...fittings[idx].item.dimensions };

    fit.forEach((itemIdx) => {
      grpName += '.';
      grpMass +=
        fittings[itemIdx].item.dimensions.weight *
        fittings[itemIdx].item.item.quantity;
      grpQuantity += fittings[itemIdx].item.item.quantity;
    });

    fitted[key] = {
      ...fittings[idx],
      item: {
        ...fittings[idx].item,
        slug: grpName,
        dimensions: {
          ...grpDimensions,
          weight: grpMass / grpQuantity,
        },
        item: {
          ...fittings[idx].item.item,
          quantity: grpQuantity,
        },
      },
    };
  });

  return fitted;
}

/**
 * Check if item fits in any global parcel
 */
function doesFitGlobalParcels(
  item: ProcessedItem,
  globalParcels: BoxSize[]
): { fits: boolean; fitsIndex: number } {
  let globalParcelIndex = 0;
  let fits = false;

  for (const globalParcel of globalParcels) {
    fits = doesFitParcel(item, globalParcel);
    if (fits) {
      break;
    }
    globalParcelIndex++;
  }

  return { fits, fitsIndex: globalParcelIndex };
}

/**
 * Check if item fits in a specific parcel
 */
function doesFitParcel(item: ProcessedItem, parcel: BoxSize): boolean {
  if (!parcel) return false;

  const parcelDims = [parcel.length * 10, parcel.width * 10, parcel.height * 10].sort(
    (a, b) => b - a
  );

  if (item.hasDimensions) {
    const productDims = [
      item.dimensions.length,
      item.dimensions.width,
      item.dimensions.height,
    ].sort((a, b) => b - a);

    return (
      productDims[0] <= parcelDims[0] &&
      productDims[1] <= parcelDims[1] &&
      productDims[2] <= parcelDims[2]
    );
  }

  return true;
}

/**
 * Flatten pools array
 */
function arrayFlatten(pools: { [key: number]: number[] }): number[] {
  const flat: number[] = [];
  Object.entries(pools).forEach(([key, value]) => {
    flat.push(parseInt(key));
    flat.push(...value);
  });
  return Array.from(new Set(flat));
}

/**
 * Content Payload Calculator - Advanced multi-item fitting
 */
class ContentPayload {
  private fittingItems: FittingItemMap;
  private globalParcels: BoxSize[];
  private j = 0;

  constructor(fittingItems: FittingItemMap, globalParcels: BoxSize[]) {
    this.fittingItems = fittingItems;
    this.globalParcels = globalParcels;
  }

  calculateMultiFittingItemsAdvanced(): BinPackingResult[] {
    const fits: { [key: number]: { [slug: string]: number } } = {};

    // Calculate how many of each item fits in each box
    Object.values(this.fittingItems).forEach((fittingItem) => {
      const pdims = [
        fittingItem.item.dimensions.length,
        fittingItem.item.dimensions.width,
        fittingItem.item.dimensions.height,
      ];

      this.globalParcels.forEach((parcel, k) => {
        if (!fits[k]) fits[k] = {};
        fits[k][fittingItem.item.slug] = getMaxPackingConfiguration(
          [parcel.length * 10, parcel.width * 10, parcel.height * 10],
          pdims
        );
      });
    });

    const tcgPackages: BinPackingResult[][] = [];

    // Try fitting with each box size
    Object.keys(fits).forEach((fitIndexStr) => {
      const fitIndex = parseInt(fitIndexStr);
      let remainingItems = { ...this.fittingItems };
      const results: BinPackingResult[] = [];
      let anyItemsLeft = true;

      while (anyItemsLeft) {
        const result = this.fitItemsInRealBoxes(remainingItems, fits, fitIndex);
        if (!result) break;

        const [r2, itemsLeft, remaining] = result;
        if (r2) {
          results.push(r2[0]);
        }
        anyItemsLeft = itemsLeft;
        remainingItems = remaining;
      }

      if (results.length === 1) {
        let boxIndex = results[0].fitIndex ?? fitIndex;
        while (
          results[0].actmass > this.globalParcels[boxIndex].maxWeight &&
          boxIndex < 4
        ) {
          boxIndex++;
        }
        results[0].fitIndex = boxIndex;
        tcgPackages.push(results);
      } else if (results.length > 0) {
        tcgPackages.push(results);
      }
    });

    // Sort by package count and volume
    tcgPackages.sort((a, b) => {
      if (a.length === b.length) {
        const aVol = a.reduce((sum, pkg) => sum + this.packVol(pkg), 0);
        const bVol = b.reduce((sum, pkg) => sum + this.packVol(pkg), 0);
        return aVol - bVol;
      }
      return a.length - b.length;
    });

    return tcgPackages[0] ?? [];
  }

  private packVol(pkg: BinPackingResult): number {
    return pkg.dim1 * pkg.dim2 * pkg.dim3;
  }

  private fitItemsInRealBoxes(
    items: FittingItemMap,
    fits: { [key: number]: { [slug: string]: number } },
    boxndx: number
  ): [BinPackingResult[], boolean, FittingItemMap] | null {
    const items1 = Object.values(items);
    this.j++;

    const entry: BinPackingResult = {
      item: this.j,
      description: 'Item',
      pieces: 1,
      dim1: 0,
      dim2: 0,
      dim3: 0,
      actmass: 0,
    };

    let boxKey: number | null = null;

    for (let key = 0; key < items1.length; key++) {
      const item = items1[key];
      if (item.item.item.quantity === 0) continue;

      const slug = item.item.slug;
      boxKey = boxKey ?? this.getBoxKey(fits, slug, item.item.item.quantity, boxndx);
      const box = this.globalParcels[boxKey];

      entry.description = item.item.slug;
      entry.dim1 = box.length;
      entry.dim2 = box.width;
      entry.dim3 = box.height;

      const pdims = [
        item.item.dimensions.length,
        item.item.dimensions.width,
        item.item.dimensions.height,
      ];
      const boxDims = [box.length * 10, box.width * 10, box.height * 10];
      const maxItems = getMaxPackingConfiguration(boxDims, pdims);

      if (maxItems === 0) return null;

      const nItemsToAdd = Math.min(maxItems, item.item.item.quantity);
      entry.actmass += nItemsToAdd * item.item.dimensions.weight;
      items1[key].item.item.quantity -= nItemsToAdd;

      // Calculate remaining virtual boxes
      const vboxes = getActualPackingConfigurationAdvanced(boxDims, pdims, nItemsToAdd);

      // Fit remaining items in virtual boxes
      vboxes.forEach((vbox) => {
        this.fitItemsInVbox(vbox, items1, entry);
      });

      break;
    }

    const r2: BinPackingResult[] = [entry];
    const itemsRemaining = items1.reduce(
      (sum, item) => sum + item.item.item.quantity,
      0
    );

    const remainingMap: FittingItemMap = {};
    items1.forEach((item) => {
      remainingMap[item.item.item.productId] = item;
    });

    return [r2, itemsRemaining > 0, remainingMap];
  }

  private fitItemsInVbox(
    vbox: number[],
    items: FittingItem[],
    entry: BinPackingResult
  ): void {
    for (let itemi = 0; itemi < items.length; itemi++) {
      const itemvb = items[itemi];
      if (itemvb.item.item.quantity === 0) continue;

      const pdims = [
        itemvb.item.dimensions.length,
        itemvb.item.dimensions.width,
        itemvb.item.dimensions.height,
      ];
      const maxItems = getMaxPackingConfiguration(vbox, pdims);

      if (maxItems === 0) continue;

      const nitems = Math.min(maxItems, itemvb.item.item.quantity);
      items[itemi].item.item.quantity -= nitems;
      entry.actmass += nitems * itemvb.item.dimensions.weight;

      const vboxes = getActualPackingConfigurationAdvanced(vbox, pdims, nitems);
      vboxes.forEach((vb) => {
        this.fitItemsInVbox(vb, items, entry);
      });

      break;
    }
  }

  private getBoxKey(
    fits: { [key: number]: { [slug: string]: number } },
    slug: string,
    itemCount: number,
    startIndex: number
  ): number {
    let fitsSlug = startIndex;
    Object.entries(fits).forEach(([key, fit]) => {
      const k = parseInt(key);
      if (k < startIndex) return;
      fitsSlug = k;
      if (fit[slug] >= itemCount) {
        return;
      }
    });
    return fitsSlug;
  }
}

/**
 * Calculate maximum items that can fit in a box with all rotations
 */
function getMaxPackingConfiguration(parcel: number[], pkg: number[]): number {
  const boxPermutations = [
    [0, 1, 2],
    [0, 2, 1],
    [1, 0, 2],
    [1, 2, 0],
    [2, 1, 0],
    [2, 0, 1],
  ];

  let maxItems = 0;
  boxPermutations.forEach((permutation) => {
    let boxItems = Math.floor(parcel[0] / pkg[permutation[0]]);
    boxItems *= Math.floor(parcel[1] / pkg[permutation[1]]);
    boxItems *= Math.floor(parcel[2] / pkg[permutation[2]]);
    maxItems = Math.max(maxItems, boxItems);
  });

  return maxItems;
}

/**
 * Calculate actual packing configuration and remaining virtual boxes
 */
function getActualPackingConfigurationAdvanced(
  parcel: number[],
  pkg: number[],
  count: number
): number[][] {
  const boxPermutations = [
    [0, 1, 2],
    [0, 2, 1],
    [1, 0, 2],
    [1, 2, 0],
    [2, 1, 0],
    [2, 0, 1],
  ];

  let usedHeight = parcel[2];
  const useds: number[][] = [];

  boxPermutations.forEach((permutation) => {
    const nl = Math.floor(parcel[0] / pkg[permutation[0]]);
    const nw = Math.floor(parcel[1] / pkg[permutation[1]]);
    const na = nl * nw;
    let h = 0;

    if (na !== 0) {
      h = Math.ceil(count / (nl * nw)) * pkg[permutation[2]];
      if (h <= usedHeight) {
        usedHeight = h;
      }
    }
    useds.push([
      nl * pkg[permutation[0]],
      nw * pkg[permutation[1]],
      h,
    ]);
  });

  let used: number[] = [];
  for (const u of useds) {
    if (u[2] === usedHeight) {
      used = u;
      break;
    }
  }

  const remainingBoxes: number[][] = [];

  // Virtual box 1
  const vb1 = [used[0], used[1], parcel[2] - used[2]].sort((a, b) => b - a);
  const vb1Vol = vb1[0] * vb1[1] * vb1[2];
  if (vb1Vol > 0) remainingBoxes.push(vb1);

  // Virtual box 2
  const vb2 = [parcel[0] - used[0], used[1], parcel[2]].sort((a, b) => b - a);
  const vb2Vol = vb2[0] * vb2[1] * vb2[2];
  if (vb2Vol > 0) remainingBoxes.push(vb2);

  // Virtual box 3
  const vb3 = [parcel[0], parcel[1] - used[1], parcel[2]].sort((a, b) => b - a);
  const vb3Vol = vb3[0] * vb3[1] * vb3[2];
  if (vb3Vol > 0) remainingBoxes.push(vb3);

  return remainingBoxes;
}
