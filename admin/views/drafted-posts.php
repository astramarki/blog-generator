<?php
/**
 * Drafted Posts Page View - Modern Interface
 *
 * Modern interface for managing drafted blog posts with scheduling and publishing features.
 * Matches the style of the approved ideas and idea generator pages.
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get scheduled time generator
$scheduler = new \AI_Blog_Generator\Services\Scheduler_Service();

?>

<div class="wrap ai-drafted-posts">
	<div class="container-fluid px-3 py-3">
		
		<!-- Header Section -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h1 class="h2 mb-1 text-primary">
							<i class="fas fa-file-alt me-2"></i>
							Drafted Posts
						</h1>
						<p class="text-muted mb-0">Review, schedule, and publish AI-generated blog posts</p>
					</div>
					<div class="d-flex align-items-center">
						<button type="button" class="btn btn-outline-secondary" id="refreshDrafts">
							<i class="fas fa-sync-alt me-1"></i>
							Refresh
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Statistics Cards -->
		<div class="row mb-4" id="statisticsCards">
			<div class="col-md-3 col-sm-6 mb-3">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body text-center">
						<div class="d-flex align-items-center justify-content-center mb-2">
							<div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
								<i class="fas fa-file-alt text-primary fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-primary" id="stat-drafts">0</h3>
								<small class="text-muted">Draft Posts</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-3">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body text-center">
						<div class="d-flex align-items-center justify-content-center mb-2">
							<div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
								<i class="fas fa-clock text-warning fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-warning" id="stat-scheduled">0</h3>
								<small class="text-muted">Scheduled</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-3">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body text-center">
						<div class="d-flex align-items-center justify-content-center mb-2">
							<div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
								<i class="fas fa-check-circle text-success fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-success" id="stat-published">0</h3>
								<small class="text-muted">Published Today</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-3">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body text-center">
						<div class="d-flex align-items-center justify-content-center mb-2">
							<div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
								<i class="fas fa-dollar-sign text-info fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-info">$<span id="stat-cost">0.00</span></h3>
								<small class="text-muted">Total Cost</small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Filter and Actions Bar -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="filter-actions-wrapper">
					<div class="filter-actions-content py-3 px-4">
						<div class="row align-items-center">
							<div class="col-md-6">
								<div class="d-flex align-items-center">
									<label class="form-label me-3 mb-0">Bulk Actions:</label>
									<button type="button" class="btn btn-outline-success btn-sm me-2" id="publishAllDrafts" disabled>
										<i class="fas fa-check-circle me-1"></i>
										Publish All Now
									</button>
									<button type="button" class="btn btn-outline-warning btn-sm me-2" id="scheduleAllDrafts" disabled>
										<i class="fas fa-clock me-1"></i>
										Schedule All
									</button>
									<button type="button" class="btn btn-outline-danger btn-sm" id="deleteSelected" disabled>
										<i class="fas fa-trash me-1"></i>
										Delete Selected
									</button>
								</div>
							</div>
							<div class="col-md-6">
								<div class="d-flex justify-content-md-end align-items-center">
									<label class="form-label me-2 mb-0">Filter:</label>
									<select class="form-select form-select-sm" id="statusFilter" style="width: auto;">
										<option value="">All Posts</option>
										<option value="draft">Drafts Only</option>
										<option value="scheduled">Scheduled Only</option>
									</select>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Drafted Posts Table -->
		<div class="row">
			<div class="col-12">
				<div class="drafted-posts-table-wrapper">
					<div class="table-header bg-light border-bottom py-3 px-4">
						<h5 class="mb-0">
							<i class="fas fa-list me-2"></i>
							Generated Posts
							<span class="badge bg-primary ms-2" id="draftCount">0</span>
						</h5>
					</div>
					<div class="table-content">
						<!-- Loading State -->
						<div class="text-center py-5 d-none" id="loadingState">
							<div class="spinner-border text-primary" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
							<p class="mt-3 text-muted">Loading drafted posts...</p>
						</div>
						
						<!-- Empty State -->
						<div class="text-center py-5 d-none" id="emptyState">
							<i class="fas fa-file-alt text-muted" style="font-size: 3rem;"></i>
							<h5 class="mt-3 text-muted">No drafted posts found</h5>
							<p class="text-muted">Generated posts will appear here for review before publishing.</p>
							<a href="<?php echo admin_url( 'admin.php?page=ai-blog-generator-approved-ideas-v2' ); ?>" class="btn btn-primary">
								<i class="fas fa-play me-2"></i>
								Generate Posts
							</a>
						</div>

						<!-- Posts Table -->
						<div class="table-responsive drafts-table-container" id="draftsTableContainer">
							<table class="table table-hover mb-0" id="draftsTable">
								<thead class="table-light">
									<tr>
										<th scope="col" style="width: 50px;">
											<div class="form-check">
												<input class="form-check-input" type="checkbox" id="selectAllCheckbox">
											</div>
										</th>
										<th scope="col">Title</th>
										<th scope="col" style="width: 200px;">Categories</th>
										<th scope="col" style="width: 100px;">Cost</th>
										<th scope="col" style="width: 150px;">Generated</th>
										<th scope="col" style="width: 200px;">Schedule</th>
										<th scope="col" style="width: 150px;">Actions</th>
									</tr>
								</thead>
								<tbody id="draftsTableBody">
									<?php if ( ! empty( $drafted_posts ) ) : ?>
										<?php foreach ( $drafted_posts as $post ) : ?>
											<tr data-post-id="<?php echo esc_attr( $post->post_id ); ?>" 
												data-blog-id="<?php echo esc_attr( $post->id ); ?>"
												data-status="<?php echo esc_attr( $post->status ); ?>">
												<td>
													<div class="form-check">
														<input class="form-check-input post-checkbox" type="checkbox" 
															   value="<?php echo esc_attr( $post->id ); ?>">
													</div>
												</td>
												<td>
													<div class="post-title-cell">
														<strong>
															<a href="<?php echo esc_url( get_edit_post_link( $post->post_id ) ); ?>" 
															   class="text-decoration-none" target="_blank">
																<?php echo esc_html( $post->post_title ); ?>
															</a>
														</strong>
														<br>
														<small class="text-muted">
															From: <?php echo esc_html( $post->idea_title ); ?>
														</small>
													</div>
												</td>
												<td>
													<?php 
													$categories = get_the_category( $post->post_id );
													if ( ! empty( $categories ) ) {
														foreach ( $categories as $category ) {
															echo '<span class="badge bg-secondary me-1">' . esc_html( $category->name ) . '</span>';
														}
													} else {
														echo '<span class="text-muted">Uncategorized</span>';
													}
													?>
												</td>
												<td>
													<span class="text-success fw-bold">
														$<?php echo esc_html( number_format( $post->cost, 2 ) ); ?>
													</span>
												</td>
												<td>
													<small><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $post->created_at ) ) ); ?></small>
												</td>
												<td>
													<input type="datetime-local" class="form-control form-control-sm schedule-time" 
														data-blog-id="<?php echo esc_attr( $post->id ); ?>"
														value="<?php echo esc_attr( 
															! empty( $post->scheduled_time ) && $post->scheduled_time !== '0000-00-00 00:00:00' 
															? date( 'Y-m-d\TH:i', strtotime( $post->scheduled_time ) )
															: date( 'Y-m-d\TH:i', strtotime( '+1 day ' . $scheduler->get_random_publish_time() ) )
														); ?>" />
												</td>
												<td>
													<div class="btn-group" role="group">
														<button type="button" class="btn btn-success btn-sm publish-now" 
															data-post-id="<?php echo esc_attr( $post->post_id ); ?>"
															data-blog-id="<?php echo esc_attr( $post->id ); ?>"
															title="Publish Now">
															<i class="fas fa-check"></i>
														</button>
														<button type="button" class="btn btn-warning btn-sm schedule-post" 
															data-post-id="<?php echo esc_attr( $post->post_id ); ?>"
															data-blog-id="<?php echo esc_attr( $post->id ); ?>"
															title="Schedule">
															<i class="fas fa-clock"></i>
														</button>
														<a href="<?php echo esc_url( get_preview_post_link( $post->post_id ) ); ?>" 
														   class="btn btn-outline-secondary btn-sm" target="_blank"
														   title="Preview">
															<i class="fas fa-eye"></i>
														</a>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Schedule All Modal -->
<div class="modal fade" id="scheduleAllModal" tabindex="-1" aria-labelledby="scheduleAllModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="scheduleAllModalLabel">
					<i class="fas fa-clock me-2 text-warning"></i>
					Schedule All Posts
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-info">
					<i class="fas fa-info-circle me-2"></i>
					Posts will be distributed evenly throughout the configured time range over the next few days.
				</div>
				
				<div class="mb-3">
					<label for="scheduleStartDate" class="form-label">Start Date</label>
					<input type="date" class="form-control" id="scheduleStartDate" 
						   value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
				</div>
				
				<div class="mb-3">
					<label for="scheduleDays" class="form-label">Distribute Over Days</label>
					<input type="number" class="form-control" id="scheduleDays" value="7" min="1" max="30">
					<div class="form-text">Number of days to spread the posts across</div>
				</div>
				
				<div class="mb-3">
					<label class="form-label">Time Range</label>
					<div class="row">
						<div class="col">
							<input type="time" class="form-control" id="scheduleTimeFrom" value="09:00">
							<small class="text-muted">From</small>
						</div>
						<div class="col">
							<input type="time" class="form-control" id="scheduleTimeTo" value="17:00">
							<small class="text-muted">To</small>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-warning" id="confirmScheduleAll">
					<i class="fas fa-clock me-2"></i>
					Schedule Posts
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Publish Confirmation Modal -->
<div class="modal fade" id="publishConfirmModal" tabindex="-1" aria-labelledby="publishConfirmModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="publishConfirmModalLabel">
					<i class="fas fa-exclamation-triangle me-2 text-warning"></i>
					Confirm Publication
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-0">Are you sure you want to publish <span id="publishCount">0</span> post(s) immediately?</p>
				<p class="text-muted mt-2 mb-0">This action cannot be undone. The posts will go live on your website right away.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="confirmPublish">
					<i class="fas fa-check-circle me-2"></i>
					Publish Now
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="deleteConfirmModalLabel">
					<i class="fas fa-exclamation-triangle me-2 text-danger"></i>
					Confirm Deletion
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-0">Are you sure you want to delete <span id="deleteCount">0</span> post(s)?</p>
				<p class="text-danger mt-2 mb-0">This action cannot be undone. The posts will be permanently deleted.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirmDelete">
					<i class="fas fa-trash me-2"></i>
					Delete Posts
				</button>
			</div>
		</div>
	</div>
</div>

<style>
/* Make the page full width */
.wrap.ai-drafted-posts {
	max-width: 100% !important;
	margin: 0 !important;
	padding: 0 !important;
}

