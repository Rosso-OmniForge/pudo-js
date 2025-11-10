# Pudo Shipping Usage Guide

## Installation & Setup

### 1. Prerequisites

- Node.js 18+ installed
- Next.js 14+ project
- Pudo API credentials from The Courier Guy
- Product dimensions configured in your system

### 2. Quick Install

```bash
# Install dependencies
npm install

# Set up environment variables
# Create .env.local file with:
PUDO_API_KEY=your_actual_key_here
PUDO_API_URL=https://sandbox.api-pudo.co.za
NODE_ENV=development
```

**Note:** The `PUDO_API_KEY` must be at least 10 characters. The configuration is validated on startup using the `getPudoConfig()` utility.

### 3. Verify Setup

```bash
# Start development server
npm run dev

# Test the lockers endpoint
curl http://localhost:3000/api/pudo/lockers
```

---

## Common Use Cases

### Use Case 1: E-commerce Checkout - Calculate Shipping

**Scenario:** Customer is checking out and needs shipping cost

```typescript
// In your checkout page or API
const response = await fetch('/api/pudo/rates', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    items: cart.items.map(item => ({
      productId: item.id,
      name: item.name,
      quantity: item.quantity,
      dimensions: {
        length: item.length,    // in cm or mm (specify unit)
        width: item.width,
        height: item.height,
        weight: item.weight     // in kg
      }
    })),
    unit: 'cm',  // Important: specify 'cm' or 'mm'
    method: selectedShippingMethod, // 'D2L', 'L2L', etc.
    collectionDetails: {
      streetAddress: storeAddress.street,
      city: storeAddress.city,
      postalCode: storeAddress.postalCode,
      zone: storeAddress.province,  // Required for door addresses
      name: storeAddress.contactName,
      email: storeAddress.email,
      mobileNumber: storeAddress.phone,
      company: storeAddress.businessName  // Optional: sets address type to 'business'
    },
    deliveryDetails: {
      terminalId: selectedLocker.code, // if locker delivery (e.g., 'CG54')
      // OR for door delivery:
      // streetAddress: customerAddress.street,
      // city: customerAddress.city,
      // postalCode: customerAddress.postalCode,
      // zone: customerAddress.province,
      name: customer.name,
      email: customer.email,
      mobileNumber: customer.phone
    }
  })
});

const { rates, parcel } = await response.json();

// Display rates to customer
rates.forEach(rate => {
  console.log(`${rate.service_level}: R${rate.total_price}`);
});
```

### Use Case 2: Order Fulfillment - Create Shipment

**Scenario:** Admin processes order and creates Pudo shipment

```typescript
async function createShipmentForOrder(orderId: string) {
  // Get order details from your database
  const order = await getOrder(orderId);
  
  const response = await fetch('/api/pudo/bookings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      method: order.shippingMethod,
      serviceLevelCode: order.selectedServiceLevel,
      collectionDetails: {
        streetAddress: process.env.WAREHOUSE_ADDRESS,
        city: process.env.WAREHOUSE_CITY,
        postalCode: process.env.WAREHOUSE_POSTAL_CODE,
        name: process.env.COMPANY_NAME,
        email: process.env.COMPANY_EMAIL,
        mobileNumber: process.env.COMPANY_PHONE
      },
      deliveryDetails: {
        terminalId: order.pudoLockerCode,
        name: order.customerName,
        email: order.customerEmail,
        mobileNumber: order.customerPhone
      },
      parcel: order.calculatedParcel
    })
  });
  
  const { booking } = await response.json();
  
  // Save to order
  await updateOrder(orderId, {
    pudoBookingId: booking.bookingId,
    pudoTrackingNumber: booking.trackingNumber,
    waybillUrl: booking.waybillUrl,
    labelUrl: booking.labelUrl
  });
  
  return booking;
}
```

### Use Case 3: Display Locker Map to Customer

**Scenario:** Customer selects delivery locker from map

```typescript
// In your React component
import { useEffect, useState } from 'react';

function LockerSelector({ onLockerSelect }) {
  const [lockers, setLockers] = useState([]);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    fetch('/api/pudo/lockers')
      .then(res => res.json())
      .then(data => {
        setLockers(data.lockers);
        setLoading(false);
      });
  }, []);
  
  if (loading) return <div>Loading lockers...</div>;
  
  return (
    <div className="locker-map">
      <h3>Select a Pudo Locker</h3>
      <div className="locker-list">
        {lockers.map(locker => (
          <div 
            key={locker.code}
            className="locker-item"
            onClick={() => onLockerSelect(locker)}
          >
            <strong>{locker.name}</strong>
            <p>{locker.address}</p>
            <small>{locker.code}</small>
          </div>
        ))}
      </div>
    </div>
  );
}
```

---

## Advanced Usage

### Custom Box Sizes

