# AI Blog Generator - Technical Implementation Document

## Architecture Overview

The AI Blog Generator follows a Model-View-Controller (MVC) architecture pattern with WordPress integration best practices.

### Core Components:
- **Models**: Database abstraction layer with base model class
- **Controllers**: Handle AJAX requests and business logic
- **Services**: API integrations and core functionality
- **Views**: Admin interface templates
- **Utilities**: Helper classes for common operations

## Directory Structure
```
ai-blog-generator/
├── admin/
│   ├── assets/
│   │   ├── css/
│   │   │   └── admin.css (940 lines - comprehensive styling)
│   │   └── js/
│   │       ├── admin.js (948 lines - AJAX handlers, UI interactions)
│   │       ├── contexts.js
│   │       ├── personas.js
│   │       └── products.js
│   ├── views/
│   │   ├── settings.php
│   │   ├── blog-ideas.php
│   │   ├── blog-ideas-view-v2.php (Idea Generator page)
│   │   ├── approved-ideas-view-v2.php
│   │   ├── approved-blogs.php
│   │   ├── drafted-posts.php
│   │   ├── published-posts.php
│   │   ├── contexts.php
│   │   ├── personas.php
│   │   ├── products.php
│   │   ├── brand-features.php#004560
│   │   ├── logs.php
│   │   └── costs-dashboard.php
│   └── class-admin-manager.php (592 lines)
├── controllers/
│   ├── class-blog-controller.php (832 lines)
│   ├── class-idea-controller.php
│   ├── class-context-controller.php
│   ├── class-persona-controller.php
│   ├── class-product-controller.php
│   ├── class-image-controller.php
│   └── class-analytics-controller.php
├── includes/
│   ├── class-plugin-activator.php (304 lines)
│   ├── class-plugin-deactivator.php (225 lines)
│   ├── class-plugin-loader.php (219 lines)
│   └── class-plugin-i18n.php (299 lines)
├── models/
│   ├── class-model.php (abstract base)
│   ├── class-database-manager.php (singleton)
│   ├── class-blog-model.php
│   ├── class-idea-model.php
│   ├── class-context-model.php
│   ├── class-persona-model.php
│   ├── class-product-model.php
│   ├── class-log-model.php
│   └── class-cost-model.php
├── services/
│   ├── class-anthropic-service.php
│   ├── class-openai-service.php
│   ├── class-content-generator.php
│   ├── class-image-generator.php
│   ├── class-scheduler-service.php (690 lines)
│   └── class-prompt-compiler-service.php
├── utilities/
│   ├── class-logger.php (singleton)
│   ├── class-validator.php
│   └── class-cost-calculator.php
├── languages/
├── ai-blog-generator.php (344 lines - main plugin file)
├── test-integration.php (integration test suite)
└── uninstall.php
```

## Database Schema

### 1. Ideas Table (`wp_ai_blog_ideas`)
```sql
id (bigint) PRIMARY KEY
title (varchar 255)
description (text)
keywords (text)
category_id (bigint)
persona_id (bigint) FOREIGN KEY
status (enum: pending, approved, denied, generated) DEFAULT 'pending'
created_at (datetime)
updated_at (datetime)
```

### 2. Generated Posts Table (`wp_ai_blog_generated_posts`)
```sql
id (bigint) PRIMARY KEY
idea_id (bigint) FOREIGN KEY
persona_id (bigint) FOREIGN KEY
post_id (bigint)
title (varchar 255)
content (longtext)
excerpt (text)
featured_image_id (bigint)
seo_title (varchar 255)
seo_description (text)
cost (decimal 10,4)
status (enum: draft, scheduled, published) DEFAULT 'draft'
scheduled_time (datetime)
published_at (datetime)
created_at (datetime)
updated_at (datetime)
```

### 3. Contexts Table (`wp_ai_blog_contexts`)
```sql
id (bigint) PRIMARY KEY
name (varchar 255)
type (varchar 50)
content (longtext)
active (tinyint) DEFAULT 1
created_at (datetime)
updated_at (datetime)
```

### 4. Logs Table (`wp_ai_blog_logs`)
```sql
id (bigint) PRIMARY KEY
level (enum: info, warning, error)
action (varchar 100)
message (text)
context (longtext)
created_at (datetime)
```

### 5. Cost Analytics Table (`wp_ai_blog_cost_analytics`)
```sql
id (bigint) PRIMARY KEY
service (varchar 50)
action (varchar 100)
cost (decimal 10,4)
tokens_used (int)
created_at (datetime)
```

### 6. Seed Images Table (`wp_ai_blog_seed_images`)
```sql
id (bigint) PRIMARY KEY
context_id (bigint) FOREIGN KEY
attachment_id (bigint)
created_at (datetime)
```

### 7. Personas Table (`wp_ai_blog_personas`)
```sql
id (bigint) PRIMARY KEY
name (varchar 100)
bio (text)
expertise (text)
writing_style (text)
tone (varchar 50) DEFAULT 'professional'
active (tinyint) DEFAULT 1
created_at (datetime)
updated_at (datetime)
```

### 8. Products Table (`wp_ai_blog_generator_products`)
```sql
id (bigint) PRIMARY KEY
name (varchar 255)
description (text)
ideal_uses (text)
created_at (datetime)
updated_at (datetime)
```

### 9. Product Images Table (`wp_ai_blog_generator_product_images`)
```sql
id (bigint) PRIMARY KEY
product_id (bigint) FOREIGN KEY
attachment_id (bigint)
image_url (varchar 500)
is_primary (tinyint) DEFAULT 0
display_order (int) DEFAULT 0
created_at (datetime)
```

### 10. Product Links Table (`wp_ai_blog_generator_product_links`)
```sql
id (bigint) PRIMARY KEY
product_id (bigint) FOREIGN KEY
link_type (enum: product_page, purchase, documentation, other) DEFAULT 'other'
link_text (varchar 255)
link_url (varchar 500)
created_at (datetime)
```

### 11. Product Seed Images Table (`wp_ai_blog_generator_product_seed_images`)
```sql
id (bigint) PRIMARY KEY
product_id (bigint) FOREIGN KEY
attachment_id (bigint)
image_url (varchar 500)
display_order (int) DEFAULT 0
created_at (datetime)
```

### 12. Brand Features Table (`wp_ai_blog_brand_features`)
```sql
id (bigint) PRIMARY KEY
name (varchar 255)
description (text)
category (enum: informational_page, document, image, video) DEFAULT 'informational_page'
url (varchar 500)
active (tinyint) DEFAULT 1
created_at (datetime)
updated_at (datetime)
```

## Key Classes and Implementation

### 1. Database Manager (Singleton)
Location: `models/class-database-manager.php`

**Key Methods:**
- `get_instance()` - Singleton pattern implementation
- `create_tables()` - Creates all plugin tables
- `drop_tables()` - Removes all plugin tables
- `insert($table, $data)` - Generic insert with validation
- `update($table, $data, $where)` - Generic update
- `delete($table, $where)` - Generic delete
- `get($table, $where)` - Get single record
- `get_all($table, $where, $orderby, $limit)` - Get multiple records
- `query($sql, $params)` - Execute custom queries

### 2. Models (Extending Base Model)
All models extend the abstract `Model` class providing:
- CRUD operations
- Validation framework
- Timestamp handling
- Error handling

**Implemented Models:**
- `Blog_Model` - Manages generated posts with WordPress post integration
- `Idea_Model` - Handles blog ideas with category relationships
- `Context_Model` - Business context management
- `Persona_Model` - Writing personas with expertise and style management
- `Product_Model` - Product catalog with images and links management
- `Log_Model` - Logging with filtering and cleanup
- `Cost_Model` - Cost tracking and budget calculations

### 3. API Services

#### Anthropic Service
Location: `services/class-anthropic-service.php`

**Key Methods:**
- `generate_ideas($contexts)` - Generate blog ideas
- `generate_content($prompts)` - Generate full blog post with pre-compiled prompts (NEW)
- `generate_blog_content($idea, $contexts)` - Legacy content generation (deprecated)
- `test_connection()` - Validate API key
- Cost tracking integrated

**New Method - generate_content():**
- Accepts pre-compiled prompts array instead of raw idea/contexts
- Combines system prompts array into single system message
- Properly formats messages for Anthropic API
- Returns raw content response for parsing

**Enhanced Methods:**
- `generate_alt_text_from_prompt()` - Enhanced to include focus keyphrase in image alt text for improved SEO
- `generate_descriptive_filename()` - Enhanced to include focus keyphrase as first part of image filenames for SEO
- `generate_filename()` - Enhanced to include focus keyphrase as first part of standard filenames for SEO

#### OpenAI Service
Location: `services/class-openai-service.php`

**Key Methods:**
- `generate_image($prompt, $seed_image_id)` - Generate featured images
- `generate_with_seed_image($prompt, $seed_image_data)` - Use seed images
- `test_connection()` - Validate API key
- Cost tracking integrated

### 4. Content Generator
Location: `services/class-content-generator.php`

Orchestrates the content generation process:
- Validates contexts
- **NEW**: Uses Prompt Compiler Service for prompt generation
- Calls Anthropic API with compiled prompts
- Processes results into WordPress format
- Handles image generation
- Creates WordPress posts
- Tracks all costs

**Updated Properties:**
- `$prompt_compiler` - Instance of Prompt_Compiler_Service

**Updated Methods:**
- `generate_blog_post()` - Now uses `$this->prompt_compiler->generate_content_prompts()`
- Calls new `generate_content()` method instead of deprecated `generate_blog_content()`
- `validate_generated_content()` - Updated to enforce 140-character limit for meta descriptions
- `generate_alt_text_from_prompt()` - Enhanced to include focus keyphrase in image alt text for SEO

### 5. Prompt Compiler Service
Location: `services/class-prompt-compiler-service.php`

