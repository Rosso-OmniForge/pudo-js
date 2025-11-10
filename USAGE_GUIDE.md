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

# Copy environment template
cp .env.example .env.local

# Edit .env.local with your Pudo API key
PUDO_API_KEY=your_actual_key_here
PUDO_API_URL=https://sandbox.api-pudo.co.za
```

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
        length: item.length,
        width: item.width,
        height: item.height,
        weight: item.weight
      }
    })),
    method: selectedShippingMethod, // 'D2L', 'L2L', etc.
    collectionDetails: {
      streetAddress: storeAddress.street,
      city: storeAddress.city,
      postalCode: storeAddress.postalCode
    },
    deliveryDetails: {
      terminalId: selectedLocker.code // if locker delivery
      // OR
      // streetAddress, city, postalCode for door delivery
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
import { PudoClient, RequestBuilder } from '@/lib';

export async function POST(request: Request) {
  const client = new PudoClient({
    apiKey: process.env.PUDO_API_KEY!,
    apiUrl: process.env.PUDO_API_URL,
  });
  
  // Your custom logic here
  const rates = await client.getRates(payload);
  
  return Response.json({ rates });
}
```

### Error Handling

```typescript
try {
  const response = await fetch('/api/pudo/rates', {
    method: 'POST',
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
    // Show user-friendly message
    alert('Unable to calculate shipping. Please try again.');
  }
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
- Verify product dimensions are correct
- Check if items are too large for Pudo boxes (max 60×41×69 cm)
- Consider splitting large orders

### Issue: "Pudo API key not configured"

**Cause:** Environment variable not set  
**Solution:**
```bash
# Check .env.local exists
cat .env.local

# Verify variable is set
echo $PUDO_API_KEY

# Restart dev server
npm run dev
```

### Issue: Rate calculation returns empty

**Cause:** Invalid address or locker code  
**Solution:**
- Verify locker code exists: `curl localhost:3000/api/pudo/lockers`
- Check address format matches requirements
- Ensure postal codes are valid South African codes

### Issue: TypeScript errors

**Cause:** Missing type definitions  
**Solution:**
```bash
npm install --save-dev @types/node
npm run type-check
```

---

## Performance Tips

### 1. Cache Lockers Data

```typescript
// Lockers rarely change, cache for 24 hours
let lockersCache = null;
let cacheTime = 0;

async function getCachedLockers() {
  const now = Date.now();
  if (lockersCache && (now - cacheTime < 24 * 60 * 60 * 1000)) {
    return lockersCache;
  }
  
  const response = await fetch('/api/pudo/lockers');
  lockersCache = await response.json();
  cacheTime = now;
  
  return lockersCache;
}
```

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
   // ❌ DON'T DO THIS
   const client = new PudoClient({ apiKey: 'abc123' }); // In frontend code
   
   // ✅ DO THIS
   // Keep PudoClient calls in API routes only
   ```

2. **Validate input data**
   ```typescript
   // In API route
   if (!items || items.length === 0) {
     return NextResponse.json({ error: 'No items' }, { status: 400 });
   }
   ```

3. **Rate limiting**
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

Happy shipping! 🚚