/* Override WordPress admin default constraints */
.wrap.ai-drafted-posts .container-fluid {
	max-width: 100% !important;
	padding-left: 20px;
	padding-right: 20px;
}

/* Override any Bootstrap defaults that might constrain width */
.ai-drafted-posts .row {
	max-width: 100% !important;
}

.ai-drafted-posts .col-12 {
	max-width: 100% !important;
}

/* Ensure cards and table are full width */
.ai-drafted-posts .card {
	width: 100%;
}

/* New table wrapper styling */
.ai-drafted-posts .drafted-posts-table-wrapper {
	width: 100%;
	max-width: 100% !important;
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
	overflow: hidden;
}

/* Filter actions wrapper styling */
.ai-drafted-posts .filter-actions-wrapper {
	width: 100%;
	max-width: 100% !important;
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.ai-drafted-posts .filter-actions-content {
	background-color: #fff;
}

.ai-drafted-posts .table-header {
	background-color: #f8f9fa;
	border-bottom: 1px solid #dee2e6;
}

.ai-drafted-posts .table-content {
	padding: 0;
}

.ai-drafted-posts .table-responsive {
	width: 100%;
	overflow-x: auto;
}

.ai-drafted-posts #draftsTable {
	width: 100%;
	min-width: 100%;
}

