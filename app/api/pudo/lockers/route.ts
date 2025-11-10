/**
 * Next.js API Route: Get Lockers
 * GET /api/pudo/lockers
 */

import { NextRequest, NextResponse } from 'next/server';
import { PudoClient } from '@/lib/pudo-client';
import { getPudoConfig } from '@/lib/config';

export async function GET(request: NextRequest) {
  try {
    // Get optional query params
    const searchParams = request.nextUrl.searchParams;
    const forceRefresh = searchParams.get('refresh') === 'true';

    // Initialize Pudo client with validated config
    const config = getPudoConfig();
    const client = new PudoClient(config);

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
