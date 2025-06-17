<?php
/**
 * Plugin Internationalization
 *
 * @package AI_Blog_Generator
 * @subpackage Includes
 */

namespace AI_Blog_Generator\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin I18n Class
 *
 * Define the internationalization functionality.
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since 1.0.0
 */
class Plugin_I18n {

	/**
	 * The domain specified for this plugin.
	 *
	 * @var string
	 */
	private $domain;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->domain = 'ai-blog-generator';
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		// Set the text domain.
		$domain = $this->domain;
		
		// First, try to load from the languages directory in WP_LANG_DIR.
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		$mofile = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
		
		if ( file_exists( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		} else {
			// Load from the plugin's languages directory.
			load_plugin_textdomain(
				$domain,
				false,
				dirname( plugin_basename( AI_BLOG_GENERATOR_PLUGIN_FILE ) ) . '/languages/'
			);
		}
	}

	/**
	 * Get the plugin text domain.
	 *
	 * @return string
	 */
	public function get_domain() {
		return $this->domain;
	}

	/**
	 * Register JavaScript translations.
	 */
	public function register_script_translations() {
		// Get all registered scripts.
		$scripts = [
			'ai-blog-generator-admin',
			'ai-blog-generator-ideas',
			'ai-blog-generator-contexts',
			'ai-blog-generator-dashboard',
		];
		
		foreach ( $scripts as $script_handle ) {
			wp_set_script_translations( 
				$script_handle, 
				$this->domain,
				AI_BLOG_GENERATOR_PLUGIN_DIR . 'languages'
			);
		}
	}

	/**
	 * Get all translatable strings for JavaScript.
	 *
	 * @return array
	 */
	public function get_js_translations() {
		return [
			// Common strings.
			'error'          => __( 'Error', 'ai-blog-generator' ),
			'success'        => __( 'Success', 'ai-blog-generator' ),
			'warning'        => __( 'Warning', 'ai-blog-generator' ),
			'info'           => __( 'Info', 'ai-blog-generator' ),
			'loading'        => __( 'Loading...', 'ai-blog-generator' ),
			'saving'         => __( 'Saving...', 'ai-blog-generator' ),
			'saved'          => __( 'Saved', 'ai-blog-generator' ),
			'cancel'         => __( 'Cancel', 'ai-blog-generator' ),
			'confirm'        => __( 'Confirm', 'ai-blog-generator' ),
			'delete'         => __( 'Delete', 'ai-blog-generator' ),
			'edit'           => __( 'Edit', 'ai-blog-generator' ),
			'update'         => __( 'Update', 'ai-blog-generator' ),
			'close'          => __( 'Close', 'ai-blog-generator' ),
			
			// API related.
			'api_error'      => __( 'API Error', 'ai-blog-generator' ),
			'api_connected'  => __( 'API Connected', 'ai-blog-generator' ),
			'api_failed'     => __( 'API Connection Failed', 'ai-blog-generator' ),
			'test_connection' => __( 'Test Connection', 'ai-blog-generator' ),
			
			// Ideas related.
			'approve'        => __( 'Approve', 'ai-blog-generator' ),
			'deny'           => __( 'Deny', 'ai-blog-generator' ),
			'generate_ideas' => __( 'Generate Ideas', 'ai-blog-generator' ),
			'no_ideas'       => __( 'No ideas found', 'ai-blog-generator' ),
			'idea_approved'  => __( 'Idea approved successfully', 'ai-blog-generator' ),
			'idea_denied'    => __( 'Idea denied successfully', 'ai-blog-generator' ),
			
			// Blog generation.
			'generating'     => __( 'Generating...', 'ai-blog-generator' ),
			'generate_blog'  => __( 'Generate Blog Post', 'ai-blog-generator' ),
			'publish'        => __( 'Publish', 'ai-blog-generator' ),
			'schedule'       => __( 'Schedule', 'ai-blog-generator' ),
			'draft'          => __( 'Save as Draft', 'ai-blog-generator' ),
			
			// Contexts.
			'add_context'    => __( 'Add Context', 'ai-blog-generator' ),
			'edit_context'   => __( 'Edit Context', 'ai-blog-generator' ),
			'delete_context' => __( 'Delete Context', 'ai-blog-generator' ),
			'context_saved'  => __( 'Context saved successfully', 'ai-blog-generator' ),
			'context_deleted' => __( 'Context deleted successfully', 'ai-blog-generator' ),
			
			// Costs and analytics.
			'total_cost'     => __( 'Total Cost', 'ai-blog-generator' ),
			'this_month'     => __( 'This Month', 'ai-blog-generator' ),
			'last_month'     => __( 'Last Month', 'ai-blog-generator' ),
			'budget_warning' => __( 'Budget limit approaching', 'ai-blog-generator' ),
			'budget_exceeded' => __( 'Budget limit exceeded', 'ai-blog-generator' ),
			
			// Confirmations.
			'confirm_delete' => __( 'Are you sure you want to delete this?', 'ai-blog-generator' ),
			'confirm_publish' => __( 'Are you sure you want to publish this post?', 'ai-blog-generator' ),
			'confirm_deny'   => __( 'Are you sure you want to deny this idea?', 'ai-blog-generator' ),
			
			// Error messages.
			'network_error'  => __( 'Network error. Please check your connection.', 'ai-blog-generator' ),
			'server_error'   => __( 'Server error. Please try again later.', 'ai-blog-generator' ),
			'validation_error' => __( 'Please check your input and try again.', 'ai-blog-generator' ),
			'permission_error' => __( 'You do not have permission to perform this action.', 'ai-blog-generator' ),
		];
	}

