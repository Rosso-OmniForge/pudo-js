/**
 * Pudo API Request Builder
 * Constructs properly formatted payloads for Pudo API requests
 * 
 * Copyright: © 2025
 * Ported from WooCommerce Pudo Plugin
 */

import type {
  Address,
  LockerAddress,
  ContactDetails,
  Parcel,
  RateRequestPayload,
  BookingRequestPayload,
  ShippingMethod,
} from './types';

export interface CollectionDetails {
  terminalId?: string;
  streetAddress?: string;
  localArea?: string;
  city?: string;
  postalCode?: string;
  zone?: string; // Province/state for the collection address
  country?: string;
  name?: string;
  email?: string;
  mobileNumber?: string;
  company?: string;
}

export interface DeliveryDetails {
  terminalId?: string;
  streetAddress?: string;
  localArea?: string;
  suburb?: string;
  city?: string;
  postalCode?: string;
  zone?: string; // Province/state for the delivery address
  country?: string;
  name?: string;
  email?: string;
  mobileNumber?: string;
  company?: string;
}

export class RequestBuilder {
  private method: ShippingMethod;
  private collectionDetails: CollectionDetails;
  private deliveryDetails: DeliveryDetails;
  private parcels: Parcel[];

  constructor(
    method: ShippingMethod,
    collectionDetails: CollectionDetails,
    deliveryDetails: DeliveryDetails,
    parcels: Parcel[]
  ) {
    this.method = method;
    this.collectionDetails = collectionDetails;
    this.deliveryDetails = deliveryDetails;
    this.parcels = parcels;
  }

  /**
   * Build payload for rate request
   * Note: API expects parcels as array containing a single parcel object
   */
  buildRatesRequest(): RateRequestPayload {
    return {
      collection_address: this.buildCollectionAddress(),
      delivery_address: this.buildDeliveryAddress(),
      parcels: [this.parcels[0]], // Wrap first parcel in array (API requirement)
    };
  }

  /**
   * Build payload for booking request
   * Note: API expects parcels as array containing a single parcel object
   */
  buildBookingRequest(serviceLevelCode: string): BookingRequestPayload {
    return {
      delivery_contact: this.buildDeliveryContact(),
      collection_contact: this.buildCollectionContact(),
      delivery_address: this.buildDeliveryAddress(),
      collection_address: this.buildCollectionAddress(),
      service_level_code: serviceLevelCode,
      parcels: [this.parcels[0]], // Wrap first parcel in array (API requirement)
    };
  }

  /**
   * Build collection address (locker or street)
   */
  private buildCollectionAddress(): Address | LockerAddress {
    if (this.method === 'L2L' || this.method === 'L2D') {
      // Locker to Locker or Locker to Door
      let terminalId = this.collectionDetails.terminalId || '';
      
      // Extract code if format is "CODE:Name"
      if (terminalId.includes(':')) {
        terminalId = terminalId.split(':')[0];
      }

      return { terminalId };
    }

    // Door to Locker or Door to Door
    const streetAddress = this.collectionDetails.streetAddress || '';
    const city = this.collectionDetails.city || '';
    const postalCode = this.collectionDetails.postalCode || '';
    const zone = this.collectionDetails.zone;
    
    // Determine address type based on company presence
    const isBusinessAddress = !!this.collectionDetails.company;
    
    const address: Address = {
      type: isBusinessAddress ? 'business' : 'residential',
      streetAddress,
      localArea: this.collectionDetails.localArea || city, // Fallback to city
      city,
      postalCode,
      zone,
      country: this.collectionDetails.country || 'South Africa',
      enteredAddress: `${streetAddress}, ${city}, ${postalCode}`,
    };

    // Only add company for business addresses
    if (isBusinessAddress) {
      address.company = this.collectionDetails.company;
    }

    return address;
  }

    /**
   * Build delivery address (locker or street)
   */
  private buildDeliveryAddress(): Address | LockerAddress {
    if (this.method === 'L2L' || this.method === 'D2L') {
      // Locker to Locker or Door to Locker
      let terminalId = this.deliveryDetails.terminalId || '';
      
      // Extract code if format is "CODE:Name"
      if (terminalId.includes(':')) {
        terminalId = terminalId.split(':')[0];
      }

      return { terminalId };
    }

    // Locker to Door or Door to Door
    const streetAddress = this.deliveryDetails.streetAddress || '';
    const city = this.deliveryDetails.city || '';
    const postalCode = this.deliveryDetails.postalCode || '';
    const zone = this.deliveryDetails.zone;
    
    // Determine address type based on company presence
    const isBusinessAddress = !!this.deliveryDetails.company;
    
    const address: Address = {
      type: isBusinessAddress ? 'business' : 'residential',
      streetAddress,
      localArea: this.deliveryDetails.localArea || city, // Fallback to city
      city,
      postalCode,
      zone,
      country: this.deliveryDetails.country || 'South Africa',
      enteredAddress: `${streetAddress}, ${city}, ${postalCode}`,
    };

    // Only add company for business addresses
    if (isBusinessAddress) {
      address.company = this.deliveryDetails.company;
    }

    return address;
  }

  /**
   * Build collection contact details
   */
  private buildCollectionContact(): ContactDetails {
    return {
      name: this.collectionDetails.name || '',
      email: this.collectionDetails.email || '',
      mobileNumber: this.collectionDetails.mobileNumber || '',
    };
  }

  /**
   * Build delivery contact details
   */
  private buildDeliveryContact(): ContactDetails {
    return {
      name: this.deliveryDetails.name || '',
      email: this.deliveryDetails.email || '',
      mobileNumber: this.deliveryDetails.mobileNumber || '',
    };
  }

  /**
   * Create standard parcel object
   */
  static createParcel(
    length: number,
    width: number,
    height: number,
    weight: number,
    description: string = 'Standard parcel'
  ): Parcel {
    return {
      submitted_length_cm: length,
      submitted_width_cm: width,
      submitted_height_cm: height,
      submitted_weight_kg: weight,
      parcel_description: description,
    };
  }

  /**
   * Create standard flyer parcel (smallest default)
   */
  static createFlyerParcel(): Parcel {
    return {
      submitted_length_cm: 1,
      submitted_width_cm: 1,
      submitted_height_cm: 1,
      submitted_weight_kg: 0.001,
      parcel_description: 'Standard flyer',
    };
  }
}
