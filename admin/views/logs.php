<?php
/**
 * Compact Logs Page View - Simple PHP-based implementation
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get statistics for display
$log_model = new \AI_Blog_Generator\Models\Log_Model();
$stats = $log_model->get_level_statistics( $filters );

// Debug: Check if logs exist
global $wpdb;
$total_logs_in_db = $wpdb->get_var( "SELECT COUNT(*) FROM " . AI_BLOG_GENERATOR_TABLE_LOGS );
$debug_info = [
	'total_logs_in_db' => $total_logs_in_db,
	'filters' => $filters,
	'per_page' => $per_page,
	'logs_count' => count( $logs ),
];
?>

<div class="wrap ai-blog-logs-compact">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- DEBUG: Ultra-Compact Design Loaded v1.0.6 -->
	<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 8px 12px; border-radius: 4px; margin: 10px 0; font-size: 12px;">
		<strong>DEBUG:</strong> Ultra-compact logs design loaded successfully (v1.0.6) - FIXED: Database query now uses correct columns!<br>
		<strong>Database Info:</strong> Total logs in DB: <?php echo $total_logs_in_db; ?> | Filters: <?php echo json_encode( $filters ); ?> | Per page: <?php echo $per_page; ?> | Current page logs: <?php echo count( $logs ); ?>
	</div>

	<!-- Statistics -->
	<div class="ai-logs-stats">
		<div class="ai-stat-card">
			<span class="dashicons dashicons-chart-area"></span>
			<div class="ai-stat-content">
				<strong><?php echo esc_html( $stats['total'] ); ?></strong>
				<span><?php esc_html_e( 'Total', 'ai-blog-generator' ); ?></span>
			</div>
		</div>
		<div class="ai-stat-card ai-stat-info">
			<span class="dashicons dashicons-info"></span>
			<div class="ai-stat-content">
				<strong><?php echo esc_html( $stats['info'] ); ?></strong>
				<span><?php esc_html_e( 'Info', 'ai-blog-generator' ); ?></span>
			</div>
		</div>
		<div class="ai-stat-card ai-stat-warning">
			<span class="dashicons dashicons-warning"></span>
			<div class="ai-stat-content">
				<strong><?php echo esc_html( $stats['warning'] ); ?></strong>
				<span><?php esc_html_e( 'Warnings', 'ai-blog-generator' ); ?></span>
			</div>
		</div>
		<div class="ai-stat-card ai-stat-error">
			<span class="dashicons dashicons-dismiss"></span>
			<div class="ai-stat-content">
				<strong><?php echo esc_html( $stats['error'] ); ?></strong>
				<span><?php esc_html_e( 'Errors', 'ai-blog-generator' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Compact Filter Form -->
	<div class="ai-filter-panel">
		<form method="get" action="" class="ai-logs-filter-form">
			<input type="hidden" name="page" value="ai-blog-generator-logs">
			
			<div class="ai-filter-row">
				<div class="ai-filter-group">
					<label><?php esc_html_e( 'Search', 'ai-blog-generator' ); ?></label>
					<input type="text" name="search" placeholder="<?php esc_attr_e( 'Search...', 'ai-blog-generator' ); ?>" value="<?php echo esc_attr( $filters['search'] ); ?>">
				</div>
				
				<div class="ai-filter-group">
					<label><?php esc_html_e( 'Level', 'ai-blog-generator' ); ?></label>
					<select name="level">
						<option value=""><?php esc_html_e( 'All', 'ai-blog-generator' ); ?></option>
						<option value="info" <?php selected( $filters['level'], 'info' ); ?>><?php esc_html_e( 'Info', 'ai-blog-generator' ); ?></option>
						<option value="warning" <?php selected( $filters['level'], 'warning' ); ?>><?php esc_html_e( 'Warning', 'ai-blog-generator' ); ?></option>
						<option value="error" <?php selected( $filters['level'], 'error' ); ?>><?php esc_html_e( 'Error', 'ai-blog-generator' ); ?></option>
					</select>
				</div>
				
				<div class="ai-filter-group">
					<label><?php esc_html_e( 'Source', 'ai-blog-generator' ); ?></label>
					<select name="action">
						<option value=""><?php esc_html_e( 'All Sources', 'ai-blog-generator' ); ?></option>
						<?php foreach ( $available_actions as $action ) : ?>
							<option value="<?php echo esc_attr( $action ); ?>" <?php selected( $filters['action'], $action ); ?>>
								<?php echo esc_html( $action ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div class="ai-filter-group">
					<label><?php esc_html_e( 'From', 'ai-blog-generator' ); ?></label>
					<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
				</div>
				
				<div class="ai-filter-group">
					<label><?php esc_html_e( 'To', 'ai-blog-generator' ); ?></label>
					<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
				</div>
				
				<div class="ai-filter-group">
					<label><?php esc_html_e( 'Per Page', 'ai-blog-generator' ); ?></label>
					<select name="per_page">
						<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
						<option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
						<option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
					</select>
				</div>
				
				<div class="ai-filter-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'ai-blog-generator' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-generator-logs' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Reset', 'ai-blog-generator' ); ?></a>
				</div>
			</div>
			
			<div class="ai-action-buttons">
				<button type="button" class="button button-secondary" id="refresh-logs">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'ai-blog-generator' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="export-logs">
					<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export', 'ai-blog-generator' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="clear-old-logs">
					<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Old', 'ai-blog-generator' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Logs Display -->
	<div class="ai-logs-container">
		<?php if ( empty( $logs ) ) : ?>
			<div class="ai-no-logs">
				<span class="dashicons dashicons-search"></span>
				<p><?php esc_html_e( 'No logs found matching your filters.', 'ai-blog-generator' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $logs as $log ) : ?>
				<?php
				$level_classes = [
					'info' => 'ai-log-info',
					'warning' => 'ai-log-warning', 
					'error' => 'ai-log-error'
				];
				$level_icons = [
					'info' => 'dashicons-info',
					'warning' => 'dashicons-warning', 
					'error' => 'dashicons-dismiss'
				];
				
				$level_class = isset( $level_classes[ $log->level ] ) ? $level_classes[ $log->level ] : 'ai-log-info';
				$level_icon = isset( $level_icons[ $log->level ] ) ? $level_icons[ $log->level ] : 'dashicons-marker';
				
				// Parse context data
				$context_data = [];
				if ( ! empty( $log->context ) ) {
					$context_data = json_decode( $log->context, true );
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$context_data = [];
					}
				}
				?>
				<div class="ai-log-card <?php echo esc_attr( $level_class ); ?>">
					<div class="ai-log-header">
						<div class="ai-log-level">
							<span class="dashicons <?php echo esc_attr( $level_icon ); ?>"></span>
							<span class="ai-level-text"><?php echo esc_html( strtoupper( $log->level ) ); ?></span>
						</div>
						<div class="ai-log-meta">
							<span class="ai-log-action"><?php echo esc_html( $log->action ); ?></span>
							<span class="ai-log-time"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?></span>
						</div>
					</div>
					
					<div class="ai-log-message">
						<?php echo esc_html( $log->message ); ?>
					</div>
					
					<?php if ( ! empty( $context_data ) ) : ?>
						<div class="ai-log-footer">
							<button type="button" class="ai-view-details" data-context="<?php echo esc_attr( wp_json_encode( $context_data ) ); ?>">
								<span class="dashicons dashicons-visibility"></span> View Details
							</button>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>

<!-- Modal for log details -->
<div id="log-details-modal" class="ai-log-modal" style="display: none;">
	<div class="ai-modal-content">
		<div class="ai-modal-header">
			<h3><?php esc_html_e( 'Log Details', 'ai-blog-generator' ); ?></h3>
			<button type="button" class="modal-close">&times;</button>
		</div>
		<div class="ai-modal-body" id="log-details-content">
			<!-- Details will be loaded here -->
		</div>
	</div>
</div>

<style>
/* Ultra-Compact Logs Page Styles with High Specificity */
.ai-blog-logs-compact * {
	box-sizing: border-box !important;
}

