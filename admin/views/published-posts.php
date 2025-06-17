<?php
/**
 * Published Posts Page View
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
		<!-- Monthly Statistics -->
		<?php if ( ! empty( $statistics ) ) : ?>
			<div class="ai-blog-monthly-stats">
				<h2><?php esc_html_e( 'Monthly Statistics', 'ai-blog-generator' ); ?></h2>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Month', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Total Posts', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Published', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Scheduled', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Drafts', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Total Cost', 'ai-blog-generator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $statistics, 0, 6 ) as $stat ) : ?>
							<tr>
								<td><?php echo esc_html( date( 'F Y', strtotime( $stat['period'] . '-01' ) ) ); ?></td>
								<td><?php echo esc_html( $stat['total_posts'] ); ?></td>
								<td><?php echo esc_html( $stat['published_count'] ); ?></td>
								<td><?php echo esc_html( $stat['scheduled_count'] ); ?></td>
								<td><?php echo esc_html( $stat['draft_count'] ); ?></td>
								<td>$<?php echo esc_html( number_format( $stat['total_cost'], 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		
		<!-- Published Posts List -->
		<h2><?php esc_html_e( 'Published Posts', 'ai-blog-generator' ); ?></h2>
		
		<?php if ( empty( $published_posts ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No published posts yet. Posts will appear here after they are published.', 'ai-blog-generator' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped ai-blog-published-table">
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
						<th scope="col" class="manage-column column-views">
							<?php esc_html_e( 'Views', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-cost">
							<?php esc_html_e( 'Cost', 'ai-blog-generator' ); ?>
						</th>
						<th scope="col" class="manage-column column-published">
							<?php esc_html_e( 'Published', 'ai-blog-generator' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $published_posts as $post ) : ?>
						<tr>
							<td class="column-title column-primary">
								<strong>
									<a href="<?php echo esc_url( get_permalink( $post->post_id ) ); ?>" 
										class="row-title" target="_blank">
										<?php echo esc_html( $post->post_title ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="view">
										<a href="<?php echo esc_url( get_permalink( $post->post_id ) ); ?>" target="_blank">
											<?php esc_html_e( 'View', 'ai-blog-generator' ); ?>
										</a> |
									</span>
									<span class="edit">
										<a href="<?php echo esc_url( get_edit_post_link( $post->post_id ) ); ?>">
											<?php esc_html_e( 'Edit', 'ai-blog-generator' ); ?>
										</a> |
									</span>
									<span class="stats">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=stats&post=' . $post->post_id ) ); ?>">
											<?php esc_html_e( 'Stats', 'ai-blog-generator' ); ?>
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
							<td class="column-views" data-colname="<?php esc_attr_e( 'Views', 'ai-blog-generator' ); ?>">
								<?php 
								// Get post views if available (requires analytics plugin)
								$views = get_post_meta( $post->post_id, 'post_views_count', true );
								echo esc_html( $views ?: '-' );
								?>
							</td>
							<td class="column-cost" data-colname="<?php esc_attr_e( 'Cost', 'ai-blog-generator' ); ?>">
								$<?php echo esc_html( number_format( $post->cost, 2 ) ); ?>
							</td>
							<td class="column-published" data-colname="<?php esc_attr_e( 'Published', 'ai-blog-generator' ); ?>">
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $post->post_date ) ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<!-- Performance Summary -->
			<div class="ai-blog-performance-summary">
				<h3><?php esc_html_e( 'Performance Summary', 'ai-blog-generator' ); ?></h3>
				<div class="ai-blog-stats-row">
					<div class="ai-blog-stat-box">
						<h4><?php echo esc_html( count( $published_posts ) ); ?></h4>
						<p><?php esc_html_e( 'Total Published', 'ai-blog-generator' ); ?></p>
					</div>
					<div class="ai-blog-stat-box">
						<h4>$<?php 
							$total_cost = array_sum( array_column( $published_posts, 'cost' ) );
							echo esc_html( number_format( $total_cost, 2 ) ); 
						?></h4>
						<p><?php esc_html_e( 'Total Cost', 'ai-blog-generator' ); ?></p>
					</div>
					<div class="ai-blog-stat-box">
						<h4>$<?php 
							$avg_cost = count( $published_posts ) > 0 ? $total_cost / count( $published_posts ) : 0;
							echo esc_html( number_format( $avg_cost, 2 ) ); 
						?></h4>
						<p><?php esc_html_e( 'Average Cost', 'ai-blog-generator' ); ?></p>
					</div>
					<div class="ai-blog-stat-box">
						<h4><?php 
							$categories_used = [];
							foreach ( $published_posts as $post ) {
								$cats = get_the_category( $post->post_id );
								foreach ( $cats as $cat ) {
									$categories_used[ $cat->term_id ] = true;
								}
							}
							echo esc_html( count( $categories_used ) );
						?></h4>
						<p><?php esc_html_e( 'Categories Used', 'ai-blog-generator' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.ai-blog-monthly-stats {
	margin-bottom: 30px;
}
.ai-blog-monthly-stats h2 {
	margin-bottom: 10px;
}
.ai-blog-published-table .column-title {
	width: 30%;
}
.ai-blog-published-table .column-idea {
	width: 20%;
}
.ai-blog-published-table .column-category {
	width: 15%;
}
.ai-blog-published-table .column-views {
	width: 10%;
}
.ai-blog-published-table .column-cost {
	width: 10%;
}
.ai-blog-published-table .column-published {
	width: 15%;
}
.ai-blog-performance-summary {
	margin-top: 30px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
}
.ai-blog-performance-summary h3 {
	margin-top: 0;
	margin-bottom: 20px;
}
.ai-blog-stats-row {
	display: flex;
	gap: 20px;
	flex-wrap: wrap;
}
.ai-blog-stat-box {
	flex: 1;
	min-width: 150px;
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
	text-align: center;
}
.ai-blog-stat-box h4 {
	margin: 0 0 10px;
	font-size: 28px;
	color: #23282d;
}
.ai-blog-stat-box p {
	margin: 0;
	color: #666;
	font-size: 13px;
}
@media screen and (max-width: 782px) {
	.ai-blog-published-table .column-idea,
	.ai-blog-published-table .column-category,
	.ai-blog-published-table .column-views {
		display: none;
	}
	.ai-blog-stat-box {
		min-width: calc(50% - 10px);
	}
}
</style> 
 
 