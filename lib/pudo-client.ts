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
    const response = await this.callApi('POST', API_ENDPOINTS.GET_RATES, payload);
    return response as RateResponse;
  }

  /**
   * Create a shipment booking
   */
  async createBooking(payload: BookingRequestPayload): Promise<BookingResponse> {
    const response = await this.callApi('POST', API_ENDPOINTS.BOOKING_REQUEST, payload);
    return response as BookingResponse;
  }

  /**
   * Get all available Pudo lockers
   */
  async getAllLockers(forceRefresh: boolean = false): Promise<LockerMap> {
    // Return cached lockers if available
    if (this.lockers && !forceRefresh) {
      return this.lockers;
    }

    const response = await this.callApi('GET', API_ENDPOINTS.GET_ALL_LOCKERS);
    
    if (Array.isArray(response)) {
      this.lockers = this.mapLockers(response);
      return this.lockers;
    }

    throw new Error('Invalid response format from lockers endpoint');
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
        const errorText = await response.text();
        throw new Error(
          `Pudo API error (${response.status}): ${errorText}`
        );
      }

      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        return response.json();
      }

      return response.text();
    } catch (error) {
      if (error instanceof Error) {
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
