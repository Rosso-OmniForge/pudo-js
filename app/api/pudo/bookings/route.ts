/**
 * Next.js API Route: Create Booking
 * POST /api/pudo/bookings
 */

import { NextRequest, NextResponse } from 'next/server';
import { PudoClient } from '@/lib/pudo-client';
import { RequestBuilder } from '@/lib/request-builder';
import type { ShippingMethod } from '@/lib/types';

interface BookingRequestBody {
  method: ShippingMethod;
  serviceLevelCode: string;
  collectionDetails: {
    terminalId?: string;
    streetAddress?: string;
    city?: string;
    postalCode?: string;
    province?: string;
    name: string;
    email: string;
    mobileNumber: string;
    company?: string;
  };
  deliveryDetails: {
    terminalId?: string;
    streetAddress?: string;
    city?: string;
    postalCode?: string;
    province?: string;
    name: string;
    email: string;
    mobileNumber: string;
    company?: string;
  };
  parcel: {
    length: number;
    width: number;
    height: number;
    weight: number;
  };
}

export async function POST(request: NextRequest) {
  try {
    const body: BookingRequestBody = await request.json();
    const { method, serviceLevelCode, collectionDetails, deliveryDetails, parcel } = body;

    // Validate required fields
    if (!method || !serviceLevelCode || !collectionDetails || !deliveryDetails || !parcel) {
      return NextResponse.json(
        { error: 'Missing required fields' },
        { status: 400 }
      );
    }

    // Validate contact details
    if (!collectionDetails.name || !collectionDetails.email || !collectionDetails.mobileNumber) {
      return NextResponse.json(
        { error: 'Missing collection contact details' },
        { status: 400 }
      );
    }

    if (!deliveryDetails.name || !deliveryDetails.email || !deliveryDetails.mobileNumber) {
      return NextResponse.json(
        { error: 'Missing delivery contact details' },
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

    // Build parcel
    const parcelData = RequestBuilder.createParcel(
      parcel.length,
      parcel.width,
      parcel.height,
      parcel.weight,
      'Shipment'
    );

    // Build request payload
    const requestBuilder = new RequestBuilder(
      method,
      collectionDetails,
      deliveryDetails,
      [parcelData]
    );

    const payload = requestBuilder.buildBookingRequest(serviceLevelCode);

    // Create booking
    const booking = await client.createBooking(payload);

    return NextResponse.json({
      success: true,
      booking: {
        bookingId: booking.booking_id,
        trackingNumber: booking.tracking_number,
        waybillUrl: client.getWaybillUrl(booking.booking_id),
        labelUrl: client.getLabelUrl(booking.booking_id),
      },
    });
  } catch (error) {
    console.error('Booking creation error:', error);
    return NextResponse.json(
      { 
        error: 'Failed to create booking',
        details: error instanceof Error ? error.message : 'Unknown error'
      },
      { status: 500 }
    );
  }
}
