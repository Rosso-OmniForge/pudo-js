/**
 * Main Library Export
 * Export all public APIs
 */

export { PudoClient } from './pudo-client';
export { RequestBuilder } from './request-builder';
export { getContentsPayload } from './bin-packing';
export { getPudoConfig, isPudoConfigured } from './config';

export type {
  PudoConfig,
  ProductDimensions,
  CartItem,
  Address,
  LockerAddress,
  ContactDetails,
  Parcel,
  BoxSize,
  FittedBox,
  ProcessedItem,
  ShippingMethod,
  RateRequestPayload,
  BookingRequestPayload,
  PudoRate,
  RateResponse,
  BookingResponse,
  LockerOpeningHours,
  LockerBoxType,
  Locker,
  LockerMap,
  BinPackingResult,
} from './types';

export { DEFAULT_BOX_SIZES, DEFAULT_LOCKER, DEFAULT_LOCKER_CODE } from './types';

// Export validation schemas and helpers
export {
  AddressSchema,
  LockerAddressSchema,
  ContactDetailsSchema,
  ParcelSchema,
  RateRequestPayloadSchema,
  BookingRequestPayloadSchema,
  RateResponseSchema,
  BookingResponseSchema,
  LockerSchema,
  LockerMapSchema,
  CartItemSchema,
  PudoConfigSchema,
  validateData,
  safeValidateData,
} from './validation';
