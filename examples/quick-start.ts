/**
 * Quick Start Example
 * Demonstrates how to use the Pudo shipping library
 */

import { PudoClient, RequestBuilder, getContentsPayload, DEFAULT_BOX_SIZES } from './lib';
import type { CartItem } from './lib/types';

async function quickStartExample() {
  console.log('🚀 Pudo Shipping Quick Start Example\n');

  // ============================================
  // 1. Initialize the Pudo Client
  // ============================================
  console.log('1. Initializing Pudo Client...');
  const client = new PudoClient({
    apiKey: process.env.PUDO_API_KEY || 'your-api-key',
    apiUrl: 'https://sandbox.api-pudo.co.za',
    isDevelopment: true,
  });
  console.log('✓ Client initialized\n');

  // ============================================
  // 2. Get All Available Lockers
  // ============================================
  console.log('2. Fetching available lockers...');
  try {
    const lockers = await client.getAllLockers();
    const lockerCodes = Object.keys(lockers);
    console.log(`✓ Found ${lockerCodes.length} lockers`);
    console.log(`   First 3: ${lockerCodes.slice(0, 3).join(', ')}\n`);
  } catch (error) {
    console.error('✗ Failed to fetch lockers:', error);
  }

  // ============================================
  // 3. Calculate Bin Packing for Cart Items
  // ============================================
  console.log('3. Calculating optimal box size...');
  
  const cartItems: CartItem[] = [
    {
      productId: 'PROD-001',
      name: 'Blue T-Shirt',
      quantity: 2,
      dimensions: {
        length: 30,  // cm
        width: 25,   // cm
        height: 2,   // cm
        weight: 0.3, // kg
      },
    },
    {
      productId: 'PROD-002',
      name: 'Running Shoes',
      quantity: 1,
      dimensions: {
        length: 35,
        width: 20,
        height: 12,
        weight: 0.8,
      },
    },
  ];

  const packingResult = getContentsPayload(cartItems, DEFAULT_BOX_SIZES, false);
  
  if (packingResult && packingResult.length > 0) {
    const box = packingResult[0];
    console.log('✓ Optimal box found:');
    console.log(`   Dimensions: ${box.dim1}×${box.dim2}×${box.dim3} cm`);
    console.log(`   Weight: ${box.actmass} kg\n`);
  } else {
    console.log('✗ Could not calculate box size\n');
    return;
  }

  // ============================================
  // 4. Get Shipping Rates
  // ============================================
  console.log('4. Calculating shipping rates...');

  const box = packingResult[0];
  const parcel = RequestBuilder.createParcel(
    box.dim1,
    box.dim2,
    box.dim3,
    box.actmass,
    'Cart items'
  );

  const requestBuilder = new RequestBuilder(
    'D2L', // Door to Locker
    {
      // Collection (your warehouse/store)
      streetAddress: '123 Main Street',
      city: 'Johannesburg',
      postalCode: '2000',
      province: 'Gauteng',
    },
    {
      // Delivery (customer's chosen locker)
      terminalId: 'CG54', // Sasol Rivonia locker
    },
    [parcel]
  );

  try {
    const ratePayload = requestBuilder.buildRatesRequest();
    const rateResponse = await client.getRates(ratePayload);
    
    console.log('✓ Available rates:');
    rateResponse.rates.forEach((rate) => {
      console.log(`   ${rate.service_level}: R${rate.total_price} (${rate.service_level_code})`);
    });
    console.log('');

    // ============================================
    // 5. Create a Booking (Optional)
    // ============================================
    if (rateResponse.rates.length > 0) {
      console.log('5. Creating shipment booking...');
      
      // Select the first (usually cheapest) rate
      const selectedRate = rateResponse.rates[0];
      
      // Build booking request with contact details
      const bookingBuilder = new RequestBuilder(
        'D2L',
        {
          streetAddress: '123 Main Street',
          city: 'Johannesburg',
          postalCode: '2000',
          province: 'Gauteng',
          name: 'Store Manager',
          email: 'manager@store.com',
          mobileNumber: '0821234567',
        },
        {
          terminalId: 'CG54',
          name: 'John Customer',
          email: 'john@example.com',
          mobileNumber: '0827654321',
        },
        [parcel]
      );

      const bookingPayload = bookingBuilder.buildBookingRequest(
        selectedRate.service_level_code
      );

      try {
        const booking = await client.createBooking(bookingPayload);
        console.log('✓ Booking created successfully:');
        console.log(`   Booking ID: ${booking.booking_id}`);
        console.log(`   Tracking: ${booking.tracking_number}`);
        console.log(`   Waybill: ${client.getWaybillUrl(booking.booking_id)}`);
        console.log(`   Label: ${client.getLabelUrl(booking.booking_id)}\n`);
      } catch (error) {
        console.error('✗ Failed to create booking:', error);
      }
    }
  } catch (error) {
    console.error('✗ Failed to get rates:', error);
  }

  console.log('✅ Quick start example completed!\n');
}

// Run the example
if (require.main === module) {
  quickStartExample().catch(console.error);
}

export default quickStartExample;
