<?php
/**
 * Content Generator Usage Example
 *
 * This file demonstrates how to use the Content_Generator class
 * to orchestrate the complete blog generation workflow.
 * DO NOT include this file in production - it's for documentation purposes only.
 *
 * @package AI_Blog_Generator
 */

// Example of complete blog generation workflow

use AI_Blog_Generator\Services\Content_Generator;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Idea_Model;

// 1. Initialize the Content Generator
$content_generator = new Content_Generator();

// 2. Check if all services are properly configured
$service_status = $content_generator->check_services_status();

if ( ! $service_status['anthropic'] ) {
	error_log( 'Anthropic API not configured. Please add your API key in settings.' );
	return;
}

if ( ! $service_status['openai'] ) {
	error_log( 'OpenAI API not configured. Please add your API key in settings.' );
	return;
}

if ( ! $service_status['contexts'] ) {
	error_log( 'No active contexts found. Please configure contexts before generating.' );
	return;
}

// 3. Generate blog ideas
echo "=== STEP 1: GENERATING IDEAS ===\n";

$ideas_result = $content_generator->generate_ideas();

if ( ! $ideas_result['success'] ) {
	error_log( 'Failed to generate ideas: ' . $ideas_result['message'] );
	return;
}

echo "Generated " . count( $ideas_result['ideas'] ) . " ideas:\n";
foreach ( $ideas_result['ideas'] as $idea ) {
	echo "- " . $idea['title'] . " (Category: " . $idea['category'] . ")\n";
}

// 4. Approve an idea (normally done through admin interface)
$idea_model = new Idea_Model();
$first_idea = $ideas_result['ideas'][0];

echo "\n=== STEP 2: APPROVING IDEA ===\n";
echo "Approving: " . $first_idea['title'] . "\n";

$idea_model->update( $first_idea['id'], [ 'status' => 'approved' ] );

// 5. Generate blog post from approved idea
echo "\n=== STEP 3: GENERATING BLOG POST ===\n";

$blog_result = $content_generator->generate_blog_post( $first_idea['id'] );

if ( ! $blog_result['success'] ) {
	error_log( 'Failed to generate blog post: ' . $blog_result['message'] );
	return;
}

echo "Blog post generated successfully!\n";
echo "- Title: " . $blog_result['title'] . "\n";
echo "- Post ID: " . $blog_result['post_id'] . "\n";
echo "- Total Cost: $" . number_format( $blog_result['cost'], 4 ) . "\n";
echo "- Edit Link: " . $blog_result['edit_link'] . "\n";
echo "- Preview Link: " . $blog_result['preview_link'] . "\n";

// Example: Working with contexts
echo "\n=== CONTEXT MANAGEMENT EXAMPLE ===\n";

$context_model = new Context_Model();

// Add a new context
$context_id = $context_model->create([
	'name' => 'Holiday Season Content',
	'type' => 'general',
	'content' => 'Focus on holiday themes, gift guides, seasonal promotions, and festive content.',
	'active' => 1,
]);

echo "Created new context ID: " . $context_id . "\n";

// Example: Managing seed images for consistent product photos
$seed_image_data = [
	'context_id' => $context_id,
	'url' => 'https://example.com/product-photo.png',
	'keywords' => 'product, main product, flagship',
	'description' => 'Main product photo with transparent background',
];

// In real usage, seed images would be uploaded through the admin interface

// Example: Handling budget limits
$cost_model = new \AI_Blog_Generator\Models\Cost_Model();
$budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
$budget_info = $cost_model->get_budget_usage( $budget );

echo "\n=== BUDGET STATUS ===\n";
echo "Monthly Budget: $" . $budget_info['budget'] . "\n";
echo "Current Usage: $" . $budget_info['spent'] . "\n";
echo "Percentage Used: " . $budget_info['percentage'] . "%\n";
echo "Remaining: $" . $budget_info['remaining'] . "\n";

if ( $budget_info['percentage'] >= 100 ) {
	echo "WARNING: Budget exceeded! Generation will be paused.\n";
}

// Example: Error handling with transaction rollback
echo "\n=== ERROR HANDLING EXAMPLE ===\n";

