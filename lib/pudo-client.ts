/**
 * Pudo API Client for Next.js
 * Handles all communication with the Pudo API
 * 
 * Copyright: © 2025
 * Ported from WooCommerce Pudo Plugin
 */

import type {
  PudoConfig,
  RateRequestPayload,
  BookingRequestPayload,
  RateResponse,
  BookingResponse,
  LockerMap,
  Locker,
} from './types';
import {
  RateRequestPayloadSchema,
  BookingRequestPayloadSchema,
  RateResponseSchema,
  BookingResponseSchema,
  LockerMapSchema,
  validateData,
} from './validation';

const API_ENDPOINTS = {
  GET_ALL_LOCKERS: '/api/v1/lockers-data',
  GET_RATES: '/api/v1/locker-rates-new',
  BOOKING_REQUEST: '/api/v1/shipments',
  GET_WAYBILL: '/generate/waybill',
  GET_LABEL: '/generate/sticker',
} as const;

export class PudoClient {
  private apiKey: string;
  private apiUrl: string;
  private lockers: LockerMap | null = null;
  private lockersExpiry: number = 0;
  private static readonly CACHE_DURATION = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

  constructor(config: PudoConfig) {
    this.apiKey = config.apiKey;
    this.apiUrl = config.apiUrl || 
      (config.isDevelopment 
        ? 'https://sandbox.api-pudo.co.za' 
        : 'https://api-pudo.co.za');
  }

  /**
   * Get shipping rates for a given parcel configuration
   */
  async getRates(payload: RateRequestPayload): Promise<RateResponse> {
    // Validate request payload
    const validatedPayload = validateData(RateRequestPayloadSchema, payload);
    
    const response = await this.callApi('POST', API_ENDPOINTS.GET_RATES, validatedPayload);
    
    // Validate response
    return validateData(RateResponseSchema, response);
  }

  /**
   * Create a shipment booking
   */
  async createBooking(payload: BookingRequestPayload): Promise<BookingResponse> {
    // Validate request payload
    const validatedPayload = validateData(BookingRequestPayloadSchema, payload);
    
    const response = await this.callApi('POST', API_ENDPOINTS.BOOKING_REQUEST, validatedPayload);
    
    // Validate response
    return validateData(BookingResponseSchema, response);
  }

  /**
   * Get all available Pudo lockers with 24-hour cache
   */
  async getAllLockers(forceRefresh: boolean = false): Promise<LockerMap> {
    const now = Date.now();
    
    // Return cached lockers if available and not expired
    if (this.lockers && !forceRefresh && now < this.lockersExpiry) {
      return this.lockers;
    }

    try {
      const response = await this.callApi('GET', API_ENDPOINTS.GET_ALL_LOCKERS);
      
      if (Array.isArray(response)) {
        this.lockers = this.mapLockers(response);
        this.lockersExpiry = now + PudoClient.CACHE_DURATION; // Cache for 24 hours
        return this.lockers;
      }

      throw new Error('Invalid response format from lockers endpoint');
    } catch (error) {
      // If API call fails and we have cached data, return it even if expired
      if (this.lockers) {
        console.warn('Using stale locker cache due to API error:', error);
        return this.lockers;
      }
      throw error;
    }
  }

  /**
   * Get waybill PDF URL for a booking
   */
  getWaybillUrl(bookingId: number): string {
    return `${this.apiUrl}${API_ENDPOINTS.GET_WAYBILL}/${bookingId}?api_key=${this.apiKey}`;
  }

  /**
   * Get label PDF URL for a booking
   */
  getLabelUrl(bookingId: number): string {
    return `${this.apiUrl}${API_ENDPOINTS.GET_LABEL}/${bookingId}?api_key=${this.apiKey}`;
  }

  /**
   * Download waybill PDF
   */
  async downloadWaybill(bookingId: number): Promise<Blob> {
    const url = this.getWaybillUrl(bookingId);
    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
      },
    });

    if (!response.ok) {
      throw new Error(`Failed to download waybill: ${response.statusText}`);
    }

    return response.blob();
  }

  /**
   * Download label PDF
   */
  async downloadLabel(bookingId: number): Promise<Blob> {
    const url = this.getLabelUrl(bookingId);
    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
      },
    });

    if (!response.ok) {
      throw new Error(`Failed to download label: ${response.statusText}`);
    }

    return response.blob();
  }

  /**
   * Make API call to Pudo
   */
  private async callApi(
    method: 'GET' | 'POST',
    endpoint: string,
    body?: any
  ): Promise<any> {
    const url = `${this.apiUrl}${endpoint}`;
    
    const headers: HeadersInit = {
      'Authorization': `Bearer ${this.apiKey}`,
    };

    const options: RequestInit = {
      method,
      headers,
    };

    if (method === 'POST' && body) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }

    try {
      const response = await fetch(url, options);

      if (!response.ok) {
        // Try to extract detailed error from Pudo API response
        let errorMessage = `Pudo API error (${response.status})`;
        
        try {
          const errorData = await response.json();
          
          // Pudo API may return: { error: { message: "...", errors: [...] } }
          if (errorData.error) {
            if (errorData.error.message) {
              errorMessage = errorData.error.message;
            }
            
            // Include validation errors if present
            if (Array.isArray(errorData.error.errors) && errorData.error.errors.length > 0) {
              const errors = errorData.error.errors.join(', ');
              errorMessage = `${errorMessage}: ${errors}`;
            }
          } 
          // Or directly: { message: "..." }
          else if (errorData.message) {
            errorMessage = errorData.message;
          }
          // Or just a string error
          else if (typeof errorData === 'string') {
            errorMessage = errorData;
          }
        } catch {
          // If JSON parsing fails, try plain text
          try {
            const errorText = await response.text();
            if (errorText) {
              errorMessage = `${errorMessage}: ${errorText}`;
            }
          } catch {
            // Keep default error message if both fail
          }
        }
        
        throw new Error(errorMessage);
      }

      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        return response.json();
      }

      return response.text();
    } catch (error) {
      if (error instanceof Error) {
        // Re-throw API errors as-is, wrap network errors
        if (error.message.includes('Pudo API error')) {
          throw error;
        }
        throw new Error(`Pudo API request failed: ${error.message}`);
      }
      throw error;
    }
  }

  /**
   * Map locker array to locker map indexed by code
   */
  private mapLockers(lockers: Locker[]): LockerMap {
    const mapped: LockerMap = {};
    lockers.forEach((locker) => {
      mapped[locker.code] = locker;
    });
    return mapped;
  }

  /**
   * Get default locker code (for fallback)
   */
  static getDefaultLockerCode(): string {
    return 'CG54';
  }
}
