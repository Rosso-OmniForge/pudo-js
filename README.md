# Pudo Shipping for Next.js

A complete TypeScript/Next.js implementation of Pudo (The Courier Guy) shipping integration, ported from the WooCommerce plugin. Works seamlessly with ERPNext/Frappe backends or any Next.js e-commerce platform.

## Table of Contents

- [Features](#features)
- [Shipping Methods](#shipping-methods)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Next.js API Routes](#option-1-use-as-nextjs-api-routes-recommended)
  - [Direct Library Usage](#option-2-use-as-library-server-side-only)
  - [ERPNext Integration](#erpnextfrappe-integration)
- [API Reference](#api-endpoints-reference)
- [Key Concepts](#key-concepts)
- [Security](#security)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [License](#license)
- [Support](#support)
- [Credits](#credits)

## Features

- **Advanced Bin Packing Algorithm** - Automatically calculates optimal box sizes for cart items
- **Complete API Integration** - Rate calculation, booking creation, locker management
- **TypeScript Support** - Fully typed for excellent developer experience
- **Next.js API Routes** - Ready-to-use serverless endpoints
- **ERPNext/Frappe Compatible** - Designed to integrate with your backend
- **No Frontend Required** - Pure backend/API implementation
- **Production Ready** - Ported from battle-tested WooCommerce plugin

## Shipping Methods

- **L2L** - Locker to Locker
- **D2L** - Door to Locker
- **L2D** - Locker to Door
- **D2D** - Door to Door

## Requirements

- **Node.js** - Version 18 or higher
- **Next.js** - Version 14 or higher
- **TypeScript** - Version 5.0 or higher
- **Pudo Account** - Business account with The Courier Guy
  - API Key (obtain from integrations@pudo.co.za)
  - API URL (sandbox or production)
- **Product Data** - All products must have dimensions (length, width, height in cm) and weight (kg)

## Installation

### 1. Clone or Copy Files

```bash
git clone <your-repo> pudo-nextjs
cd pudo-nextjs
```

### 2. Install Dependencies

```bash
npm install
# or
yarn install
# or
pnpm install
```

### 3. Configure Environment Variables

Create a `.env.local` file in the root directory:

```bash
cp .env.example .env.local
```

Edit `.env.local` and add your Pudo API credentials:

```env
PUDO_API_KEY=your_actual_api_key_here
PUDO_API_URL=https://sandbox.api-pudo.co.za  # or production URL
NODE_ENV=development
```

**Get Your API Key:**

1. Contact The Courier Guy / Pudo for API access (integrations@pudo.co.za)
2. For testing, use sandbox URL: `https://sandbox.api-pudo.co.za`
3. For production, use live URL: `https://api-pudo.co.za`

## Configuration

The system requires only environment variables for configuration. No complex setup needed.

## Usage

### Option 1: Use as Next.js API Routes (Recommended)

The package includes ready-to-use API routes that you can call from your frontend or ERPNext backend.

#### Calculate Shipping Rates

```typescript
// POST /api/pudo/rates
const response = await fetch('/api/pudo/rates', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    items: [
      {
        productId: '123',
        name: 'Product Name',
        quantity: 2,
        dimensions: {
          length: 30,  // cm
          width: 20,   // cm
          height: 10,  // cm
          weight: 1.5  // kg
        }
      }
    ],
    method: 'D2L',  // Door to Locker
    collectionDetails: {
      streetAddress: '123 Main St',
      city: 'Johannesburg',
      postalCode: '2000',
      province: 'Gauteng'
    },
    deliveryDetails: {
      terminalId: 'CG54'  // Locker code
    }
  })
});

const data = await response.json();
// Returns: { success: true, rates: [...], parcel: {...} }
```

#### Create Booking

```typescript
// POST /api/pudo/bookings
const response = await fetch('/api/pudo/bookings', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    method: 'D2L',
    serviceLevelCode: 'ECO',  // From rate response
    collectionDetails: {
      streetAddress: '123 Main St',
      city: 'Johannesburg',
      postalCode: '2000',
      province: 'Gauteng',
      name: 'John Doe',
      email: 'john@example.com',
      mobileNumber: '0821234567'
    },
    deliveryDetails: {
      terminalId: 'CG54',
      name: 'Jane Smith',
      email: 'jane@example.com',
      mobileNumber: '0827654321'
    },
    parcel: {
      length: 30,
      width: 20,
      height: 10,
      weight: 1.5
    }
  })
});

const data = await response.json();
// Returns: { success: true, booking: { bookingId, trackingNumber, waybillUrl, labelUrl } }
```

#### Get All Lockers

```typescript
// GET /api/pudo/lockers
const response = await fetch('/api/pudo/lockers');
const data = await response.json();
// Returns: { success: true, lockers: [...], count: 150 }
```

#### Download Waybill/Label

```typescript
// GET /api/pudo/waybill/[bookingId]
window.open(`/api/pudo/waybill/12345`, '_blank');

// GET /api/pudo/label/[bookingId]
window.open(`/api/pudo/label/12345`, '_blank');
```

### Option 2: Use as Library (Server-Side Only)

You can also use the library directly in your server-side code:

```typescript
import { PudoClient, RequestBuilder, getContentsPayload, DEFAULT_BOX_SIZES } from '@/lib';

// Initialize client
const client = new PudoClient({
  apiKey: process.env.PUDO_API_KEY!,
  apiUrl: process.env.PUDO_API_URL,
  isDevelopment: true
});

// Calculate bin packing
const items = [
  {
    productId: '123',
    name: 'Product',
    quantity: 1,
    dimensions: { length: 30, width: 20, height: 10, weight: 1.5 }
  }
];

const packingResult = getContentsPayload(items, DEFAULT_BOX_SIZES);

// Build request
const builder = new RequestBuilder(
  'D2L',
  {
    streetAddress: '123 Main St',
    city: 'Johannesburg',
    postalCode: '2000'
  },
  {
    terminalId: 'CG54'
  },
  [RequestBuilder.createParcel(30, 20, 10, 1.5)]
);

// Get rates
const ratePayload = builder.buildRatesRequest();
const rates = await client.getRates(ratePayload);

// Create booking
const bookingPayload = builder.buildBookingRequest('ECO');
const booking = await client.createBooking(bookingPayload);

// Get lockers
const lockers = await client.getAllLockers();
```

## ERPNext/Frappe Integration

### Method 1: Direct API Calls from Frappe

Call the Next.js API routes from your Frappe backend using Python's `requests`:

```python
import requests
import frappe

def calculate_pudo_shipping(items, delivery_address):
    """Calculate Pudo shipping rates from Frappe"""
    
    # Format items for Pudo API
    pudo_items = []
    for item in items:
        pudo_items.append({
            "productId": item.item_code,
            "name": item.item_name,
            "quantity": item.qty,
            "dimensions": {
                "length": item.length,  # cm
                "width": item.width,    # cm
                "height": item.height,  # cm
                "weight": item.weight   # kg
            }
        })
    
    # Call Next.js API
    response = requests.post(
        "https://your-nextjs-domain.com/api/pudo/rates",
        json={
            "items": pudo_items,
            "method": "D2L",
            "collectionDetails": {
                "streetAddress": frappe.db.get_single_value("Company", "address_line1"),
                "city": frappe.db.get_single_value("Company", "city"),
                "postalCode": frappe.db.get_single_value("Company", "pincode")
            },
            "deliveryDetails": {
                "terminalId": delivery_address.get("pudo_locker_code")
            }
        }
    )
    
    if response.status_code == 200:
        data = response.json()
        return data.get("rates", [])
    
    return []

def create_pudo_shipment(sales_order):
    """Create Pudo shipment from Sales Order"""
    
    response = requests.post(
        "https://your-nextjs-domain.com/api/pudo/bookings",
        json={
            "method": sales_order.pudo_shipping_method,
            "serviceLevelCode": sales_order.pudo_service_level,
            "collectionDetails": {
                # Your company details
                "streetAddress": "...",
                "city": "...",
                "postalCode": "...",
                "name": "...",
                "email": "...",
                "mobileNumber": "..."
            },
            "deliveryDetails": {
                # Customer details
                "terminalId": sales_order.pudo_locker_code,
                "name": sales_order.customer_name,
                "email": sales_order.contact_email,
                "mobileNumber": sales_order.contact_mobile
            },
            "parcel": {
                "length": sales_order.pudo_parcel_length,
                "width": sales_order.pudo_parcel_width,
                "height": sales_order.pudo_parcel_height,
                "weight": sales_order.pudo_parcel_weight
            }
        }
    )
    
    if response.status_code == 200:
        booking = response.json().get("booking")
        
        # Save booking details to Sales Order
        sales_order.pudo_booking_id = booking["bookingId"]
        sales_order.pudo_tracking_number = booking["trackingNumber"]
        sales_order.pudo_waybill_url = booking["waybillUrl"]
        sales_order.save()
        
        return booking
    
    return None
```

### Method 2: Webhook Integration

Set up webhooks in ERPNext to trigger Next.js API calls on order events:

1. Create webhook in ERPNext for "Sales Order" on "Submit"
2. Point webhook to your Next.js API route
3. Next.js processes and returns shipping details

## API Endpoints Reference

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/pudo/rates` | POST | Calculate shipping rates |
| `/api/pudo/bookings` | POST | Create shipment booking |
| `/api/pudo/lockers` | GET | Get all available lockers |
| `/api/pudo/waybill/[id]` | GET | Download waybill PDF |
| `/api/pudo/label/[id]` | GET | Download label PDF |

## Key Concepts

### Bin Packing Algorithm

The system uses a sophisticated 3D bin-packing algorithm (ported from the WooCommerce plugin) to:
1. Analyze cart item dimensions
2. Calculate optimal box configurations
3. Minimize number of parcels
4. Reduce shipping costs

**Important:** Product dimensions (length, width, height, weight) must be accurate for proper rate calculation.

### Box Sizes

Default Pudo box sizes (V4 series):
- V4-XS: 60×17×8 cm (max 2kg) - Flyer
- V4-S: 60×41×8 cm (max 5kg)
- V4-M: 60×41×19 cm (max 10kg)
- V4-L: 60×41×41 cm (max 15kg)
- V4-XL: 60×41×69 cm (max 20kg)

You can customize box sizes by passing a custom `boxSizes` array to the rates endpoint.

### Shipping Methods

- **L2L**: Customer drops off at locker A, picks up at locker B
- **D2L**: Courier collects from address, customer picks up at locker
- **L2D**: Customer drops off at locker, courier delivers to address
- **D2D**: Full door-to-door courier service

## Security

- API key stored in environment variables (never exposed to frontend)
- Server-side API calls only
- No CORS issues (all routes are server-side)
- TypeScript type safety

## Testing

### Using Sandbox API

```env
PUDO_API_URL=https://sandbox.api-pudo.co.za
```

### Test Locker Codes

Use locker code `CG54` (Sasol Rivonia) for testing.

### Example Test Request

```bash
curl -X POST http://localhost:3000/api/pudo/rates \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{
      "productId": "TEST-1",
      "name": "Test Product",
      "quantity": 1,
      "dimensions": {
        "length": 20,
        "width": 15,
        "height": 10,
        "weight": 0.5
      }
    }],
    "method": "D2L",
    "collectionDetails": {
      "streetAddress": "123 Test St",
      "city": "Johannesburg",
      "postalCode": "2000"
    },
    "deliveryDetails": {
      "terminalId": "CG54"
    }
  }'
```

## Project Structure

```
pudo-nextjs/
├── app/
│   └── api/
│       └── pudo/
│           ├── rates/route.ts           # Rate calculation endpoint
│           ├── bookings/route.ts        # Booking creation endpoint
│           ├── lockers/route.ts         # Locker listing endpoint
│           ├── waybill/[id]/route.ts    # Waybill download
│           └── label/[id]/route.ts      # Label download
├── lib/
│   ├── types.ts                         # TypeScript type definitions
│   ├── bin-packing.ts                   # Bin packing algorithm
│   ├── pudo-client.ts                   # API client
│   ├── request-builder.ts               # Request payload builder
│   └── index.ts                         # Main exports
├── .env.example                         # Environment template
├── package.json                         # Dependencies
├── tsconfig.json                        # TypeScript config
└── README.md                            # This file
```

## Contributing

This is a direct port from the WooCommerce Pudo plugin. The core algorithms and API interactions remain faithful to the original implementation.

## License

MIT License - See LICENSE file for details.

Pudo and The Courier Guy are trademarks of The Courier Guy (Pty) Ltd. This is an independent implementation for Next.js/ERPNext platforms and is not officially endorsed by The Courier Guy.

## Support

**For Pudo API Issues:**
- Email: integrations@pudo.co.za
- Website: https://www.pudo.co.za

**For This Integration:**
- Documentation: See USAGE_GUIDE.md and ERPNEXT_INTEGRATION.md
- Issues: Open an issue in this repository
- ERPNext Specific: See ERPNEXT_INTEGRATION.md for detailed Python examples

**Additional Resources:**
- The Courier Guy Help Center: https://thecourierguy.zendesk.com/hc/en-us
- ERPNext Documentation: https://docs.erpnext.com
- Frappe Framework: https://frappeframework.com

## Credits

Ported from the official Pudo Shipping for WooCommerce plugin.

Original WooCommerce Plugin Copyright (c) 2025 The Courier Guy (Pty) Ltd.

This Next.js implementation created for modern e-commerce platforms and ERPNext integration.

---

## Important Notes

**Product Dimensions Required:** This package requires accurate product dimensions (length, width, height in cm) and weight (kg) to be configured in your system. Without these measurements, the bin-packing algorithm cannot calculate proper shipping rates.

**South Africa Only:** Pudo services operate exclusively within South Africa. All addresses and locker locations are SA-based.

**API Credentials:** Contact integrations@pudo.co.za to obtain API credentials. Different keys are provided for sandbox (testing) and production environments.

**For Comprehensive Guides:**
- **Setup & Installation:** See this README
- **Detailed Usage Examples:** USAGE_GUIDE.md
- **ERPNext Integration:** ERPNEXT_INTEGRATION.md
- **Quick Reference:** QUICK_REFERENCE.md
- **Migration Checklist:** MIGRATION_CHECKLIST.md
