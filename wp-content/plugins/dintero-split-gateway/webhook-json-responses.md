# Dintero Webhook JSON Responses

## Overview

The Dintero webhook now returns JSON responses instead of plain text. This provides better error handling and more detailed information about the processing results.

## Response Format

### Success Response

When a webhook is processed successfully:

```json
{
    "success": true,
    "message": "Payment captured successfully",
    "timestamp": "2024-01-01 12:00:00",
    "data": {
        "order_id": "123",
        "status": "processing"
    }
}
```

### Error Response

When an error occurs during processing:

```json
{
    "success": false,
    "error": "Order not found: 999999",
    "code": 404,
    "timestamp": "2024-01-01 12:00:00"
}
```

## Response Fields

### Success Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Always `true` for successful responses |
| `message` | string | Human-readable success message |
| `timestamp` | string | ISO timestamp of when the response was generated |
| `data` | object | Additional data about the processed order (optional) |

### Error Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Always `false` for error responses |
| `error` | string | Human-readable error message |
| `code` | integer | HTTP status code (400, 404, 500, etc.) |
| `timestamp` | string | ISO timestamp of when the response was generated |

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success - Webhook processed successfully |
| 400 | Bad Request - Invalid data or missing required fields |
| 404 | Not Found - Order not found in the system |
| 500 | Internal Server Error - Server error during processing |

## Example Responses

### Payment Captured Successfully

**Request:**
```
GET /dintero-webhook/?orderid=123&status=captured&amount=1000&currency=NOK
```

**Response:**
```json
{
    "success": true,
    "message": "Payment captured successfully",
    "timestamp": "2024-01-01 12:00:00",
    "data": {
        "order_id": "123",
        "status": "processing"
    }
}
```

### Order Not Found

**Request:**
```
GET /dintero-webhook/?orderid=999999&status=captured
```

**Response:**
```json
{
    "success": false,
    "error": "Order not found: 999999",
    "code": 404,
    "timestamp": "2024-01-01 12:00:00"
}
```

### Missing Order ID

**Request:**
```
GET /dintero-webhook/?status=captured&amount=1000
```

**Response:**
```json
{
    "success": false,
    "error": "No order ID found in webhook data",
    "code": 400,
    "timestamp": "2024-01-01 12:00:00"
}
```

## Testing

You can test the JSON responses using the test script:

```
https://yourdomain.com/wp-content/plugins/dintero-split-gateway/test-json-webhook.php
```

This script will test various scenarios and show you the actual JSON responses returned by the webhook.

## Integration Notes

- Always check the `success` field first to determine if the request was successful
- Use the `code` field for proper HTTP status code handling
- The `message` or `error` field provides human-readable information
- The `data` field contains additional information when available
- All responses include a `timestamp` for logging and debugging purposes 