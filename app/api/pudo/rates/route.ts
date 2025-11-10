/**
 * Next.js API Route: Get Shipping Rates
 * POST /api/pudo/rates
 */

import { NextRequest, NextResponse } from 'next/server';
import { PudoClient } from '@/lib/pudo-client';
import { RequestBuilder } from '@/lib/request-builder';
import { getContentsPayload } from '@/lib/bin-packing';
import type { CartItem, ShippingMethod, DEFAULT_BOX_SIZES } from '@/lib/types';

interface RateRequestBody {
  items: CartItem[];
  method: ShippingMethod;
  collectionDetails: {
    terminalId?: string;
    streetAddress?: string;
    city?: string;
    postalCode?: string;
    province?: string;
  };
  deliveryDetails: {
    terminalId?: string;
    streetAddress?: string;
    city?: string;
    postalCode?: string;
    province?: string;
  };
  boxSizes?: typeof DEFAULT_BOX_SIZES;
}

export async function POST(request: NextRequest) {
  try {
    const body: RateRequestBody = await request.json();
    const { items, method, collectionDetails, deliveryDetails, boxSizes } = body;

    // Validate required fields
    if (!items || !method || !collectionDetails || !deliveryDetails) {
      return NextResponse.json(
        { error: 'Missing required fields' },
        { status: 400 }
      );
    }

    // Initialize Pudo client
    const apiKey = process.env.PUDO_API_KEY;
    const apiUrl = process.env.PUDO_API_URL;
    
    if (!apiKey) {
      return NextResponse.json(
        { error: 'Pudo API key not configured' },
        { status: 500 }
      );
    }

    const client = new PudoClient({
      apiKey,
      apiUrl,
      isDevelopment: process.env.NODE_ENV === 'development',
    });

    // Use default box sizes from types if not provided
    const { DEFAULT_BOX_SIZES } = await import('@/lib/types');
    const boxes = boxSizes || DEFAULT_BOX_SIZES;

    // Calculate bin packing to determine optimal box size
    const packingResult = getContentsPayload(items, boxes, false);

    if (!packingResult || packingResult.length === 0) {
      return NextResponse.json(
        { error: 'Unable to calculate shipping for these items' },
        { status: 400 }
      );
    }

    // Get the first fitted box (smallest that fits all items)
    const fittedBox = packingResult[0];
    
    // Build parcel from bin packing result
    const parcel = RequestBuilder.createParcel(
      fittedBox.dim1,
      fittedBox.dim2,
      fittedBox.dim3,
      fittedBox.actmass,
      'Cart items'
    );

    // Build request payload
    const requestBuilder = new RequestBuilder(
      method,
      collectionDetails,
      deliveryDetails,
      [parcel]
    );

    const payload = requestBuilder.buildRatesRequest();

    // Get rates from Pudo API
    const rates = await client.getRates(payload);

    return NextResponse.json({
      success: true,
      rates: rates.rates,
      parcel: {
        length: fittedBox.dim1,
        width: fittedBox.dim2,
        height: fittedBox.dim3,
        weight: fittedBox.actmass,
      },
    });
  } catch (error) {
    console.error('Rate calculation error:', error);
    return NextResponse.json(
      { 
        error: 'Failed to calculate shipping rates',
        details: error instanceof Error ? error.message : 'Unknown error'
      },
      { status: 500 }
    );
  }
}
