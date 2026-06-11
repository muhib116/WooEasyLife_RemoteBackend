import type { IconName } from "@/types";

export type HttpMethod = "GET" | "POST" | "ANY";
export type AuthLevel = "public" | "token" | "full";

export interface ApiParam {
    name: string;
    type: string;
    required?: boolean;
    description: string;
}

export interface ApiEndpoint {
    id: string;
    name: string;
    method: HttpMethod;
    path: string;
    auth: AuthLevel;
    description: string;
    params?: ApiParam[];
    queryParams?: ApiParam[];
    requestExample?: string;
    responseExample?: string;
    notes?: string;
}

export interface ApiCategory {
    id: string;
    title: string;
    description: string;
    icon: IconName;
    endpoints: ApiEndpoint[];
}

const success = (data: unknown, message = "Success") =>
    JSON.stringify({ status: true, message, data }, null, 2);

const error = (message: string) =>
    JSON.stringify({ status: false, message, data: null, errors: null }, null, 2);

export const apiCategories: ApiCategory[] = [
    {
        id: "auth",
        title: "Authentication & User",
        description: "Token validation and merchant profile",
        icon: "PhLockKeyOpen",
        endpoints: [
            {
                id: "get-user",
                name: "Get User",
                method: "GET",
                path: "/api/get-user",
                auth: "token",
                description:
                    "Returns the authenticated merchant profile, SMS balance, remaining package orders, and optional notices. Requires a valid bearer token and matching Origin domain.",
                responseExample: JSON.stringify(
                    {
                        id: 1,
                        name: "Merchant Name",
                        email: "merchant@example.com",
                        remaining_order: 150,
                        sms_balance: 42.5,
                        notice: null,
                    },
                    null,
                    2,
                ),
            },
            {
                id: "get-user-data",
                name: "Get User Data",
                method: "GET",
                path: "/api/get-user-data",
                auth: "full",
                description: "Returns the Sanctum-authenticated user model for the current token.",
                responseExample: JSON.stringify({ id: 1, name: "Merchant Name" }, null, 2),
            },
            {
                id: "validate-token",
                name: "Validate Token",
                method: "GET",
                path: "/api/validate-token",
                auth: "full",
                description: "Lightweight health check that returns true when the token is valid.",
                responseExample: "true",
            },
        ],
    },
    {
        id: "plugins",
        title: "Plugin Distribution",
        description: "Public endpoints for WooCommerce plugin delivery",
        icon: "PhPlugsConnected",
        endpoints: [
            {
                id: "app-logo",
                name: "App Logo",
                method: "GET",
                path: "/app-logo",
                auth: "public",
                description: "Returns the application logo image (webp). No authentication required.",
                notes: "Response is binary image content, not JSON.",
            },
            {
                id: "download-plugins",
                name: "Download Latest Plugin",
                method: "GET",
                path: "/download-plugins",
                auth: "public",
                description:
                    "Downloads the latest published plugin ZIP. Increments the download counter.",
                notes: "Response is a file attachment, not JSON.",
            },
            {
                id: "get-metadata",
                name: "Plugin Metadata",
                method: "GET",
                path: "/get-metadata",
                auth: "public",
                description: "Returns JSON metadata/settings for the latest plugin version.",
                responseExample: JSON.stringify(
                    { version: "1.2.0", requires: "5.8", tested: "6.4" },
                    null,
                    2,
                ),
            },
        ],
    },
    {
        id: "data",
        title: "App Data",
        description: "Tutorials and contact information",
        icon: "PhBookOpen",
        endpoints: [
            {
                id: "get-tutorials",
                name: "Get Tutorials",
                method: "ANY",
                path: "/api/get-tutorials",
                auth: "full",
                description: "Returns tutorial content configured for the plugin dashboard.",
                responseExample: success([{ title: "Getting Started", url: "..." }]),
            },
            {
                id: "get-contact-info",
                name: "Get Contact Info",
                method: "ANY",
                path: "/api/get-contact-info",
                auth: "full",
                description: "Returns support contact cards with icon URLs and content.",
                responseExample: success([
                    { icon: "https://example.com/images/contacts/phone.png", content: "+880..." },
                ]),
            },
        ],
    },
    {
        id: "package",
        title: "Package Hub",
        description: "Order limit tracking for merchant packages",
        icon: "PhPackage",
        endpoints: [
            {
                id: "package-order-use",
                name: "Record Package Use",
                method: "POST",
                path: "/api/package-order-use",
                auth: "full",
                description:
                    "Deducts order credits from the merchant's active package for the token domain. Returns error with is_order_limit_over when no package remains.",
                params: [
                    { name: "order_count", type: "integer", required: true, description: "Number of orders to deduct (min: 1)" },
                    { name: "use_details", type: "object|array", required: false, description: "Optional cart/order metadata for analytics" },
                ],
                requestExample: JSON.stringify({ order_count: 1, use_details: { from: "checkout" } }, null, 2),
                responseExample: success({ id: 1, order_count: 1, remaining_order: 149 }, "History stored successfully"),
                notes: "When limit is exceeded, response includes is_order_limit_over: true",
            },
        ],
    },
    {
        id: "courier-config",
        title: "Courier Configuration",
        description: "Manage Steadfast and Pathao credentials",
        icon: "PhGear",
        endpoints: [
            {
                id: "courier-list",
                name: "List Couriers",
                method: "POST",
                path: "/api/courier/list",
                auth: "full",
                description: "Returns available courier integrations with slug, title, and logo URL.",
                responseExample: success([
                    { slug: "steadfast", title: "Steadfast", logo: "https://example.com/images/steadfast.png" },
                    { slug: "pathao", title: "Pathao", logo: "https://example.com/images/pathao.png" },
                ]),
            },
            {
                id: "courier-save",
                name: "Save Configuration",
                method: "POST",
                path: "/api/courier/save-configuration",
                auth: "full",
                description: "Creates or updates courier API credentials for the authenticated user.",
                params: [
                    { name: "id", type: "integer", required: false, description: "Existing configuration ID for updates" },
                    { name: "title", type: "string", required: true, description: "Display name" },
                    { name: "slug", type: "string", required: true, description: "steadfast | pathao | paperfly | redx" },
                    { name: "api_key", type: "string", required: true, description: "Courier API key / client ID" },
                    { name: "secret_key", type: "string", required: true, description: "Courier secret key" },
                    { name: "is_active", type: "boolean", required: false, description: "Enable or disable integration" },
                    { name: "settings", type: "object", required: false, description: "Pathao-only: store_id, username, password, sender_name, sender_phone, recipient_city, recipient_zone, recipient_area" },
                ],
                requestExample: JSON.stringify(
                    {
                        title: "Steadfast",
                        slug: "steadfast",
                        api_key: "your-api-key",
                        secret_key: "your-secret-key",
                        is_active: true,
                    },
                    null,
                    2,
                ),
                responseExample: success({ id: 1, slug: "steadfast" }, "Configuration saved successfully!"),
            },
            {
                id: "courier-get",
                name: "Get Configuration",
                method: "POST",
                path: "/api/courier/get-configuration",
                auth: "full",
                description: "Returns saved Steadfast and Pathao configurations. Pathao password is masked.",
                responseExample: success({ steadfast: {}, pathao: {} }),
            },
            {
                id: "check-courier-balance",
                name: "All Courier Balances",
                method: "GET",
                path: "/api/check-courier-balance",
                auth: "full",
                description: "Aggregated balance snapshot for Steadfast, Pathao, Paperfly, and RedX.",
                responseExample: success({
                    steadfast: { logo: "...", balance: 5000 },
                    pathao: { logo: "...", balance: null, balance_available: false },
                    paperfly: { logo: "...", balance: 0 },
                    redx: { logo: "...", balance: 0 },
                    total: 5000,
                }),
            },
        ],
    },
    {
        id: "steadfast",
        title: "Steadfast Courier",
        description: "Order creation and tracking via Steadfast API",
        icon: "PhTruck",
        endpoints: [
            {
                id: "sf-create-order",
                name: "Create Order",
                method: "POST",
                path: "/api/steadfast/create-order",
                auth: "full",
                description: "Creates a single Steadfast consignment.",
                params: [
                    { name: "invoice", type: "string", required: true, description: "Unique invoice ID (alphanumeric, dash, underscore)" },
                    { name: "recipient_name", type: "string", required: true, description: "Customer name (max 100)" },
                    { name: "recipient_phone", type: "string", required: true, description: "11-digit BD mobile (01XXXXXXXXX)" },
                    { name: "recipient_address", type: "string", required: true, description: "Delivery address (max 250)" },
                    { name: "cod_amount", type: "number", required: true, description: "Cash on delivery amount" },
                    { name: "note", type: "string", required: false, description: "Delivery note (max 255)" },
                ],
                requestExample: JSON.stringify(
                    {
                        invoice: "INV-1001",
                        recipient_name: "John Doe",
                        recipient_phone: "01712345678",
                        recipient_address: "House 12, Road 5, Dhaka",
                        cod_amount: 1200,
                        note: "Call before delivery",
                    },
                    null,
                    2,
                ),
                responseExample: success({ consignment_id: "SF123456", invoice: "INV-1001" }),
            },
            {
                id: "sf-bulk-order",
                name: "Create Bulk Orders",
                method: "POST",
                path: "/api/steadfast/create-bulk-order",
                auth: "full",
                description: "Creates multiple Steadfast orders in one request.",
                params: [
                    { name: "orders", type: "array", required: true, description: "Array of order objects (same fields as create-order)" },
                ],
                requestExample: JSON.stringify({ orders: [{ invoice: "INV-1", recipient_name: "...", recipient_phone: "01712345678", recipient_address: "...", cod_amount: 500 }] }, null, 2),
                responseExample: success([{ consignment_id: "SF123", status: "success" }]),
            },
            {
                id: "sf-check-status",
                name: "Check Status",
                method: "POST",
                path: "/api/steadfast/check-status",
                auth: "full",
                description: "Returns delivery status for a consignment ID.",
                params: [{ name: "consignment_id", type: "string", required: true, description: "Steadfast consignment ID" }],
                requestExample: JSON.stringify({ consignment_id: "SF123456" }, null, 2),
                responseExample: success("delivered"),
            },
            {
                id: "sf-bulk-status",
                name: "Bulk Check Status",
                method: "POST",
                path: "/api/steadfast/bulk-check-status",
                auth: "full",
                description: "Check status for multiple consignment or invoice IDs.",
                params: [
                    { name: "consignment_ids", type: "array", required: false, description: "List of consignment IDs" },
                    { name: "invoice_ids", type: "array", required: false, description: "List of invoice IDs" },
                ],
                requestExample: JSON.stringify({ consignment_ids: ["SF1", "SF2"] }, null, 2),
                responseExample: success({ SF1: "delivered", SF2: "pending" }),
            },
            {
                id: "sf-balance",
                name: "Check Balance",
                method: "POST",
                path: "/api/steadfast/check-balance",
                auth: "full",
                description: "Returns Steadfast merchant wallet balance.",
                responseExample: success({ balance: 5000 }),
            },
        ],
    },
    {
        id: "pathao",
        title: "Pathao Courier",
        description: "Orders, stores, and location data via Pathao API",
        icon: "PhMapPin",
        endpoints: [
            {
                id: "pathao-create-order",
                name: "Create Order",
                method: "POST",
                path: "/api/pathao/create-order",
                auth: "full",
                description: "Creates a Pathao delivery order using saved store and location settings.",
                params: [
                    { name: "invoice", type: "string", required: true, description: "Unique invoice reference" },
                    { name: "recipient_name", type: "string", required: true, description: "Customer name" },
                    { name: "recipient_phone", type: "string", required: true, description: "11-digit BD mobile" },
                    { name: "recipient_address", type: "string", required: true, description: "Full address (min 10 chars)" },
                    { name: "cod_amount", type: "number", required: true, description: "COD amount" },
                    { name: "note", type: "string", required: false, description: "Delivery instructions" },
                ],
                requestExample: JSON.stringify(
                    { invoice: "INV-2001", recipient_name: "Jane", recipient_phone: "01812345678", recipient_address: "Mirpur, Dhaka", cod_amount: 850 },
                    null,
                    2,
                ),
                responseExample: success({ consignment_id: "PA123", delivery_fee: 60 }),
            },
            {
                id: "pathao-bulk-order",
                name: "Create Bulk Orders",
                method: "POST",
                path: "/api/pathao/create-bulk-order",
                auth: "full",
                description: "Creates up to 200 Pathao orders in one request.",
                params: [{ name: "orders", type: "array", required: true, description: "Array of order objects" }],
                requestExample: JSON.stringify({ orders: [{ recipient_name: "...", recipient_phone: "01812345678", recipient_address: "...", cod_amount: 500 }] }, null, 2),
                responseExample: success([{ consignment_id: "PA1" }]),
            },
            {
                id: "pathao-check-status",
                name: "Check Status",
                method: "POST",
                path: "/api/pathao/check-status",
                auth: "full",
                params: [{ name: "consignment_id", type: "string", required: true, description: "Pathao consignment ID" }],
                requestExample: JSON.stringify({ consignment_id: "PA123456" }, null, 2),
                responseExample: success("Delivered"),
            },
            {
                id: "pathao-bulk-status",
                name: "Bulk Check Status",
                method: "POST",
                path: "/api/pathao/bulk-check-status",
                auth: "full",
                params: [{ name: "consignment_ids", type: "array", required: true, description: "List of consignment IDs" }],
                requestExample: JSON.stringify({ consignment_ids: ["PA1", "PA2"] }, null, 2),
                responseExample: success({ PA1: "Delivered", PA2: "Pending" }),
            },
            {
                id: "pathao-stores",
                name: "List Stores",
                method: "POST",
                path: "/api/pathao/stores",
                auth: "full",
                description: "Returns Pathao merchant stores. Requires saved Pathao login credentials.",
                responseExample: success([{ store_id: 1, store_name: "Main Store" }]),
            },
            {
                id: "pathao-cities",
                name: "List Cities",
                method: "POST",
                path: "/api/pathao/cities",
                auth: "full",
                responseExample: success([{ city_id: 1, city_name: "Dhaka" }]),
            },
            {
                id: "pathao-zones",
                name: "List Zones",
                method: "POST",
                path: "/api/pathao/zones",
                auth: "full",
                params: [{ name: "city_id", type: "integer", required: true, description: "Pathao city ID" }],
                requestExample: JSON.stringify({ city_id: 1 }, null, 2),
                responseExample: success([{ zone_id: 1, zone_name: "Mirpur" }]),
            },
            {
                id: "pathao-areas",
                name: "List Areas",
                method: "POST",
                path: "/api/pathao/areas",
                auth: "full",
                params: [{ name: "zone_id", type: "integer", required: true, description: "Pathao zone ID" }],
                requestExample: JSON.stringify({ zone_id: 1 }, null, 2),
                responseExample: success([{ area_id: 1, area_name: "Section 10" }]),
            },
            {
                id: "pathao-create-store",
                name: "Create Store",
                method: "POST",
                path: "/api/pathao/create-store",
                auth: "full",
                params: [
                    { name: "name", type: "string", required: true, description: "Store name" },
                    { name: "contact_name", type: "string", required: true, description: "Contact person" },
                    { name: "contact_number", type: "string", required: true, description: "Contact phone" },
                    { name: "address", type: "string", required: true, description: "Store address" },
                    { name: "city_id", type: "integer", required: true, description: "City ID" },
                    { name: "zone_id", type: "integer", required: true, description: "Zone ID" },
                    { name: "area_id", type: "integer", required: true, description: "Area ID" },
                ],
                responseExample: success({ store_id: 2 }, "Store created"),
            },
            {
                id: "pathao-price-plan",
                name: "Price Plan",
                method: "POST",
                path: "/api/pathao/price-plan",
                auth: "full",
                description: "Calculates delivery charge. Uses saved settings when fields are omitted.",
                params: [
                    { name: "store_id", type: "integer", required: false, description: "Defaults to saved store" },
                    { name: "recipient_city", type: "integer", required: false, description: "Destination city ID" },
                    { name: "recipient_zone", type: "integer", required: false, description: "Destination zone ID" },
                    { name: "item_weight", type: "number", required: false, description: "Weight in kg (default 0.5)" },
                    { name: "delivery_type", type: "integer", required: false, description: "48 = normal delivery" },
                ],
                responseExample: success({ price: 60, discount: 0 }),
            },
            {
                id: "pathao-balance",
                name: "Check Balance",
                method: "POST",
                path: "/api/pathao/check-balance",
                auth: "full",
                description: "Pathao does not expose a merchant balance API.",
                responseExample: success({ balance: null, balance_available: false, message: "Pathao does not expose a merchant balance API." }),
            },
        ],
    },
    {
        id: "redx",
        title: "RedX Courier",
        description: "RedX parcel creation and tracking",
        icon: "PhShippingContainer",
        endpoints: [
            {
                id: "redx-areas",
                name: "Get Areas",
                method: "POST",
                path: "/api/redx/get-areas",
                auth: "full",
                description: "Returns RedX delivery areas for order creation.",
                responseExample: success([{ id: 1, name: "Dhaka - Gulshan" }]),
            },
            {
                id: "redx-create-order",
                name: "Create Order",
                method: "POST",
                path: "/api/redx/create-order",
                auth: "full",
                params: [
                    { name: "customer_name", type: "string", required: true, description: "Recipient name" },
                    { name: "customer_phone", type: "string", required: true, description: "Recipient phone" },
                    { name: "delivery_area", type: "string", required: true, description: "Area name" },
                    { name: "delivery_area_id", type: "integer", required: true, description: "Area ID from get-areas" },
                    { name: "customer_address", type: "string", required: true, description: "Full address" },
                    { name: "cash_collection_amount", type: "number", required: true, description: "COD amount" },
                    { name: "parcel_weight", type: "number", required: true, description: "Weight in grams" },
                    { name: "value", type: "number", required: true, description: "Compensation value" },
                    { name: "merchant_invoice_id", type: "string", required: false, description: "Your invoice reference" },
                    { name: "instruction", type: "string", required: false, description: "Delivery note" },
                ],
                responseExample: success({ tracking_id: "RX123" }),
            },
            {
                id: "redx-track",
                name: "Track Parcel",
                method: "POST",
                path: "/api/redx/track-parcel",
                auth: "full",
                params: [{ name: "track_id", type: "string|array", required: true, description: "Single tracking ID or array for bulk" }],
                requestExample: JSON.stringify({ track_id: "20A316MOG0DI" }, null, 2),
                responseExample: success({ "20A316MOG0DI": [{ status: "delivered" }] }),
            },
            {
                id: "redx-bulk-order",
                name: "Create Bulk Orders",
                method: "POST",
                path: "/api/redx/create-bulk-order",
                auth: "full",
                description: "Validates bulk order payloads and returns the normalized array. Does not submit parcels to RedX yet.",
                params: [{ name: "orders", type: "array", required: true, description: "Array of order payloads" }],
                responseExample: success([]),
                notes: "Validation-only endpoint; upstream RedX bulk API is not wired.",
            },
            {
                id: "redx-balance",
                name: "Check Balance",
                method: "POST",
                path: "/api/redx/check-balance",
                auth: "full",
                description: "RedX does not expose a merchant balance API. Returns balance_available: false when configured.",
                responseExample: success({
                    balance: null,
                    balance_available: false,
                    message: "RedX does not expose a merchant balance API.",
                }),
            },
        ],
    },
    {
        id: "sms",
        title: "SMS",
        description: "Send SMS and manage recharge balance",
        icon: "PhChatText",
        endpoints: [
            {
                id: "sms-send",
                name: "Send SMS",
                method: "POST",
                path: "/api/sms/send",
                auth: "full",
                description: "Sends SMS via BulkSMS BD. Deducts balance at ৳0.40 per SMS segment per number.",
                params: [
                    { name: "phone", type: "string", required: true, description: "Single number or comma-separated list (017...,018...)" },
                    { name: "content", type: "string", required: true, description: "SMS message body" },
                ],
                requestExample: JSON.stringify({ phone: "01712345678", content: "Your order is confirmed!" }, null, 2),
                responseExample: success({ message_id: "abc123" }, "Sms sent successfully"),
            },
            {
                id: "sms-recharge",
                name: "Request Recharge",
                method: "POST",
                path: "/api/sms/recharge",
                auth: "full",
                params: [
                    { name: "total_amount", type: "number", required: true, description: "Recharge amount in BDT" },
                    { name: "total_charge", type: "number", required: true, description: "Transaction charge" },
                    { name: "account_number", type: "string", required: true, description: "Sender account number" },
                    { name: "transaction_id", type: "string", required: true, description: "Payment reference" },
                    { name: "transaction_method", type: "string", required: true, description: "bKash, Nagad, bank, etc." },
                ],
                responseExample: success({ id: 1, status: "pending" }),
            },
            {
                id: "sms-recharge-history",
                name: "Recharge History",
                method: "GET",
                path: "/api/sms/recharge-history",
                auth: "full",
                queryParams: [
                    { name: "start_date", type: "date", required: false, description: "Filter from date (YYYY-MM-DD)" },
                    { name: "end_date", type: "date", required: false, description: "Filter to date" },
                ],
                responseExample: success([{ id: 1, total_amount: 500, status: "approved" }]),
            },
            {
                id: "sms-use-history",
                name: "Use History",
                method: "GET",
                path: "/api/sms/use-history",
                auth: "full",
                queryParams: [
                    { name: "start_date", type: "date", required: false, description: "Filter from date" },
                    { name: "end_date", type: "date", required: false, description: "Filter to date" },
                ],
                responseExample: success([{ phone: "017...", sms_text: "...", amount: -0.4 }]),
            },
            {
                id: "sms-balance",
                name: "SMS Balance",
                method: "GET",
                path: "/api/sms/balance",
                auth: "full",
                description: "Current SMS wallet balance for the token domain.",
                responseExample: success(42.5),
            },
        ],
    },
    {
        id: "fraud",
        title: "Fraud Checker",
        description: "Customer delivery success rate lookup",
        icon: "PhUserCheck",
        endpoints: [
            {
                id: "fraud-check",
                name: "Fraud Check",
                method: "POST",
                path: "/api/fraud-check",
                auth: "full",
                description: "Checks customer delivery history across Pathao, Steadfast, and Paperfly. Pass a single phone or bulk data array.",
                params: [
                    { name: "phone", type: "string", required: false, description: "Single BD mobile number" },
                    { name: "data", type: "array", required: false, description: "Bulk: [{ id, phone }, ...] returns report per entry" },
                ],
                requestExample: JSON.stringify({ phone: "01712345678" }, null, 2),
                responseExample: JSON.stringify(
                    { confirmed: 12, cancel: 2, total_order: 14, success_rate: "85.71%" },
                    null,
                    2,
                ),
            },
            {
                id: "fraud-check-stream",
                name: "Fraud Check (SSE Stream)",
                method: "POST",
                path: "/api/fraud-check-stream",
                auth: "full",
                description:
                    "Server-Sent Events stream for bulk fraud checks. Emits user_report events with progress, then a done event.",
                params: [{ name: "data", type: "array", required: true, description: "[{ id, phone }, ...]" }],
                requestExample: JSON.stringify({ data: [{ id: 1, phone: "01712345678" }] }, null, 2),
                notes: "Content-Type: text/event-stream. Events: user_report, done.",
            },
        ],
    },
];

export const allEndpoints = apiCategories.flatMap((c) =>
    c.endpoints.map((e) => ({ ...e, categoryId: c.id, categoryTitle: c.title })),
);

export const authLabels: Record<AuthLevel, string> = {
    public: "Public",
    token: "Bearer + Origin",
    full: "Bearer + Origin + Sanctum",
};
