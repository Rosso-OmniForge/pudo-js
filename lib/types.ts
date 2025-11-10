/**
 * Pudo Shipping for Next.js
 * TypeScript Type Definitions
 * 
 * Copyright: © 2025
 * Ported from WooCommerce Pudo Plugin
 */

export interface PudoConfig {
  apiKey: string;
  apiUrl?: string;
  isDevelopment?: boolean;
}

export interface ProductDimensions {
  length: number; // in cm
  width: number;  // in cm
  height: number; // in cm
  weight: number; // in kg
}

export interface CartItem {
  productId: string;
  name: string;
  quantity: number;
  dimensions: ProductDimensions;
}

export interface Address {
  type?: 'residential' | 'business';
  streetAddress: string;
  localArea?: string;
  suburb?: string;
  city: string;
  postalCode: string;
  zone?: string; // Use 'zone' to match PHP (province/state)
  country: string;
  company?: string;
  enteredAddress?: string;
}

export interface LockerAddress {
  terminalId: string; // Locker code e.g., "CG54"
}

export interface ContactDetails {
  name: string;
  email: string;
  mobileNumber: string;
}

export interface Parcel {
  submitted_length_cm: number;
  submitted_width_cm: number;
  submitted_height_cm: number;
  submitted_weight_kg: number;
  parcel_description: string;
}

export interface BoxSize {
  length: number;
  width: number;
  height: number;
  maxWeight: number;
  volume?: number;
}

export interface FittedBox extends BoxSize {
  weight: number;
}

export interface ProcessedItem {
  item: CartItem;
  dimensions: ProductDimensions;
  volume: number;
  slug: string;
  hasDimensions: boolean;
  tooBig: boolean;
  single: boolean;
}

export type ShippingMethod = 'L2L' | 'L2D' | 'D2L' | 'D2D';

export interface RateRequestPayload {
  collection_address: Address | LockerAddress;
  delivery_address: Address | LockerAddress;
  parcels: Parcel[];
}

export interface BookingRequestPayload extends RateRequestPayload {
  delivery_contact: ContactDetails;
  collection_contact: ContactDetails;
  service_level_code: string;
}

export interface PudoRate {
  service_level_code: string;
  service_level: string;
  total_price: number;
  currency: string;
}

export interface RateResponse {
  rates: PudoRate[];
}

export interface BookingResponse {
  booking_id: number;
  tracking_number: string;
  waybill_url?: string;
  label_url?: string;
}

export interface LockerOpeningHours {
  day: string;
  open_time: string;
  close_time: string;
}

export interface LockerBoxType {
  id: number;
  name: string;
  type: string;
  width: number;
  height: number;
  length: number;
  weight: number;
}

export interface Locker {
  code: string;
  name: string;
  latitude: string;
  longitude: string;
  openinghours: LockerOpeningHours[];
  address: string;
  type: {
    id: number;
    name: string;
  };
  place: {
    placeNumber: string;
    town: string;
    postalCode: string;
  };
  lstTypesBoxes: LockerBoxType[];
}

export interface LockerMap {
  [code: string]: Locker;
}

// Default locker code for fallback
export const DEFAULT_LOCKER_CODE = 'CG54';

// Default locker (Sasol Rivonia Uplifted) - used as fallback
export const DEFAULT_LOCKER: Locker = {
  code: 'CG54',
  name: 'Sasol Rivonia Uplifted',
  latitude: '-26.049703',
  longitude: '28.059084',
  openinghours: [
    { day: 'Monday', open_time: '08:00:00', close_time: '17:00:00' },
    { day: 'Tuesday', open_time: '08:00:00', close_time: '17:00:00' },
    { day: 'Wednesday', open_time: '08:00:00', close_time: '17:00:00' },
    { day: 'Thursday', open_time: '08:00:00', close_time: '17:00:00' },
    { day: 'Friday', open_time: '08:00:00', close_time: '17:00:00' },
    { day: 'Saturday', open_time: '08:00:00', close_time: '13:00:00' },
    { day: 'Sunday', open_time: '08:00:00', close_time: '13:00:00' },
    { day: 'Public Holidays', open_time: '08:00:00', close_time: '13:00:00' },
  ],
  address: '375 Rivonia Rd, Rivonia, Sandton, 2191, South Africa',
  type: {
    id: 2,
    name: 'Locker',
  },
  place: {
    placeNumber: '',
    town: 'Sandton',
    postalCode: '2191',
  },
  lstTypesBoxes: [
    { id: 6, name: 'V4-XS', type: '10', width: 17, height: 8, length: 60, weight: 2 },
    { id: 4, name: 'V4-S', type: '11', width: 41, height: 8, length: 60, weight: 5 },
    { id: 7, name: 'V4-M', type: '12', width: 41, height: 19, length: 60, weight: 10 },
    { id: 3, name: 'V4-L', type: '13', width: 41, height: 41, length: 60, weight: 15 },
    { id: 8, name: 'V4-XL', type: '14', width: 41, height: 69, length: 60, weight: 20 },
  ],
};

// Default box sizes (Pudo standard boxes in mm, converted to cm)
export const DEFAULT_BOX_SIZES: BoxSize[] = [
  { length: 60, width: 17, height: 8, maxWeight: 2, volume: 8160 },   // V4-XS (Flyer)
  { length: 60, width: 41, height: 8, maxWeight: 5, volume: 19680 },  // V4-S
  { length: 60, width: 41, height: 19, maxWeight: 10, volume: 46740 }, // V4-M
  { length: 60, width: 41, height: 41, maxWeight: 15, volume: 100860 }, // V4-L
  { length: 60, width: 41, height: 69, maxWeight: 20, volume: 169740 }, // V4-XL
];

export interface BinPackingResult {
  item: number;
  description: string;
  pieces: number;
  dim1: number;
  dim2: number;
  dim3: number;
  actmass: number;
  fitIndex?: number;
}
