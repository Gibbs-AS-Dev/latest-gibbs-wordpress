# DIBS Payment Gateway Plugin

A WooCommerce payment gateway plugin that integrates with DIBS Payment API (api.dibspayment.com), providing split payment functionality similar to the Dintero Split Gateway plugin.

## Features

- **Payment Processing**: Full integration with DIBS Payment API
- **Split Payments**: Support for splitting payments between admin and sellers
- **Webhook Handling**: Automatic order status updates via webhooks
- **Admin Settings**: Comprehensive configuration options for test and live environments
- **User Management**: Frontend interface for sellers to manage their payment settings
- **Security**: Webhook signature verification and secure API communication
- **Responsive Design**: Mobile-friendly interface

## Installation

1. Upload the `nets-easy-gateway` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in WooCommerce > Settings > Payments

## Configuration

### Admin Settings

Navigate to WooCommerce > Settings > Payments > Nets Easy Gateway to configure:

- **Enable/Disable**: Toggle the payment gateway on/off
- **Title**: Display name for the payment method
- **Description**: Description shown to customers
- **Environment**: Choose between Test and Live modes
- **API Credentials**: 
  - Test/Live Secret Key
  - Test/Live Checkout Key
  - Merchant ID
- **Split Percentage**: Percentage of payment that goes to admin (default: 20%)
- **Webhook Secret**: Secret key for webhook verification

### API Credentials

You'll need to obtain the following from Nets Easy:

1. **Secret Key**: Used for API authentication
2. **Checkout Key**: Used for creating checkout sessions
3. **Merchant ID**: Your unique merchant identifier
4. **Webhook Secret**: For verifying webhook signatures

## Usage

### For Administrators

1. Configure the plugin settings with your Nets Easy credentials
2. Set the desired split percentage for admin payments
3. Test the integration using test credentials
4. Switch to live mode when ready for production

### For Sellers

Sellers can manage their payment settings through the frontend interface:

1. Navigate to their account settings
2. Configure bank account details
3. Enable/disable Nets Easy payment method
4. Create seller accounts for split payments

## Webhook Configuration

The plugin automatically creates a webhook endpoint at:
```
https://yoursite.com/dibs-payment-webhook/
```

Configure this URL in your DIBS Payment merchant dashboard to receive payment notifications.

## File Structure

```
nets-easy-gateway/
├── nets-easy-gateway.php          # Main plugin file
├── includes/
│   ├── class-nets-easy-gateway.php        # Payment gateway class
│   ├── class-nets-easy-api.php            # API communication class
│   ├── class-nets-easy-webhook-handler.php # Webhook processing
│   └── class-nets-easy-frontend.php       # Frontend functionality
├── assets/
│   ├── css/
│   │   └── nets-easy-frontend.css         # Frontend styles
│   └── js/
│       └── nets-easy-frontend.js          # Frontend JavaScript
└── README.md                              # This file
```

## API Integration

The plugin integrates with DIBS Payment API endpoints:

- **Authentication**: `/v1/merchants/{merchantId}/auth/token`
- **Create Checkout**: `/v1/payments`
- **Get Payment**: `/v1/payments/{paymentId}`
- **Capture Payment**: `/v1/payments/{paymentId}/charges`
- **Refund Payment**: `/v1/payments/{paymentId}/refunds`

**API Base URLs:**
- Test: `https://test.api.dibspayment.com/`
- Live: `https://api.dibspayment.com/`

## Webhook Events

The plugin handles the following webhook events:

- `payment.checkout.completed`: Payment successfully completed
- `payment.charge.created`: Payment captured
- `payment.charge.failed`: Payment failed
- `payment.refund.created`: Payment refunded

## Split Payment Logic

The plugin implements split payments as follows:

1. **Admin Split**: Configurable percentage (default 20%) goes to admin
2. **Seller Split**: Remaining amount goes to the seller
3. **Automatic Calculation**: Splits are calculated automatically based on order total
4. **Metadata Storage**: Split information is stored in order metadata

## Security Features

- **Webhook Verification**: Signature verification for incoming webhooks
- **Nonce Protection**: CSRF protection for AJAX requests
- **Data Sanitization**: All user inputs are sanitized
- **Secure API Calls**: HTTPS-only communication with Nets Easy API

## Error Handling

The plugin includes comprehensive error handling:

- **API Errors**: Graceful handling of API communication failures
- **Validation**: Form validation with user-friendly error messages
- **Logging**: Detailed error logging for debugging
- **Fallbacks**: Graceful degradation when services are unavailable

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+
- cURL extension
- JSON extension

## Troubleshooting

### Common Issues

1. **Payment Not Processing**
   - Check API credentials
   - Verify webhook URL configuration
   - Check error logs

2. **Webhook Not Working**
   - Verify webhook URL is accessible
   - Check webhook secret configuration
   - Ensure rewrite rules are flushed

3. **Split Payments Not Working**
   - Verify seller has payout destination configured
   - Check split percentage settings
   - Ensure booking data exists

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and questions:

1. Check the error logs in `/wp-content/debug.log`
2. Verify all settings are correctly configured
3. Test with Nets Easy test environment first
4. Contact Nets Easy support for API-related issues

## Changelog

### Version 1.0
- Initial release
- Basic payment processing
- Split payment functionality
- Webhook handling
- Admin and frontend interfaces

## License

This plugin is provided as-is for educational and development purposes. Please ensure compliance with Nets Easy terms of service and applicable regulations.
