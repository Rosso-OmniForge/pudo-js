/**
 * Next.js API Route: Download Waybill
 * GET /api/pudo/waybill/[bookingId]
 */

import { NextRequest, NextResponse } from 'next/server';
import { PudoClient } from '@/lib/pudo-client';

export async function GET(
  request: NextRequest,
  { params }: { params: { bookingId: string } }
) {
  try {
    const bookingId = parseInt(params.bookingId);

    if (isNaN(bookingId)) {
      return NextResponse.json(
        { error: 'Invalid booking ID' },
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

    // Download waybill
    const blob = await client.downloadWaybill(bookingId);

    // Return PDF
    return new NextResponse(blob, {
      headers: {
        'Content-Type': 'application/pdf',
        'Content-Disposition': `attachment; filename="waybill-${bookingId}.pdf"`,
      },
    });
  } catch (error) {
    console.error('Waybill download error:', error);
    return NextResponse.json(
      { 
        error: 'Failed to download waybill',
        details: error instanceof Error ? error.message : 'Unknown error'
      },
      { status: 500 }
    );
  }
}
