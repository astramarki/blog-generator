# AI Blog Generator - Debug Guide

## Enabling Error Display

Since you don't have access to system logs, follow these steps to display all PHP errors and warnings on screen:

### 1. Enable WordPress Debug Mode

Edit your `wp-config.php` file (located in your WordPress root directory) and add these lines **before** the line that says `/* That's all, stop editing! Happy publishing. */`:

```php
// Enable WordPress debugging
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DEBUG_LOG', true );
```

### 2. Verify Error Display is Working

1. Navigate to: `http://yoursite.com/wp-content/plugins/blog-generator/test-errors.php`
2. Log in as an administrator
3. You should see:
   - Current error reporting settings
   - Test warnings and notices displayed on screen
   - Plugin class loading status

### 3. Error Locations

When errors occur, they will appear in these locations:

- **On Screen**: Errors will be displayed directly on your WordPress pages (admin and frontend)
- **WordPress Debug Log**: `/wp-content/debug.log`
- **Plugin Debug Log**: `/wp-content/plugins/blog-generator/debug.log`

### 4. Common Errors and Solutions

#### "Class not found" errors
- The autoloader couldn't find the class file
- Check that the class file exists in the correct directory
- Verify the filename follows the pattern: `class-[classname].php`

#### Database errors
- Check that plugin tables were created during activation
- Deactivate and reactivate the plugin to recreate tables
- Look for SQL errors in the debug output

#### Permission errors
- Ensure the plugin directory has proper write permissions
- WordPress needs to create log files and upload directories

### 5. Development Configuration

Copy the settings from `wp-config-dev.example.php` to your `wp-config.php` for full development mode:

```php
// Additional development settings
define( 'SCRIPT_DEBUG', true );
define( 'SAVEQUERIES', true );
@ini_set( 'display_errors', 'On' );
@ini_set( 'error_reporting', E_ALL );
```

### 6. Disable Debug Mode for Production

**IMPORTANT**: Before going live, disable debug mode:

```php
define( 'WP_DEBUG', false );
```

Or comment out all debug lines in `wp-config.php`.

### 7. Clean Up

After debugging:
1. Delete the `test-errors.php` file
2. Clear any debug logs
3. Disable debug mode
4. Test the plugin functionality

## Troubleshooting Tips

1. **White Screen of Death**: Add the debug constants to wp-config.php to see the actual error
2. **Plugin Won't Activate**: Check PHP version (requires 7.4+) and WordPress version (requires 5.8+)
3. **AJAX Errors**: Open browser console (F12) to see JavaScript errors
4. **Database Issues**: Use phpMyAdmin to check if tables were created with prefix `wp_ai_blog_`

## Need Help?

If you're still experiencing issues:
1. Check the WordPress debug log at `/wp-content/debug.log`
2. Check the plugin debug log at `/wp-content/plugins/blog-generator/debug.log`
3. Review the error messages displayed on screen
4. Ensure all required PHP extensions are installed: curl, json, mbstring 
 
 