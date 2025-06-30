<?php
/**
 * Settings Page View
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get settings with defaults.
$settings = [
	'anthropic_api_key' => get_option( 'ai_blog_generator_anthropic_api_key', '' ),
	'anthropic_model' => get_option( 'ai_blog_generator_anthropic_model', 'claude-sonnet-4-20250514' ),
	'openai_api_key' => get_option( 'ai_blog_generator_openai_api_key', '' ),
	'ideas_per_day' => get_option( 'ai_blog_generator_ideas_per_day', 5 ),
	'posts_per_day' => get_option( 'ai_blog_generator_posts_per_day', 10 ),
	'auto_publish' => get_option( 'ai_blog_generator_auto_publish', false ),
	'default_category' => get_option( 'ai_blog_generator_default_category', 0 ),
	'publish_time_min' => get_option( 'ai_blog_generator_publish_time_min', '08:00' ),
	'publish_time_max' => get_option( 'ai_blog_generator_publish_time_max', '20:00' ),
	'weekend_publishing' => get_option( 'ai_blog_generator_weekend_publishing', false ),
	'monthly_budget' => get_option( 'ai_blog_generator_monthly_budget', 100.00 ),
	'budget_alert_threshold' => get_option( 'ai_blog_generator_budget_alert_threshold', 80 ),
	'debug_logging' => get_option( 'ai_blog_generator_debug_logging', false ),
	'enable_idea_generation' => get_option( 'ai_blog_generator_enable_idea_generation', true ),
	'enable_image_generation' => get_option( 'ai_blog_generator_enable_image_generation', true ),
	'enable_seo_optimization' => get_option( 'ai_blog_generator_enable_seo_optimization', true ),
	'delete_data_on_deactivation' => get_option( 'ai_blog_generator_delete_data_on_deactivation', false ),
];

// Get available Claude models
$anthropic_service = new \AI_Blog_Generator\Services\Anthropic_Service();
$available_models = $anthropic_service->get_available_models();

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="ai-blog-admin-content">
		<form method="post" id="ai-blog-settings-form">
			
			<!-- API Settings -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'API Settings', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="anthropic_api_key"><?php esc_html_e( 'Anthropic API Key', 'ai-blog-generator' ); ?></label>
							</th>
							<td class="ai-blog-api-test">
								<input type="password" id="anthropic_api_key" name="anthropic_api_key" 
									value="<?php echo esc_attr( $settings['anthropic_api_key'] ); ?>" 
									class="regular-text" />
								<button type="button" class="button ai-blog-test-connection" data-service="anthropic">
									<?php esc_html_e( 'Test Connection', 'ai-blog-generator' ); ?>
								</button>
								<div class="ai-blog-test-result"></div>
								<p class="description">
									<?php esc_html_e( 'Enter your Anthropic API key for Claude models.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="anthropic_model"><?php esc_html_e( 'Claude Model', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<select id="anthropic_model" name="anthropic_model" class="regular-text">
									<?php foreach ( $available_models as $model_id => $model_name ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" 
											<?php selected( $settings['anthropic_model'], $model_id ); ?>>
											<?php echo esc_html( $model_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the Claude model to use for content generation. Claude Sonnet 4 is recommended for most use cases.', 'ai-blog-generator' ); ?>
								</p>
								<div class="ai-model-pricing">
									<p class="description">
										<strong><?php esc_html_e( 'Pricing:', 'ai-blog-generator' ); ?></strong>
										<span id="model-pricing-info">
											<?php esc_html_e( 'Claude Sonnet 4: $3/1M input tokens, $15/1M output tokens', 'ai-blog-generator' ); ?>
										</span>
									</p>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ai-blog-generator' ); ?></label>
							</th>
							<td class="ai-blog-api-test">
								<input type="password" id="openai_api_key" name="openai_api_key" 
									value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" 
									class="regular-text" />
								<button type="button" class="button ai-blog-test-connection" data-service="openai">
									<?php esc_html_e( 'Test Connection', 'ai-blog-generator' ); ?>
								</button>
								<div class="ai-blog-test-result"></div>
								<p class="description">
									<?php esc_html_e( 'Enter your OpenAI API key for GPT-Image-1.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Generation Settings -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Generation Settings', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="ideas_per_day"><?php esc_html_e( 'Ideas Per Day', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<input type="number" id="ideas_per_day" name="ideas_per_day" 
									value="<?php echo esc_attr( $settings['ideas_per_day'] ); ?>" 
									min="1" class="small-text" />
								<p class="description">
									<?php esc_html_e( 'Number of blog ideas to generate daily.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="posts_per_day"><?php esc_html_e( 'Posts Per Day', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<input type="number" id="posts_per_day" name="posts_per_day" 
									value="<?php echo esc_attr( $settings['posts_per_day'] ); ?>" 
									min="1" class="small-text" />
								<p class="description">
									<?php esc_html_e( 'Maximum number of posts to generate and publish daily.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="auto_publish"><?php esc_html_e( 'Auto Publish', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<label for="auto_publish">
									<input type="checkbox" id="auto_publish" name="auto_publish" value="1" 
										<?php checked( $settings['auto_publish'], true ); ?> />
									<?php esc_html_e( 'Automatically publish generated posts', 'ai-blog-generator' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'If enabled, posts will be scheduled for publishing. Otherwise, they remain as drafts.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="default_category"><?php esc_html_e( 'Default Category', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<?php wp_dropdown_categories( [
									'name'             => 'default_category',
									'id'               => 'default_category',
									'selected'         => $settings['default_category'],
									'show_option_none' => __( 'Select Category', 'ai-blog-generator' ),
									'option_none_value' => '',
									'hide_empty'       => false,
								] ); ?>
								<p class="description">
									<?php esc_html_e( 'Default category for generated posts.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Scheduling Settings -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Scheduling Settings', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="publish_time_min"><?php esc_html_e( 'Publish Time Range', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<input type="time" id="publish_time_min" name="publish_time_min" 
									value="<?php echo esc_attr( $settings['publish_time_min'] ); ?>" />
								<span><?php esc_html_e( 'to', 'ai-blog-generator' ); ?></span>
								<input type="time" id="publish_time_max" name="publish_time_max" 
									value="<?php echo esc_attr( $settings['publish_time_max'] ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Time range for scheduling posts (24-hour format).', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="weekend_publishing"><?php esc_html_e( 'Weekend Publishing', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<label for="weekend_publishing">
									<input type="checkbox" id="weekend_publishing" name="weekend_publishing" value="1" 
										<?php checked( $settings['weekend_publishing'], true ); ?> />
									<?php esc_html_e( 'Publish posts on weekends', 'ai-blog-generator' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Budget Settings -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Budget Settings', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="monthly_budget"><?php esc_html_e( 'Monthly Budget', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<span class="currency-symbol">$</span>
								<input type="number" id="monthly_budget" name="monthly_budget" 
									value="<?php echo esc_attr( $settings['monthly_budget'] ); ?>" 
									min="0" step="0.01" class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Maximum monthly spend for API usage.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="budget_alert_threshold"><?php esc_html_e( 'Alert Threshold', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<input type="number" id="budget_alert_threshold" name="budget_alert_threshold" 
									value="<?php echo esc_attr( $settings['budget_alert_threshold'] ); ?>" 
									min="0" max="100" class="small-text" />
								<span>%</span>
								<p class="description">
									<?php esc_html_e( 'Send alert when budget usage reaches this percentage.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Feature Flags -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Feature Settings', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Features', 'ai-blog-generator' ); ?></th>
							<td>
								<fieldset>
									<label for="enable_idea_generation">
										<input type="checkbox" id="enable_idea_generation" name="enable_idea_generation" value="1" 
											<?php checked( $settings['enable_idea_generation'], true ); ?> />
										<?php esc_html_e( 'Idea Generation', 'ai-blog-generator' ); ?>
									</label>
									<br />
									<label for="enable_image_generation">
										<input type="checkbox" id="enable_image_generation" name="enable_image_generation" value="1" 
											<?php checked( $settings['enable_image_generation'], true ); ?> />
										<?php esc_html_e( 'Image Generation', 'ai-blog-generator' ); ?>
									</label>
									<br />
									<label for="enable_seo_optimization">
										<input type="checkbox" id="enable_seo_optimization" name="enable_seo_optimization" value="1" 
											<?php checked( $settings['enable_seo_optimization'], true ); ?> />
										<?php esc_html_e( 'SEO Optimization', 'ai-blog-generator' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Debug Settings -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Debug Settings', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="debug_logging"><?php esc_html_e( 'Debug Logging', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<label for="debug_logging">
									<input type="checkbox" id="debug_logging" name="debug_logging" value="1" 
										<?php checked( $settings['debug_logging'], true ); ?> />
									<?php esc_html_e( 'Enable detailed debug logging', 'ai-blog-generator' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, detailed logs will be recorded for all operations and displayed in the browser console for debugging. Disable in production for better performance.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Data Management Settings -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Data Management', 'ai-blog-generator' ); ?></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="delete_data_on_deactivation"><?php esc_html_e( 'Data Deletion', 'ai-blog-generator' ); ?></label>
							</th>
							<td>
								<label for="delete_data_on_deactivation">
									<input type="checkbox" id="delete_data_on_deactivation" name="delete_data_on_deactivation" value="1" 
										<?php checked( $settings['delete_data_on_deactivation'], true ); ?> />
									<?php esc_html_e( 'Delete all plugin data when deactivating', 'ai-blog-generator' ); ?>
								</label>
								<p class="description" style="color: #d63638; font-weight: 500;">
									<strong><?php esc_html_e( 'WARNING:', 'ai-blog-generator' ); ?></strong> 
									<?php esc_html_e( 'If enabled, ALL plugin data will be permanently deleted when you deactivate the plugin, including:', 'ai-blog-generator' ); ?>
								</p>
								<ul class="description" style="margin-left: 20px; list-style-type: disc;">
									<li><?php esc_html_e( 'All database tables (ideas, posts, contexts, personas, products, etc.)', 'ai-blog-generator' ); ?></li>
									<li><?php esc_html_e( 'All plugin settings and options', 'ai-blog-generator' ); ?></li>
									<li><?php esc_html_e( 'All transients and temporary data', 'ai-blog-generator' ); ?></li>
									<li><?php esc_html_e( 'All log files and generated images', 'ai-blog-generator' ); ?></li>
								</ul>
								<p class="description">
									<?php esc_html_e( 'If disabled (default), your data will be preserved and available when you reactivate the plugin.', 'ai-blog-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" 
					value="<?php esc_attr_e( 'Save Settings', 'ai-blog-generator' ); ?>" />
				<span id="ai-blog-save-status" class="save-status"></span>
			</p>
		</form>
	</div>
</div>

<style>
.ai-blog-admin-content {
	max-width: 800px;
	margin-top: 20px;
}
.postbox {
	margin-bottom: 20px;
}
.currency-symbol {
	display: inline-block;
	margin-right: 5px;
}
.api-test-result {
	margin-left: 10px;
	font-style: italic;
}
.api-test-result.success {
	color: #46b450;
}
.api-test-result.error {
	color: #dc3232;
}
@media screen and (max-width: 782px) {
	.form-table th {
		display: block;
		padding-bottom: 0;
	}
	.form-table td {
		display: block;
		padding-left: 0;
	}
}
</style> 
 
 