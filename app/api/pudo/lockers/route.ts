/**
 * Next.js API Route: Get Lockers
 * GET /api/pudo/lockers
 */

import { NextRequest, NextResponse } from 'next/server';
import { PudoClient } from '@/lib/pudo-client';

export async function GET(request: NextRequest) {
  try {
    // Get optional query params
    const searchParams = request.nextUrl.searchParams;
    const forceRefresh = searchParams.get('refresh') === 'true';

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

    // Get all lockers
    const lockers = await client.getAllLockers(forceRefresh);

    return NextResponse.json({
      success: true,
      lockers: Object.values(lockers),
      count: Object.keys(lockers).length,
    });
  } catch (error) {
    console.error('Lockers fetch error:', error);
    return NextResponse.json(
      { 
        error: 'Failed to fetch lockers',
        details: error instanceof Error ? error.message : 'Unknown error'
      },
      { status: 500 }
    );
  }
}
