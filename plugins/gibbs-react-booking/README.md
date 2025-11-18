# React Modules Plugin

A WordPress plugin that provides React.js modules accessible via shortcodes with API data integration.

## Features

- **React.js Integration**: Uses React 18 for modern, interactive components
- **Shortcode Support**: Easy to use shortcodes for embedding React components
- **REST API Integration**: Components fetch data from WordPress REST API
- **Responsive Design**: Mobile-friendly components with modern styling
- **Multiple Modules**: Dashboard, Chart, and Form components included
- **Extensible**: Easy to add new modules and components

## Installation

1. Upload the `react-modules-plugin` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically load React.js and ReactDOM from CDN



## Available Shortcodes

### 1. Dashboard Module
Displays site statistics and recent posts in a beautiful dashboard layout.

```php
[react_dashboard]
```

### 2. Chart Module
Displays a bar chart with sample data (easily customizable).

```php
[react_chart]
```

### 3. Form Module
Displays a contact form with dynamic field configuration.

```php
[react_form]
```

### 4. Generic Module
Use any module type with the generic shortcode.

```php
[react_module module="dashboard"]
[react_module module="chart"]
[react_module module="form"]
```

## API Endpoints

The plugin creates REST API endpoints for data retrieval:

- `GET /wp-json/rmp/v1/data/dashboard` - Dashboard data
- `GET /wp-json/rmp/v1/data/chart` - Chart data
- `GET /wp-json/rmp/v1/data/form` - Form configuration

## Customization

### Adding New Modules

1. **Add PHP Data Handler**: Extend the `get_module_data_by_type()` method in the main plugin file:

```php
case 'your_module':
    return array(
        'your_data' => 'your_value',
        'another_data' => 'another_value'
    );
```

2. **Add React Component**: Create a new component in `assets/js/components.js`:

```javascript
function YourModule({ containerId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        async function loadData() {
            const result = await fetchModuleData('your_module');
            setData(result);
            setLoading(false);
        }
        loadData();
    }, []);

    if (loading) return <div className="rmp-loading">Loading...</div>;
    if (!data) return <div className="rmp-error">No data available</div>;

    return (
        <div className="rmp-your-module">
            <h2>Your Module</h2>
            {/* Your component content */}
        </div>
    );
}
```

3. **Add to Module Switch**: Update the `ReactModule` component:

```javascript
case 'your_module':
    return h(YourModule, { containerId });
```

4. **Register Shortcode**: Add a new shortcode in the `init()` method:

```php
add_shortcode('react_your_module', array($this, 'render_your_module'));
```

5. **Add Shortcode Method**:

```php
public function render_your_module($atts) {
    return $this->render_react_module(array_merge($atts, array('module' => 'your_module')));
}
```

### Styling

Customize the appearance by modifying `assets/css/style.css`. The plugin uses CSS classes prefixed with `rmp-` for easy targeting.

## File Structure

```
react-modules-plugin/
├── react-modules-plugin.php    # Main plugin file
├── index.php                   # Security file
├── README.md                   # This file
└── assets/
    ├── index.php              # Security file
    ├── js/
    │   ├── index.php          # Security file
    │   └── components.js      # React components
    └── css/
        ├── index.php          # Security file
        └── style.css          # Component styles
```

## Browser Support

- Modern browsers with ES6+ support
- React 18 compatibility
- Mobile responsive design

## Security Features

- Nonce verification for AJAX requests
- Input sanitization
- Direct file access prevention
- WordPress security best practices

## Performance

- React and ReactDOM loaded from CDN
- Optimized component rendering
- Efficient data fetching
- Minimal impact on page load

## Troubleshooting

### Component Not Loading
1. Check browser console for JavaScript errors
2. Verify React and ReactDOM are loading correctly
3. Ensure shortcode is properly formatted

### API Data Not Loading
1. Check WordPress REST API is enabled
2. Verify API endpoints are accessible
3. Check browser network tab for API errors

### Styling Issues
1. Ensure CSS file is loading correctly
2. Check for theme CSS conflicts
3. Verify CSS classes are applied

### Slot Booking Issues

#### "window.rmpSlotBookingInit is not a function" Error
This error occurs when the JavaScript function isn't loaded before the shortcode tries to use it. The plugin now includes automatic retry logic, but if you still encounter issues:

1. **Check script loading order**: Ensure the plugin scripts are loaded before any shortcode initialization
2. **Clear browser cache**: Hard refresh the page (Ctrl+F5 or Cmd+Shift+R)
3. **Check for JavaScript conflicts**: Disable other plugins temporarily to identify conflicts
4. **Verify file paths**: Ensure the built JavaScript files exist in the assets directory

#### Testing Slot Booking Functionality
Use the included test file to verify everything is working:

```php
// Include the test file in your theme or create a test page
include_once(WP_PLUGIN_DIR . '/gibbs-react-booking/test-slot-booking.php');
```

Or access the test directly at: `/wp-content/plugins/gibbs-react-booking/test-slot-booking.php`

#### Manual Function Call
If you need to call the function manually:

```javascript
// Wait for the function to be available
function initSlotBooking() {
    if (typeof window.rmpSlotBookingInit === 'function') {
        window.rmpSlotBookingInit('container-id', pageId);
    } else {
        setTimeout(initSlotBooking, 100);
    }
}
initSlotBooking();
```

## Support

For issues or questions:
1. Check the browser console for errors
2. Verify WordPress debug mode is enabled
3. Test with a default theme to rule out conflicts

## Changelog

### Version 1.0.0
- Initial release
- Dashboard, Chart, and Form modules
- REST API integration
- Responsive design
- Shortcode support

## License

This plugin is provided as-is for educational and development purposes. 