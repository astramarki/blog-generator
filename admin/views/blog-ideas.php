<?php
/**
 * Blog Ideas Page View
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<a href="#" class="page-title-action ai-blog-generate-ideas" data-modal="generate-ideas-modal">
		<?php esc_html_e( 'Generate New Ideas', 'ai-blog-generator' ); ?>
	</a>
	<hr class="wp-header-end">
	
	<div class="ai-blog-admin-content">
		<!-- Statistics -->
		<div class="ai-blog-stats-row">
			<div class="ai-blog-stat-box">
				<h3><?php echo esc_html( $statistics['pending'] ); ?></h3>
				<p><?php esc_html_e( 'Pending Ideas', 'ai-blog-generator' ); ?></p>
			</div>
			<div class="ai-blog-stat-box">
				<h3><?php echo esc_html( $statistics['approved'] ); ?></h3>
				<p><?php esc_html_e( 'Approved', 'ai-blog-generator' ); ?></p>
			</div>
			<div class="ai-blog-stat-box">
				<h3><?php echo esc_html( $statistics['denied'] ); ?></h3>
				<p><?php esc_html_e( 'Denied', 'ai-blog-generator' ); ?></p>
			</div>
			<div class="ai-blog-stat-box">
				<h3><?php echo esc_html( $statistics['generated'] ); ?></h3>
				<p><?php esc_html_e( 'Generated', 'ai-blog-generator' ); ?></p>
			</div>
		</div>
		
		<!-- Ideas List -->
		<?php if ( empty( $ideas ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No pending ideas found. Generate some ideas to get started!', 'ai-blog-generator' ); ?></p>
			</div>
		<?php else : ?>
			<form id="ideas-form" method="post">
				<?php wp_nonce_field( 'ai_blog_bulk_ideas', 'ai_blog_bulk_ideas_nonce' ); ?>
				
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-top" class="screen-reader-text">
							<?php esc_html_e( 'Select bulk action', 'ai-blog-generator' ); ?>
						</label>
						<select name="action" id="bulk-action-selector-top">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'ai-blog-generator' ); ?></option>
							<option value="approve"><?php esc_html_e( 'Approve', 'ai-blog-generator' ); ?></option>
							<option value="deny"><?php esc_html_e( 'Deny', 'ai-blog-generator' ); ?></option>
						</select>
						<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'ai-blog-generator' ); ?>">
					</div>
				</div>
				
				<table class="wp-list-table widefat fixed striped ai-blog-ideas-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="cb-select-all-1">
									<?php esc_html_e( 'Select All', 'ai-blog-generator' ); ?>
								</label>
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th scope="col" class="manage-column column-title">
								<?php esc_html_e( 'Title', 'ai-blog-generator' ); ?>
							</th>
							<th scope="col" class="manage-column column-description">
								<?php esc_html_e( 'Description', 'ai-blog-generator' ); ?>
							</th>
							<th scope="col" class="manage-column column-category">
								<?php esc_html_e( 'Category', 'ai-blog-generator' ); ?>
							</th>
							<th scope="col" class="manage-column column-persona">
								<?php esc_html_e( 'Suggested Writer', 'ai-blog-generator' ); ?>
							</th>
							<th scope="col" class="manage-column column-date">
								<?php esc_html_e( 'Created', 'ai-blog-generator' ); ?>
							</th>
							<th scope="col" class="manage-column column-actions">
								<?php esc_html_e( 'Actions', 'ai-blog-generator' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ideas as $idea ) : ?>
							<tr data-idea-id="<?php echo esc_attr( $idea->id ); ?>">
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $idea->id ); ?>">
										<?php /* translators: %s: idea title */ 
										printf( esc_html__( 'Select %s', 'ai-blog-generator' ), esc_html( $idea->title ) ); ?>
									</label>
									<input id="cb-select-<?php echo esc_attr( $idea->id ); ?>" type="checkbox" 
										name="idea_ids[]" value="<?php echo esc_attr( $idea->id ); ?>">
								</th>
								<td class="column-title">
									<strong><?php echo esc_html( $idea->title ); ?></strong>
								</td>
								<td class="column-description">
									<?php echo esc_html( wp_trim_words( $idea->description, 20 ) ); ?>
									<?php if ( strlen( $idea->description ) > 100 ) : ?>
										<a href="#" class="view-full-description" data-description="<?php echo esc_attr( $idea->description ); ?>">
											<?php esc_html_e( 'View more', 'ai-blog-generator' ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td class="column-category">
									<?php if ( ! empty( $idea->category_name ) ) : ?>
										<?php echo esc_html( $idea->category_name ); ?>
									<?php else : ?>
										<select class="idea-category" data-idea-id="<?php echo esc_attr( $idea->id ); ?>">
											<option value=""><?php esc_html_e( 'Select Category', 'ai-blog-generator' ); ?></option>
											<?php foreach ( $categories as $category ) : ?>
												<option value="<?php echo esc_attr( $category->term_id ); ?>" 
													<?php selected( $idea->category_id, $category->term_id ); ?>>
													<?php echo esc_html( $category->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php endif; ?>
								</td>
								<td class="column-persona">
									<?php if ( ! empty( $idea->persona_name ) ) : ?>
										<span class="persona-badge persona-tone-<?php echo esc_attr( $idea->persona_tone ?? 'professional' ); ?>">
											<?php echo esc_html( $idea->persona_name ); ?>
										</span>
									<?php else : ?>
										<span class="text-muted"><?php esc_html_e( 'No suggestion', 'ai-blog-generator' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="column-date">
									<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $idea->created_at ) ) ); ?>
								</td>
								<td class="column-actions">
									<button type="button" class="button button-primary ai-blog-approve-idea" 
										data-idea-id="<?php echo esc_attr( $idea->id ); ?>">
										<?php esc_html_e( 'Approve', 'ai-blog-generator' ); ?>
									</button>
									<button type="button" class="button ai-blog-deny-idea" 
										data-idea-id="<?php echo esc_attr( $idea->id ); ?>">
										<?php esc_html_e( 'Deny', 'ai-blog-generator' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</form>
		<?php endif; ?>
	</div>
</div>

<!-- Description Modal -->
<div id="description-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<span class="ai-blog-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Full Description', 'ai-blog-generator' ); ?></h2>
		<p id="modal-description-content"></p>
	</div>
</div>

<!-- Generate Ideas Modal -->
<div id="generate-ideas-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content">
		<div class="ai-blog-modal-header">
			<h2><?php esc_html_e( 'Generate New Ideas', 'ai-blog-generator' ); ?></h2>
			<span class="ai-blog-modal-close">&times;</span>
		</div>
		<div class="ai-blog-modal-body">
			<form id="generate-ideas-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ai-blog-ideas-count"><?php esc_html_e( 'Number of Ideas', 'ai-blog-generator' ); ?></label>
						</th>
						<td>
							<input type="number" id="ai-blog-ideas-count" name="count" value="5" min="1" max="10" class="small-text" />
							<p class="description"><?php esc_html_e( 'How many blog ideas to generate (1-10)', 'ai-blog-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ai-blog-context-select"><?php esc_html_e( 'Select Context', 'ai-blog-generator' ); ?></label>
						</th>
						<td>
							<select id="ai-blog-context-select" name="context_id" class="regular-text">
								<option value=""><?php esc_html_e( 'Use all active contexts', 'ai-blog-generator' ); ?></option>
								<?php
								$context_model = new AI_Blog_Generator\Models\Context_Model();
								$available_contexts = $context_model->find_all( ['active' => 1], 'type ASC, name ASC' );
								$context_types = [
									'general' => __( 'General Business Information', 'ai-blog-generator' ),
									'products' => __( 'Products and Services', 'ai-blog-generator' ),
									'seo' => __( 'SEO Guidelines', 'ai-blog-generator' ),
									'keywords' => __( 'Target Keywords', 'ai-blog-generator' ),
									'image' => __( 'Image Generation Guidelines', 'ai-blog-generator' ),
									'layout' => __( 'Layout Guidelines', 'ai-blog-generator' ),
								];
								foreach ( $available_contexts as $context ) : ?>
									<option value="<?php echo esc_attr( $context->id ); ?>">
										<?php echo esc_html( $context->name . ' (' . ($context_types[$context->type] ?? ucfirst($context->type)) . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose a specific context or leave blank to use all active contexts for idea generation.', 'ai-blog-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ai-blog-custom-prompt"><?php esc_html_e( 'Custom Prompt (Optional)', 'ai-blog-generator' ); ?></label>
						</th>
						<td>
							<textarea id="ai-blog-custom-prompt" name="custom_prompt" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Enter a custom prompt to guide idea generation, or leave blank to use your configured contexts...', 'ai-blog-generator' ); ?>"></textarea>
							<p class="description"><?php esc_html_e( 'Provide additional context or specific topics for idea generation. This will be combined with your configured business contexts.', 'ai-blog-generator' ); ?></p>
						</td>
					</tr>
				</table>
			</form>
		</div>
		<div class="ai-blog-modal-footer">
			<button type="button" class="button button-secondary ai-blog-modal-close">
				<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
			</button>
			<button type="button" class="button button-primary ai-blog-generate-ideas-submit">
				<span class="dashicons dashicons-lightbulb"></span>
				<?php esc_html_e( 'Generate Ideas', 'ai-blog-generator' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Ideas Confirmation Modal -->
<div id="ideas-confirmation-modal" class="ai-blog-modal" style="display: none;">
	<div class="ai-blog-modal-content ai-blog-modal-large">
		<div class="ai-blog-modal-header">
			<h2><?php esc_html_e( 'Review Generated Ideas', 'ai-blog-generator' ); ?></h2>
			<span class="ai-blog-modal-close">&times;</span>
		</div>
		<div class="ai-blog-modal-body">
			<p class="description"><?php esc_html_e( 'Review the generated ideas below and select which ones you want to save. You can approve or deny each idea individually.', 'ai-blog-generator' ); ?></p>
			<div id="generated-ideas-container">
				<!-- Generated ideas will be inserted here -->
			</div>
		</div>
		<div class="ai-blog-modal-footer">
			<button type="button" class="button button-secondary ai-blog-modal-close">
				<?php esc_html_e( 'Cancel', 'ai-blog-generator' ); ?>
			</button>
			<button type="button" class="button button-primary ai-blog-save-selected-ideas">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Save Selected Ideas', 'ai-blog-generator' ); ?>
			</button>
		</div>
	</div>
</div>

<style>
.ai-blog-stats-row {
	display: flex;
	gap: 20px;
	margin: 20px 0;
	flex-wrap: wrap;
}
.ai-blog-stat-box {
	flex: 1;
	min-width: 150px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	text-align: center;
}
.ai-blog-stat-box h3 {
	margin: 0 0 10px;
	font-size: 32px;
	color: #23282d;
}
.ai-blog-stat-box p {
	margin: 0;
	color: #666;
}
.ai-blog-ideas-table .column-cb {
	width: 2.2em;
}
.ai-blog-ideas-table .column-title {
	width: 20%;
}
.ai-blog-ideas-table .column-description {
	width: 30%;
}
.ai-blog-ideas-table .column-category {
	width: 12%;
}
.ai-blog-ideas-table .column-persona {
	width: 12%;
}
.ai-blog-ideas-table .column-date {
	width: 10%;
}
.ai-blog-ideas-table .column-actions {
	width: 13.8%;
}
.view-full-description {
	display: inline-block;
	margin-left: 5px;
}
.ai-blog-modal {
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.5);
	display: none;
}
.ai-blog-modal-active {
	display: flex !important;
	align-items: center;
	justify-content: center;
}
.ai-blog-modal-open {
	overflow: hidden;
}
.ai-blog-modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 0;
	border: 1px solid #888;
	width: 80%;
	max-width: 600px;
	border-radius: 4px;
	box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.ai-blog-modal-large {
	max-width: 900px;
	width: 90%;
}
.ai-blog-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px;
	border-bottom: 1px solid #ddd;
	background: #f9f9f9;
	border-radius: 4px 4px 0 0;
}
.ai-blog-modal-header h2 {
	margin: 0;
	font-size: 1.3em;
}
.ai-blog-modal-body {
	padding: 20px;
}
.ai-blog-modal-footer {
	padding: 15px 20px;
	border-top: 1px solid #ddd;
	background: #f9f9f9;
	border-radius: 0 0 4px 4px;
	text-align: right;
}
.ai-blog-modal-footer .button {
	margin-left: 10px;
}
.ai-blog-modal-close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
}
.ai-blog-modal-close:hover,
.ai-blog-modal-close:focus {
	color: #000;
}
.ai-blog-idea-card {
	border: 1px solid #ddd;
	border-radius: 4px;
	margin-bottom: 15px;
	background: #f9f9f9;
	transition: all 0.3s ease;
}
.ai-blog-idea-card.approved {
	border-color: #46b450;
	background: #f0f9f0;
}
.ai-blog-idea-card.denied {
	border-color: #dc3232;
	background: #fdf0f0;
}
.ai-blog-idea-card-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: 15px;
	border-bottom: 1px solid #ddd;
	gap: 15px;
}
.ai-blog-idea-card-title {
	font-weight: 600;
	font-size: 16px;
	margin: 0;
	flex: 1;
	line-height: 1.4;
}
.ai-blog-idea-card-toggle {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 5px;
	flex-shrink: 0;
}
.ai-blog-idea-toggle {
	appearance: none;
	position: relative;
	width: 50px;
	height: 25px;
	background: #dc3232;
	border-radius: 25px;
	cursor: pointer;
	transition: background 0.3s;
	margin: 0;
	border: none;
	outline: none;
}
.ai-blog-idea-toggle:checked {
	background: #46b450;
}
.ai-blog-idea-toggle:before {
	content: '';
	position: absolute;
	top: 2px;
	left: 2px;
	width: 21px;
	height: 21px;
	background: white;
	border-radius: 50%;
	transition: left 0.3s;
	box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.ai-blog-idea-toggle:checked:before {
	left: 27px;
}
.ai-blog-toggle-label {
	font-size: 12px;
	font-weight: 500;
	color: #666;
	text-align: center;
	min-width: 60px;
}
.ai-blog-idea-card-body {
	padding: 15px;
}
.ai-blog-idea-description {
	margin: 0 0 10px;
	color: #666;
	line-height: 1.4;
}
.ai-blog-idea-meta {
	display: flex;
	gap: 15px;
	font-size: 12px;
	color: #999;
}
.ai-blog-idea-meta span {
	background: #f0f0f0;
	padding: 2px 8px;
	border-radius: 3px;
}
.persona-badge {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
	color: #fff;
	background: #666;
}
.persona-tone-professional {
	background: #0073aa;
}
.persona-tone-friendly {
	background: #46b450;
}
.persona-tone-analytical {
	background: #1e73be;
}
.persona-tone-inspirational {
	background: #e91e63;
}
.persona-tone-casual {
	background: #ff9800;
}
.persona-tone-academic {
	background: #9c27b0;
}
.text-muted {
	color: #666;
	font-style: italic;
}
@media screen and (max-width: 782px) {
	.ai-blog-ideas-table .column-description,
	.ai-blog-ideas-table .column-category,
	.ai-blog-ideas-table .column-persona {
		display: none;
	}
	.ai-blog-stat-box {
		min-width: calc(50% - 10px);
	}
}
</style> 

