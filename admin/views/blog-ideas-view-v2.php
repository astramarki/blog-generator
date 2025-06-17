<?php
/**
 * Blog Ideas V2 View
 *
 * Modern interface for managing blog ideas with Bootstrap styling.
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap ai-blog-ideas-v2">
	<div class="container-fluid px-3 py-3">
		
		<!-- Header Section -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h1 class="h2 mb-1 text-primary">
							<i class="fas fa-lightbulb me-2"></i>
							Blog Ideas V2
						</h1>
						<p class="text-muted mb-0">Manage and generate AI-powered blog post ideas</p>
					</div>
					<button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#generateIdeasModal">
						<i class="fas fa-plus me-2"></i>
						Generate New Ideas
					</button>
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
								<i class="fas fa-chart-line text-primary fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-primary" id="stat-total">0</h3>
								<small class="text-muted">Total Generated</small>
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
								<h3 class="mb-0 text-success" id="stat-approved">0</h3>
								<small class="text-muted">Approved</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-3">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body text-center">
						<div class="d-flex align-items-center justify-content-center mb-2">
							<div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
								<i class="fas fa-times-circle text-danger fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-danger" id="stat-denied">0</h3>
								<small class="text-muted">Denied</small>
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
								<i class="fas fa-cogs text-info fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-info" id="stat-generated">0</h3>
								<small class="text-muted">Generated Posts</small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Filter and Actions Bar -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="card border-0 shadow-sm">
					<div class="card-body">
						<div class="row align-items-center">
							<div class="col-md-6">
								<div class="d-flex align-items-center">
									<label class="form-label me-3 mb-0">Quick Actions:</label>
									<button type="button" class="btn btn-outline-success btn-sm me-2" id="bulkApproveSelected" disabled>
										<i class="fas fa-check me-1"></i>
										Approve Selected
									</button>
									<button type="button" class="btn btn-outline-danger btn-sm" id="bulkDenySelected" disabled>
										<i class="fas fa-times me-1"></i>
										Deny Selected
									</button>
								</div>
							</div>
							<div class="col-md-6">
								<div class="d-flex justify-content-md-end">
									<button type="button" class="btn btn-outline-secondary btn-sm me-2" id="selectAllIdeas">
										<i class="fas fa-check-square me-1"></i>
										Select All
									</button>
									<button type="button" class="btn btn-outline-secondary btn-sm" id="refreshIdeas">
										<i class="fas fa-sync-alt me-1"></i>
										Refresh
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Ideas Table -->
		<div class="row">
			<div class="col-12">
				<div class="card border-0 shadow-sm">
					<div class="card-header bg-white border-bottom-0 py-3">
						<h5 class="mb-0">
							<i class="fas fa-list me-2"></i>
							Pending Ideas
							<span class="badge bg-primary ms-2" id="pendingCount">0</span>
						</h5>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive ideas-table-container">
							<table class="table table-hover mb-0" id="ideasTable">
								<thead class="table-light">
									<tr>
										<th scope="col" style="width: 50px;">
											<div class="form-check">
												<input class="form-check-input" type="checkbox" id="selectAllCheckbox">
											</div>
										</th>
										<th scope="col">Title</th>
										<th scope="col" style="width: 150px;">Persona</th>
										<th scope="col" style="width: 180px;">Created</th>
										<th scope="col" style="width: 120px;">Categories</th>
										<th scope="col" style="width: 120px;">Actions</th>
									</tr>
								</thead>
								<tbody id="ideasTableBody">
									<!-- Ideas will be loaded here -->
								</tbody>
							</table>
						</div>
						
						<!-- Loading State -->
						<div class="text-center py-5" id="loadingState">
							<div class="spinner-border text-primary" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
							<p class="mt-3 text-muted">Loading ideas...</p>
						</div>
						
						<!-- Empty State -->
						<div class="text-center py-5 d-none" id="emptyState">
							<i class="fas fa-lightbulb text-muted" style="font-size: 3rem;"></i>
							<h5 class="mt-3 text-muted">No pending ideas</h5>
							<p class="text-muted">Generate some new blog ideas to get started!</p>
							<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateIdeasModal">
								<i class="fas fa-plus me-2"></i>
								Generate Ideas
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Generate Ideas Modal -->
<div class="modal fade" id="generateIdeasModal" tabindex="-1" aria-labelledby="generateIdeasModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="generateIdeasModalLabel">
					<i class="fas fa-plus-circle me-2"></i>
					Generate New Blog Ideas
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="generateIdeasForm">
					<div class="row">
						<div class="col-md-6 mb-3">
							<label for="ideaCount" class="form-label">Number of Ideas</label>
							<select class="form-select" id="ideaCount" required>
								<option value="">Select count...</option>
								<?php for ( $i = 1; $i <= 30; $i++ ) : ?>
									<option value="<?php echo $i; ?>" <?php selected( $i, 5 ); ?>><?php echo $i; ?> idea<?php echo $i > 1 ? 's' : ''; ?></option>
								<?php endfor; ?>
							</select>
							<div class="form-text">Choose between 1 and 30 ideas to generate</div>
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">AI Service</label>
							<div class="form-text">Using: <span class="fw-semibold" id="currentAiService">Anthropic Claude</span></div>
						</div>
					</div>
					
					<div class="mb-3">
						<label for="contextPrompt" class="form-label">Additional Context (Optional)</label>
						<textarea class="form-control" id="contextPrompt" rows="4" placeholder="Provide any specific context, themes, or requirements for the blog ideas..."></textarea>
						<div class="form-text">This will be combined with your existing contexts to generate more targeted ideas</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="generateIdeasBtn">
					<i class="fas fa-magic me-2"></i>
					Generate Ideas
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Loading Overlay for Generate Modal -->
<div class="modal fade" id="generatingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-body text-center py-5">
				<div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
					<span class="visually-hidden">Loading...</span>
				</div>
				<h5 class="mb-2">Generating Ideas...</h5>
				<p class="text-muted mb-0">This may take a moment while our AI creates unique blog ideas for you.</p>
			</div>
		</div>
	</div>
</div>

<!-- Approve Ideas Modal -->
<div class="modal fade" id="approveIdeasModal" tabindex="-1" aria-labelledby="approveIdeasModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="approveIdeasModalLabel">
					<i class="fas fa-check-circle me-2"></i>
					Review Generated Ideas
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<p class="text-muted">Review the generated ideas below. You can uncheck any ideas you don't want to approve.</p>
				</div>
				<div id="generatedIdeasContainer">
					<!-- Generated ideas will be loaded here -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="approveSelectedIdeas">
					<i class="fas fa-check me-2"></i>
					Approve Selected Ideas
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Idea Description Modal -->
<div class="modal fade" id="ideaDescriptionModal" tabindex="-1" aria-labelledby="ideaDescriptionModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="ideaDescriptionModalLabel">Idea Details</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<h6 class="fw-bold" id="ideaModalTitle">Title</h6>
				<p id="ideaModalDescription">Description</p>
				<div class="row">
					<div class="col-md-6">
						<small class="text-muted">Persona:</small>
						<div id="ideaModalPersona">-</div>
					</div>
					<div class="col-md-6">
						<small class="text-muted">Created:</small>
						<div id="ideaModalCreated">-</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Categories Modal -->
<div class="modal fade" id="categoriesModal" tabindex="-1" aria-labelledby="categoriesModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="categoriesModalLabel">Assigned Categories</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="categoriesModalContent">
					<!-- Categories will be loaded here -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Custom Styles -->
<style>
.ai-blog-ideas-v2 {
	background-color: #f8f9fa;
	min-height: 100vh;
}

.ai-blog-ideas-v2 .container-fluid {
	max-width: none !important;
	padding-left: 1.5rem;
	padding-right: 1.5rem;
}

.ai-blog-ideas-v2 .card {
	border-radius: 12px;
	transition: all 0.3s ease;
	max-width: 100%;
}

.ai-blog-ideas-v2 .card:hover {
	transform: translateY(-2px);
}

.ai-blog-ideas-v2 .btn {
	border-radius: 8px;
	font-weight: 500;
}

.ai-blog-ideas-v2 .badge {
	border-radius: 6px;
}

/* Table Styling */
.ai-blog-ideas-v2 .ideas-table-container {
	min-height: 500px;
	max-height: 70vh;
	overflow-y: auto;
}

