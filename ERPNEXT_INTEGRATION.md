# ERPNext/Frappe Integration Examples

This document provides detailed examples for integrating Pudo shipping with ERPNext/Frappe.

## Table of Contents

1. [Server Script Integration](#server-script-integration)
2. [Custom App Integration](#custom-app-integration)
3. [Webhook Configuration](#webhook-configuration)
4. [Custom Fields Setup](#custom-fields-setup)

---

## Server Script Integration

### 1. Calculate Shipping Rates (Sales Order)

Create a Server Script in ERPNext:

**DocType:** Sales Order  
**Event:** Before Save

```python
import frappe
import requests
import json

def calculate_pudo_shipping(doc, method=None):
    """Calculate Pudo shipping rates when Sales Order is saved"""
    
    if not doc.pudo_shipping_enabled:
        return
    
    # Get Next.js API URL from settings
    nextjs_url = frappe.db.get_single_value("Pudo Settings", "nextjs_api_url")
    
    # Prepare items data
    items = []
    for item in doc.items:
        item_doc = frappe.get_doc("Item", item.item_code)
        
        items.append({
            "productId": item.item_code,
            "name": item.item_name,
            "quantity": item.qty,
            "dimensions": {
                "length": float(item_doc.length or 10),
                "width": float(item_doc.width or 10),
                "height": float(item_doc.height or 10),
                "weight": float(item_doc.weight_per_unit or 0.5)
            }
        })
    
    # Get company address for collection
    company = frappe.get_doc("Company", doc.company)
    company_address = frappe.get_doc("Address", company.default_address)
    
    # Prepare request payload
    payload = {
        "items": items,
        "method": doc.pudo_shipping_method or "D2L",
        "collectionDetails": {
            "streetAddress": company_address.address_line1,
            "city": company_address.city,
            "postalCode": company_address.pincode,
            "province": company_address.state
        },
        "deliveryDetails": {}
    }
    
    # Add delivery details based on method
    if doc.pudo_shipping_method in ["L2L", "D2L"]:
        payload["deliveryDetails"]["terminalId"] = doc.pudo_destination_locker
    else:
        customer_address = frappe.get_doc("Address", doc.shipping_address_name)
        payload["deliveryDetails"] = {
            "streetAddress": customer_address.address_line1,
            "city": customer_address.city,
            "postalCode": customer_address.pincode,
            "province": customer_address.state
        }
    
    try:
        # Call Next.js API
        response = requests.post(
            f"{nextjs_url}/api/pudo/rates",
            json=payload,
            headers={"Content-Type": "application/json"},
            timeout=30
        )
        
        if response.status_code == 200:
            data = response.json()
            rates = data.get("rates", [])
            parcel = data.get("parcel", {})
            
            # Save parcel details
            doc.pudo_parcel_length = parcel.get("length")
            doc.pudo_parcel_width = parcel.get("width")
            doc.pudo_parcel_height = parcel.get("height")
            doc.pudo_parcel_weight = parcel.get("weight")
            
            # Save rates as JSON in a text field for selection
            doc.pudo_available_rates = json.dumps(rates)
            
            # Auto-select cheapest rate
            if rates:
                cheapest = min(rates, key=lambda x: x["total_price"])
                doc.pudo_service_level_code = cheapest["service_level_code"]
                doc.pudo_shipping_cost = cheapest["total_price"]
        else:
            frappe.throw(f"Failed to get shipping rates: {response.text}")
            
    except Exception as e:
        frappe.log_error(f"Pudo Rate Calculation Error: {str(e)}")
        frappe.throw(f"Failed to calculate shipping: {str(e)}")
```

### 2. Create Pudo Shipment (Delivery Note)

Create a Server Script in ERPNext:

**DocType:** Delivery Note  
**Event:** On Submit

```python
import frappe
import requests

def create_pudo_shipment(doc, method=None):
    """Create Pudo shipment when Delivery Note is submitted"""
    
    # Get the linked Sales Order
    sales_order_name = frappe.db.get_value(
        "Delivery Note Item",
        {"parent": doc.name},
        "against_sales_order"
    )
    
    if not sales_order_name:
        return
    
    sales_order = frappe.get_doc("Sales Order", sales_order_name)
    
    if not sales_order.pudo_shipping_enabled:
        return
    
    # Get Next.js API URL
    nextjs_url = frappe.db.get_single_value("Pudo Settings", "nextjs_api_url")
    
    # Get addresses
    company = frappe.get_doc("Company", doc.company)
    company_address = frappe.get_doc("Address", company.default_address)
    
    # Get customer contact
    customer = frappe.get_doc("Customer", doc.customer)
    contact = frappe.get_doc("Contact", customer.customer_primary_contact)
    
    # Prepare collection details
    collection_details = {
        "name": company.company_name,
        "email": company.email,
        "mobileNumber": company.phone_no
    }
    
    # Add address based on method
    if sales_order.pudo_shipping_method in ["L2L", "L2D"]:
        collection_details["terminalId"] = sales_order.pudo_source_locker
    else:
        collection_details.update({
            "streetAddress": company_address.address_line1,
            "city": company_address.city,
            "postalCode": company_address.pincode,
            "province": company_address.state
        })
    
    # Prepare delivery details
    delivery_details = {
        "name": contact.first_name + " " + (contact.last_name or ""),
        "email": contact.email_id,
        "mobileNumber": contact.mobile_no
    }
    
    if sales_order.pudo_shipping_method in ["L2L", "D2L"]:
        delivery_details["terminalId"] = sales_order.pudo_destination_locker
    else:
        customer_address = frappe.get_doc("Address", doc.shipping_address_name)
        delivery_details.update({
            "streetAddress": customer_address.address_line1,
            "city": customer_address.city,
            "postalCode": customer_address.pincode,
            "province": customer_address.state
        })
    
    # Prepare booking payload
    payload = {
        "method": sales_order.pudo_shipping_method,
        "serviceLevelCode": sales_order.pudo_service_level_code,
        "collectionDetails": collection_details,
        "deliveryDetails": delivery_details,
        "parcel": {
            "length": float(sales_order.pudo_parcel_length),
            "width": float(sales_order.pudo_parcel_width),
            "height": float(sales_order.pudo_parcel_height),
            "weight": float(sales_order.pudo_parcel_weight)
        }
    }
    
    try:
        # Call Next.js API to create booking
        response = requests.post(
            f"{nextjs_url}/api/pudo/bookings",
            json=payload,
            headers={"Content-Type": "application/json"},
            timeout=30
        )
        
        if response.status_code == 200:
            data = response.json()
            booking = data.get("booking", {})
            
            # Save booking details to Delivery Note
            doc.pudo_booking_id = booking["bookingId"]
            doc.pudo_tracking_number = booking["trackingNumber"]
            doc.pudo_waybill_url = booking["waybillUrl"]
            doc.pudo_label_url = booking["labelUrl"]
            doc.save()
            
            # Also update Sales Order
            sales_order.pudo_booking_id = booking["bookingId"]
            sales_order.pudo_tracking_number = booking["trackingNumber"]
            sales_order.save()
            
            frappe.msgprint(f"Pudo shipment created successfully. Tracking: {booking['trackingNumber']}")
        else:
            frappe.throw(f"Failed to create Pudo booking: {response.text}")
            
    except Exception as e:
        frappe.log_error(f"Pudo Booking Error: {str(e)}")
        frappe.throw(f"Failed to create shipment: {str(e)}")
```

---

## Custom App Integration

For a more robust integration, create a custom Frappe app:

### 1. Install Custom App

```bash
bench new-app pudo_integration
bench --site your-site install-app pudo_integration
```

### 2. Create Pudo Settings DocType

```json
{
  "doctype": "DocType",
  "name": "Pudo Settings",
  "module": "Pudo Integration",
  "issingle": 1,
  "fields": [
    {
      "fieldname": "nextjs_api_url",
      "label": "Next.js API URL",
      "fieldtype": "Data",
      "reqd": 1,
      "description": "URL of your Next.js Pudo API (e.g., https://yourapp.com)"
    },
    {
      "fieldname": "default_shipping_method",
      "label": "Default Shipping Method",
      "fieldtype": "Select",
      "options": "L2L\nD2L\nL2D\nD2D",
      "default": "D2L"
    },
    {
      "fieldname": "default_source_locker",
      "label": "Default Source Locker",
      "fieldtype": "Data",
      "description": "Default locker code for L2L/L2D shipments"
    }
  ]
}
```

### 3. Create Custom Methods

File: `pudo_integration/pudo_integration/api.py`

```python
import frappe
import requests

@frappe.whitelist()
def get_available_lockers():
    """Fetch all Pudo lockers from Next.js API"""
    nextjs_url = frappe.db.get_single_value("Pudo Settings", "nextjs_api_url")
    
    try:
        response = requests.get(f"{nextjs_url}/api/pudo/lockers", timeout=30)
        
        if response.status_code == 200:
            data = response.json()
            return data.get("lockers", [])
        
        frappe.throw(f"Failed to fetch lockers: {response.text}")
    except Exception as e:
        frappe.log_error(f"Pudo Lockers Error: {str(e)}")
        frappe.throw(f"Failed to fetch lockers: {str(e)}")

@frappe.whitelist()
def calculate_shipping_cost(items, shipping_method, destination_locker=None, destination_address=None):
    """Calculate shipping cost for given items"""
    nextjs_url = frappe.db.get_single_value("Pudo Settings", "nextjs_api_url")
    
    # Build payload
    payload = {
        "items": items,
        "method": shipping_method,
        "collectionDetails": get_company_collection_details(),
        "deliveryDetails": {}
    }
    
    if shipping_method in ["L2L", "D2L"]:
        payload["deliveryDetails"]["terminalId"] = destination_locker
    else:
        payload["deliveryDetails"] = destination_address
    
    try:
        response = requests.post(
            f"{nextjs_url}/api/pudo/rates",
            json=payload,
            headers={"Content-Type": "application/json"},
            timeout=30
        )
        
        if response.status_code == 200:
            return response.json()
        
        frappe.throw(f"Failed to calculate rates: {response.text}")
    except Exception as e:
        frappe.log_error(f"Pudo Rate Error: {str(e)}")
        frappe.throw(f"Failed to calculate rates: {str(e)}")

def get_company_collection_details():
    """Get company address for collection"""
    company = frappe.defaults.get_defaults().company
    company_doc = frappe.get_doc("Company", company)
    address = frappe.get_doc("Address", company_doc.default_address)
    
    return {
        "streetAddress": address.address_line1,
        "city": address.city,
        "postalCode": address.pincode,
        "province": address.state
    }
```

---

## Custom Fields Setup

Add these custom fields to ERPNext doctypes:

### Sales Order Custom Fields

```python
# Run in ERPNext console or migration script
custom_fields = {
    "Sales Order": [
        {
            "fieldname": "pudo_section",
            "label": "Pudo Shipping",
            "fieldtype": "Section Break",
            "insert_after": "shipping_rule"
        },
        {
            "fieldname": "pudo_shipping_enabled",
            "label": "Enable Pudo Shipping",
            "fieldtype": "Check",
            "insert_after": "pudo_section"
        },
        {
            "fieldname": "pudo_shipping_method",
            "label": "Shipping Method",
            "fieldtype": "Select",
            "options": "L2L\nD2L\nL2D\nD2D",
            "insert_after": "pudo_shipping_enabled",
            "depends_on": "pudo_shipping_enabled"
        },
        {
            "fieldname": "pudo_destination_locker",
            "label": "Destination Locker",
            "fieldtype": "Data",
            "insert_after": "pudo_shipping_method",
            "depends_on": "eval:doc.pudo_shipping_method=='L2L' || doc.pudo_shipping_method=='D2L'"
        },
        {
            "fieldname": "pudo_service_level_code",
            "label": "Service Level",
            "fieldtype": "Data",
            "insert_after": "pudo_destination_locker",
            "read_only": 1
        },
        {
            "fieldname": "pudo_shipping_cost",
            "label": "Pudo Shipping Cost",
            "fieldtype": "Currency",
            "insert_after": "pudo_service_level_code",
            "read_only": 1
        },
        {
            "fieldname": "pudo_parcel_details",
            "label": "Parcel Details",
            "fieldtype": "Section Break",
            "insert_after": "pudo_shipping_cost",
            "collapsible": 1
        },
        {
            "fieldname": "pudo_parcel_length",
            "label": "Length (cm)",
            "fieldtype": "Float",
            "insert_after": "pudo_parcel_details",
            "read_only": 1
        },
        {
            "fieldname": "pudo_parcel_width",
            "label": "Width (cm)",
            "fieldtype": "Float",
            "insert_after": "pudo_parcel_length",
            "read_only": 1
        },
        {
            "fieldname": "pudo_parcel_height",
            "label": "Height (cm)",
            "fieldtype": "Float",
            "insert_after": "pudo_parcel_width",
            "read_only": 1
        },
        {
            "fieldname": "pudo_parcel_weight",
            "label": "Weight (kg)",
            "fieldtype": "Float",
            "insert_after": "pudo_parcel_height",
            "read_only": 1
        },
        {
            "fieldname": "pudo_booking_id",
            "label": "Booking ID",
            "fieldtype": "Data",
            "insert_after": "pudo_parcel_weight",
            "read_only": 1
        },
        {
            "fieldname": "pudo_tracking_number",
            "label": "Tracking Number",
            "fieldtype": "Data",
            "insert_after": "pudo_booking_id",
            "read_only": 1
        }
    ]
}

# Create custom fields
from frappe.custom.doctype.custom_field.custom_field import create_custom_fields
create_custom_fields(custom_fields)
```

### Item Master Custom Fields

```python
custom_fields = {
    "Item": [
        {
            "fieldname": "pudo_dimensions_section",
            "label": "Shipping Dimensions",
            "fieldtype": "Section Break",
            "insert_after": "weight_per_unit"
        },
        {
            "fieldname": "length",
            "label": "Length (cm)",
            "fieldtype": "Float",
            "insert_after": "pudo_dimensions_section"
        },
        {
            "fieldname": "width",
            "label": "Width (cm)",
            "fieldtype": "Float",
            "insert_after": "length"
        },
        {
            "fieldname": "height",
            "label": "Height (cm)",
            "fieldtype": "Float",
            "insert_after": "width"
        }
    ]
}

from frappe.custom.doctype.custom_field.custom_field import create_custom_fields
create_custom_fields(custom_fields)
```

---

## Testing the Integration

1. **Setup:**
   - Deploy your Next.js app with Pudo routes
   - Configure Pudo Settings in ERPNext with your Next.js URL
   - Add dimensions to your Item masters

2. **Test Rate Calculation:**
   - Create a Sales Order
   - Enable Pudo Shipping
   - Select shipping method
   - Save - rates should calculate automatically

3. **Test Booking:**
   - Create Delivery Note from Sales Order
   - Submit - booking should be created
   - Check tracking number and download waybill

---

For more information, see the main README.md file.
