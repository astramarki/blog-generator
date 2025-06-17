<?php
/**
 * Approved Ideas V2 View
 *
 * Modern interface for managing approved blog ideas with generation workflow.
 * Features live updates and progress tracking for AI generation.
 *
 * @package AI_Blog_Generator
 * @subpackage Admin/Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap ai-approved-ideas-v2">
	<div class="container-fluid px-3 py-3">
		
		<!-- Header Section -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h1 class="h2 mb-1 text-success">
							<i class="fas fa-check-circle me-2"></i>
							Approved Ideas V2
						</h1>
						<p class="text-muted mb-0">Generate blog posts from approved ideas with AI</p>
					</div>
					<div class="d-flex align-items-center">
						<div class="me-3">
							<small class="text-muted">Auto-refresh: </small>
							<span class="badge bg-success" id="refreshStatus">ON</span>
						</div>
						<button type="button" class="btn btn-outline-secondary" id="toggleAutoRefresh">
							<i class="fas fa-pause me-1"></i>
							Pause Updates
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
							<div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
								<i class="fas fa-check-circle text-success fs-4"></i>
							</div>
							<div class="text-start">
								<h3 class="mb-0 text-success" id="stat-approved">0</h3>
								<small class="text-muted">Ready to Generate</small>
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
								<h3 class="mb-0 text-warning" id="stat-generating">0</h3>
								<small class="text-muted">In Progress</small>
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
									<button type="button" class="btn btn-outline-success btn-sm me-2" id="bulkSubmitForGeneration" disabled>
										<i class="fas fa-play me-1"></i>
										Submit for Generation
									</button>
									<button type="button" class="btn btn-outline-danger btn-sm" id="bulkDenySelected" disabled>
										<i class="fas fa-times me-1"></i>
										Deny Selected
									</button>
								</div>
							</div>
							<div class="col-md-6">
								<div class="d-flex justify-content-md-end">
									<button type="button" class="btn btn-outline-warning btn-sm me-2" id="generationManagementBtn" data-bs-toggle="modal" data-bs-target="#generationManagementModal">
										<i class="fas fa-cogs me-1"></i>
										Manage Generations
									</button>
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
							Approved Ideas
							<span class="badge bg-success ms-2" id="approvedCount">0</span>
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
										<th scope="col" style="width: 250px;">Generation Status</th>
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
							<div class="spinner-border text-success" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
							<p class="mt-3 text-muted">Loading approved ideas...</p>
						</div>
						
						<!-- Empty State -->
						<div class="text-center py-5 d-none" id="emptyState">
							<i class="fas fa-check-circle text-muted" style="font-size: 3rem;"></i>
							<h5 class="mt-3 text-muted">No approved ideas</h5>
							<p class="text-muted">Head over to Blog Ideas V2 to approve some ideas for generation!</p>
							<a href="<?php echo admin_url( 'admin.php?page=ai-blog-generator-ideas-v2' ); ?>" class="btn btn-success">
								<i class="fas fa-lightbulb me-2"></i>
								Go to Blog Ideas
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Edit Idea Modal -->
<div class="modal fade" id="editIdeaModal" tabindex="-1" aria-labelledby="editIdeaModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editIdeaModalLabel">
					<i class="fas fa-edit me-2 text-primary"></i>
					Edit Idea Details
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="editIdeaForm">
				<div class="modal-body">
					<input type="hidden" id="editIdeaId" name="idea_id">
					
					<div class="mb-3">
						<label for="editIdeaTitle" class="form-label">
							<i class="fas fa-heading me-1"></i>
							Title <span class="text-danger">*</span>
						</label>
						<input type="text" class="form-control" id="editIdeaTitle" name="title" required 
							   placeholder="Enter the blog post title...">
						<div class="form-text">This will be the main title of your blog post.</div>
					</div>
					
					<div class="mb-3">
						<label for="editIdeaDescription" class="form-label">
							<i class="fas fa-align-left me-1"></i>
							Description <span class="text-danger">*</span>
						</label>
						<textarea class="form-control" id="editIdeaDescription" name="description" rows="4" required
								  placeholder="Describe what this blog post should cover..."></textarea>
						<div class="form-text">Provide a detailed description of the blog post content and angle.</div>
					</div>
					
					<div class="mb-3">
						<label for="editIdeaPersona" class="form-label">
							<i class="fas fa-user-edit me-1"></i>
							Writing Persona
						</label>
						<select class="form-control" id="editIdeaPersona" name="persona_id">
							<option value="">No specific persona</option>
							<?php
							try {
								$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
								$personas = $persona_model->get_all();
								foreach ( $personas as $persona ) {
									// Handle both object and array formats for compatibility
									if ( is_object( $persona ) ) {
										$persona_id = $persona->id;
										$persona_name = $persona->name;
										$persona_tone = $persona->tone;
									} else {
										$persona_id = $persona['id'];
										$persona_name = $persona['name'];
										$persona_tone = $persona['tone'];
									}
									
									echo '<option value="' . esc_attr( $persona_id ) . '">' . 
										 esc_html( $persona_name ) . ' (' . esc_html( $persona_tone ) . ')</option>';
								}
							} catch ( Exception $e ) {
								echo '<option disabled>Error loading personas</option>';
							}
							?>
						</select>
						<div class="form-text">Choose a writing persona to define the tone and style of the blog post.</div>
					</div>
					
					<div class="alert alert-info">
						<i class="fas fa-info-circle me-2"></i>
						<strong>Note:</strong> Ideas that are currently being generated cannot be edited. 
						You can cancel the generation first if needed.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save me-2"></i>
						Save Changes
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Generation Log Viewer Modal -->
<div class="modal fade" id="logViewerModal" tabindex="-1" aria-labelledby="logViewerModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="logViewerModalLabel">
					<i class="fas fa-file-alt me-2 text-info"></i>
					Generation Log
				</h5>
				<div class="d-flex align-items-center">
					<button type="button" class="btn btn-sm btn-outline-secondary me-2" id="refreshLogBtn">
						<i class="fas fa-sync-alt me-1"></i>
						Refresh
					</button>
					<button type="button" class="btn btn-sm btn-outline-danger me-2" id="cancelGenerationBtn">
						<i class="fas fa-stop me-1"></i>
						Cancel Generation
					</button>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
			</div>
			<div class="modal-body p-0">
				<div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light border-bottom">
					<div>
						<small class="text-muted">Idea:</small>
						<strong id="logIdeaTitle">Loading...</strong>
					</div>
					<div>
						<small class="text-muted">Log Size:</small>
						<span id="logSize" class="badge bg-secondary">0 B</span>
						<small class="text-muted ms-2">Last Updated:</small>
						<span id="logLastModified" class="text-muted">-</span>
					</div>
				</div>
				
				<div class="position-relative">
					<pre id="logContent" class="mb-0 p-3" style="height: 60vh; overflow-y: auto; background-color: #1e1e1e; color: #d4d4d4; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;">Loading generation log...</pre>
					
					<!-- Auto-scroll controls -->
					<div class="position-absolute top-0 end-0 p-2">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="autoScrollLog" checked>
							<label class="form-check-label" for="autoScrollLog" style="color: #d4d4d4; font-size: 12px;">
								Auto-scroll
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Generation Management Modal -->
<div class="modal fade" id="generationManagementModal" tabindex="-1" aria-labelledby="generationManagementModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="generationManagementModalLabel">
					<i class="fas fa-cogs me-2 text-warning"></i>
					Generation Management
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-warning">
					<i class="fas fa-exclamation-triangle me-2"></i>
					<strong>Manage Active Generations</strong>
				</div>
				
				<div class="d-grid gap-2">
					<button type="button" class="btn btn-outline-danger" id="cancelAllGenerationsBtn">
						<i class="fas fa-stop-circle me-2"></i>
						Cancel All Active Generations
					</button>
					<button type="button" class="btn btn-outline-warning" id="resetStuckGenerationsBtn">
						<i class="fas fa-redo me-2"></i>
						Reset Stuck Generations (10+ minutes)
					</button>
				</div>
				
				<hr>
				
				<div class="text-center">
					<small class="text-muted">
						These actions affect all generating ideas. Individual generations can be canceled 
						from their respective log viewers.
					</small>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Generation Confirmation Modal -->
<div class="modal fade" id="generationConfirmModal" tabindex="-1" aria-labelledby="generationConfirmModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="generationConfirmModalLabel">
					<i class="fas fa-exclamation-triangle me-2 text-warning"></i>
					Confirm Blog Generation
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-info">
					<i class="fas fa-info-circle me-2"></i>
					<strong>Generating a blog post will:</strong>
					<ul class="mt-2 mb-0">
						<li>Create AI-generated content using your contexts and persona</li>
						<li>Generate images for the post</li>
						<li>Create a WordPress draft post ready for review</li>
						<li>Consume AI credits from your budget</li>
					</ul>
				</div>
				<p class="mb-0">
					Are you sure you want to generate a blog post for: 
					<strong id="confirmIdeaTitle">Idea Title</strong>?
				</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="confirmGeneration">
					<i class="fas fa-play me-2"></i>
					Start Generation
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Bulk Generation Confirmation Modal -->
<div class="modal fade" id="bulkGenerationConfirmModal" tabindex="-1" aria-labelledby="bulkGenerationConfirmModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="bulkGenerationConfirmModalLabel">
					<i class="fas fa-exclamation-triangle me-2 text-warning"></i>
					Confirm Bulk Generation
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-warning">
					<i class="fas fa-exclamation-triangle me-2"></i>
					<strong>You are about to generate <span id="bulkGenerationCount">0</span> blog posts.</strong>
				</div>
				<p>This will:</p>
				<ul>
					<li>Generate AI content for each selected idea</li>
					<li>Create images for each post</li>
					<li>Create WordPress draft posts</li>
					<li>May consume significant AI credits</li>
				</ul>
				<p class="mb-0"><strong>Are you sure you want to proceed?</strong></p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="confirmBulkGeneration">
					<i class="fas fa-play me-2"></i>
					Start Bulk Generation
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
						<small class="text-muted">Status:</small>
						<div id="ideaModalStatus">-</div>
					</div>
				</div>
				<div class="row mt-2">
					<div class="col-md-12">
						<small class="text-muted">Generation Status:</small>
						<div id="ideaModalGenerationStatus">-</div>
					</div>
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
.ai-approved-ideas-v2 {
	background-color: #f8f9fa;
	min-height: 100vh;
}

.ai-approved-ideas-v2 .container-fluid {
	max-width: none !important;
	padding-left: 1.5rem;
	padding-right: 1.5rem;
}

.ai-approved-ideas-v2 .card {
	border-radius: 12px;
	transition: all 0.3s ease;
	max-width: 100%;
}

.ai-approved-ideas-v2 .card:hover {
	transform: translateY(-2px);
}

.ai-approved-ideas-v2 .btn {
	border-radius: 8px;
	font-weight: 500;
}

.ai-approved-ideas-v2 .badge {
	border-radius: 6px;
}

/* Table Styling */
.ai-approved-ideas-v2 .ideas-table-container {
	min-height: 500px;
	max-height: 70vh;
	overflow-y: auto;
}