// If an error occurs during generation, the Content_Generator automatically:
// 1. Rolls back database changes
// 2. Resets idea status
// 3. Logs the error
// 4. Returns detailed error message

// Example of checking logs
$logs = \AI_Blog_Generator\Utilities\Logger::get_logs([
	'action' => 'blog_generation_complete',
	'level' => 'info',
	'limit' => 5,
]);

echo "Recent successful generations:\n";
foreach ( $logs as $log ) {
	$context = maybe_unserialize( $log['context'] );
	echo "- " . $log['created_at'] . ": Post ID " . ( $context['post_id'] ?? 'N/A' ) . "\n";
}

// Workflow Summary:
// 1. Set up contexts with business info, products, SEO guidelines
// 2. Optionally upload seed images for product consistency
// 3. Generate ideas (avoiding duplicates and denied titles)
// 4. Review and approve ideas through admin interface
// 5. Generate complete blog posts with images
// 6. Posts are saved as drafts for review
// 7. Schedule or publish through WordPress

echo "\n=== COMPLETE WORKFLOW ===\n";
echo "1. Configure contexts in admin → Contexts page\n";
echo "2. Upload seed images for consistent product photos\n";
echo "3. Generate ideas daily (automated or manual)\n";
echo "4. Approve/deny ideas in admin → Blog Ideas page\n";
echo "5. Generate posts from approved ideas\n";
echo "6. Review drafts in admin → Drafted Posts page\n";
echo "7. Schedule or publish posts\n";
echo "8. Monitor costs in admin → Cost Dashboard\n";

// Example of enhanced context system usage

// 1. Initialize the Content Generator with enhanced context system
$enhanced_content_generator = new \AI_Blog_Generator\Services\Content_Generator();
$enhanced_context_model = new \AI_Blog_Generator\Models\Context_Model();

// 2. Example: Creating contexts with enhanced features
echo "=== ENHANCED CONTEXT SYSTEM EXAMPLES ===\n";

// Example 1: Multiple SEO contexts with priorities
$seo_contexts = [
    [
        'name' => 'Primary SEO Guidelines',
        'description' => 'Main SEO rules for all content',
        'type' => 'seo',
        'content' => 'Focus on user intent, optimize for featured snippets, use semantic keywords...',
        'priority' => 100, // Critical priority
        'usage_flags' => 'ideas,content', // Not used for images
        'active' => 1
    ],
    [
        'name' => 'Technical SEO Requirements',
        'description' => 'Technical SEO specifications',
        'type' => 'seo', 
        'content' => 'Ensure proper schema markup, optimize meta tags, use proper heading hierarchy...',
        'priority' => 75, // High priority
        'usage_flags' => 'content', // Only for content generation
        'active' => 1
    ],
    [
        'name' => 'Local SEO Guidelines',
        'description' => 'Location-specific SEO rules',
        'type' => 'seo',
        'content' => 'Include local keywords, mention service areas, add local schema...',
        'priority' => 50, // Normal priority
        'usage_flags' => 'ideas,content',
        'active' => 1
    ]
];

foreach ($seo_contexts as $context) {
    $enhanced_context_model->create($context);
    echo "Created SEO context: {$context['name']} (Priority: {$context['priority']})\n";
}

// Example 2: Product contexts with different priorities
$product_contexts = [
    [
        'name' => 'Core Product Line',
        'description' => 'Main products and services',
        'type' => 'products',
        'content' => 'Web development services, e-commerce solutions, custom applications...',
        'priority' => 100,
        'usage_flags' => 'ideas,content,images',
        'active' => 1
    ],
    [
        'name' => 'Premium Services',
        'description' => 'High-end service offerings',
        'type' => 'products',
        'content' => 'Enterprise consulting, custom integrations, white-glove support...',
        'priority' => 75,
        'usage_flags' => 'ideas,content',
        'active' => 1
    ]
];

foreach ($product_contexts as $context) {
    $enhanced_context_model->create($context);
    echo "Created product context: {$context['name']} (Priority: {$context['priority']})\n";
}