/* Remove any potential WordPress admin margin - scoped to this page only */
body.ai-blog-generator_page_ai-blog-generator-drafts #wpcontent {
	padding-left: 0;
}

body.ai-blog-generator_page_ai-blog-generator-drafts #wpbody-content {
	float: none !important;
	width: 100% !important;
}

/* Custom styles for drafted posts page */
.ai-drafted-posts .post-title-cell {
	max-width: 400px;
}

.ai-drafted-posts .schedule-time {
	min-width: 180px;
}

.ai-drafted-posts .drafts-table-container {
	min-height: 300px;
}

.ai-drafted-posts .table td {
	vertical-align: middle;
}

/* Status-specific styling */
.ai-drafted-posts tr[data-status="scheduled"] {
	background-color: rgba(255, 193, 7, 0.05);
}

.ai-drafted-posts tr[data-status="published"] {
	background-color: rgba(25, 135, 84, 0.05);
}

/* Animation for row removal */
.ai-drafted-posts tr.removing {
	transition: all 0.3s ease-out;
	opacity: 0;
	transform: translateX(-20px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
	.ai-drafted-posts .btn-group {
		display: flex;
		flex-direction: column;
		width: 100%;
	}
	
	.ai-drafted-posts .btn-group .btn {
		border-radius: 0.25rem !important;
		margin-bottom: 0.25rem;
	}
	
	.ai-drafted-posts .schedule-time {
		min-width: auto;
		width: 100%;
	}
}
</style> 
 
 