.ai-blog-ideas-v2 .table {
	width: 100%;
	margin-bottom: 0;
	font-size: 0.95rem;
}

/* Title column should use remaining space */
.ai-blog-ideas-v2 .table th:nth-child(2),
.ai-blog-ideas-v2 .table td:nth-child(2) {
	min-width: 250px;
}

.ai-blog-ideas-v2 .table th {
	border-top: none;
	font-weight: 600;
	color: #495057;
	white-space: nowrap;
	position: sticky;
	top: 0;
	background-color: #f8f9fa;
	z-index: 10;
}

.ai-blog-ideas-v2 .table td {
	vertical-align: middle;
	border-color: #e9ecef;
}

.ai-blog-ideas-v2 .table tbody tr:hover {
	background-color: #f8f9fa;
}

.ai-blog-ideas-v2 .form-check-input:checked {
	background-color: #0d6efd;
	border-color: #0d6efd;
}

.ai-blog-ideas-v2 .spinner-border {
	width: 2rem;
	height: 2rem;
}

/* Modal Styling */
.ai-blog-ideas-v2 .modal-content {
	border-radius: 12px;
	border: none;
	box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.ai-blog-ideas-v2 .modal-header {
	border-bottom: 1px solid #e9ecef;
}

.ai-blog-ideas-v2 .modal-footer {
	border-top: 1px solid #e9ecef;
}

.ai-blog-ideas-v2 .idea-card {
	border: 1px solid #e9ecef;
	border-radius: 8px;
	padding: 1rem;
	margin-bottom: 1rem;
	transition: all 0.3s ease;
}

.ai-blog-ideas-v2 .idea-card:hover {
	border-color: #0d6efd;
	box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.ai-blog-ideas-v2 .idea-card.selected {
	border-color: #0d6efd;
	background-color: #f8f9ff;
}

/* Loading and Empty States */
#loadingState, #emptyState {
	min-height: 400px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
}

/* Responsive Design */
@media (max-width: 768px) {
	.ai-blog-ideas-v2 .container-fluid {
		padding-left: 1rem;
		padding-right: 1rem;
	}
	
	.ai-blog-ideas-v2 .table-responsive {
		font-size: 0.875rem;
	}
	
	.ai-blog-ideas-v2 .ideas-table-container {
		min-height: 400px;
	}
}
</style>

<!-- WordPress Admin Notices Container -->
<div id="aiAdminNotices"></div>

<script>
// Debug the ai_blog_admin object
console.log('🔍 Debug: ai_blog_admin object:', typeof ai_blog_admin !== 'undefined' ? ai_blog_admin : 'UNDEFINED');
console.log('🔍 Debug: ai_blog_admin.nonce:', typeof ai_blog_admin !== 'undefined' && ai_blog_admin ? ai_blog_admin.nonce : 'NO NONCE');
console.log('🔍 Debug: ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED');

// If ai_blog_admin is undefined, create a fallback
if (typeof ai_blog_admin === 'undefined') {
	console.log('⚠️ ai_blog_admin is undefined, creating fallback');
	window.ai_blog_admin = {
		nonce: '<?php echo wp_create_nonce( "ai_blog_admin_nonce" ); ?>',
		ajaxurl: '<?php echo admin_url( "admin-ajax.php" ); ?>',
		adminUrl: '<?php echo admin_url(); ?>',
		personas: {},
		categories: {},
		strings: {
			confirm_approve: 'Are you sure you want to approve this idea?',
			confirm_deny: 'Are you sure you want to deny this idea?',
			generating: 'Generating...',
			loading: 'Loading...',
			success: 'Success!',
			error: 'Error!'
		},
		settings: {
			posts_per_day: 999,
			auto_publish: false,
			generation_paused: false
		}
	};
	console.log('✅ Fallback ai_blog_admin created:', window.ai_blog_admin);
} else {
	console.log('✅ ai_blog_admin already exists:', ai_blog_admin);
}

// Final verification after DOM loads
document.addEventListener('DOMContentLoaded', function() {
	// Initialize Bootstrap tooltips
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
	var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new bootstrap.Tooltip(tooltipTriggerEl);
	});
	
	// Final verification
	setTimeout(function() {
		console.log('🔍 Final ai_blog_admin verification:', {
			defined: typeof ai_blog_admin !== 'undefined',
			has_nonce: ai_blog_admin && ai_blog_admin.nonce,
			nonce_value: ai_blog_admin ? ai_blog_admin.nonce : 'none'
		});
	}, 1000);
});
</script> 