// 3. Example: Usage-specific context compilation
echo "\n=== CONTEXT COMPILATION EXAMPLES ===\n";

// Example 1: Contexts for idea generation (excludes low-priority and image-only contexts)
$idea_contexts = $enhanced_content_generator->compile_contexts_for_ideas([
    'max_contexts_per_type' => 2,  // Limit to top 2 per type
    'priority_threshold' => 50,    // Only priority 50+
]);

echo "Contexts for IDEA generation:\n";
foreach ($idea_contexts as $type => $content) {
    if (!empty($content)) {
        echo "- {$type}: " . substr($content, 0, 100) . "...\n";
    }
}

// Example 2: Contexts for content generation (includes all relevant contexts)
$content_contexts = $enhanced_content_generator->compile_contexts_enhanced('content', [
    'max_contexts_per_type' => 5,
    'priority_threshold' => 0,     // Include all priorities
]);

echo "\nContexts for CONTENT generation:\n";
foreach ($content_contexts as $type => $content) {
    if (!empty($content)) {
        echo "- {$type}: " . substr($content, 0, 100) . "...\n";
    }
}

// Example 3: Contexts for image generation (focuses on visual contexts)
$image_contexts = $enhanced_content_generator->compile_contexts_for_images([
    'priority_threshold' => 25,
]);

echo "\nContexts for IMAGE generation:\n";
foreach ($image_contexts as $type => $content) {
    if (!empty($content)) {
        echo "- {$type}: " . substr($content, 0, 100) . "...\n";
    }
}

// 4. Example: Advanced context filtering
echo "\n=== ADVANCED CONTEXT FILTERING ===\n";

// Get contexts with metadata for analysis
$prompt_ready_contexts = $enhanced_context_model->get_for_prompt('content', [
    'max_contexts_per_type' => 3,
    'include_metadata' => true,
    'priority_threshold' => 50,
    'type_filters' => ['seo', 'products'] // Only these types
]);

echo "Filtered contexts for prompts:\n";
foreach ($prompt_ready_contexts as $type => $data) {
    echo "Type: {$type}\n";
    echo "  Context count: {$data['context_count']}\n";
    if (isset($data['metadata']['contexts'])) {
        foreach ($data['metadata']['contexts'] as $ctx) {
            echo "  - {$ctx['name']} (Priority: {$ctx['priority']})\n";
        }
    }
    echo "\n";
}

// 5. Example: Context usage flags
echo "=== CONTEXT USAGE FLAGS ===\n";

$usage_flags = $enhanced_context_model->get_usage_flags();
echo "Available usage flags:\n";
foreach ($usage_flags as $flag => $description) {
    echo "- {$flag}: {$description}\n";
}

// Example of parsing usage flags
$sample_flags = "ideas,content";
$parsed_flags = $enhanced_context_model->parse_usage_flags($sample_flags);
echo "\nParsed flags from '{$sample_flags}': " . implode(', ', $parsed_flags) . "\n";

// 6. Example: Priority levels
echo "\n=== PRIORITY LEVELS ===\n";

$priority_levels = $enhanced_context_model->get_priority_levels();
echo "Available priority levels:\n";
foreach ($priority_levels as $level => $description) {
    echo "- {$level}: {$description}\n";
}

echo "\n=== ENHANCED CONTEXT WORKFLOW SUMMARY ===\n";
echo "1. Create multiple contexts of same type with different priorities\n";
echo "2. Configure usage flags for each context (ideas, content, images)\n";
echo "3. Use specialized compilation methods for different generation types\n";
echo "4. Apply filters and thresholds to control which contexts are used\n";
echo "5. Contexts are automatically organized with headers and descriptions\n";
echo "6. Backward compatibility maintained with existing functionality\n";