	/**
	 * Get date format based on locale.
	 *
	 * @return string
	 */
	public function get_date_format() {
		$locale = get_locale();
		
		// Define date formats for different locales.
		$formats = [
			'en_US' => 'm/d/Y',
			'en_GB' => 'd/m/Y',
			'de_DE' => 'd.m.Y',
			'fr_FR' => 'd/m/Y',
			'es_ES' => 'd/m/Y',
			'it_IT' => 'd/m/Y',
			'pt_BR' => 'd/m/Y',
			'ja'    => 'Y年m月d日',
			'zh_CN' => 'Y年m月d日',
			'ko_KR' => 'Y년 m월 d일',
		];
		
		// Return format for locale or default.
		return isset( $formats[ $locale ] ) ? $formats[ $locale ] : get_option( 'date_format' );
	}

	/**
	 * Get time format based on locale.
	 *
	 * @return string
	 */
	public function get_time_format() {
		$locale = get_locale();
		
		// Define time formats for different locales.
		$formats = [
			'en_US' => 'g:i A',  // 12-hour with AM/PM.
			'en_GB' => 'H:i',    // 24-hour.
			'de_DE' => 'H:i',    // 24-hour.
			'fr_FR' => 'H:i',    // 24-hour.
			'es_ES' => 'H:i',    // 24-hour.
			'it_IT' => 'H:i',    // 24-hour.
			'pt_BR' => 'H:i',    // 24-hour.
			'ja'    => 'H:i',    // 24-hour.
			'zh_CN' => 'H:i',    // 24-hour.
			'ko_KR' => 'H:i',    // 24-hour.
		];
		
		// Return format for locale or default.
		return isset( $formats[ $locale ] ) ? $formats[ $locale ] : get_option( 'time_format' );
	}

	/**
	 * Get currency symbol based on locale.
	 *
	 * @return string
	 */
	public function get_currency_symbol() {
		$locale = get_locale();
		
		// Define currency symbols for different locales.
		$symbols = [
			'en_US' => '$',
			'en_GB' => '£',
			'de_DE' => '€',
			'fr_FR' => '€',
			'es_ES' => '€',
			'it_IT' => '€',
			'pt_BR' => 'R$',
			'ja'    => '¥',
			'zh_CN' => '¥',
			'ko_KR' => '₩',
		];
		
		// Return symbol for locale or default to USD.
		return isset( $symbols[ $locale ] ) ? $symbols[ $locale ] : '$';
	}

	/**
	 * Format number based on locale.
	 *
	 * @param float $number Number to format.
	 * @param int   $decimals Number of decimal places.
	 * @return string
	 */
	public function format_number( $number, $decimals = 2 ) {
		$locale = get_locale();
		
		// Define number formats for different locales.
		$formats = [
			'en_US' => [ '.', ',' ],  // decimal: . thousands: ,
			'en_GB' => [ '.', ',' ],
			'de_DE' => [ ',', '.' ],  // decimal: , thousands: .
			'fr_FR' => [ ',', ' ' ],  // decimal: , thousands: space
			'es_ES' => [ ',', '.' ],
			'it_IT' => [ ',', '.' ],
			'pt_BR' => [ ',', '.' ],
			'ja'    => [ '.', ',' ],
			'zh_CN' => [ '.', ',' ],
			'ko_KR' => [ '.', ',' ],
		];
		
		// Get format for locale or use default.
		$format = isset( $formats[ $locale ] ) ? $formats[ $locale ] : [ '.', ',' ];
		
		return number_format( $number, $decimals, $format[0], $format[1] );
	}

	/**
	 * Get RTL languages.
	 *
	 * @return array
	 */
	public function get_rtl_languages() {
		return [
			'ar',    // Arabic.
			'he_IL', // Hebrew.
			'fa_IR', // Persian.
			'ur',    // Urdu.
		];
	}

	/**
	 * Check if current locale is RTL.
	 *
	 * @return bool
	 */
	public function is_rtl() {
		$locale = get_locale();
		$rtl_languages = $this->get_rtl_languages();
		
		// Check if locale or language code is in RTL list.
		return in_array( $locale, $rtl_languages, true ) || 
		       in_array( substr( $locale, 0, 2 ), $rtl_languages, true );
	}
} 