.ai-approved-ideas-v2 .table {
	width: 100%;
	margin-bottom: 0;
	font-size: 0.95rem;
}

/* Title column should use remaining space */
.ai-approved-ideas-v2 .table th:nth-child(2),
.ai-approved-ideas-v2 .table td:nth-child(2) {
	min-width: 250px;
}

.ai-approved-ideas-v2 .table th {
	border-top: none;
	font-weight: 600;
	color: #495057;
	white-space: nowrap;
	position: sticky;
	top: 0;
	background-color: #f8f9fa;
	z-index: 10;
}

.ai-approved-ideas-v2 .table td {
	vertical-align: middle;
	border-color: #e9ecef;
}

.ai-approved-ideas-v2 .table tbody tr:hover {
	background-color: #f8f9fa;
}

.ai-approved-ideas-v2 .form-check-input:checked {
	background-color: #198754;
	border-color: #198754;
}

.ai-approved-ideas-v2 .spinner-border {
	width: 2rem;
	height: 2rem;
}

/* Progress Bar Styling */
.generation-progress {
	height: 20px;
	border-radius: 10px;
	background-color: #e9ecef;
	overflow: hidden;
	position: relative;
}

.generation-progress .progress-bar {
	background-color: #198754;
	color: #000;
	font-size: 0.75rem;
	font-weight: 600;
	line-height: 20px;
	text-align: center;
	transition: width 0.6s ease;
}

