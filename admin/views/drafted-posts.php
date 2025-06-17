<?php
/**
 * Drafted Posts Page View
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
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="ai-blog-admin-content">
		<?php if ( empty( $drafted_posts ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No drafted posts found. Generated posts will appear here for review before publishing.', 'ai-blog-generator' ); ?></p>
			</div>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Review and edit generated posts before publishing. You can schedule them for specific times or publish immediately.', 'ai-blog-generator' ); ?>
			</p>
			
			<table class="wp-list-table widefat fixed striped ai-blog-drafts-table">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-title column-primary">
							<?php esc_html_e( 'Title', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-idea">
							<?php esc_html_e( 'Original Idea', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-category">
							<?php esc_html_e( 'Category', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-cost">
							<?php esc_html_e( 'Cost', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-created">
							<?php esc_html_e( 'Generated', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-scheduled">
							<?php esc_html_e( 'Scheduled For', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-actions">
							<?php esc_html_e( 'Actions', 'ai-blog-generator' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $drafted_posts as $post ) : ?>
						<tr data-post-id="<?php echo esc_attr( $post->post_id ); ?>" 
							data-blog-id="<?php echo esc_attr( $post->id ); ?>">
							<td class="column-title column-primary">
								<strong>
									<a href="<?php echo esc_url( get_edit_post_link( $post->post_id ) ); ?>" 
										class="row-title">
										<?php echo esc_html( $post->post_title ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( get_edit_post_link( $post->post_id ) ); ?>">
											<?php esc_html_e( 'Edit', 'ai-blog-generator' ); ?>
										</a> |
									</span>
									<span class="view">
										<a href="<?php echo esc_url( get_preview_post_link( $post->post_id ) ); ?>" target="_blank">
											<?php esc_html_e( 'Preview', 'ai-blog-generator' ); ?>
										</a>
									</span>
								</div>
								<button type="button" class="toggle-row">
									<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'ai-blog-generator' ); ?></span>
								</button>
							</td>
							<td class="column-idea" data-colname="<?php esc_attr_e( 'Original Idea', 'ai-blog-generator' ); ?>">
								<?php echo esc_html( $post->idea_title ); ?>
							</td>
							<td class="column-category" data-colname="<?php esc_attr_e( 'Category', 'ai-blog-generator' ); ?>">
								<?php 
								$categories = get_the_category( $post->post_id );
								if ( ! empty( $categories ) ) {
									$category_names = wp_list_pluck( $categories, 'name' );
									echo esc_html( implode( ', ', $category_names ) );
								} else {
									echo esc_html__( 'Uncategorized', 'ai-blog-generator' );
								}
								?>
							</td>
							<td class="column-cost" data-colname="<?php esc_attr_e( 'Cost', 'ai-blog-generator' ); ?>">
								$<?php echo esc_html( number_format( $post->cost, 2 ) ); ?>
							</td>
							<td class="column-created" data-colname="<?php esc_attr_e( 'Generated', 'ai-blog-generator' ); ?>">
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $post->created_at ) ) ); ?>
							</td>
							<td class="column-scheduled" data-colname="<?php esc_attr_e( 'Scheduled For', 'ai-blog-generator' ); ?>">
								<?php if ( ! empty( $post->scheduled_time ) && $post->scheduled_time !== '0000-00-00 00:00:00' ) : ?>
									<input type="datetime-local" class="schedule-time" 
										data-blog-id="<?php echo esc_attr( $post->id ); ?>"
										value="<?php echo esc_attr( date( 'Y-m-d\TH:i', strtotime( $post->scheduled_time ) ) ); ?>" />
								<?php else : ?>
									<input type="datetime-local" class="schedule-time" 
										data-blog-id="<?php echo esc_attr( $post->id ); ?>"
										value="<?php echo esc_attr( date( 'Y-m-d\TH:i', strtotime( '+1 day ' . $scheduler->get_random_publish_time() ) ) ); ?>" />
								<?php endif; ?>
							</td>
							<td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'ai-blog-generator' ); ?>">
								<button type="button" class="button button-primary publish-now" 
									data-post-id="<?php echo esc_attr( $post->post_id ); ?>"
									data-blog-id="<?php echo esc_attr( $post->id ); ?>">
									<?php esc_html_e( 'Publish Now', 'ai-blog-generator' ); ?>
								</button>
								<button type="button" class="button schedule-post" 
									data-post-id="<?php echo esc_attr( $post->post_id ); ?>"
									data-blog-id="<?php echo esc_attr( $post->id ); ?>">
									<?php esc_html_e( 'Schedule', 'ai-blog-generator' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<!-- Bulk Actions -->
			<div class="ai-blog-bulk-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'ai-blog-generator' ); ?></h3>
				<p>
					<button type="button" class="button" id="publish-all-drafts">
						<?php esc_html_e( 'Publish All Now', 'ai-blog-generator' ); ?>
					</button>
					<button type="button" class="button" id="schedule-all-drafts">
						<?php esc_html_e( 'Schedule All', 'ai-blog-generator' ); ?>
					</button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Bulk scheduling will distribute posts evenly throughout the configured time range over the next few days.', 'ai-blog-generator' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.ai-blog-drafts-table .column-title {
	width: 30%;
}
.ai-blog-drafts-table .column-idea {
	width: 20%;
}
.ai-blog-drafts-table .column-category {
	width: 10%;
}
.ai-blog-drafts-table .column-cost {
	width: 8%;
}
.ai-blog-drafts-table .column-created {
	width: 10%;
}
.ai-blog-drafts-table .column-scheduled {
	width: 12%;
}
.ai-blog-drafts-table .column-actions {
	width: 10%;
}
.schedule-time {
	width: 100%;
	max-width: 200px;
}
.ai-blog-bulk-actions {
	margin-top: 30px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
}
.ai-blog-bulk-actions h3 {
	margin-top: 0;
}
@media screen and (max-width: 782px) {
	.ai-blog-drafts-table .column-title {
		width: auto;
	}
	.ai-blog-drafts-table .column-idea,
	.ai-blog-drafts-table .column-category,
	.ai-blog-drafts-table .column-cost,
	.ai-blog-drafts-table .column-created {
		display: none;
	}
	.ai-blog-drafts-table .column-scheduled,
	.ai-blog-drafts-table .column-actions {
		width: auto;
		display: table-cell;
	}
	.ai-blog-drafts-table .column-actions button {
		display: block;
		width: 100%;
		margin-bottom: 5px;
	}
}
</style> 
 
 