/* Statistics Row */
.ai-blog-logs-compact .ai-logs-stats {
	display: flex !important;
	gap: 8px !important;
	margin: 10px 0 15px 0 !important;
	flex-wrap: wrap !important;
}

.ai-blog-logs-compact .ai-stat-card {
	background: #fff !important;
	border: 1px solid #ddd !important;
	border-radius: 3px !important;
	padding: 8px 10px !important;
	display: flex !important;
	align-items: center !important;
	gap: 6px !important;
	min-width: 100px !important;
	flex: 0 1 auto !important;
	box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
}

.ai-blog-logs-compact .ai-stat-card .dashicons {
	font-size: 16px !important;
	width: 16px !important;
	height: 16px !important;
	color: #666 !important;
}

.ai-blog-logs-compact .ai-stat-info .dashicons { color: #2196F3 !important; }
.ai-blog-logs-compact .ai-stat-warning .dashicons { color: #ff9800 !important; }
.ai-blog-logs-compact .ai-stat-error .dashicons { color: #f44336 !important; }

.ai-blog-logs-compact .ai-stat-content {
	display: flex !important;
	flex-direction: column !important;
	line-height: 1 !important;
}

.ai-blog-logs-compact .ai-stat-content strong {
	font-size: 16px !important;
	line-height: 1 !important;
	margin: 0 !important;
	padding: 0 !important;
}

.ai-blog-logs-compact .ai-stat-content span {
	font-size: 10px !important;
	color: #666 !important;
	text-transform: uppercase !important;
	margin-top: 2px !important;
}

/* Ultra-Compact Filter Panel */
.ai-blog-logs-compact .ai-filter-panel {
	background: #fff !important;
	border: 1px solid #ddd !important;
	border-radius: 3px !important;
	padding: 8px !important;
	margin: 0 0 15px 0 !important;
	box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
}

.ai-blog-logs-compact .ai-logs-filter-form {
	margin: 0 !important;
	padding: 0 !important;
}

.ai-blog-logs-compact .ai-filter-row {
	display: flex !important;
	gap: 8px !important;
	align-items: flex-end !important;
	flex-wrap: wrap !important;
	margin: 0 !important;
}

.ai-blog-logs-compact .ai-filter-group {
	display: flex !important;
	flex-direction: column !important;
	gap: 2px !important;
	margin: 0 !important;
}

.ai-blog-logs-compact .ai-filter-group label {
	font-size: 10px !important;
	font-weight: 600 !important;
	color: #555 !important;
	text-transform: uppercase !important;
	letter-spacing: 0.3px !important;
	margin: 0 !important;
	padding: 0 !important;
	line-height: 1.2 !important;
}

.ai-blog-logs-compact .ai-filter-group input[type="text"],
.ai-blog-logs-compact .ai-filter-group input[type="date"],
.ai-blog-logs-compact .ai-filter-group select {
	padding: 2px 6px !important;
	border: 1px solid #ddd !important;
	border-radius: 2px !important;
	font-size: 12px !important;
	height: 26px !important;
	line-height: 1 !important;
	margin: 0 !important;
	width: auto !important;
	min-width: 80px !important;
}

.ai-blog-logs-compact .ai-filter-group input[type="text"] {
	width: 120px !important;
}

.ai-blog-logs-compact .ai-filter-group select[name="level"] {
	width: 80px !important;
}

.ai-blog-logs-compact .ai-filter-group select[name="action"] {
	width: 140px !important;
}

.ai-blog-logs-compact .ai-filter-group input[type="date"] {
	width: 110px !important;
}

.ai-blog-logs-compact .ai-filter-group select[name="per_page"] {
	width: 60px !important;
}

.ai-blog-logs-compact .ai-filter-group input:focus,
.ai-blog-logs-compact .ai-filter-group select:focus {
	border-color: #2196F3 !important;
	outline: none !important;
	box-shadow: 0 0 0 1px rgba(33, 150, 243, 0.2) !important;
}

.ai-blog-logs-compact .ai-filter-actions {
	display: flex !important;
	gap: 4px !important;
	margin: 0 !important;
	align-items: flex-end !important;
}

.ai-blog-logs-compact .ai-filter-actions .button {
	height: 26px !important;
	padding: 0 10px !important;
	font-size: 11px !important;
	line-height: 24px !important;
	margin: 0 !important;
	min-height: 26px !important;
}

.ai-blog-logs-compact .ai-action-buttons {
	display: flex !important;
	gap: 4px !important;
	margin-top: 6px !important;
	padding-top: 6px !important;
	border-top: 1px solid #eee !important;
}

.ai-blog-logs-compact .ai-action-buttons .button {
	height: 26px !important;
	padding: 0 8px !important;
	font-size: 11px !important;
	line-height: 24px !important;
	display: inline-flex !important;
	align-items: center !important;
	gap: 3px !important;
	margin: 0 !important;
	min-height: 26px !important;
}

.ai-blog-logs-compact .ai-action-buttons .dashicons {
	font-size: 12px !important;
	width: 12px !important;
	height: 12px !important;
	line-height: 12px !important;
}

/* Logs Container */
.ai-blog-logs-compact .ai-logs-container {
	background: #fff !important;
	border: 1px solid #ddd !important;
	border-radius: 3px !important;
	box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
	margin: 0 !important;
}

.ai-blog-logs-compact .ai-no-logs {
	padding: 30px !important;
	text-align: center !important;
	color: #666 !important;
}

.ai-blog-logs-compact .ai-no-logs .dashicons {
	font-size: 32px !important;
	color: #ccc !important;
	margin-bottom: 8px !important;
	display: block !important;
}

.ai-blog-logs-compact .ai-no-logs p {
	margin: 0 !important;
	font-size: 13px !important;
}

/* Log Cards */
.ai-blog-logs-compact .ai-log-card {
	border-bottom: 1px solid #eee !important;
	padding: 8px 10px !important;
	margin: 0 !important;
}

.ai-blog-logs-compact .ai-log-card:last-child {
	border-bottom: none !important;
}

.ai-blog-logs-compact .ai-log-card.ai-log-info { border-left: 3px solid #2196F3 !important; }
.ai-blog-logs-compact .ai-log-card.ai-log-warning { border-left: 3px solid #ff9800 !important; }
.ai-blog-logs-compact .ai-log-card.ai-log-error { border-left: 3px solid #f44336 !important; }

.ai-blog-logs-compact .ai-log-header {
	display: flex !important;
	justify-content: space-between !important;
	align-items: center !important;
	margin-bottom: 4px !important;
}

.ai-blog-logs-compact .ai-log-level {
	display: flex !important;
	align-items: center !important;
	gap: 4px !important;
}

.ai-blog-logs-compact .ai-log-level .dashicons {
	font-size: 14px !important;
	width: 14px !important;
	height: 14px !important;
}

.ai-blog-logs-compact .ai-log-info .ai-log-level .dashicons { color: #2196F3 !important; }
.ai-blog-logs-compact .ai-log-warning .ai-log-level .dashicons { color: #ff9800 !important; }
.ai-blog-logs-compact .ai-log-error .ai-log-level .dashicons { color: #f44336 !important; }

.ai-blog-logs-compact .ai-level-text {
	font-size: 10px !important;
	font-weight: 600 !important;
	color: #555 !important;
}

.ai-blog-logs-compact .ai-log-meta {
	display: flex !important;
	gap: 8px !important;
	font-size: 10px !important;
	color: #666 !important;
}

.ai-blog-logs-compact .ai-log-message {
	color: #333 !important;
	font-size: 12px !important;
	line-height: 1.3 !important;
	margin: 0 0 4px 0 !important;
}

.ai-blog-logs-compact .ai-log-footer {
	display: flex !important;
	justify-content: flex-end !important;
	margin: 0 !important;
}

.ai-blog-logs-compact .ai-view-details {
	background: none !important;
	border: 1px solid #ddd !important;
	padding: 2px 6px !important;
	font-size: 10px !important;
	cursor: pointer !important;
	border-radius: 2px !important;
	display: inline-flex !important;
	align-items: center !important;
	gap: 3px !important;
	color: #555 !important;
	margin: 0 !important;
	height: auto !important;
	line-height: 1 !important;
}

.ai-blog-logs-compact .ai-view-details:hover {
	background: #f5f5f5 !important;
	border-color: #2196F3 !important;
	color: #2196F3 !important;
}

.ai-blog-logs-compact .ai-view-details .dashicons {
	font-size: 10px !important;
	width: 10px !important;
	height: 10px !important;
}

/* Modal */
.ai-log-modal {
	position: fixed !important;
	z-index: 100000 !important;
	left: 0 !important;
	top: 0 !important;
	width: 100% !important;
	height: 100% !important;
	background-color: rgba(0,0,0,0.5) !important;
	align-items: center !important;
	justify-content: center !important;
	/* Removed display: flex !important; - let JavaScript control visibility */
}

.ai-log-modal[style*="display: none"] {
	display: none !important;
}

.ai-log-modal:not([style*="display: none"]) {
	display: flex !important;
}

.ai-log-modal .ai-modal-content {
	background: #fff !important;
	padding: 0 !important;
	border-radius: 4px !important;
	width: 90% !important;
	max-width: 600px !important;
	max-height: 80vh !important;
	overflow: hidden !important;
	box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
}

.ai-log-modal .ai-modal-header {
	padding: 12px 15px !important;
	border-bottom: 1px solid #eee !important;
	display: flex !important;
	justify-content: space-between !important;
	align-items: center !important;
}

.ai-log-modal .ai-modal-header h3 {
	margin: 0 !important;
	font-size: 14px !important;
}

.ai-log-modal .modal-close {
	background: none !important;
	border: none !important;
	font-size: 18px !important;
	cursor: pointer !important;
	color: #666 !important;
	padding: 0 !important;
	margin: 0 !important;
}

.ai-log-modal .modal-close:hover {
	color: #000 !important;
}

.ai-log-modal .ai-modal-body {
	padding: 15px !important;
	max-height: 60vh !important;
	overflow-y: auto !important;
}

.ai-log-modal .context-data {
	font-family: monospace !important;
	background: #f5f5f5 !important;
	padding: 10px !important;
	border-radius: 3px !important;
	font-size: 11px !important;
	white-space: pre-wrap !important;
	word-break: break-all !important;
	margin: 0 !important;
}
</style> 
 
	