// Example of generated content structure
$example_content = [
	'title' => 'The Rise of Remote Work: Statistics and Trends for 2024',
	'meta_description' => 'Discover the latest remote work statistics and trends for 2024, backed by research from leading organizations. Learn how businesses are adapting.',
	'focus_keyphrase' => 'remote work statistics 2024',
	'tags' => ['remote work', 'work from home', 'telecommuting', 'workplace trends', 'employee statistics', 'hybrid work', 'digital nomad', 'workforce analytics'],
	'html' => '
<article class="blog-post">
	<section class="hero-section py-5 mb-5">
		<div class="container">
			<div class="row align-items-center">
				<div class="col-lg-8">
					<h1 class="display-4 mb-4">The Rise of Remote Work: Statistics and Trends for 2024</h1>
					<p class="lead">Remote work statistics for 2024 reveal a transformative shift in how we approach employment. According to recent studies, 35% of workers now operate fully remotely, while 58% enjoy hybrid arrangements (Gallup, 2024).</p>
				</div>
				<div class="col-lg-4">
					{{image1}}
				</div>
			</div>
		</div>
	</section>

	<section class="content-section mb-5">
		<div class="container">
			<div class="row">
				<div class="col-lg-10 mx-auto">
					<h2 class="h3 mb-4">Key Remote Work Statistics for 2024</h2>
					<p>The landscape of remote work has evolved dramatically. Research from Stanford University (2024) indicates that productivity has increased by 13% among remote workers, while employee satisfaction scores have risen by 22% compared to pre-pandemic levels.</p>
					
					<div class="alert alert-info mb-4" role="alert">
						<h4 class="alert-heading">Quick Stats:</h4>
						<ul class="mb-0">
							<li>87% of workers offered remote work take it (McKinsey, 2024)</li>
							<li>25% reduction in employee turnover for remote-friendly companies (Buffer, 2024)</li>
							<li>$11,000 average annual savings per remote employee (Global Workplace Analytics, 2024)</li>
						</ul>
					</div>

					<div class="chart-container my-5">
						<h3 class="h5 mb-3">Remote Work Adoption by Industry</h3>
						<div id="chart1" style="height: 400px;"></div>
					</div>

					<h2 class="h3 mb-4 mt-5">The Economic Impact</h2>
					<p>The economic implications of remote work extend beyond individual savings. A comprehensive study by the National Bureau of Economic Research (2024) found that remote work has contributed to a 5% increase in housing prices in suburban areas, while commercial real estate in urban centers has seen a 15% decline in value.</p>

					{{image2}}

					<div class="chart-container my-5">
						<h3 class="h5 mb-3">Remote Work Productivity Metrics</h3>
						<div id="chart2" style="height: 350px;"></div>
					</div>

					<h2 class="h3 mb-4 mt-5">Future Projections</h2>
					<p>Looking ahead, Gartner (2024) predicts that by 2025, 70% of organizations will have adopted a hybrid-first approach. This shift is driven by employee preferences, with 98% of workers expressing a desire for remote work options at least part of the time (Microsoft Work Trends Index, 2024).</p>

					<div class="card bg-light mb-4">
						<div class="card-body">
							<h5 class="card-title">Key Takeaways</h5>
							<ul class="card-text">
								<li>Remote work is now a permanent fixture in the employment landscape</li>
								<li>Productivity metrics consistently favor flexible work arrangements</li>
								<li>Companies must invest in digital infrastructure to remain competitive</li>
								<li>The future of work is hybrid, not binary</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</article>
	',
	'images' => [
		[
			'token' => '{{image1}}',
			'prompt' => 'Professional modern home office setup with a laptop, dual monitors, ergonomic chair, and plants, showing a productive remote work environment with natural lighting',
			'alt_text' => 'Modern home office setup for remote work',
		],
		[
			'token' => '{{image2}}',
			'prompt' => 'Infographic showing remote work statistics with colorful charts and icons representing different aspects of remote work benefits including productivity, work-life balance, and cost savings',
			'alt_text' => 'Remote work statistics and benefits infographic',
		],
	],
	'charts' => '
<script>
// Chart 1: Remote Work Adoption by Industry
var options1 = {
	series: [{
		name: "Percentage of Remote Workers",
		data: [85, 78, 72, 65, 58, 45, 42, 38]
	}],
	chart: {
		type: "bar",
		height: 400,
		toolbar: {
			show: false
		}
	},
	plotOptions: {
		bar: {
			borderRadius: 4,
			horizontal: true,
			dataLabels: {
				position: "top"
			}
		}
	},
	dataLabels: {
		enabled: true,
		formatter: function (val) {
			return val + "%";
		},
		offsetX: -6,
		style: {
			fontSize: "12px",
			colors: ["#304758"]
		}
	},
	xaxis: {
		categories: ["Technology", "Finance", "Marketing", "Consulting", "Education", "Healthcare", "Manufacturing", "Retail"],
		labels: {
			formatter: function (val) {
				return val + "%";
			}
		}
	},
	colors: ["#008FFB"],
	title: {
		text: "Remote Work Adoption by Industry (2024)",
		align: "center",
		style: {
			fontSize: "16px",
			fontWeight: "bold"
		}
	},
	responsive: [{
		breakpoint: 480,
		options: {
			legend: {
				position: "bottom",
				offsetX: -10,
				offsetY: 0
			}
		}
	}]
};

var chart1 = new ApexCharts(document.querySelector("#chart1"), options1);
chart1.render();

// Chart 2: Productivity Metrics
var options2 = {
	series: [{
		name: "Remote",
		data: [92, 88, 85, 91, 87]
	}, {
		name: "Office",
		data: [78, 82, 79, 83, 80]
	}, {
		name: "Hybrid",
		data: [95, 90, 88, 93, 89]
	}],
	chart: {
		type: "line",
		height: 350,
		toolbar: {
			show: false
		}
	},
	dataLabels: {
		enabled: false
	},
	stroke: {
		curve: "smooth",
		width: 3
	},
	xaxis: {
		categories: ["Productivity", "Satisfaction", "Engagement", "Retention", "Innovation"]
	},
	yaxis: {
		title: {
			text: "Score (%)"
		},
		min: 70,
		max: 100
	},
	colors: ["#00E396", "#008FFB", "#FEB019"],
	title: {
		text: "Work Arrangement Performance Metrics",
		align: "center",
		style: {
			fontSize: "16px",
			fontWeight: "bold"
		}
	},
	legend: {
		position: "top"
	},
	tooltip: {
		shared: true,
		intersect: false,
		y: {
			formatter: function (val) {
				return val + "%";
			}
		}
	},
	responsive: [{
		breakpoint: 480,
		options: {
			legend: {
				position: "bottom"
			}
		}
	}]
};

var chart2 = new ApexCharts(document.querySelector("#chart2"), options2);
chart2.render();
</script>
	',
	'references' => '
Buffer. (2024). State of Remote Work 2024. Retrieved from https://buffer.com/state-of-remote-work/2024

Gallup. (2024). State of the Global Workplace Report. Gallup, Inc.

Gartner. (2024). Future of Work Trends Post-COVID-19. Gartner Research.

Global Workplace Analytics. (2024). Telework Savings Calculator & Statistics. 

McKinsey & Company. (2024). The future of work in America: People and places, today and tomorrow.

Microsoft. (2024). Work Trend Index Annual Report. Microsoft Corporation.

National Bureau of Economic Research. (2024). The Donut Effect of COVID-19 on Cities. NBER Working Paper No. 28876.

Stanford University. (2024). How Working from Home Works Out. Stanford Institute for Economic Policy Research.
	',
];

// Example of how the final HTML would look after image replacement
$example_final_html = '
<article class="blog-post">
	<!-- Content with actual images replacing tokens -->
	<figure class="figure my-4 ai-generated-image">
		<img src="/wp-content/uploads/2024/01/remote-work-office.jpg" alt="Modern home office setup for remote work" class="figure-img img-fluid rounded" loading="lazy" />
	</figure>
	<!-- Rest of content... -->
</article>

<!-- References section appended -->
<section class="references-section mt-5 pt-4 border-top">
	<div class="container">
		<h2 class="h4 mb-3">References</h2>
		<div class="references-list small">
			<p class="mb-2">Buffer. (2024). State of Remote Work 2024. Retrieved from https://buffer.com/state-of-remote-work/2024</p>
			<!-- More references... -->
		</div>
	</div>
</section>

<!-- Charts script appended -->
<script>
	// ApexCharts initialization scripts
</script>
';
 
 