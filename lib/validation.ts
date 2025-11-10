/**
 * Zod validation schemas for Pudo API requests and responses
 * Ensures runtime type safety for all API interactions
 * 
 * Copyright: © 2025
 */

import { z } from 'zod';

/**
 * Address Schema
 */
export const AddressSchema = z.object({
  type: z.enum(['residential', 'business']),
  streetAddress: z.string().min(1, 'Street address is required'),
  localArea: z.string().optional(),
  city: z.string().min(1, 'City is required'),
  postalCode: z.string().min(1, 'Postal code is required'),
  zone: z.string().optional(),
  country: z.string().default('South Africa'),
  company: z.string().optional(),
  enteredAddress: z.string().optional(),
});

/**
 * Locker Address Schema
 */
export const LockerAddressSchema = z.object({
  terminalId: z.string().min(1, 'Terminal ID is required'),
});

/**
 * Contact Details Schema
 */
export const ContactDetailsSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  mobileNumber: z.string().regex(/^[0-9]{10,15}$/, 'Invalid mobile number format'),
  email: z.string().email('Invalid email address'),
});

/**
 * Parcel Dimensions Schema
 */
export const ParcelSchema = z.object({
  submitted_length_cm: z.number().int().positive('Length must be positive'),
  submitted_width_cm: z.number().int().positive('Width must be positive'),
  submitted_height_cm: z.number().int().positive('Height must be positive'),
  submitted_weight_kg: z.number().positive('Weight must be positive'),
});

/**
 * Rate Request Payload Schema
 */
export const RateRequestPayloadSchema = z.object({
  collection: z.union([AddressSchema, LockerAddressSchema]),
  collectionContact: ContactDetailsSchema,
  delivery: z.union([AddressSchema, LockerAddressSchema]),
  deliveryContact: ContactDetailsSchema,
  parcels: z.array(ParcelSchema).min(1, 'At least one parcel is required'),
});

/**
 * Booking Request Payload Schema
 */
export const BookingRequestPayloadSchema = z.object({
  collection: z.union([AddressSchema, LockerAddressSchema]),
  collectionContact: ContactDetailsSchema,
  delivery: z.union([AddressSchema, LockerAddressSchema]),
  deliveryContact: ContactDetailsSchema,
  parcels: z.array(ParcelSchema).min(1, 'At least one parcel is required'),
  service: z.string().min(1, 'Service type is required'),
  reference: z.string().optional(),
});

/**
 * Rate Response Item Schema (using snake_case from Pudo API)
 */
export const RateItemSchema = z.object({
  service_level_code: z.string(),
  service_level: z.string(),
  total_price: z.number().nonnegative(),
  currency: z.string().default('ZAR'),
});

/**
 * Rate Response Schema
 */
export const RateResponseSchema = z.object({
  rates: z.array(RateItemSchema),
});

/**
 * Booking Response Schema (using snake_case from Pudo API)
 */
export const BookingResponseSchema = z.object({
  booking_id: z.number().int().positive(),
  tracking_number: z.string(),
  waybill_url: z.string().url().optional(),
  label_url: z.string().url().optional(),
});

/**
 * Locker Box Type Schema
 */
export const BoxTypeSchema = z.object({
  length: z.number().int().positive(),
  width: z.number().int().positive(),
  height: z.number().int().positive(),
  maxWeight: z.number().positive(),
});

/**
 * Locker Schema
 */
export const LockerSchema = z.object({
  code: z.string().min(1, 'Locker code is required'),
  name: z.string().min(1, 'Locker name is required'),
  address: z.string().optional(),
  city: z.string().optional(),
  province: z.string().optional(),
  postalCode: z.string().optional(),
  latitude: z.number().optional(),
  longitude: z.number().optional(),
  boxTypes: z.array(BoxTypeSchema).optional(),
});

/**
 * Locker Map Schema
 */
export const LockerMapSchema = z.record(z.string(), LockerSchema);

/**
 * Cart Item Dimensions Schema
 */
export const CartItemDimensionsSchema = z.object({
  length: z.number().positive('Length must be positive'),
  width: z.number().positive('Width must be positive'),
  height: z.number().positive('Height must be positive'),
  weight: z.number().positive('Weight must be positive'),
});

/**
 * Cart Item Schema
 */
export const CartItemSchema = z.object({
  slug: z.string().min(1, 'Item slug is required'),
  dimensions: CartItemDimensionsSchema,
  item: z.object({
    quantity: z.number().int().positive('Quantity must be at least 1'),
  }),
});

/**
 * Pudo Config Schema
 */
export const PudoConfigSchema = z.object({
  apiKey: z.string().min(10, 'API key must be at least 10 characters'),
  apiUrl: z.string().url().optional(),
  isDevelopment: z.boolean().optional().default(false),
});

/**
 * Validation helper function
 */
export function validateData<T>(schema: z.ZodSchema<T>, data: unknown): T {
  try {
    return schema.parse(data);
  } catch (error) {
    if (error instanceof z.ZodError) {
      const messages = error.issues.map((issue) => `${issue.path.join('.')}: ${issue.message}`).join(', ');
      throw new Error(`Validation failed: ${messages}`);
    }
    throw error;
  }
}

/**
 * Safe validation that returns result object
 */
export function safeValidateData<T>(
  schema: z.ZodSchema<T>, 
  data: unknown
): { success: true; data: T } | { success: false; error: string } {
  const result = schema.safeParse(data);
  
  if (result.success) {
    return { success: true, data: result.data };
  }
  
  const messages = result.error.issues.map((issue) => `${issue.path.join('.')}: ${issue.message}`).join(', ');
  return { success: false, error: `Validation failed: ${messages}` };
}
