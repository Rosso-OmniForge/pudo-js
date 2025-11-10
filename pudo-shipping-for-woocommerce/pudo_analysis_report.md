# Pudo Shipping for WooCommerce: Codebase Analysis Report

This report provides a detailed analysis of the Pudo shipping plugin for WooCommerce. The goal is to understand its architecture and functionality to facilitate its adaptation for a Next.js-based e-commerce platform.

## 1. Summary of Findings

The Pudo shipping plugin is a standard WooCommerce shipping method plugin with a heavy reliance on a custom API. The architecture can be broken down into several key areas relevant for a Next.js adaptation:

1.  **Configuration**: A store administrator provides an API Key, Sender Contact/Address details, and chooses a shipping origin (Locker or Street Address) via a settings page (`Pudo_Shipping_Settings.php`). These are stored in the WordPress options table.

2.  **Rate Calculation (Cart/Checkout)**: When a customer is on the cart or checkout page, the `Pudo_Shipping_Method::calculate_shipping` function is triggered. It uses the complex bin-packing logic in `Pudo_Api_Payload.php` to determine the smallest box size required for the cart contents. It then calls the Pudo API (`POST /api/v1/locker-rates-new`) with a payload constructed by `ApiRequestBuilder.php` to get the shipping cost for that box size. This means a Next.js implementation cannot simply ask for a rate; it must first replicate the bin-packing logic.

3.  **Shipment Booking (Admin)**: After an order is placed, an admin can create the shipment. This triggers an AJAX call (`pudoCreateShipment` or `submit_pudo_collection_from_listing_page`) that uses `ShippingData.php` to gather sender info (from settings) and recipient info (from the order). It then calls the Pudo API (`POST /api/v1/shipments`) with a payload from `ApiRequestBuilder.php` to finalize the booking.

4.  **API Interaction**: All API calls are routed through `PudoApi.php`. The API base URL is `https://sandbox.api-pudo.co.za` (configurable) and authentication is done via a Bearer token in the `Authorization` header. The exact endpoint paths and payload structures are defined within the `vendor/pudo/pudo-common` library, specifically in `APIProcessor.php` and `ApiRequestBuilder.php`.

### Adaptation Strategy for Next.js

To adapt this for a Next.js website, the following is required:

-   A secure backend (e.g., Next.js API routes) to store the Pudo API key and make calls to the Pudo API. The API key should not be exposed to the frontend.
-   The backend must replicate the JSON payload structures defined in `ApiRequestBuilder.php` for `/rates` and `/shipments` endpoints.
-   To provide accurate shipping estimates in the cart, the frontend or backend must replicate the bin-packing logic from `Pudo_Api_Payload.php`. This requires product dimensions to be available.
-   The frontend will need to interact with the `/lockers-data` endpoint to power a map/selector for customers to choose a Pudo locker.

## 2. Exploration Trace

The analysis followed these steps to build a comprehensive understanding of the codebase:

1.  Started by reading the main plugin file `pudo-shipping-for-woocommerce.php` to understand the plugin's entry point and overall structure.
2.  Investigated `classes/Pudo_Shipping_Method.php` as it was identified as the core class for the WooCommerce shipping method integration.
3.  Read `classes/PudoApi.php` to understand how the plugin communicates with the external Pudo API.
4.  Examined `vendor/pudo/pudo-common/src/Processor/APIProcessor.php` to find the specific API endpoint paths and HTTP methods.
5.  Analyzed `vendor/pudo/pudo-common/src/Request/ApiRequestBuilder.php` to determine the exact JSON structure of the API request payloads.
6.  Read `classes/ShippingData.php` to understand how data from WooCommerce and plugin settings is gathered and prepared for the API request.
7.  Investigated `classes/Pudo_Api_Payload.php` to understand the complex bin-packing algorithm used for calculating shipping rates based on cart contents.
8.  Finished by reviewing `classes/Pudo_Shipping_Settings.php` to get a complete list of all administrative configuration options.

## 3. Relevant Locations & Key Components

The following files are critical to the plugin's operation and contain the core logic that needs to be understood and replicated.

---

### `pudo-shipping-for-woocommerce.php`

-   **Reasoning**: This is the main plugin file and entry point. It initializes all the hooks, scripts, and settings pages, providing a high-level overview of how the plugin integrates with WordPress and WooCommerce. The AJAX action hooks `pudoGetRates` and `pudoCreateShipment` are the primary entry points for frontend interactions.
-   **Key Symbols**:
    -   `wc_pudo_shipping_init`
    -   `pudoGetRates`
    -   `pudoCreateShipment`

---

### `classes/Pudo_Shipping_Method.php`

-   **Reasoning**: This class is the heart of the WooCommerce integration. It extends `WC_Shipping_Method` and contains the core logic for calculating shipping costs (`calculate_shipping`), fetching rates from the API (`buildPudoRate`), and creating shipments in the admin panel (`createShipmentFromOrder`).
-   **Key Symbols**:
    -   `calculate_shipping`
    -   `buildPudoRate`
    -   `createShipmentFromOrder`

---

### `classes/PudoApi.php`

-   **Reasoning**: This class is the sole bridge to the Pudo API. It handles making the `wp_remote_post` and `wp_remote_get` calls, adding the necessary `Authorization` header, and retrieving API credentials from the database. It abstracts the raw HTTP communication.
-   **Key Symbols**:
    -   `callPudoApi`
    -   `getRates`
    -   `bookingRequest`
    -   `getAllLockers`

---

### `vendor/pudo/pudo-common/src/Processor/APIProcessor.php`

-   **Reasoning**: This file contains the definitive map of internal function names to the actual API endpoint paths (e.g., `get_rates` -> `/api/v1/locker-rates-new`). This is critical for knowing which URLs to call in a new implementation.
-   **Key Symbols**:
    -   `API_METHODS`

---

### `vendor/pudo/pudo-common/src/Request/ApiRequestBuilder.php`

-   **Reasoning**: This is the most critical file for API interaction. It defines the exact JSON structure for the POST request bodies sent to the Pudo API for getting rates and creating bookings. Any new implementation must replicate these payload structures precisely.
-   **Key Symbols**:
    -   `buildRatesRequest`
    -   `buildBookingRequest`

---

### `classes/ShippingData.php`

-   **Reasoning**: This class acts as a data aggregator. It sources information from plugin settings (for the sender) and the WooCommerce order (for the recipient) to prepare the data needed by the `ApiRequestBuilder`. It shows where the data for the API calls comes from.
-   **Key Symbols**:
    -   `initializeAPIRequestBuilder`
    -   `buildCollectionDetails`
    -   `buildShippingDetails`
    -   `buildParcels`

---

### `classes/Pudo_Api_Payload.php`

-   **Reasoning**: This file contains the complex 'bin packing' logic used to determine the smallest possible box for a given set of cart items. This logic is essential for accurately calculating shipping rates and must be replicated in a headless environment to get correct price estimates.
-   **Key Symbols**:
    -   `getContentsPayload`

---

### `classes/Pudo_Shipping_Settings.php`

-   **Reasoning**: This file defines all the configuration options available to the store administrator, such as API keys, sender address, and shipping origin (locker vs. street). These settings are the source for much of the data used in API requests.
-   **Key Symbols**:
    -   `overrideFormFieldsVariable`
