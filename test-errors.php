<?php
/**
 * Error Display Test File
 * 
 * Access this file directly in your browser to test error display:
 * http://yoursite.com/wp-content/plugins/blog-generator/test-errors.php
 * 
 * If errors are displayed on screen, your configuration is correct.
 * Remember to delete this file when done testing!
 */

// Include WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You must be an administrator to run this test.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>AI Blog Generator - Error Display Test</title>
	<style>
		body {
			font-family: Arial, sans-serif;
			margin: 40px;
			background: #f1f1f1;
		}
		.container {
			background: white;
			padding: 20px;
			border-radius: 5px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		h1 { color: #333; }
		.test { 
			margin: 20px 0; 
			padding: 15px;
			background: #f9f9f9;
			border-left: 4px solid #0073aa;
		}
		.error { border-left-color: #dc3232; }
		.warning { border-left-color: #ffb900; }
		.success { border-left-color: #46b450; }
		pre { background: #eee; padding: 10px; overflow: auto; }
		.status { margin: 10px 0; }
		.status-on { color: #46b450; font-weight: bold; }
		.status-off { color: #dc3232; font-weight: bold; }
	</style>
</head>
<body>
	<div class="container">
		<h1>AI Blog Generator - Error Display Test</h1>
		
		<div class="test">
			<h2>Current Error Reporting Settings</h2>
			<div class="status">
				WP_DEBUG: <span class="<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'status-on' : 'status-off'; ?>">
					<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'ON' : 'OFF'; ?>
				</span>
			</div>
			<div class="status">
				WP_DEBUG_DISPLAY: <span class="<?php echo defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'status-on' : 'status-off'; ?>">
					<?php echo defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'ON' : 'OFF'; ?>
				</span>
			</div>
			<div class="status">
				display_errors: <span class="<?php echo ini_get('display_errors') ? 'status-on' : 'status-off'; ?>">
					<?php echo ini_get('display_errors') ? 'ON' : 'OFF'; ?>
				</span>
			</div>
			<div class="status">
				error_reporting level: <strong><?php echo error_reporting(); ?></strong>
			</div>
		</div>

		<div class="test warning">
			<h2>Test 1: PHP Warning</h2>
			<p>This should display a warning about an undefined variable:</p>
			<pre>
<?php
// This will generate a warning
echo "Undefined variable test: " . $undefined_variable;
?>
			</pre>
		</div>

		<div class="test warning">
			<h2>Test 2: PHP Notice</h2>
			<p>This should display a notice about an undefined array key:</p>
			<pre>
<?php
// This will generate a notice
$array = array();
echo "Undefined array key test: " . $array['undefined_key'];
?>
			</pre>
		</div>

		<div class="test error">
			<h2>Test 3: PHP Error</h2>
			<p>This should display an error about calling an undefined function:</p>
			<pre>
<?php
// This will generate a fatal error - uncomment to test
// undefined_function();
echo "Uncomment the line above to test fatal error display";
?>
			</pre>
		</div>

		<div class="test">
			<h2>Test 4: Plugin Classes</h2>
			<p>Testing if plugin classes can be loaded:</p>
			<pre>
<?php
// Test autoloader
try {
	if ( class_exists( 'AI_Blog_Generator\Utilities\Logger' ) ) {
		echo "✓ Logger class found\n";
	} else {
		echo "✗ Logger class not found\n";
	}
	
	if ( class_exists( 'AI_Blog_Generator\Models\Database_Manager' ) ) {
		echo "✓ Database_Manager class found\n";
	} else {
		echo "✗ Database_Manager class not found\n";
	}
	
	if ( class_exists( 'AI_Blog_Generator\Includes\Plugin_Loader' ) ) {
		echo "✓ Plugin_Loader class found\n";
	} else {
		echo "✗ Plugin_Loader class not found\n";
	}
} catch ( Exception $e ) {
	echo "Error: " . $e->getMessage();
}
?>
			</pre>
		</div>

		<div class="test success">
			<h2>Recommendations</h2>
			<?php if ( defined('WP_DEBUG') && WP_DEBUG ): ?>
				<p>✓ WP_DEBUG is enabled. You should see errors displayed above.</p>
			<?php else: ?>
				<p>✗ WP_DEBUG is disabled. To enable error display:</p>
				<ol>
					<li>Open your wp-config.php file</li>
					<li>Add: <code>define( 'WP_DEBUG', true );</code></li>
					<li>Add: <code>define( 'WP_DEBUG_DISPLAY', true );</code></li>
					<li>Save and refresh this page</li>
				</ol>
			<?php endif; ?>
			
			<p><strong>Remember:</strong> Delete this test file when you're done, and disable debug mode on production sites!</p>
		</div>
	</div>
</body>
</html> 
 
 