<?php
/**
 * Costs Dashboard Page View
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
		<!-- Budget Overview -->
		<div class="ai-blog-budget-overview <?php echo $budget_used_percentage >= 80 ? 'warning' : ''; ?>">
			<h2><?php esc_html_e( 'Monthly Budget Overview', 'ai-blog-generator' ); ?></h2>
			
			<div class="budget-stats">
				<div class="budget-stat">
					<span class="label"><?php esc_html_e( 'Budget:', 'ai-blog-generator' ); ?></span>
					<span class="value">$<?php echo esc_html( number_format( $monthly_budget, 2 ) ); ?></span>
				</div>
				<div class="budget-stat">
					<span class="label"><?php esc_html_e( 'Spent:', 'ai-blog-generator' ); ?></span>
					<span class="value">$<?php echo esc_html( number_format( $current_month_costs, 2 ) ); ?></span>
				</div>
				<div class="budget-stat">
					<span class="label"><?php esc_html_e( 'Remaining:', 'ai-blog-generator' ); ?></span>
					<span class="value">$<?php echo esc_html( number_format( max( 0, $monthly_budget - $current_month_costs ), 2 ) ); ?></span>
				</div>
				<div class="budget-stat">
					<span class="label"><?php esc_html_e( 'Used:', 'ai-blog-generator' ); ?></span>
					<span class="value"><?php echo esc_html( number_format( $budget_used_percentage, 1 ) ); ?>%</span>
				</div>
			</div>
			
			<div class="budget-progress-bar">
				<div class="progress-fill" style="width: <?php echo esc_attr( min( 100, $budget_used_percentage ) ); ?>%"></div>
			</div>
			
			<?php if ( $generation_paused ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Generation Paused:', 'ai-blog-generator' ); ?></strong>
						<?php esc_html_e( 'Blog generation has been paused because you have reached your budget limit.', 'ai-blog-generator' ); ?>
					</p>
				</div>
			<?php elseif ( $budget_used_percentage >= 80 ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Budget Alert:', 'ai-blog-generator' ); ?></strong>
						<?php esc_html_e( 'You have used over 80% of your monthly budget.', 'ai-blog-generator' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		
		<!-- Cost Charts -->
		<div class="ai-blog-cost-charts">
			<div class="chart-container">
				<h3><?php esc_html_e( '30-Day Cost Trend', 'ai-blog-generator' ); ?></h3>
				<canvas id="daily-cost-chart" width="400" height="200"></canvas>
			</div>
			
			<div class="chart-container">
				<h3><?php esc_html_e( 'Cost by Service', 'ai-blog-generator' ); ?></h3>
				<canvas id="service-cost-chart" width="400" height="200"></canvas>
			</div>
		</div>
		
		<!-- Cost Breakdown Table -->
		<div class="ai-blog-cost-breakdown">
			<h3><?php esc_html_e( 'Current Month Breakdown', 'ai-blog-generator' ); ?></h3>
			<?php if ( ! empty( $cost_breakdown ) ) : ?>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Action', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Service', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Count', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Total Tokens', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Total Cost', 'ai-blog-generator' ); ?></th>
							<th><?php esc_html_e( 'Avg Cost', 'ai-blog-generator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cost_breakdown as $item ) : ?>
							<tr>
								<td><code><?php echo esc_html( $item['action'] ); ?></code></td>
								<td><?php echo esc_html( ucfirst( $item['service'] ) ); ?></td>
								<td><?php echo esc_html( $item['request_count'] ); ?></td>
								<td><?php echo esc_html( number_format( $item['total_tokens'] ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $item['total_cost'], 4 ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $item['avg_cost'], 4 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="4"><?php esc_html_e( 'Total', 'ai-blog-generator' ); ?></th>
							<th>$<?php echo esc_html( number_format( $current_month_costs, 2 ) ); ?></th>
							<th>-</th>
						</tr>
					</tfoot>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No cost data available for the current month.', 'ai-blog-generator' ); ?></p>
			<?php endif; ?>
		</div>
		
		<!-- Cost Projections -->
		<?php if ( ! empty( $projections ) ) : ?>
			<div class="ai-blog-cost-projections">
				<h3><?php esc_html_e( 'Monthly Projections', 'ai-blog-generator' ); ?></h3>
				<div class="projection-stats">
					<div class="projection-stat">
						<h4>$<?php echo esc_html( number_format( $projections['daily_average'], 2 ) ); ?></h4>
						<p><?php esc_html_e( 'Daily Average', 'ai-blog-generator' ); ?></p>
					</div>
					<div class="projection-stat">
						<h4>$<?php echo esc_html( number_format( $projections['projected_total'], 2 ) ); ?></h4>
						<p><?php esc_html_e( 'Projected Total', 'ai-blog-generator' ); ?></p>
					</div>
					<div class="projection-stat">
						<h4><?php echo esc_html( $projections['days_remaining'] ); ?></h4>
						<p><?php esc_html_e( 'Days Remaining', 'ai-blog-generator' ); ?></p>
					</div>
					<div class="projection-stat <?php echo $projections['projected_total'] > $monthly_budget ? 'warning' : ''; ?>">
						<h4><?php 
							$projected_percentage = $monthly_budget > 0 ? ( $projections['projected_total'] / $monthly_budget ) * 100 : 0;
							echo esc_html( number_format( $projected_percentage, 1 ) ); 
						?>%</h4>
						<p><?php esc_html_e( 'Projected Usage', 'ai-blog-generator' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>
		
		<!-- Service Comparison -->
		<div class="ai-blog-service-comparison">
			<h3><?php esc_html_e( 'Service Cost Comparison', 'ai-blog-generator' ); ?></h3>
			<div class="service-stats">
				<?php foreach ( $cost_by_service as $service ) : ?>
					<div class="service-stat">
						<h4><?php echo esc_html( ucfirst( $service['service'] ) ); ?></h4>
						<p class="cost">$<?php echo esc_html( number_format( $service['total_cost'], 2 ) ); ?></p>
						<p class="requests"><?php 
							/* translators: %d: number of requests */
							printf( esc_html__( '%d requests', 'ai-blog-generator' ), $service['request_count'] ); 
						?></p>
						<?php if ( $service['service'] === 'anthropic' ) : ?>
							<p class="tokens"><?php 
								/* translators: %s: formatted number of tokens */
								printf( esc_html__( '%s tokens', 'ai-blog-generator' ), number_format( $service['total_tokens'] ) ); 
							?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<script>
