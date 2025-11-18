# Core PHP API Server

A pure PHP API server implementation that connects to the WordPress database without using WordPress functions. Built with core PHP and PDO for database operations.

## Features

- **Pure Core PHP**: No WordPress dependencies, built with native PHP
- **WordPress Database Integration**: Connects to WordPress database using PDO
- **CRUD Operations**: Complete Create, Read, Update, Delete functionality
- **Search Capabilities**: Full-text search across data
- **Pagination Support**: Built-in pagination for large datasets
- **Input Validation**: Comprehensive input sanitization and validation
- **Error Handling**: Standardized error responses with proper HTTP status codes
- **CORS Support**: Cross-origin resource sharing enabled
- **Security**: SQL injection prevention, XSS protection, input sanitization

## File Structure

```
react-modules-plugin/server/
├── Database.php              # Database operations using PDO
├── Response.php              # Response handling and validation
├── ApiHandler.php            # Main API logic
├── api-endpoint.php          # Main API endpoint
├── test-api.php              # Test and documentation file
└── README.md                 # This file
```

## Installation

1. The server files are already in the `react-modules-plugin/server/` directory
2. No additional installation required - it's ready to use
3. The API will automatically create the required database table on first use

## API Endpoint

**Main Endpoint**: `/wp-content/plugins/react-modules-plugin/server/api-endpoint.php`

## API Actions

### GET Actions
- `get_data` - Retrieve all data with pagination
- `get_data_by_id` - Get specific data by ID
- `search_data` - Search data by title or content
- `get_users` - Get WordPress users
- `get_posts` - Get WordPress posts
- `get_data_count` - Get total data count
- `test_connection` - Test database connection

### POST Actions
- `create_data` - Create new data entry
- `create_table` - Create the database table

### PUT Actions
- `update_data` - Update existing data

### DELETE Actions
- `delete_data` - Delete data by ID

## Usage Examples

### Test Connection
```bash
curl -X GET '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=test_connection'
```

### Create Database Table
```bash
curl -X POST '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php' \
  -H 'Content-Type: application/json' \
  -d '{"action": "create_table"}'
```

### Get All Data
```bash
curl -X GET '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=get_data&page=1&limit=10'
```

### Create New Data
```bash
curl -X POST '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php' \
  -H 'Content-Type: application/json' \
  -d '{
    "action": "create_data",
    "title": "Test Title",
    "content": "Test Content"
  }'
```

### Update Data
```bash
curl -X PUT '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php' \
  -H 'Content-Type: application/json' \
  -d '{
    "action": "update_data",
    "id": 1,
    "title": "Updated Title",
    "content": "Updated Content"
  }'
```

### Delete Data
```bash
curl -X DELETE '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php' \
  -H 'Content-Type: application/json' \
  -d '{
    "action": "delete_data",
    "id": 1
  }'
```

### Search Data
```bash
curl -X GET '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=search_data&search=test&limit=5'
```

### Get WordPress Users
```bash
curl -X GET '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=get_users&limit=10'
```

### Get WordPress Posts
```bash
curl -X GET '/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=get_posts&limit=10'
```

## JavaScript/Fetch API Examples

```javascript
// Test connection
fetch('/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=test_connection')
  .then(response => response.json())
  .then(data => console.log(data));

// Get all data
fetch('/wp-content/plugins/react-modules-plugin/server/api-endpoint.php?action=get_data')
  .then(response => response.json())
  .then(data => console.log(data));

// Create new data
fetch('/wp-content/plugins/react-modules-plugin/server/api-endpoint.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    action: 'create_data',
    title: 'New Title',
    content: 'New Content'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Response Format

All API responses follow this standardized format:

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data here
  },
  "timestamp": "2024-01-01 12:00:00",
  "status_code": 200
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": "Error description"
  },
  "timestamp": "2024-01-01 12:00:00",
  "status_code": 400
}
```

## Database Schema

The API creates a custom table `wp_gibbs_api_data` with the following structure:

```sql
CREATE TABLE wp_gibbs_api_data (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    content text NOT NULL,
    status varchar(50) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Database Connection

The API automatically reads WordPress database configuration from `wp-config.php` and connects using PDO:

- **Host**: From `DB_HOST` constant
- **Database**: From `DB_NAME` constant  
- **Username**: From `DB_USER` constant
- **Password**: From `DB_PASSWORD` constant
- **Table Prefix**: From `$table_prefix` variable

## Testing

Visit the test file to verify the API is working:
`/wp-content/plugins/react-modules-plugin/server/test-api.php`

This file will:
- Test database connectivity
- Create sample data
- Display usage examples
- Show available endpoints

## Security Features

### Input Sanitization
- All string inputs are sanitized using `strip_tags()` and `htmlspecialchars()`
- Null bytes are removed
- Input is trimmed of whitespace

### SQL Injection Prevention
- Uses PDO prepared statements with parameter binding
- All database queries use parameterized queries
- No direct string concatenation in SQL

### XSS Protection
- Output is properly escaped using `htmlspecialchars()`
- HTML tags are stripped from input

### Error Handling
- Comprehensive exception handling
- No sensitive information leaked in error messages
- Proper HTTP status codes returned

## Error Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `422` - Validation Error
- `500` - Internal Server Error

## Customization

### Adding New Actions

1. Add the action to the appropriate handler method in `ApiHandler.php`
2. Create the corresponding method in the `CoreApiHandler` class
3. Add any required database methods in `Database.php`

### Extending Database Operations

Add new methods to the `CoreDatabase` class:

```php
public function customOperation($params) {
    $sql = "SELECT * FROM {$this->table_name} WHERE custom_field = :custom_field";
    
    try {
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':custom_field', $params['custom_field'], PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        throw new Exception('Failed to execute custom operation: ' . $e->getMessage());
    }
}
```

### Custom Response Headers

Modify the `sendResponse` method in `Response.php` to add custom headers:

```php
header('X-Custom-Header: Custom Value');
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**: Ensure `wp-config.php` is accessible and contains valid database credentials
2. **Permission Denied**: Check file permissions on the server directory
3. **404 Errors**: Verify the file paths are correct
4. **CORS Issues**: Check that CORS headers are being sent correctly

### Debug Mode

The API includes error reporting by default. Check the response for detailed error messages.

## Performance

- Uses PDO for efficient database connections
- Prepared statements for optimal query performance
- Connection pooling through PDO
- Minimal memory footprint

## Requirements

- PHP 7.4 or higher
- PDO extension enabled
- MySQL/MariaDB database
- Access to WordPress `wp-config.php` file

## License

This API server is part of the react-modules-plugin and follows the same license terms. 