**Purpose:** Centralizes all prompt compilation logic, providing abstraction between AI services and prompt generation.

**Key Methods:**
- `generate_content_prompts($idea_id)` - Main entry point for content generation prompts
- `build_system_prompts($idea, $persona, $contexts, $brand_features, $keywords, $products)` - Constructs array of system prompts
- `build_user_prompt($idea, $target_keywords, $persona, $products)` - Creates user prompt with requirements
- `generate_image_prompts($image_requirements, $persona)` - Generates prompts for image creation

**System Prompt Components:**
1. **Persona Information**:
   - Biography and expertise
   - Writing style and tone
   - Format preferences (HTML/Avada)

2. **Layout Instructions**:
   - Based on persona's layout style settings
   - Chart usage preferences
   - Image placement guidelines

3. **Context Compilation**:
   - Always-include contexts (always_include_content = 1)
   - Persona-specific contexts
   - Format-specific contexts (HTML or Avada)

4. **Product Requirements**:
   - Minimum 2 product promotions
   - Product descriptions and ideal uses
   - Natural integration instructions

5. **SEO Guidelines**:
   - Previously used keyphrases to avoid
   - Keyword density requirements
   - Meta description format (120-140 characters for optimal SEO performance)

6. **Brand Features**:
   - Internal linking opportunities
   - Brand feature descriptions