If you have custom packaging, define your own box sizes:

```typescript
const customBoxes = [
  { length: 40, width: 30, height: 20, maxWeight: 5, volume: 24000 },
  { length: 50, width: 40, height: 30, maxWeight: 10, volume: 60000 },
  { length: 60, width: 50, height: 40, maxWeight: 15, volume: 120000 },
];

const response = await fetch('/api/pudo/rates', {
  method: 'POST',
  body: JSON.stringify({
    items: cartItems,
    method: 'D2L',
    collectionDetails: {...},
    deliveryDetails: {...},
    boxSizes: customBoxes  // Use custom boxes
  })
});
```

### Server-Side Direct Usage

Use the library directly in API routes or server components:

```typescript
// app/api/my-custom-route/route.ts
import { PudoClient, RequestBuilder, getPudoConfig } from '@/lib';

export async function POST(request: Request) {
  // Use validated config
  const config = getPudoConfig();
  const client = new PudoClient(config);
  
  // Your custom logic here
  const rates = await client.getRates(payload);
  
  return Response.json({ rates });
}
```

**Note:** Always use `getPudoConfig()` instead of directly accessing `process.env` for proper validation.

### Error Handling

The library now includes comprehensive error handling with detailed API error messages:

```typescript
try {
  const response = await fetch('/api/pudo/rates', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.details || error.error);
  }
  
  const data = await response.json();
  // Process data
  
} catch (error) {
  if (error instanceof Error) {
    console.error('Pudo error:', error.message);
    // Error messages now include specific details from Pudo API
    // Examples:
    // - "Invalid postal code format"
    // - "Locker terminal ID not found"
    // - "Items exceed maximum weight for selected box"
    alert('Unable to calculate shipping. Please try again.');
  }
}
```

### Input Validation

All requests and responses are validated using Zod schemas:

```typescript
import { validateData, RateRequestPayloadSchema } from '@/lib';

try {
  // Validate your data before sending
  const validatedPayload = validateData(RateRequestPayloadSchema, payload);
  const response = await fetch('/api/pudo/rates', {
    method: 'POST',
    body: JSON.stringify(validatedPayload)
  });
} catch (error) {
  // Validation errors will be detailed:
  // "Validation failed: collectionDetails.postalCode: Postal code is required"
  console.error(error.message);
}
```

---

## Environment-Specific Configuration

### Development (Sandbox)

```env
PUDO_API_KEY=sandbox_test_key
PUDO_API_URL=https://sandbox.api-pudo.co.za
NODE_ENV=development
```

### Production

```env
PUDO_API_KEY=live_production_key
PUDO_API_URL=https://api-pudo.co.za
NODE_ENV=production
```

---

## Troubleshooting

### Issue: "Unable to calculate shipping for these items"

**Cause:** Items don't fit in any available box size  
**Solution:** 
- Verify product dimensions are correct (use `unit: 'cm'` or `unit: 'mm'`)
- Check if items are too large for Pudo boxes (max 60×41×69 cm)
- Verify item weight doesn't exceed box maxWeight
- Check console for detailed bin-packing errors
- Consider splitting large orders

### Issue: "Pudo API key not configured" or "API key must be at least 10 characters"

**Cause:** Environment variable not set or invalid  
**Solution:**
```bash
# Check .env.local exists
cat .env.local

# Verify variable is set and valid (min 10 chars)
echo $PUDO_API_KEY

# Restart dev server to load new env variables
npm run dev
```

### Issue: Rate calculation returns empty or "Validation failed"

**Cause:** Invalid address, locker code, or missing required fields  
**Solution:**
- Verify locker code exists: `curl localhost:3000/api/pudo/lockers`
- Ensure all required fields are present:
  - For door addresses: `streetAddress`, `city`, `postalCode`, `zone`
  - For locker addresses: `terminalId`
  - Contact details: `name`, `email`, `mobileNumber` (10-15 digits)
- Check validation error message for specific missing fields
- Ensure postal codes are valid South African codes
- Verify `zone` field is included (replaces deprecated `province` field)

### Issue: "Missing local_area" or address validation errors

**Cause:** Required address fields not provided  
**Solution:**
- The library now auto-fills `localArea` with `city` if not provided
- Always include `zone` field (province/state)
- For business addresses, include `company` field (sets type to 'business')
- Format: `{ streetAddress, city, postalCode, zone, country }`

### Issue: TypeScript errors

**Cause:** Missing type definitions  
**Solution:**
```bash
npm install --save-dev @types/node
npm run type-check
```

### Issue: Incorrect shipping costs

**Cause:** Dimension conversion or bin-packing issues  
**Solution:**
- Always specify `unit: 'cm'` or `unit: 'mm'` in your request
- Verify all dimensions are positive numbers
- Check that weight is in kilograms (kg)
- All dimensions are automatically rounded to integers
- Compare with PHP WooCommerce plugin output for validation