// Prepare chart data
var dailyCostData = <?php echo wp_json_encode( array_map( function( $item ) {
	return [
		'date' => $item['date'],
		'cost' => floatval( $item['total_cost'] ),
	];
}, array_reverse( $daily_costs ) ) ); ?>;

var serviceCostData = <?php echo wp_json_encode( array_map( function( $item ) {
	return [
		'service' => ucfirst( $item['service'] ),
		'cost' => floatval( $item['total_cost'] ),
	];
}, $cost_by_service ) ); ?>;
</script>

<style>
.ai-blog-budget-overview {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 30px;
}
.ai-blog-budget-overview.warning {
	border-color: #ffb900;
}
.budget-stats {
	display: flex;
	justify-content: space-between;
	margin: 20px 0;
	flex-wrap: wrap;
}
.budget-stat {
	text-align: center;
	flex: 1;
	min-width: 120px;
}
.budget-stat .label {
	display: block;
	color: #666;
	font-size: 14px;
	margin-bottom: 5px;
}
.budget-stat .value {
	display: block;
	font-size: 24px;
	font-weight: 600;
	color: #23282d;
}
.budget-progress-bar {
	background: #e5e5e5;
	height: 20px;
	border-radius: 10px;
	overflow: hidden;
	margin: 20px 0;
}
.progress-fill {
	background: #00a0d2;
	height: 100%;
	transition: width 0.3s ease;
}
.ai-blog-budget-overview.warning .progress-fill {
	background: #ffb900;
}
.budget-used-percentage >= 100 .progress-fill {
	background: #dc3232;
}
.notice.inline {
	margin: 20px 0 0;
}
.ai-blog-cost-charts {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin-bottom: 30px;
}
.chart-container {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
}
.chart-container h3 {
	margin-top: 0;
	margin-bottom: 20px;
}
.ai-blog-cost-breakdown {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 30px;
}
.ai-blog-cost-breakdown h3 {
	margin-top: 0;
	margin-bottom: 20px;
}
.ai-blog-cost-projections {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 30px;
}
.projection-stats {
	display: flex;
	justify-content: space-around;
	flex-wrap: wrap;
	gap: 20px;
}
.projection-stat {
	text-align: center;
	flex: 1;
	min-width: 150px;
}
.projection-stat h4 {
	margin: 0 0 10px;
	font-size: 32px;
	color: #23282d;
}
.projection-stat.warning h4 {
	color: #dc3232;
}
.projection-stat p {
	margin: 0;
	color: #666;
}
.ai-blog-service-comparison {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
}
.service-stats {
	display: flex;
	justify-content: space-around;
	flex-wrap: wrap;
	gap: 30px;
	margin-top: 20px;
}
.service-stat {
	text-align: center;
	flex: 1;
	min-width: 200px;
	background: #f8f9fa;
	border: 1px solid #e2e4e7;
	border-radius: 4px;
	padding: 20px;
}
.service-stat h4 {
	margin: 0 0 15px;
	font-size: 18px;
}
.service-stat .cost {
	font-size: 28px;
	font-weight: 600;
	color: #23282d;
	margin: 10px 0;
}
.service-stat .requests,
.service-stat .tokens {
	color: #666;
	font-size: 14px;
	margin: 5px 0;
}
@media screen and (max-width: 1200px) {
	.ai-blog-cost-charts {
		grid-template-columns: 1fr;
	}
}
@media screen and (max-width: 782px) {
	.budget-stats {
		flex-direction: column;
	}
	.budget-stat {
		margin-bottom: 15px;
	}
	.projection-stats,
	.service-stats {
		flex-direction: column;
	}
}
</style> 
 
 