.generation-progress .progress-text {
	position: absolute;
	width: 100%;
	text-align: center;
	line-height: 20px;
	font-size: 0.75rem;
	font-weight: 600;
	color: #000;
	z-index: 1;
}

/* Status Badge Styling */
.status-ready {
	background-color: #198754;
	color: white;
}

.status-queue {
	background-color: #fd7e14;
	color: white;
}

.status-generating {
	background-color: #0dcaf0;
	color: #000;
}

.status-generated {
	background-color: #6f42c1;
	color: white;
}

.status-error {
	background-color: #dc3545;
	color: white;
}

/* Modal Styling */
.ai-approved-ideas-v2 .modal-content {
	border-radius: 12px;
	border: none;
	box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.ai-approved-ideas-v2 .modal-header {
	border-bottom: 1px solid #e9ecef;
}

.ai-approved-ideas-v2 .modal-footer {
	border-top: 1px solid #e9ecef;
}

/* Loading and Empty States */
#loadingState, #emptyState {
	min-height: 400px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
}

/* Auto-refresh indicator */
.refresh-indicator {
	position: fixed;
	top: 32px;
	right: 20px;
	z-index: 1000;
	padding: 8px 12px;
	background-color: #198754;
	color: white;
	border-radius: 6px;
	font-size: 0.875rem;
	opacity: 0;
	transition: opacity 0.3s ease;
}

.refresh-indicator.show {
	opacity: 1;
}

/* Responsive Design */
@media (max-width: 768px) {
	.ai-approved-ideas-v2 .container-fluid {
		padding-left: 1rem;
		padding-right: 1rem;
	}
	
	.ai-approved-ideas-v2 .table-responsive {
		font-size: 0.875rem;
	}
	
	.ai-approved-ideas-v2 .ideas-table-container {
		min-height: 400px;
	}
	
	.generation-progress .progress-text {
		font-size: 0.65rem;
	}
}
</style>

<!-- WordPress Admin Notices Container -->
<div id="aiAdminNotices"></div>

<!-- Auto-refresh indicator -->
<div class="refresh-indicator" id="refreshIndicator">
	<i class="fas fa-sync-alt fa-spin me-1"></i>
	Updating...
</div>

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
			confirm_generation: 'Are you sure you want to generate a post for this idea?',
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

// Auto-refresh configuration
window.approvedIdeasConfig = {
	autoRefresh: true,
	refreshInterval: 5000, // 5 seconds
	refreshTimer: null
};

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