---

## Performance Tips

### 1. Cache Lockers Data (Built-in)

The PudoClient now includes automatic 24-hour caching for locker data:

```typescript
// Lockers are automatically cached for 24 hours
const response = await fetch('/api/pudo/lockers');
const { lockers } = await response.json();

// Force refresh cache if needed
const freshData = await fetch('/api/pudo/lockers?refresh=true');
```

**Cache behavior:**
- Cached for 24 hours (86,400,000ms)
- Stale-while-revalidate on API errors
- Automatic expiry checking
- In-memory storage (per server instance)

### 2. Debounce Rate Calculations

```typescript
import { debounce } from 'lodash';

const calculateRates = debounce(async (items) => {
  // Rate calculation logic
}, 500); // Wait 500ms after last change
```

### 3. Pre-calculate Common Routes

If you ship from a single warehouse to common lockers, pre-calculate and cache rates.

---

## Security Best Practices

1. **Never expose API key to frontend**
   ```typescript
   // DON'T DO THIS
   const client = new PudoClient({ apiKey: 'abc123' }); // In frontend code
   
   // DO THIS
   // Keep PudoClient calls in API routes only
   // Use getPudoConfig() for validated configuration
   ```

2. **Validate input data (Built-in)**
   ```typescript
   // Automatic validation with Zod schemas
   import { validateData, RateRequestPayloadSchema } from '@/lib';
   
   // In API route
   const validatedPayload = validateData(RateRequestPayloadSchema, requestBody);
   // Throws detailed error if validation fails
   ```

3. **Use type-safe interfaces**
   ```typescript
   import type { CollectionDetails, DeliveryDetails } from '@/lib';
   
   const collection: CollectionDetails = {
     streetAddress: '123 Main St',
     city: 'Cape Town',
     postalCode: '8001',
     zone: 'Western Cape',  // Required for door addresses
     name: 'John Doe',
     email: 'john@example.com',
     mobileNumber: '0821234567'
   };
   ```

4. **Rate limiting**
   ```typescript
   // Consider adding rate limiting to prevent abuse
   import rateLimit from 'express-rate-limit';
   ```

---

## Migration from WooCommerce

If you're migrating from the WooCommerce plugin:

1. **Export settings:** Note your API key, sender details
2. **Product data:** Export product dimensions from WooCommerce
3. **Test thoroughly:** Sandbox API first
4. **Parallel run:** Keep WooCommerce running while testing
5. **Verify calculations:** Compare shipping costs between systems

---

## Support Resources

- **Pudo API Documentation:** Contact The Courier Guy
- **This Repository:** Open issues for bugs
- **ERPNext Integration:** See ERPNEXT_INTEGRATION.md
- **Examples:** Check `/examples` folder

---

## Quick Reference

### Shipping Methods
- `L2L` - Locker to Locker
- `D2L` - Door to Locker (most common)
- `L2D` - Locker to Door
- `D2D` - Door to Door

### API Endpoints
- `POST /api/pudo/rates` - Calculate rates
- `POST /api/pudo/bookings` - Create booking
- `GET /api/pudo/lockers` - List lockers
- `GET /api/pudo/waybill/[id]` - Download waybill
- `GET /api/pudo/label/[id]` - Download label

### Default Box Sizes (cm)
- V4-XS: 60×17×8 (2kg)
- V4-S: 60×41×8 (5kg)
- V4-M: 60×41×19 (10kg)
- V4-L: 60×41×41 (15kg)
- V4-XL: 60×41×69 (20kg)

---

## Recent Updates

### Version 1.0.0 - Critical Fixes Applied

**All CRITICAL and HIGH priority issues resolved:**

1. **Dimension Handling:** Added unit parameter support ('cm' or 'mm'), automatic integer rounding
2. **Weight Validation:** Box maxWeight constraints now enforced
3. **API Payload Structure:** Fixed parcels array wrapping for Pudo API
4. **Address Validation:** Added zone field, local_area fallback, business type support
5. **Error Handling:** Detailed API error extraction and reporting
6. **Caching:** 24-hour locker cache with stale-while-revalidate
7. **Runtime Validation:** Zod schemas for all requests and responses
8. **Configuration:** Centralized config validation with descriptive errors

**Breaking Changes:**
- `province` field replaced with `zone` (update your address objects)
- Dimension `unit` parameter now required for `getContentsPayload()`
- All API methods now validate input/output with Zod schemas

**Migration Guide:**
- Replace `province: 'Western Cape'` with `zone: 'Western Cape'`
- Add `unit: 'cm'` to rate/booking requests
- Update address objects to include `type`, `zone`, and optional `company` fields

For detailed changelog, see AUDIT_REPORT.md and FIX_SUMMARY.md
