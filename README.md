# QA Automation Dashboard v2.0

A modular, scalable QA testing system for Jarir.com with 13 embedded testing tools.

## Features

- **13 Testing Tools**: Brand Links, CMS Blocks, Category Links, Filtered Category, Get Categories, Images, Login, Price Checker, Products, SKU Lookup, Stock/Availability, Subcategories, Add to Cart
- **Run All Tests**: Execute multiple tools sequentially with proper async handling
- **Configuration Management**: Save and manage test configurations for each tool
- **Test Run Logging**: Track all test runs with detailed results
- **Reporting**: Generate detailed PDF-style reports for each run
- **Support Center**: Integrated ticketing system with threaded messaging, admin/user roles, and real-time notifications.
- **User Management**: Manage testers and their roles

## File Structure

## File Structure

```
qa-automation/
├── dashboard.php          # Main dashboard application (formerly qa-dash9)
├── index.php              # Login page & Entry point
├── auth_session.php       # Authentication & DB Connection
├── qa_run_report.php      # Report generator
├── system_architecture.md # Technical documentation
├── README.md              # This file
└── tools/                 # Individual tool HTML files
```

## Installation

1. Upload all files to your web server
2. Ensure the `tools/` directory is in the same location as `dashboard.php`
3. Update database credentials in `auth_session.php` if needed
4. Access `index.php` in your browser

## Database Configuration

The system uses MySQL with auto-initialization. Update these constants in `qa-dashboard.php`:

```php
const QA_DB_HOST = 'your-host';
const QA_DB_PORT = 3306;
const QA_DB_NAME = 'your-database';
const QA_DB_USER = 'your-user';
const QA_DB_PASS = 'your-password';
```

## Usage

### Running Individual Tools

1. Click on a tool tile in the "Test Modules Overview" section
2. The tool will load in the iframe
3. Enter the required inputs (URLs, SKUs, etc.)
4. Click the tool's run button

### Running All Tests

1. Check the boxes next to the tools you want to run
2. Ensure each selected tool has a saved configuration
3. Click "Run All Tests"
4. The system will execute each tool sequentially and save results

### Creating Configurations

1. Go to the "Configurations" tab
2. Enter a configuration name
3. Select the tool
4. Enter the required inputs (URLs, SKUs, etc.)
5. Click "Save Configuration"

### Viewing Reports

1. In the "Test Runs" section, click "Report" next to any run
2. A detailed HTML report will open in a new tab
3. Use the "Print Report" button to save as PDF

## Key Fixes in v2.0

### Run All Tests Fix

The main issue in the previous version was that the "Run All Tests" feature would fail after the first tool because:

1. The iframe wasn't being properly reset between tool runs
2. Configuration wasn't being reapplied after loading new tools
3. There was no proper mechanism to detect when async tools completed
4. Alert messages (like "Please enter at least one valid URL") were blocking execution

**Solution implemented:**

- Added proper iframe load event handling with cleanup
- Implemented polling mechanism to detect test completion
- Override alert() in tool context to capture validation errors without blocking
- Added delays between tool runs to ensure clean state
- Added timeout handling (2 minutes per tool)
- Added progress indicator during multi-tool runs

## Troubleshooting

### "Please enter at least one valid URL" error

This error occurs when a tool doesn't have proper configuration. Solutions:

1. Go to the Configurations tab
2. Create a configuration for the failing tool
3. Enter valid URLs/inputs in the configuration
4. Enable the configuration
5. Try "Run All Tests" again

### Tools timing out

If tests are taking too long:

1. Reduce the number of URLs/SKUs in the configuration
2. Run tools individually first to verify they work
3. Check network connectivity to Jarir.com APIs

### Database errors

1. Verify database credentials are correct
2. Ensure the database user has CREATE TABLE permissions
3. Check that the database server is accessible

## API Endpoints

The dashboard provides these internal API endpoints:

- `?api=list-configs` - Get all configurations
- `?api=save-config` - Create/update configuration
- `?api=delete-config` - Delete configuration
- `?api=list-runs` - Get test runs
- `?api=run-details` - Get run details
- `?api=save-run` - Create/update test run
- `?api=delete-run` - Delete test run
- `?api=stats` - Get dashboard statistics
- `?api=list-users` - Get users
- `?api=save-user` - Create/update user
- `?api=delete-user` - Delete user

## Adding New Tools

To add a new testing tool:

1. Create a new HTML file in the `tools/` directory (e.g., `new_tool.html`)
2. Add the tool definition to `$TOOL_DEFS` array in `qa-dashboard.php`:
   ```php
   ['code' => 'new_tool', 'name' => 'New Tool'],
   ```
3. Ensure your tool exposes a `run()` function
4. Add loading indicator with `id="loading"`
5. Add results container with `id="results"` containing `<li>` elements
6. Use `.ok`, `.warn`, `.err` classes for status badges

## License

Internal use only - Jarir.com QA Team

## Version History

- **v2.0.0** - Fixed Run All Tests, modular architecture, improved reporting
- **v1.0.0** - Stable Release: Support Section Overhaul (Threading, UI Polish, Notifications), 13 Tools.
