# Pudo Shipping Quick Reference Card

## 🚀 Quick Start (5 Minutes)

```bash
# 1. Install
npm install

# 2. Configure
cp .env.example .env.local
# Edit .env.local and add your PUDO_API_KEY

# 3. Start
npm run dev

# 4. Test
curl http://localhost:3000/api/pudo/lockers
```

## 📡 API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/pudo/rates` | POST | Calculate shipping rates |
| `/api/pudo/bookings` | POST | Create shipment |
| `/api/pudo/lockers` | GET | List all lockers |
| `/api/pudo/waybill/[id]` | GET | Download waybill |
| `/api/pudo/label/[id]` | GET | Download label |

## 🎯 Shipping Methods

- **L2L** = Locker → Locker
- **D2L** = Door → Locker (most common for e-commerce)
- **L2D** = Locker → Door
- **D2D** = Door → Door

## 📦 Box Sizes (Standard Pudo)

| Name | Dimensions (cm) | Max Weight |
|------|----------------|------------|
| V4-XS | 60×17×8 | 2 kg |
| V4-S | 60×41×8 | 5 kg |
| V4-M | 60×41×19 | 10 kg |
| V4-L | 60×41×41 | 15 kg |
| V4-XL | 60×41×69 | 20 kg |

## 💻 Code Snippets

### Calculate Rates

```typescript
const response = await fetch('/api/pudo/rates', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    items: [{
      productId: '123',
      name: 'Product',
      quantity: 1,
      dimensions: { length: 30, width: 20, height: 10, weight: 1.5 }
    }],
    method: 'D2L',
    collectionDetails: {
      streetAddress: '123 Main St',
      city: 'Johannesburg',
      postalCode: '2000'
    },
    deliveryDetails: {
      terminalId: 'CG54'
    }
  })
});

const { rates, parcel } = await response.json();
```

### Create Booking

```typescript
const response = await fetch('/api/pudo/bookings', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    method: 'D2L',
    serviceLevelCode: 'ECO',
    collectionDetails: {
      streetAddress: '123 Main St',
      city: 'Johannesburg',
      postalCode: '2000',
      name: 'Store Name',
      email: 'store@example.com',
      mobileNumber: '0821234567'
    },
    deliveryDetails: {
      terminalId: 'CG54',
      name: 'Customer Name',
      email: 'customer@example.com',
      mobileNumber: '0827654321'
    },
    parcel: { length: 30, width: 20, height: 10, weight: 1.5 }
  })
});

const { booking } = await response.json();
// booking.bookingId, booking.trackingNumber
```

### Use Library Directly

```typescript
import { PudoClient, RequestBuilder, getContentsPayload } from '@/lib';

const client = new PudoClient({
  apiKey: process.env.PUDO_API_KEY!,
  apiUrl: process.env.PUDO_API_URL,
});

// Get lockers
const lockers = await client.getAllLockers();

// Calculate rates
const rates = await client.getRates(payload);

// Create booking
const booking = await client.createBooking(payload);
```

## 🐍 ERPNext Integration (Python)

```python
import requests
import frappe

def get_pudo_rates(items):
    nextjs_url = frappe.db.get_single_value("Pudo Settings", "nextjs_api_url")
    
    response = requests.post(
        f"{nextjs_url}/api/pudo/rates",
        json={"items": items, "method": "D2L", ...}
    )
    
    return response.json()["rates"]
```

## 🔧 Environment Variables

```env
# Required
PUDO_API_KEY=your_api_key_here

# Optional (defaults to sandbox)
PUDO_API_URL=https://sandbox.api-pudo.co.za

# Development vs Production
NODE_ENV=development
```

## 🧪 Test Locker

Use **CG54** (Sasol Rivonia) for testing in sandbox.

## ⚠️ Common Issues

| Issue | Solution |
|-------|----------|
| "Unable to calculate shipping" | Check product dimensions are set |
| "API key not configured" | Add to .env.local and restart server |
| Rate returns empty | Verify locker code and addresses |
| TypeScript errors | Run `npm install --save-dev @types/node` |

## 📂 Project Structure

```
pudo-nextjs/
├── lib/                    # Core library
│   ├── types.ts           # TypeScript types
│   ├── bin-packing.ts     # Box calculation
│   ├── pudo-client.ts     # API client
│   └── request-builder.ts # Payload builder
├── app/api/pudo/          # API routes
│   ├── rates/
│   ├── bookings/
│   ├── lockers/
│   ├── waybill/[id]/
│   └── label/[id]/
├── examples/              # Usage examples
├── .env.example           # Config template
└── README.md             # Full documentation
```

## 📚 Documentation Files

- **README.md** - Main documentation
- **USAGE_GUIDE.md** - Detailed examples
- **ERPNEXT_INTEGRATION.md** - ERPNext guide
- **PROJECT_SUMMARY.md** - Overview
- **MIGRATION_CHECKLIST.md** - Setup steps
- **QUICK_REFERENCE.md** - This file

## 🆘 Need Help?

1. Check **USAGE_GUIDE.md** for detailed examples
2. See **ERPNEXT_INTEGRATION.md** for Frappe integration
3. Review **examples/quick-start.ts** for working code
4. Open issue on GitHub for bugs

## 🎓 Key Concepts

**Bin Packing:** Automatically calculates the smallest box that fits your items

**Service Level Code:** Rate identifier (e.g., 'ECO', 'OVN') returned from rate request

**Terminal ID:** Locker code (e.g., 'CG54')

**Parcel:** A single box with dimensions and weight

## ✅ Pre-Flight Checklist

Before production:
- [ ] API key configured
- [ ] All products have dimensions
- [ ] Tested rate calculation
- [ ] Tested booking creation
- [ ] Downloaded sample waybill
- [ ] ERPNext integration tested

## 🔗 URLs

- **Sandbox API:** https://sandbox.api-pudo.co.za
- **Production API:** https://api-pudo.co.za
- **Pudo Website:** https://www.pudo.co.za

---

**Print this page for quick reference during development!**