**User Prompt Structure:**
- 2 randomly selected target keywords
- Image placeholder requirements (based on persona's `number_of_images` setting):
  - 0 = No content images, only featured image generated
  - 1+ = That many content images plus featured image
  - Default is 2 content images if not specified
- SEO metadata requirements
- Output format specifications
- Word count requirements

**Logging Features:**
- Writes all compiled prompts to `{upload_dir}/ai-blog-generator-logs/{idea_id}_prompts.txt`
- Includes timestamp and formatting for readability
- Useful for debugging and prompt optimization

**Integration Points:**
- Used by Content_Generator service
- Works with all model classes to gather required data
- Compatible with both Anthropic and future AI providers
- Uses `Product_Model::get_all_with_relations()` to fetch complete product data including images and links

### 6. Controllers

All controllers implement:
- `register_ajax_handlers()` - Registers WordPress AJAX hooks
- Nonce verification
- Capability checking
- Input sanitization
- JSON response format
- Error handling with logging

**Implemented Controllers:**
- `Blog_Controller` - Handles blog post generation and management
- `Idea_Controller` - Manages idea generation, approval, and denial
- `Context_Controller` - CRUD operations for contexts
- `Persona_Controller` - CRUD operations for personas, persona assignment
- `Product_Controller` - CRUD operations for products, images, links, and WooCommerce import
- `Image_Controller` - Image generation and seed image management
- `Analytics_Controller` - Cost tracking and analytics data

### 7. Scheduler Service
Location: `services/class-scheduler-service.php`

**Cron Jobs:**
- `daily_idea_generation()` - Runs at 6 AM daily
- `process_approved_ideas_queue()` - Runs hourly (currently disabled)
- `publish_scheduled_posts()` - Runs every 15 minutes
- `cleanup_old_data()` - Runs at 3 AM daily

### 8. Utilities

#### Logger (Singleton)
- Database and error_log dual logging
- Log levels: info, warning, error
- Automatic cleanup of old logs
- Context serialization

#### Cost Calculator
- Pricing models for both APIs
- Budget checking
- Monthly projections
- Service-specific tracking

#### Validator
- Content validation
- SEO checks
- Input sanitization helpers

## WordPress Integration

### 1. Hooks and Filters
- Activation/Deactivation hooks properly registered
- Custom cron schedules added
- Admin menu integration
- AJAX handlers registered

### 2. Security Implementation
- Nonce verification on all AJAX calls
- Capability checking (`manage_options`)
- Input sanitization using WordPress functions
- Output escaping in all views

### 3. Database Operations
- Uses WordPress `$wpdb` for all queries
- Prepared statements for security
- Table prefix handling
- Charset/collation from WordPress

### 4. Admin Interface
- WordPress admin styling
- Responsive design
- AJAX-based interactions
- Proper loading states

## Current Implementation Status

### ✅ Completed:
1. **Database Layer**: All models and Database Manager implemented
2. **API Services**: Both Anthropic and OpenAI services complete with error handling
3. **Controllers**: All 7 controllers with AJAX handlers (including Products and Brand Features)
4. **Admin Views**: All 11 admin pages created with proper escaping
5. **Logger**: Complete with database storage and cleanup
6. **Cost Tracking**: Integrated in all API calls
7. **Plugin Infrastructure**: Activator, Deactivator, Loader implemented
8. **Cron System**: All scheduled tasks registered
9. **Admin Assets**: CSS (940 lines) and JS (948 lines) complete
10. **Products Management**: Full CRUD with images, links, and WooCommerce import
11. **Brand Features Management**: Complete system for internal linking
12. **Prompt Compiler Service**: Full abstraction layer for prompt generation

### ⚠️ Areas for Enhancement:
1. **Dependency Injection**: Services currently use direct instantiation instead of DI
2. **Unit Tests**: Only integration test created, unit tests needed
3. **Internationalization**: Structure in place but translations needed
4. **API Rate Limiting**: Basic retry logic but could be enhanced
5. **Caching Layer**: No caching implemented yet

### 🔧 Integration Points Verified:
1. ✅ Database tables created on activation
2. ✅ Cron jobs registered correctly
3. ✅ Admin pages load without errors
4. ✅ AJAX handlers properly connected
5. ✅ Costs tracked for all API calls
6. ✅ Logs capture all important events
7. ✅ Error handling prevents crashes
8. ✅ All user inputs validated
9. ✅ All outputs properly escaped

## Testing

### Integration Test
Location: `test-integration.php`

Run with: `wp eval-file test-integration.php`

Tests:
- Database table creation
- Singleton patterns
- Cron job registration
- Admin page registration
- AJAX handler connections
- Cost tracking functionality
- Logging system
- Error handling
- Input validation
- Output escaping

## Security Considerations

1. **API Keys**: Stored in WordPress options (consider encryption)
2. **Nonces**: Used on all AJAX requests
3. **Capabilities**: All actions require `manage_options`
4. **SQL Injection**: Prepared statements used throughout
5. **XSS Prevention**: Output escaping in all views
6. **CSRF Protection**: WordPress nonce system

## Performance Considerations

1. **Database Queries**: Indexed columns for common queries
2. **API Calls**: Asynchronous processing via cron
3. **Batch Processing**: Limited items per cron run
4. **Resource Management**: Memory limits considered
5. **Cleanup**: Old data automatically removed

## Future Enhancements

1. **Multi-language Support**: Full i18n implementation
2. **Advanced Scheduling**: More sophisticated post distribution
3. **Content Templates**: Custom content structures
4. **Analytics Dashboard**: Detailed performance metrics
5. **Export/Import**: Backup and migration tools
6. **REST API**: For external integrations
7. **Gutenberg Blocks**: Custom blocks for generated content
8. **Multi-site Support**: Network-wide functionality

## Contexts Page Implementation

### Overview
The contexts management page (`/wp-admin/admin.php?page=ai-blog-generator-contexts`) provides a comprehensive interface for managing AI content contexts with full AJAX functionality, modal-based editing, and integrated seed image management.

### Frontend Implementation (`admin/views/contexts.php`)

#### Context Cards Grid
- **Responsive Grid Layout**: Auto-fill grid with 300px minimum column width
- **Card Components**: Each context displayed as individual cards with header, content preview, and action buttons
- **Status Indicators**: Visual badges showing context type and active/inactive states
- **Hover Effects**: Smooth transitions and shadow effects for better UX

#### Modal System
- **Context Edit Modal**: Large modal with form for editing context properties
- **Seed Image Upload Modal**: Dedicated modal for uploading and managing seed images
- **Responsive Design**: Modals adapt to different screen sizes
- **Keyboard Navigation**: ESC key closes modals, proper focus management

#### AJAX Integration
- **Real-time Updates**: All operations update the UI without page refresh
- **Loading States**: Visual feedback during AJAX operations
- **Error Handling**: User-friendly error messages with console logging
- **Success Feedback**: Toast-style success messages

### JavaScript Framework (`admin/assets/js/contexts.js`)

#### Core Object Structure
```javascript
window.aiBlogContexts = {
    init: function() { ... },
    // Context methods
    showModal: function() { ... },
    editContext: function(contextId) { ... },
    saveContext: function() { ... },
    deleteContext: function(contextId) { ... },
    toggleContext: function(contextId) { ... },
    
    // Seed image methods
    showSeedImageModal: function() { ... },
    editSeedImage: function(seedId) { ... },
    saveSeedImage: function() { ... },
    deleteSeedImage: function(seedId) { ... },
    previewSeedImage: function(fileInput) { ... },
    
    // Utility methods
    makeAjaxRequest: function(data) { ... },
    showMessage: function(message, type) { ... },
    closeModal: function() { ... }
};
```

#### Event Handler System
- **Delegated Events**: Uses `$(document).on()` for dynamic content handling
- **Form Validation**: Client-side validation before AJAX submission
- **File Upload**: Handles file selection, preview, and validation
- **Loading States**: Disables buttons and shows loading text during operations

#### AJAX Communication
- **Centralized Request Handler**: `makeAjaxRequest()` method for consistent error handling
- **Promise-based**: Returns promises for better async handling
- **Error Recovery**: Automatic retry logic for failed requests
- **Response Validation**: Validates JSON responses and handles edge cases

### Seed Images Management System

#### Database Schema
```sql
CREATE TABLE ai_blog_seed_images (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  product_name VARCHAR(100) NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  context_id BIGINT(20) UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_product (product_name),
  INDEX idx_context (context_id)
);
```

#### Upload Process
1. **Client-side Validation**: Validates PNG format and 10MB size limit
2. **File Preview**: Creates image preview using FileReader API
3. **Form Data**: Constructs FormData object with file and metadata
4. **WordPress Upload**: Uses WordPress media upload functions
5. **Database Storage**: Stores metadata in seed images table
6. **Media Library Integration**: Creates WordPress attachment records

#### File Validation
- **Format Restriction**: Only PNG files allowed (validated on client and server)
- **Size Limit**: Maximum 10MB file size with user-friendly error messages
- **MIME Type Checking**: Server-side validation using `wp_check_filetype()`
- **Upload Security**: Uses WordPress upload security functions

#### WordPress Integration
- **Media Library**: Images stored as standard WordPress attachments
- **Attachment Metadata**: Generates standard WordPress image metadata
- **URL Management**: Uses WordPress attachment URLs for consistency
- **Media Management**: Leverages WordPress media management features

### Backend Implementation (`controllers/class-context-controller.php`)

#### AJAX Handlers Registration
```php
public function register_ajax_handlers() {
    // Context operations
    add_action( 'wp_ajax_ai_blog_get_context', [ $this, 'get_context' ] );
    add_action( 'wp_ajax_ai_blog_update_context', [ $this, 'update_context' ] );
    add_action( 'wp_ajax_ai_blog_toggle_context', [ $this, 'toggle_context' ] );
    add_action( 'wp_ajax_ai_blog_delete_context', [ $this, 'delete_context' ] );
    
    // Seed image operations
    add_action( 'wp_ajax_ai_blog_upload_seed_image', [ $this, 'upload_seed_image' ] );
    add_action( 'wp_ajax_ai_blog_update_seed_image', [ $this, 'update_seed_image' ] );
    add_action( 'wp_ajax_ai_blog_delete_seed_image', [ $this, 'delete_seed_image' ] );
    add_action( 'wp_ajax_ai_blog_get_seed_image', [ $this, 'get_seed_image' ] );
}
```

#### Seed Image Upload Handler
```php
public function upload_seed_image() {
    // Security checks (nonce, capabilities)
    // File validation (PNG, size limits)
    // WordPress media upload
    // Database storage
    // Error handling and logging
}
```

#### Security Implementation
- **Nonce Verification**: All AJAX requests verify WordPress nonces
- **Capability Checking**: Requires `manage_options` capability
- **Input Sanitization**: All inputs sanitized using WordPress functions
- **Output Buffering**: Prevents PHP warnings from contaminating JSON responses
- **SQL Injection Protection**: Uses prepared statements for all database queries

#### Error Handling Strategy
- **Try-Catch Blocks**: Comprehensive exception handling
- **Database Error Logging**: Logs all database errors with context
- **User-Friendly Messages**: Translatable error messages for users
- **Debug Information**: Detailed logging for administrators
- **Graceful Degradation**: System continues functioning if non-critical operations fail

### Data Validation and Sanitization

#### Input Validation
- **Required Fields**: Validates presence of required data
- **Data Types**: Ensures correct data types (strings, integers, booleans)
- **Length Limits**: Enforces maximum lengths for text fields
- **Format Validation**: Validates email addresses, URLs, etc.

#### WordPress Security Functions
- **`sanitize_text_field()`**: For single-line text inputs
- **`sanitize_textarea_field()`**: For multi-line text content
- **`absint()`**: For integer values that must be positive
- **`esc_html()`**: For HTML output escaping
- **`esc_url()`**: For URL output escaping

### Performance Considerations

#### Database Optimization
- **Indexed Columns**: Strategic indexes on frequently queried columns
- **Query Optimization**: Efficient SQL queries with proper joins
- **Pagination**: Limits results to prevent memory issues
- **Caching Strategy**: Leverages WordPress object caching where appropriate

#### Frontend Performance
- **Minified Assets**: JavaScript and CSS assets are optimized
- **Conditional Loading**: Assets only loaded on relevant admin pages
- **Lazy Loading**: Images loaded only when needed
- **AJAX Batching**: Multiple operations combined when possible

### Integration Points

#### WordPress Core Integration
- **Media Library**: Full integration with WordPress media management
- **User Capabilities**: Respects WordPress user role system
- **Hooks and Filters**: Provides hooks for extensibility
- **Coding Standards**: Follows WordPress PHP coding standards

#### Plugin Ecosystem Integration
- **Translation Ready**: All strings marked for translation
- **Multisite Compatible**: Works with WordPress multisite installations
- **Theme Independence**: Functionality independent of active theme
- **Plugin Conflicts**: Designed to avoid conflicts with other plugins

## Update Protocol

When adding new features:
1. Update this document first
2. Add new methods to relevant classes
3. Update the implementation plan
4. Add changelog entry
5. Create/update unit tests
6. Test full workflow

This document is the source of truth for implementation. Any questions about where code belongs or how to implement features should be answered by referencing this document.

## Bootstrap Layout Implementation (v1.4.0)

### CSS Styling Framework Integration (v1.5.11)

#### Overview
The AI content generation now includes a comprehensive CSS styling framework that provides professional, consistent styling across all generated content. The system automatically injects CSS context into prompts and ensures generated content uses predefined styling classes.

#### Technical Implementation

**CSS Framework File**: `admin/assets/css/blogs.css`
- 160 lines of professional CSS classes with 'blog-' prefix
- Avada theme compatibility with CSS custom properties
- Bootstrap 5 integration for responsive layouts
- Professional color palette with variant support

**Prompt Integration**: Located in `services/class-anthropic-service.php`
```php
// CSS content is loaded and injected into prompts
$css_file_path = AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/assets/css/blogs.css';
if ( file_exists( $css_file_path ) ) {
    $css_content = file_get_contents( $css_file_path );
    $prompt .= "CSS FRAMEWORK CLASSES AVAILABLE:\n";
    $prompt .= $css_content . "\n\n";
}
```

**Frontend Integration**: CSS automatically enqueued for all singular posts/pages
```php
// In ai-blog-generator.php
wp_enqueue_style(
    'ai-blog-generator-blogs-css',
    AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/css/blogs.css',
    [],
    $this->version
);
```

#### Available CSS Classes

**Card Components**:
- `blog-card` - Base card styling with border, shadow, and padding
- `blog-card--primary`, `blog-card--success`, `blog-card--info`, `blog-card--warning`, `blog-card--danger` - Color variants
- `blog-card__header`, `blog-card__body` - Card structure elements

**Alert Components**:
- `blog-alert` - Base alert styling with left border and background
- `blog-alert--info`, `blog-alert--success`, `blog-alert--warning`, `blog-alert--danger` - Alert variants
- `blog-alert__title` - Alert heading styling

**Badge Components**:
- `blog-badge` - Inline badge styling with rounded corners
- Color variants with appropriate contrast ratios
- `blog-badge--outline` - Outline variant for secondary badges

**Button Components**:
- `blog-btn` - Professional button styling with hover effects
- Compatible with Avada `fusion-button` classes
- Color variants matching theme palette
- Outline variants for secondary actions

**Typography Classes**:
- `blog-heading--hero` - Large hero headings (2.75rem)
- `blog-heading--section` - Section headings (2rem)
- `blog-text-lead` - Lead paragraph styling (1.25rem)
- `blog-text-muted`, `blog-text-small` - Text variants

**Layout Utilities**:
- `blog-container` - Responsive container with max-widths
- `blog-row`, `blog-col` - Flexbox grid system
- Shadow utilities: `blog-shadow-sm/md/lg`
- Border radius: `blog-radius-sm/md/lg`

**Specialized Components**:
- `blog-table` - Professional table styling with hover effects
- `blog-accordion` - Collapsible content sections with animation
- `blog-compare` - Comparison/pricing table layouts
- `blog-image-hero`, `blog-image-figure` - Image container styling

#### AI Styling Instructions

The AI receives detailed instructions for using CSS classes:

```
STYLING INSTRUCTIONS:
- Use the 'blog-' prefixed classes extensively for all styling needs
- Apply blog-card, blog-card--primary, blog-card--success, etc. for content containers
- Use blog-alert, blog-alert--info, blog-alert--success, blog-alert--warning for callouts
- Apply blog-badge, blog-badge--success, blog-badge--info for tags and labels
- Use blog-btn, blog-btn--primary, blog-btn--success for any buttons or CTAs
- Apply blog-heading--hero, blog-heading--section for important headings
- Use blog-image-hero, blog-image-figure for image styling
- Apply blog-table for any data tables
- Use blog-accordion for FAQ sections or collapsible content
- Apply blog-compare for comparison sections
- Use utility classes like blog-shadow-md, blog-radius-lg for enhanced visuals
- Combine these classes with Bootstrap 5 layout classes for optimal design
- Prioritize these blog-specific classes over generic Bootstrap styling classes
```

#### Theme Integration

**Avada Compatibility**:
- CSS custom properties link to Avada global colors: `--awb-color1` through `--awb-color5`
- Professional color palette with consistent naming
- Responsive breakpoints matching Bootstrap 5 standards

**Color System**:
```css
:root {
    --color-primary: var(--awb-color1, #0062ff);
    --color-success: var(--awb-color2, #28a745);
    --color-info: var(--awb-color3, #17a2b8);
    --color-warning: var(--awb-color4, #ffc107);
    --color-danger: var(--awb-color5, #dc3545);
}
```

#### Content Generation Impact

**Before CSS Integration**:
- Generic Bootstrap classes only
- Inconsistent styling across content
- Limited visual hierarchy options

**After CSS Integration**:
- Professional, branded styling classes available
- Consistent visual identity across all generated content
- Rich component library for enhanced content presentation
- Better visual hierarchy with specialized typography classes

#### Performance Considerations

**File Size**: 160 lines of optimized CSS (approximately 8KB)
**Loading**: Conditionally enqueued only on singular posts/pages
**Caching**: Leverages WordPress asset versioning for browser caching
**Compression**: Compatible with standard CSS minification tools

### Overview
Starting with version 1.4.0, all generated blog content uses modern, responsive Bootstrap 5 layouts with integrated image token system and SEO-optimized structure.

### Content Generation Architecture

#### Enhanced Prompt System
The content generation prompts have been completely redesigned to produce Bootstrap-styled HTML:

**Critical SEO Requirements**:
- **H1 Prevention**: Generated content never uses H1 headings since WordPress post titles are already H1
- **Heading Structure**: Content uses H2 as top-level headings, then H3, H4, etc. for proper SEO hierarchy
- **Safety Conversion**: Automatic H1-to-H2 conversion during content processing as backup measure

**Before Enhancement**: Basic text generation without specific styling instructions
**After Enhancement**: Complete HTML generation with:

1. **Layout Requirements**:
   - Bootstrap 5 components (containers, rows, columns, cards)
   - Mobile-first responsive design
   - Proper spacing utilities (mb-4, mt-3, py-5)
   - Semantic HTML5 elements (article, section, aside)

2. **Image Token System**:
   - Minimum 2 images per post using {{image1}}, {{image2}} tokens
   - Strategic placement after intro and mid-content
   - Bootstrap figure classes for image containers
   - Responsive image classes (img-fluid, rounded)

3. **SEO Optimization**:
   - 1-2% keyword density for primary keyword
   - Primary keyword in first paragraph, H2 headings, and conclusion
   - Proper heading hierarchy (H1 → H2 → H3)
   - Meta description with primary keyword
   - 5-8 relevant tags including LSI terms

#### Content Output Format
Generated content now returns structured data:
```php
[
    'title' => 'SEO-optimized title under 60 characters',
    'meta_description' => '150-160 character description with keyword',
    'focus_keyphrase' => 'primary keyword for Yoast SEO',
    'tags' => ['tag1', 'tag2', 'tag3'],
    'html' => '<article class="container">Bootstrap HTML with {{image}} tokens</article>',
    'images' => [
        [
            'token' => '{{image1}}',
            'prompt' => 'Detailed description for image generation',
            'alt_text' => 'Automatically generated alt text'
        ]
    ]
]
```

### Image Generation and Integration

#### Seed Image System Enhancement
When seed images are used, the system now:

1. **Preserves Original Products**: The `add_seed_images_to_requirements()` method adds specific instructions to preserve the exact product shown in seed images
2. **Enhanced Prompts**: Automatically prepends preservation instructions to image prompts
3. **Product Context**: Maintains visual consistency with brand elements

#### Batch Image Processing
The new `generate_batch_images()` method in OpenAI service:
```php
public function generate_batch_images( $image_requirements ) {
    // Processes multiple images efficiently
    // Handles seed image requirements
    // Saves to WordPress media library
    // Returns results with tokens for replacement
}
```

#### Image HTML Generation
Images are now wrapped in Bootstrap components:
```html
<figure class="figure my-4 ai-generated-image">
    <img src="image-url" alt="alt-text" class="figure-img img-fluid rounded" loading="lazy" />
</figure>
```

### Frontend Script Loading

#### ApexCharts Integration (v1.3.1)
- CDN loading for charting capabilities
- Smart detection based on content keywords
- Available in both frontend and admin areas
- Performance optimized with conditional loading

### Bootstrap Components Usage

#### Common Layout Patterns
Generated content uses these Bootstrap patterns:

1. **Hero Sections**:
   ```html
   <section class="hero-section py-5 mb-5">
       <div class="container">
           <div class="row align-items-center">
               <div class="col-lg-6">
                   <h1 class="display-4">Title</h1>
                   <p class="lead">Introduction</p>
               </div>
               <div class="col-lg-6">
                   {{image1}}
               </div>
           </div>
       </div>
   </section>
   ```

2. **Content Sections**:
   ```html
   <section class="content-section mb-5">
       <div class="container">
           <div class="row">
               <div class="col-lg-8 mx-auto">
                   <h2 class="h3 mb-4">Section Title</h2>
                   <p>Content...</p>
               </div>
           </div>
       </div>
   </section>
   ```

3. **Feature Cards**:
   ```html
   <div class="row g-4 mb-5">
       <div class="col-md-4">
           <div class="card h-100">
               <div class="card-body">
                   <h3 class="card-title h5">Feature</h3>
                   <p class="card-text">Description</p>
               </div>
           </div>
       </div>
   </div>
   ```

### Technical Implementation Details

#### Anthropic Service Updates
- `build_content_prompt()`: Completely rewritten with Bootstrap requirements
- `parse_content_response()`: Enhanced to handle multi-section output
- New regex patterns for parsing image descriptions

#### Content Generator Updates
- `validate_generated_content()`: Updated for new required fields
- `create_image_html()`: Bootstrap-styled image output
- Enhanced error handling for malformed responses

#### OpenAI Service Updates
- Added `generate_batch_images()` for efficient multi-image processing
- Enhanced logging for seed image usage
- Improved error recovery and retry logic

### Backward Compatibility
The system maintains backward compatibility:
- Old content format still parsed correctly
- Fallback handling for missing image descriptions
- Graceful degradation if Bootstrap not available

## Research-Based Content Generation (v1.4.1)

### Overview
Version 1.4.1 introduces research-driven content generation with data visualizations, transforming the blog generator into a sophisticated content creation system that produces authoritative, data-backed articles.

### Research Integration Architecture

#### Enhanced Content Requirements
The content generation now requires:

1. **Statistical Evidence**:
   - Actual statistics from credible sources
   - Recent data (preferably within 2-3 years)
   - Minimum 5-10 credible sources per post
   - Academic papers, industry reports, government data

2. **Citation Format**:
   - Inline citations using (Source, Year) format
   - Full bibliography in references section
   - Proper attribution for all facts and figures
   - Bootstrap-styled references display

3. **Deep Analysis**:
   - 1500-2500 words of research-backed content
   - Critical thinking and unique insights
   - Evidence-based arguments
   - Data-driven conclusions

### Data Visualization System

#### ApexCharts Integration
The system automatically generates interactive charts when presenting:
- Statistical trends over time
- Comparative data across categories
- Proportional relationships
- Performance metrics

#### Chart Implementation
```javascript
// Example chart configuration
var options = {
    series: [{ name: "Data", data: [30, 40, 35, 50, 49] }],
    chart: { type: "line", height: 350 },
    xaxis: { categories: ["Jan", "Feb", "Mar", "Apr", "May"] },
    responsive: [{
        breakpoint: 480,
        options: { legend: { position: "bottom" } }
    }]
};
```

#### Chart Types Supported
1. **Line Charts**: For trends and time-series data
2. **Bar Charts**: For comparisons and rankings
3. **Pie Charts**: For proportions and distributions
4. **Area Charts**: For cumulative data
5. **Mixed Charts**: For complex data relationships

### Content Structure Enhancement

#### New Output Sections
```php
[
    'title' => 'SEO-optimized research-based title',
    'meta_description' => 'Description with primary keyword',
    'focus_keyphrase' => 'primary keyword',
    'tags' => ['research-based', 'data-driven', 'tags'],
    'html' => 'Bootstrap HTML with citations and chart containers',
    'images' => [/* Image requirements */],
    'charts' => '<script>ApexCharts initialization code</script>',
    'references' => '<section class="references">Bibliography</section>'
]
```

#### Chart Container Pattern
```html
<div class="chart-container my-5">
    <h3 class="h5 mb-3">Chart Title</h3>
    <div id="chart1" style="height: 400px;"></div>
</div>
```

### Implementation Details

#### Prompt Engineering
The `build_content_prompt()` method now includes:
- Explicit research requirements
- Data visualization instructions
- Citation format specifications
- Chart type recommendations
- Reference formatting guidelines

#### Content Parsing
The `parse_content_response()` method handles:
- CHARTS section extraction
- Script tag wrapping for charts
- REFERENCES section parsing
- Bootstrap formatting for references
- Backward compatibility checks

#### Post Generation
The `generate_blog_post()` method:
- Appends references after main content
- Adds chart scripts at the end
- Stores metadata about charts/references
- Maintains proper content flow

### Quality Assurance

#### Research Validation
- Sources must be credible and verifiable
- Data should be recent and relevant
- Statistics require proper attribution
- Claims backed by evidence

#### Chart Best Practices
- Mobile-responsive configurations
- Accessible color schemes
- Clear data labels
- Interactive tooltips
- Professional styling

### Example Implementation

See `content-generator-example.php` for a complete example featuring:
- Remote work statistics article
- Multiple data visualizations
- Proper research citations
- Bootstrap layout integration
- Interactive ApexCharts

### Performance Considerations

#### Script Loading
- ApexCharts loaded conditionally
- Charts initialized after DOM ready
- Responsive breakpoints configured
- Minimal performance impact

#### Content Size
- Increased word count (1500-2500)
- Additional script overhead for charts
- Optimized for readability
- Lazy loading maintained

### Future Enhancements

1. **Chart Templates**: Pre-built chart configurations
2. **Data Import**: CSV/JSON data support
3. **Real-time Updates**: Dynamic chart data
4. **Export Options**: Chart image downloads
5. **A/B Testing**: Chart type optimization

## Dynamic Token Limits (v1.4.2)

### Overview
Version 1.4.2 introduces dynamic token limits based on the selected Claude model, maximizing the potential of each model for comprehensive content generation.

### Token Allocation System

#### Model-Based Limits
The system automatically detects the model type and allocates maximum tokens:
- **Opus Models** (claude-opus-4-*): 32,000 tokens maximum
- **Sonnet Models** (claude-sonnet-4-*): 64,000 tokens maximum

#### Implementation
```php
private function get_max_tokens() {
    // Check if it's an Opus model
    if ( strpos( $this->model, 'opus' ) !== false ) {
        return 32000; // 32k tokens for Opus models
    }
    
    // Check if it's a Sonnet model
    if ( strpos( $this->model, 'sonnet' ) !== false ) {
        return 64000; // 64k tokens for Sonnet models
    }
    
    // Default fallback
    return 32000;
}
```

### Benefits

#### Content Generation
- **Longer Articles**: Sonnet models can generate up to 64k tokens of content
- **More Ideas**: Increased token limits allow for more comprehensive idea generation
- **Better Context**: More tokens mean better context understanding
- **Detailed Research**: Ample space for citations, data, and analysis

#### Cost Efficiency
- **Optimal Usage**: Uses full capacity of each model
- **No Waste**: Avoids artificially limiting capable models
- **Better ROI**: More content per API call

### Usage Tracking

#### Enhanced Logging
All API calls now log:
- Model being used
- Maximum tokens allocated
- Actual tokens consumed (input + output)
- Token efficiency ratio

#### Connection Test
The test connection feature now displays:
- Current model selection
- Maximum tokens available
- Confirmation of proper configuration

### Technical Details

#### API Request Structure
```php
$request_data = [
    'model' => $this->model,
    'max_tokens' => $this->get_max_tokens(),
    'messages' => [
        [
            'role' => 'user',
            'content' => $prompt,
        ],
    ],
];
```

#### Previous Limitations
- Ideas: Fixed at 4,000 tokens
- Content: Fixed at 6,000 tokens
- No model differentiation

#### Current System
- Ideas: Up to model maximum
- Content: Up to model maximum
- Dynamic based on model capabilities

### Best Practices

1. **Model Selection**: Choose Sonnet for longer, research-heavy content
2. **Context Usage**: Leverage higher limits for comprehensive contexts
3. **Monitoring**: Track token usage to optimize prompts
4. **Cost Management**: Balance token usage with generation costs

## Persona System Implementation (v1.5.0)

### Overview
Version 1.5.0 introduces a sophisticated persona system that adds authentic voice and perspective to generated content. Each persona has unique expertise, writing style, and tone, creating more varied and engaging blog posts.

### Database Schema

#### Personas Table (`wp_ai_blog_personas`)
```sql
CREATE TABLE wp_ai_blog_personas (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    bio TEXT NOT NULL,
    expertise TEXT,
    writing_style TEXT,
    tone VARCHAR(50) DEFAULT 'professional',
    active TINYINT(1) DEFAULT 1,
    uses_avada_layouts TINYINT(1) DEFAULT 0,
    uses_plain_html TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_active (active),
    KEY idx_name (name)
);
```

**Note**: The `uses_avada_layouts` and `uses_plain_html` fields are mutually exclusive. In the UI, they are presented as radio buttons for "Content Generation Format" where users must select either HTML Format or Avada Layout.

#### Schema Updates
- **Ideas Table**: Add `persona_id` column for persona assignment
- **Generated Posts Table**: Add `persona_id` to track authorship
- Foreign key relationships maintain referential integrity

### Model Implementation

#### Persona_Model (models/class-persona-model.php)
```php
class Persona_Model extends Model {
    protected $table_name = 'ai_blog_personas';
    
    public function get_active_personas() {
        return $this->get_all(['active' => 1]);
    }
    
    public function get_by_expertise($keywords) {
        // Match personas based on expertise keywords
    }
    
    public function validate($data) {
        // Validate name, bio required
        // Validate tone in allowed values
    }
}
```

### Controller Implementation

#### Persona_Controller (controllers/class-persona-controller.php)
```php
class Persona_Controller {
    public function register_ajax_handlers() {
        add_action('wp_ajax_ai_blog_get_personas', [$this, 'get_personas']);
        add_action('wp_ajax_ai_blog_create_persona', [$this, 'create_persona']);
        add_action('wp_ajax_ai_blog_update_persona', [$this, 'update_persona']);
        add_action('wp_ajax_ai_blog_delete_persona', [$this, 'delete_persona']);
        add_action('wp_ajax_ai_blog_toggle_persona', [$this, 'toggle_persona']);
    }
}
```

### Persona Selection Algorithm

#### Implementation in Content_Generator
```php
public function select_persona_for_idea($idea) {
    $personas = $this->persona_model->get_active_personas();
    $scores = [];
    
    foreach ($personas as $persona) {
        $score = 0;
        
        // Match expertise keywords
        $expertise_keywords = explode(',', $persona['expertise']);
        foreach ($expertise_keywords as $keyword) {
            if (stripos($idea['title'] . ' ' . $idea['description'], trim($keyword)) !== false) {
                $score += 10;
            }
        }
        
        // Match category relevance
        if ($this->category_matches_persona($idea['category'], $persona)) {
            $score += 5;
        }
        
        // Consider tone appropriateness
        if ($this->tone_appropriate_for_topic($idea, $persona['tone'])) {
            $score += 3;
        }
        
        $scores[$persona['id']] = $score;
    }
    
    // Return persona with highest score
    arsort($scores);
    return key($scores);
}
```

#### Content Generation with Persona Context
```php
private function build_content_prompt_with_persona($idea, $contexts, $persona) {
    $prompt = "You are {$persona['name']}. {$persona['bio']}\n\n";
    $prompt .= "Your expertise includes: {$persona['expertise']}\n";
    $prompt .= "Your writing style: {$persona['writing_style']}\n";
    $prompt .= "Your tone is: {$persona['tone']}\n\n";
    
    $prompt .= "Writing as {$persona['name']}, create a detailed blog post ";
    $prompt .= "that reflects your unique perspective and expertise.\n\n";
    
    // Include existing content requirements...
    return $prompt;
}
```

### Admin Interface

#### Content Format Selection
The personas edit modal includes a required "Content Generation Format" field with two mutually exclusive options:

1. **HTML Format** - Generate standard HTML content without page builder elements
2. **Avada Layout** - Generate content with Avada page builder elements and shortcodes

**Implementation Details:**
- Radio button group ensures only one format can be selected
- HTML format is selected by default for new personas
- JavaScript validation prevents saving without a selection
- Modern CSS styling with visual feedback for selected option
- Database stores selection as two boolean fields for backward compatibility

#### Format-Specific Context Requirements
Contexts can be marked as required for specific content generation formats:

**Database Fields:**
- `always_include_avada` - Context is required when generating Avada layouts
- `always_include_html` - Context is required when generating HTML content
- Note: These options are mutually exclusive in the UI - selecting one automatically deselects the other

**UI Behavior:**
- When a persona selects a content format, required contexts are automatically checked
- Required contexts are visually indicated with gray background and "(Required for [format])" label
- Required contexts cannot be unchecked while that format is selected
- AJAX endpoint `ai_blog_get_format_contexts` fetches format-specific contexts

**Implementation:**
- Context model includes `get_always_include_avada()` and `get_always_include_html()` methods
- Persona controller handles format context requests
- JavaScript dynamically updates context checkboxes based on format selection

#### Personas Management Page (admin/views/personas.php)
```php
<div class="wrap">
    <h1>Writing Personas</h1>
    
    <div class="personas-grid">
        <?php foreach ($personas as $persona): ?>
            <div class="persona-card">
                <h3><?php echo esc_html($persona['name']); ?></h3>
                <p class="bio"><?php echo esc_html($persona['bio']); ?></p>
                <div class="expertise">
                    <strong>Expertise:</strong> 
                    <?php echo esc_html($persona['expertise']); ?>
                </div>
                <div class="writing-info">
                    <span class="tone"><?php echo esc_html($persona['tone']); ?></span>
                </div>
                <div class="actions">
                    <button class="edit-persona" data-id="<?php echo $persona['id']; ?>">
                        Edit
                    </button>
                    <button class="toggle-persona" data-id="<?php echo $persona['id']; ?>">
                        <?php echo $persona['active'] ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <button class="add-new-persona">Add New Persona</button>
</div>
```

### JavaScript Implementation (admin/assets/js/personas.js)
```javascript
window.aiBlogPersonas = {
    init: function() {
        this.bindEvents();
    },
    
    bindEvents: function() {
        $(document).on('click', '.add-new-persona', this.showAddModal);
        $(document).on('click', '.edit-persona', this.editPersona);
        $(document).on('click', '.toggle-persona', this.togglePersona);
    },
    
    savePersona: function(data) {
        return $.post(ajaxurl, {
            action: 'ai_blog_create_persona',
            nonce: ai_blog_admin.nonce,
            ...data
        });
    }
};
```

### Integration Points

#### Idea Generation Flow
1. Query active personas before generating ideas
2. Include persona list in Anthropic prompt
3. Parse response to extract persona recommendations
4. Store persona_id with each generated idea

#### Content Generation Flow
1. Retrieve assigned persona for the idea
2. Include full persona context in content prompt
3. Generate content with persona's voice and style
4. Track persona_id in generated_posts table

#### Admin UI Updates
1. **Blog Ideas Page**: Show assigned persona for each idea
2. **Approved Blogs Page**: Display persona assignment
3. **Generated Posts**: Show "Written by [Persona Name]"
4. **Cost Dashboard**: Track costs by persona

### Default Personas

The system includes four default personas:

1. **John** - Academic/Professional Writer
   - PhD in Education, copywriting expert
   - Professional, research-focused tone
   - Best for: Educational content, how-to guides

2. **Ginny** - Community Organizer
   - PTA president, fundraising experience
   - Friendly, conversational tone
   - Best for: Community content, fundraising ideas

3. **Marcus** - Digital Marketing Specialist
   - E-commerce and analytics focus
   - Analytical, data-driven tone
   - Best for: Marketing content, business strategies

4. **Sarah** - Wellness Coach
   - Holistic health and mindfulness
   - Inspirational, empathetic tone
   - Best for: Wellness content, lifestyle topics

### Performance Considerations

#### Caching
- Cache active personas in transient (5 minutes)
- Reduces database queries during generation
- Clear cache on persona updates

#### Scoring Optimization
- Pre-calculate expertise keywords array
- Use full-text search for better matching
- Index expertise and bio columns

### Future Enhancements

1. **Persona Analytics**: Track performance by persona
2. **Custom Fields**: Add persona-specific custom fields
3. **Avatar Support**: Add profile images for personas
4. **Persona Templates**: Pre-built persona templates
5. **AI Learning**: Let AI suggest new personas based on content
6. **Multi-language**: Personas for different languages

## Content Generation Pipeline

### 1. Idea Selection and Approval
- User reviews generated ideas and approves them
- Approved ideas are queued for content generation
- Background processor picks up approved ideas

### 2. Prompt Compilation (NEW)
**Service**: `Prompt_Compiler_Service`

The content generation now uses a dedicated prompt compilation service:

1. **Data Gathering**:
   - Load idea details from database
   - Retrieve assigned persona with all settings
   - Get all active contexts (general, always-include, format-specific)
   - Fetch brand features for internal linking
   - Get previously used keyphrases for uniqueness
   - Load active products for promotion

2. **System Prompt Construction**:
   - Build persona prompt with bio, expertise, writing style
   - Add layout instructions based on persona settings
   - Include chart usage preferences
   - Compile all relevant contexts
   - Add format-specific instructions (HTML or Avada)
   - Include product promotion requirements
   - Add SEO guidelines and keyphrase avoidance
   - Include brand features for linking

3. **User Prompt Generation**:
   - Select 2 random target keywords
   - Define image requirements (minimum 2, based on persona)
   - Specify SEO metadata requirements
   - Set output format and structure
   - Include word count and other constraints

4. **Prompt Logging**:
   - Write complete prompts to `{idea_id}_prompts.txt`
   - Include timestamps and clear formatting
   - Store in uploads directory for debugging

### 3. Content Generation
**Service**: `Content_Generator` with `Anthropic_Service`

1. **API Call Preparation**:
   - Use compiled prompts from Prompt Compiler
   - Call new `generate_content()` method with prompts array
   - System prompts combined into single system message

2. **AI Processing**:
   - Anthropic processes prompts with persona context
   - Generates content following all specifications
   - Returns structured response with title, content, metadata

3. **Response Parsing**:
   - Extract title, meta description, focus keyphrase
   - Parse HTML content with image tokens
   - Extract image requirements and descriptions
   - Identify chart configurations if applicable

### 4. Image Generation
**Service**: `OpenAI_Service`

1. **Prompt Enhancement**:
   - Use Prompt Compiler's `generate_image_prompts()` method
   - Include persona style preferences
   - Add seed image requirements if applicable

2. **Batch Processing**:
   - Generate all required images in batch
   - Use seed images for product consistency
   - Save to WordPress media library

3. **Token Replacement**:
   - Replace {{image}} tokens with actual URLs
   - Add proper alt text and captions

### 5. Post Creation
**Service**: `Content_Generator`

1. **WordPress Post**:
   - Create draft post with generated content
   - Set SEO metadata (Yoast/RankMath compatible)
   - Assign categories and tags
   - Set featured image

2. **Tracking**:
   - Store generation costs
   - Log completion status
   - Update idea status to 'generated'

### 6. CSS Styling Framework Integration

**NEW FEATURE**: The AI content generation now includes comprehensive CSS styling context to ensure professional, consistent styling across all generated content.

**Implementation Details**:

**CSS Framework Inclusion**:
- The `admin/assets/css/blogs.css` file (160 lines) is loaded and included in every content generation prompt
- Framework provides 160 professional CSS classes with 'blog-' prefix for consistent branding
- CSS content is read from file during prompt building and injected as context

**Styling Classes Available**:
- **Cards**: `blog-card`, `blog-card--primary`, `blog-card--success`, `blog-card--info`, `blog-card--warning`, `blog-card--danger`
- **Alerts**: `blog-alert`, `blog-alert--info`, `blog-alert--success`, `blog-alert--warning`, `blog-alert--danger`
- **Badges**: `blog-badge`, `blog-badge--success`, `blog-badge--info`, `blog-badge--warning`, `blog-badge--danger`
- **Buttons**: `blog-btn`, `blog-btn--primary`, `blog-btn--success`, `blog-btn--info`, `blog-btn--warning`, `blog-btn--danger`
- **Typography**: `blog-heading--hero`, `blog-heading--section`, `blog-text-lead`, `blog-text-muted`, `blog-text-small`
- **Images**: `blog-image-hero`, `blog-image-figure`
- **Tables**: `blog-table` with responsive styling
- **Accordions**: `blog-accordion` for FAQ sections
- **Comparisons**: `blog-compare` for pricing/comparison tables
- **Utilities**: `blog-shadow-sm/md/lg`, `blog-radius-sm/md/lg`, spacing utilities

**Theme Compatibility**:
- Avada theme integration with CSS custom properties: `--awb-color1` through `--awb-color5`
- Professional color palette: primary, success, info, warning, danger variants
- Responsive breakpoints: mobile-first approach with Bootstrap 5 compatibility

**AI Instructions**:
The AI is explicitly instructed to:
- Use blog-prefixed classes extensively throughout content
- Prioritize blog-specific classes over generic Bootstrap classes
- Apply appropriate color variants for visual hierarchy
- Combine with Bootstrap 5 layout classes for optimal design
- Use specific classes for specific content types (cards for sections, alerts for callouts, etc.)

**Frontend Integration**:
- CSS file is enqueued on frontend via `wp_enqueue_scripts` for all singular posts/pages
- Generated content displays with proper styling immediately upon publication
- No additional setup required for styling to take effect

**File Location**: `services/class-anthropic-service.php` - `build_content_prompt()` method
**CSS File**: `admin/assets/css/blogs.css`
**Frontend Enqueue**: `ai-blog-generator.php` - `enqueue_frontend_scripts()` method

// ... existing code ...

## Approved Ideas V2 Queue System (v1.6.0)

### Overview
Version 1.6.0 introduces a sophisticated generation queue system for the Approved Ideas V2 page that supports concurrent generation of up to 5 blog posts, real-time status updates, live log tailing, and comprehensive error handling.

### Database Schema Updates

#### Added Fields to `ai_blog_ideas` Table
```sql
ALTER TABLE wp_ai_blog_ideas ADD COLUMN generation_status VARCHAR(255) NULL AFTER status;
ALTER TABLE wp_ai_blog_ideas ADD COLUMN generation_error TEXT NULL AFTER generation_status;
ALTER TABLE wp_ai_blog_ideas ADD COLUMN generation_started_at DATETIME NULL AFTER generation_error;
ALTER TABLE wp_ai_blog_ideas ADD COLUMN generation_completed_at DATETIME NULL AFTER generation_started_at;
ALTER TABLE wp_ai_blog_ideas ADD INDEX idx_generation_status (generation_status);
```

### New Services

#### Generation_Queue Service
Location: `services/class-generation-queue.php`

**Key Features:**
- Maximum 5 concurrent generations
- FIFO queue for excess requests
- Automatic cleanup of stuck generations
- Real-time queue status tracking

**Key Methods:**
- `add_to_queue($idea_ids)` - Add one or more ideas to generation queue
- `cancel_generation($idea_id)` - Cancel active or queued generation
- `process_queue($completed_idea_id)` - Process queue after completion
- `get_queue_status()` - Get current queue and active generation status
- `mark_failed($idea_id, $error)` - Mark generation as failed
- `mark_complete($idea_id)` - Mark generation as complete

**Constants:**
- `MAX_CONCURRENT = 5` - Maximum simultaneous generations
- `QUEUE_STATUS_KEY` - Transient key for queue items
- `ACTIVE_GENERATIONS_KEY` - Transient key for active generations

### Updated Controllers

#### Approved_Ideas_Controller_V2 Updates
Location: `controllers/class-approved-ideas-controller-v2.php`

**New AJAX Handlers:**
- `ajax_retry_generation()` - Retry failed generations
- `ajax_get_queue_status()` - Get real-time queue status
- Enhanced `ajax_submit_for_generation()` - Now supports bulk submissions
- Enhanced `ajax_get_generation_log()` - Now supports tail logging

**Key Changes:**
- Uses Generation_Queue service for all generation operations
- Automatic cleanup of stuck generations on page load
- Support for bulk generation submissions
- Real-time status updates stored in database

### JavaScript Enhancements

#### approved-ideas-v2.js Updates
Location: `admin/assets/js/approved-ideas-v2.js`

**New Features:**
1. **Live Log Tailing**
   - Incremental log loading (only new lines)
   - Color-coded log entries by type
   - Auto-scroll to bottom
   - Stops refresh when generation completes

2. **Queue Status Display**
   - Real-time queue status indicator
   - Shows active generations with duration
   - Shows queued items with positions
   - Auto-hide when queue is empty

3. **Fast Refresh Mode**
   - 2-second refresh interval during active generations
   - Automatically returns to normal speed after 5 minutes
   - Triggered when new generations start

**New Functions:**
- `updateQueueStatusDisplay(queueStatus)` - Update queue status UI
- `startFastRefresh()` - Enable fast refresh mode
- `stopFastRefresh()` - Return to normal refresh speed
- Enhanced `refreshGenerationLog()` - Tail log support

### Generation Status Flow

#### Status Values
1. **approved** - Ready for generation
2. **generating** - Currently being processed
3. **generated** - Successfully completed
4. **denied** - Rejected by admin

#### Generation Status Values (generation_status field)
- "Starting..." - Initial state
- "Compiling Context" - Gathering contexts
- "Submitting request" - Sending to AI
- "Waiting for Content Response" - AI processing
- "Compiling image prompts" - Preparing images
- "Submitting Images Request" - Generating images
- "Waiting for images Response" - Image processing
- "Saving Images" - Storing in media library
- "Publishing Post" - Creating WordPress post
- "Complete" - Finished successfully
- "Failed" - Error occurred
- "In Queue (Position: X)" - Waiting in queue

### Error Handling

#### Automatic Recovery
- Stuck generations (10+ minutes) automatically reset on page load
- Failed generations can be retried with one click
- Queue automatically processes when slots become available

#### Error Tracking
- `generation_error` field stores error messages
- Errors displayed in UI with retry option
- Detailed error logging to debug files

### Performance Optimizations

#### Targeted Refresh
- Only updates generating/queued ideas
- Reduces server load vs full page refresh
- Maintains real-time feel without waste

#### Queue Management
- Prevents overwhelming AI services
- Distributes load over time
- Maintains system stability

### User Experience Features

1. **Immediate Feedback**
   - Status updates instantly on action
   - No page refresh required
   - Progress shown in real-time

2. **Bulk Operations**
   - Select multiple ideas for generation
   - Queue handles overflow gracefully
   - Clear feedback on queue position

3. **Error Recovery**
   - One-click retry for failures
   - Clear error messages
   - Automatic cleanup of stuck items

### Integration Points

#### With Background_Processor
- Queue triggers background processing
- Status updates flow through queue
- Completion/failure notifications

#### With Content_Generator
- Real-time status updates to database
- Generation logging to individual files
- Error capture and reporting

#### With WordPress Cron
- Uses `wp_schedule_single_event` for processing
- Non-blocking generation execution
- Automatic retry on cron failure

### Security Considerations

1. **Nonce Verification** - All AJAX calls verified
2. **Capability Checks** - Requires manage_options
3. **Input Sanitization** - All inputs sanitized
4. **SQL Injection Prevention** - Prepared statements used

### Future Enhancements

1. **Priority Queue** - VIP ideas jump to front
2. **Scheduling** - Set generation times
3. **Resource Monitoring** - CPU/memory tracking
4. **API Rate Limiting** - Respect service limits
5. **Multi-user Support** - User-specific queues
6. **Email Notifications** - Completion alerts
7. **Webhook Integration** - External notifications

This implementation provides a robust, scalable solution for managing concurrent blog generation with excellent user experience and system reliability.

#### Status Updates

## Brand Features Management System (v1.8.0)

### Overview
Version 1.8.0 introduces a brand features management system that allows users to catalog internal links for services, informational pages, documents, images, and videos. These features can be used by the AI during content generation for intelligent internal linking.

### Database Schema

#### Brand Features Table (`wp_ai_blog_brand_features`)
```sql
CREATE TABLE wp_ai_blog_brand_features (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('informational_page','document','image','video') DEFAULT 'informational_page',
    url VARCHAR(500) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_active (active),
    KEY idx_category (category),
    KEY idx_name (name)
);
```

### Model Implementation

#### Brand_Feature_Model
Location: `models/class-brand-feature-model.php`

**Key Features:**
- Extends base Model class
- Full CRUD operations with validation
- Category-based filtering
- Active/inactive state management
- Search functionality

**Key Methods:**
```php
public function get_active_features($category = null) {
    // Gets active features, optionally filtered by category
}

public function get_by_category($category) {
    // Gets all features in a specific category
}

public function get_for_content_generation() {
    // Returns formatted features for AI context
}

public function search($search) {
    // Searches features by name or description
}

public function toggle_active($id) {
    // Toggles active/inactive state
}
```

### Controller Implementation

#### Brand_Feature_Controller
Location: `controllers/class-brand-feature-controller.php`

**AJAX Handlers:**
```php
// Feature CRUD
'wp_ajax_ai_blog_get_brand_features' => 'ajax_get_brand_features'
'wp_ajax_ai_blog_get_brand_feature' => 'ajax_get_brand_feature'
'wp_ajax_ai_blog_create_brand_feature' => 'ajax_create_brand_feature'
'wp_ajax_ai_blog_update_brand_feature' => 'ajax_update_brand_feature'
'wp_ajax_ai_blog_delete_brand_feature' => 'ajax_delete_brand_feature'
'wp_ajax_ai_blog_toggle_brand_feature' => 'ajax_toggle_brand_feature'
'wp_ajax_ai_blog_search_brand_features' => 'ajax_search_brand_features'
```

**Category Options:**
- `informational_page` - Informational pages about services or topics
- `document` - Downloadable documents or PDFs
- `image` - Image resources
- `video` - Video content

### Frontend Implementation

#### Admin View
Location: `admin/views/brand-features.php`

**Features:**
- Grid layout with responsive cards
- Search functionality with debouncing
- Category filtering dropdown
- Modal-based add/edit interface
- Pagination support

#### JavaScript Implementation
Location: `admin/assets/js/brand-features.js`

**Core Object Structure:**
```javascript
window.aiBlogBrandFeatures = {
    currentPage: 1,
    currentSearch: '',
    currentCategory: '',
    
    // Core methods
    init: function() { ... },
    loadFeatures: function() { ... },
    renderFeatures: function(features) { ... },
    showModal: function(feature) { ... },
    saveFeature: function() { ... },
    deleteFeature: function(featureId, featureName) { ... },
    toggleFeature: function(featureId) { ... }
};
```

**Key Features:**
1. **Real-time Search**
   - Debounced search input (300ms delay)
   - Searches across name and description
   - Maintains pagination state

2. **Category Filtering**
   - Dropdown filter by category type
   - Instant filtering without page reload
   - Visual category badges

3. **Modal Management**
   - Add/Edit features in modal
   - Form validation
   - URL format validation
   - Active state checkbox

### CSS Styling

**Key Styles:**
- `.ai-blog-brand-features-grid`: Responsive grid layout
- `.ai-blog-brand-feature-card`: Individual feature cards with hover effects
- `.brand-feature-category`: Color-coded category badges
- `.brand-feature-status`: Active/inactive status indicators
- Gradient hover effect matching other admin pages

**Category Color Scheme:**
```css
.category-informational-page { background: #dbeafe; color: #1e40af; }
.category-document { background: #fce7f3; color: #be185d; }
.category-image { background: #d1fae5; color: #065f46; }
.category-video { background: #e9d5ff; color: #6b21a8; }
```

### Integration with Content Generation

#### Context Inclusion
Brand features can be included in content generation context:

```php
// In Content_Generator or similar service
$brand_feature_controller = new Brand_Feature_Controller();
$brand_features = $brand_feature_controller->get_features_for_generation();

// Include in AI prompt
$prompt .= "\n\nBRAND FEATURES FOR INTERNAL LINKING:\n";
foreach ($brand_features as $feature) {
    $prompt .= "- {$feature['name']} ({$feature['category']}): {$feature['url']}\n";
    if ($feature['description']) {
        $prompt .= "  Description: {$feature['description']}\n";
    }
}
```

#### AI Instructions
The AI can be instructed to:
- Use brand features for relevant internal linking
- Prefer informational pages for educational content
- Link to documents when referencing resources
- Include image/video features when appropriate

### Security Implementation

1. **Access Control**
   - Requires `manage_options` capability
   - Admin-only functionality

2. **Input Validation**
   - Name and URL are required fields
   - URL format validation
   - Category enum validation
   - Text sanitization

3. **AJAX Security**
   - Nonce verification on all requests
   - Capability checking
   - Sanitized inputs and outputs

### Performance Considerations

1. **Database Optimization**
   - Indexed columns: active, category, name
   - Efficient queries with proper WHERE clauses
   - Pagination to limit results

2. **Frontend Performance**
   - Debounced search to reduce requests
   - Client-side filtering where possible
   - Lazy loading of feature data

3. **Caching Strategy**
   - Features cached in transients for content generation
   - Cache invalidation on updates
   - Minimal database queries

### Error Handling

1. **User-Friendly Messages**
   - Clear validation errors
   - Success confirmations
   - Network error handling

2. **Logging**
   - All operations logged with context
   - Error tracking for debugging
   - Performance metrics

3. **Graceful Degradation**
   - Features optional for content generation
   - Fallbacks for missing data
   - Recovery mechanisms

### Future Enhancements

1. **Advanced Features**
   - Bulk import/export
   - Usage tracking (which features are linked most)
   - Auto-discovery of internal links
   - Link validation/checking

2. **AI Integration**
   - Smarter context-aware linking
   - Link relevance scoring
   - Automatic anchor text generation
   - Link distribution optimization

3. **Analytics**
   - Track which features are used in generation
   - Click-through rates on generated links
   - Link effectiveness metrics

This implementation provides a robust system for managing brand features that can be intelligently used by the AI during content generation, enhancing the internal linking structure of generated blog posts.
- Generation status is stored in both database and transients for real-time updates
- Database field: `generation_status` in `ai_blog_ideas` table
- Transient key: `ai_blog_generation_status_{$idea_id}` (1 hour expiration)
- Frontend polls for updates every 2-5 seconds during active generation
- Ideas Model merges transient data with database data for real-time display

### Frontend Features

## Products Management System (v1.7.0)

### Overview
Version 1.7.0 introduces a comprehensive products management system that allows users to catalog their products with multiple images and links. The system supports both manual product creation and WooCommerce product import, providing a centralized product database for AI content generation context.

### Database Schema

#### Products Table (`wp_ai_blog_generator_products`)
```sql
CREATE TABLE wp_ai_blog_generator_products (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    ideal_uses TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_name (name)
);
```

#### Product Images Table (`wp_ai_blog_generator_product_images`)
```sql
CREATE TABLE wp_ai_blog_generator_product_images (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    attachment_id BIGINT(20) UNSIGNED NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_primary (is_primary),
    KEY idx_order (display_order)
);
```

#### Product Seed Images Table (`wp_ai_blog_generator_product_seed_images`)
```sql
CREATE TABLE wp_ai_blog_generator_product_seed_images (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    attachment_id BIGINT(20) UNSIGNED NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    display_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_order (display_order),
    FOREIGN KEY (product_id) REFERENCES wp_ai_blog_generator_products(id) ON DELETE CASCADE
);
```

#### Product Links Table (`wp_ai_blog_generator_product_links`)
```sql
CREATE TABLE wp_ai_blog_generator_product_links (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    link_type ENUM('product_page','purchase','documentation','other') DEFAULT 'other',
    link_text VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_type (link_type)
);
```

### Model Implementation

#### Product_Model
Location: `models/class-product-model.php`

**Key Features:**
- Extends base Model class but overrides all CRUD methods
- Direct wpdb queries for compatibility
- Handles relationships with images and links tables
- Comprehensive validation and sanitization

**Key Methods:**
```php
public function get_all($where = [], $order_by = 'name ASC', $limit = '') {
    // Gets products with primary image and link counts
}

public function get($where = [], $fields = '*') {
    // Gets single product with all images and links
}

public function insert($data) {
    // Creates new product
}

public function update($data, $where) {
    // Updates existing product
}

public function delete($where) {
    // Deletes product and cascade deletes images/links
}

public function get_product_images($product_id) {
    // Gets all images for a product ordered by display_order
}

public function get_product_links($product_id) {
    // Gets all links for a product
}

public function get_product_seed_images($product_id) {
    // Gets all seed images for a product ordered by display_order
}

public function add_seed_image($product_id, $attachment_id, $display_order = 0) {
    // Adds PNG seed image to product with validation
}

public function remove_seed_image($product_id, $attachment_id) {
    // Removes seed image from product
}

public function update_seed_image_order($product_id, $image_order) {
    // Updates display order of seed images
}

public function get_all_seed_images() {
    // Gets all seed images across all products with product details
}

public function get_active_products() {
    // Gets all active products (currently returns all products)
    // Used by Prompt Compiler Service for product promotion requirements
}
```

### Controller Implementation

#### Product_Controller
Location: `controllers/class-product-controller.php`

**AJAX Handlers:**
```php
// Product CRUD
'wp_ajax_ai_blog_get_products' => 'ajax_get_products'
'wp_ajax_ai_blog_get_product' => 'ajax_get_product'
'wp_ajax_ai_blog_create_product' => 'ajax_create_product'
'wp_ajax_ai_blog_update_product' => 'ajax_update_product'
'wp_ajax_ai_blog_delete_product' => 'ajax_delete_product'

// Image management
'wp_ajax_ai_blog_add_product_images' => 'ajax_add_product_images'
'wp_ajax_ai_blog_update_product_images' => 'ajax_update_product_images'
'wp_ajax_ai_blog_delete_product_image' => 'ajax_delete_product_image'
'wp_ajax_ai_blog_set_primary_image' => 'ajax_set_primary_image'
'wp_ajax_ai_blog_reorder_images' => 'ajax_reorder_images'

// Link management
'wp_ajax_ai_blog_update_product_links' => 'ajax_update_product_links'

// Seed image management
'wp_ajax_ai_blog_add_product_seed_image' => 'ajax_add_product_seed_image'
'wp_ajax_ai_blog_remove_product_seed_image' => 'ajax_remove_product_seed_image'
'wp_ajax_ai_blog_update_seed_image_order' => 'ajax_update_seed_image_order'

// WooCommerce integration
'wp_ajax_ai_blog_get_woocommerce_products' => 'ajax_get_woocommerce_products'
'wp_ajax_ai_blog_import_woocommerce_product' => 'ajax_import_woocommerce_product'
```

**Security Implementation:**
- All handlers use `verify_ajax_security()` for nonce and capability checks
- Input sanitization using WordPress functions
- Output escaping in JSON responses
- SQL injection prevention with prepared statements

### Frontend Implementation

#### Admin View
Location: `admin/views/products.php`

**Features:**
- Grid layout with responsive cards
- Search functionality with real-time filtering
- Pagination (12 products per page)
- Modal-based add/edit interface
- WooCommerce import section

#### JavaScript Implementation
Location: `admin/assets/js/products.js`

**Core Object Structure:**
```javascript
window.aiBlogProducts = {
    currentPage: 1,
    searchTerm: '',
    editingProductId: null,
    selectedImages: [],
    productLinks: [],
    
    // Core methods
    init: function() { ... },
    loadProducts: function() { ... },
    showAddModal: function() { ... },
    editProduct: function(productId) { ... },
    saveProduct: function() { ... },
    deleteProduct: function(productId) { ... },
    
    // Image management
    openMediaLibrary: function() { ... },
    updateImageDisplay: function() { ... },
    removeImage: function(attachmentId) { ... },
    setPrimaryImage: function(attachmentId) { ... },
    
    // Link management
    addLink: function() { ... },
    removeLink: function(index) { ... },
    
    // WooCommerce integration
    loadWooCommerceProducts: function() { ... },
    importWooCommerceProduct: function(productId) { ... }
};
```

**Key Features:**
1. **Media Library Integration**
   - Uses WordPress media modal for image selection
   - Supports multiple image selection
   - Automatic thumbnail generation

2. **Drag-and-Drop Image Ordering**
   - jQuery UI sortable for image reordering
   - Visual feedback during drag
   - Automatic order saving

3. **Dynamic Link Management**
   - Add unlimited links per product
   - Predefined link types with icons
   - URL validation

4. **Real-time Search**
   - Client-side filtering for instant results
   - Searches product names and descriptions
   - Maintains pagination state

### CSS Styling

**Key Styles:**
- `.ai-blog-products-grid`: Responsive grid layout
- `.ai-blog-product-card`: Individual product cards with hover effects
- `.ai-blog-product-images-grid`: Image grid in edit modal
- `.ai-blog-product-image-item`: Individual images with actions
- `.ai-blog-product-links`: Link management section
- `.ai-blog-woocommerce-section`: WooCommerce import styling

### WooCommerce Integration

**Import Process:**
1. Queries WooCommerce products via `wc_get_products()`
2. Retrieves product images and galleries
3. Maps WooCommerce data to plugin structure
4. Creates product with all images and product page link
5. Handles variations as separate products

**Data Mapping:**
```php
// WooCommerce -> Plugin
'name' => $wc_product->get_name()
'description' => $wc_product->get_description()
'ideal_uses' => $wc_product->get_short_description()

// Images
- Featured image becomes primary
- Gallery images added with order preserved

// Links
- Product permalink added as 'product_page' type
```

### Integration Points

#### With Content Generation
- Products can be referenced in contexts
- Product descriptions enhance AI understanding
- Product images provide visual reference
- Product seed images used for AI image generation
- Links provide additional context

#### With WordPress Core
- Full media library integration
- Proper capability checking
- WordPress coding standards
- Hooks for extensibility

### Performance Considerations

1. **Database Optimization**
   - Indexed columns for fast queries
   - Efficient JOIN operations
   - Pagination to limit results

2. **Frontend Performance**
   - Lazy loading of images
   - Debounced search input
   - Optimized JavaScript execution

3. **Caching Strategy**
   - Transient caching for expensive queries
   - Client-side caching of loaded products

### Security Measures

1. **Input Validation**
   - Product name required and sanitized
   - URL validation for links
   - File type validation for images

2. **Access Control**
   - Requires `manage_options` capability
   - Nonce verification on all actions
   - User permission checks

3. **Data Sanitization**
   - All text inputs sanitized
   - URLs properly escaped
   - SQL injection prevention

### Error Handling

1. **User-Friendly Messages**
   - Clear error descriptions
   - Suggested solutions
   - Success confirmations

2. **Logging**
   - All errors logged with context
   - Debug information available
   - Performance metrics tracked

3. **Graceful Degradation**
   - Fallbacks for missing data
   - Partial success handling
   - Recovery mechanisms

### Future Enhancements

1. **Bulk Operations**
   - Multi-select for bulk delete
   - Bulk import from CSV
   - Bulk image upload

2. **Advanced Features**
   - Product categories/tags
   - Custom fields support
   - Product variations

3. **Integration Expansion**
   - Other e-commerce platforms
   - External product feeds
   - API access

This implementation provides a robust, user-friendly products management system that seamlessly integrates with the AI blog generation workflow while maintaining WordPress best practices and security standards.

#### Status Updates

## Known Issues

### Context Update Issue
- **Problem**: Context updates failing with "Failed to update context" error
- **Root Cause**: context_id parameter not being sent in AJAX request
- **Investigation**:
  - Added debugging to contexts.js to track context ID field value
  - Console shows form data is missing context_id even though code attempts to add it
  - Hidden field `#context-id` should contain the ID but appears to be empty
- **Possible Causes**:
  1. Form is being reset at the wrong time (closeModal resets the form)
  2. Hidden field value not being populated correctly in populateModal
  3. Timing issue between loading context data and form submission
  4. Race condition where field is cleared before being read
- **Debugging Added**:
  - Log context ID field value and element in saveContext
  - Log when context ID is set in populateModal
  - Added validation to prevent sending request without context_id
- **Temporary Workaround**: 
  - Added check in saveContext to show error if context_id is missing
  - This prevents the AJAX request from being sent with invalid data
- **Next Steps**:
  - Monitor console logs to identify when/why context_id becomes empty
  - Consider storing context ID in a data attribute or JavaScript variable instead of hidden field
  - Review modal lifecycle to ensure data persistence

## UI/UX Features

### Context Checkbox Behavior
- **Always Include in Content Generation**:
  - When checked: Automatically checks and disables both Avada and HTML options
  - When unchecked: Re-enables both options and maintains their mutual exclusivity
  - Rationale: Content generation requires proper formatting, so both options must be selected
- **Avada and HTML Options**:
  - Mutually exclusive when enabled (checking one unchecks the other)
  - Both automatically selected and disabled when content generation is always included
  - State properly managed during form population and reset
