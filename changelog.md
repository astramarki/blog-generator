# Changelog - AI Blog Generator

All notable changes to the AI Blog Generator WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Current Development]

### Fixed
- **Frontend Modal Conflict Fixed**: Resolved conflict with existing website modals (like pricing modal)
  - **Problem**: 
    - Plugin was loading Bootstrap 5.3.0 JS globally on all frontend pages
    - This conflicted with existing modals using older Bootstrap syntax (data-dismiss vs data-bs-dismiss)
    - Pricing modal and other frontend modals stopped working when plugin was activated
  - **Solution**:
    - Made Bootstrap loading conditional - only loads for AI-generated posts with accordions
    - Changed script handle from 'bootstrap-js' to 'ai-blog-bootstrap-js' to avoid naming conflicts
    - Added checks to prevent loading Bootstrap if already loaded by theme/other plugins
    - Scoped accordion initialization to only target AI-specific classes (.ai-blog-accordion, .ai-generated-accordion)
    - Wrapped initialization in isolated function to prevent global scope pollution
  - **Technical Changes**:
    - Modified `enqueue_frontend_scripts()` to check post meta for AI-generated content
    - Only loads scripts when content actually needs them
    - Prevents interference with existing modal systems on the site
  - **Result**: Website modals (pricing, etc.) now work correctly alongside AI Blog Generator
- **Removed Global ApexCharts Enqueuing**: ApexCharts is no longer loaded globally on frontend pages
  - **Rationale**: ApexCharts script tags are included directly in generated blog posts when needed
  - **Changes Made**:
    - Removed `enqueue_apexcharts()` method entirely
    - Removed `post_might_need_charts()` method as it's no longer needed
    - Removed all calls to enqueue ApexCharts on frontend
    - Fixed fatal error by removing orphaned hook registration for deleted method
  - **Result**: Reduced unnecessary script loading - ApexCharts only loads when posts contain charts via embedded script tags
- **Comprehensive Fusion Code Cleaning After Post Save**: Fixed HTML entities and formatting issues in fusion_code blocks
  - **Problem**: 
    - Fusion code blocks were getting worse with HTML entities like `&gt;`, `&lt;`, `&amp;` being added
    - Manual editing was required for every post to fix code inside fusion_code blocks
    - Previous cleaning filters weren't comprehensive enough
    - Tags were inadequately closed in Avada layouts
  - **Solution**:
    - Added `deep_clean_fusion_code` method that performs comprehensive cleaning:
      - Decodes HTML entities (multiple passes to handle double-encoding)
      - Removes all HTML tags (p, br, div, span) in various forms
      - Fixes common encoding issues (&amp;, &nbsp;, &quot;, etc.)
      - Detects JavaScript code and adds script tags when missing
      - Handles arrow functions and modern JavaScript syntax
    - Registered `cleanup_fusion_code_after_save` hook that runs after WordPress saves posts
    - Added infinite loop protection to prevent recursion
    - Only updates posts if content actually changed
  - **Technical Changes**:
    - Added post-save hooks in `define_admin_hooks` (save_post and wp_insert_post)
    - Created comprehensive cleaning method with multi-step processing
    - Updated all existing cleaning methods to use the new deep clean approach
    - Updated Content_Generator's clean method to match
    - Enhanced fix-existing-apexcharts.php script to use new cleaning method
  - **Result**: Fusion code blocks are now automatically cleaned after saving - no manual editing required
- **Live System Error Fixes**: Fixed errors encountered during live system deployment
  - **Settings Save Logic**: Fixed false "SETTING_UPDATE_FAILED" errors when saving unchanged settings
    - Updated type comparison logic to handle numeric strings vs integers properly  
    - WordPress `update_option()` returns false for unchanged values, now properly handled
  - **API Key Initialization**: Fixed "ANTHROPIC_MISSING_API_KEY" errors during plugin initialization
    - Updated Anthropic_Service constructor to accept optional API key parameter
    - Updated OpenAI_Service constructor to accept optional API key parameter
    - Prevents error logging when API keys are provided via test connection
  - **API Connection Test Errors**: Fixed JSON parse errors when testing API connections
    - Added output buffering to prevent PHP notices/warnings from corrupting JSON responses
    - Fixed JavaScript error handling to properly handle non-string error messages
    - Fixed incorrect context reference in AJAX error handler (this vs self)
    - Fixed error callback parameters to properly access xhr, status, and error
    - Added better error messages for server-side issues
    - Added logging to capture unexpected output during API tests
    - **Enhanced error handling to prevent HTML output in JSON responses**:
      - Added PHP error handler to catch all errors/warnings during AJAX requests
      - Suppressed error display with `ini_set('display_errors', '0')` during AJAX
      - Added comprehensive logging for debugging without breaking JSON output
      - Improved error tracking to identify source of HTML output issues

### Enhanced
- **Automatic ApexCharts Code Cleaning**: Enhanced the blog post generation process to automatically fix ApexCharts markup issues
  - **Enhanced `clean_fusion_code_content` method** to more aggressively clean ApexCharts code:
    - Detects ApexCharts code both inside and outside of fusion_code blocks
    - Removes all HTML tags (`<p>`, `</p>`, `<br />`) that WordPress adds
    - Automatically wraps JavaScript code in `<script>` tags
    - Handles multiple patterns of ApexCharts code insertion
    - Preserves code that's already properly formatted
  - **Added WordPress filter** (`content_save_pre`) as a safety net:
    - Runs automatically when any post is saved
    - Cleans ApexCharts code even if WordPress modifies it after initial generation
    - Only processes content that contains ApexCharts or chart-related code
    - Prevents the need for manual cleanup on every post
  - **Result**: ApexCharts code is now automatically cleaned during generation and maintained clean on every save

### Changed
- **Plugin Cleanup for Production Deployment**: Removed all test scripts, debug files, and temporary migration scripts
  - **Test Scripts Removed**:
    - `test-category-assignment.php` - Category assignment testing
    - `test-debug-log.php` - Debug log testing
    - `test-direct-generation.php` - Direct generation testing
    - `test-download-button.html` - Download button testing
    - `test-generation-queue.php` - Generation queue testing
  - **Debug Scripts Removed**:
    - `debug-approved-ideas.php` - Approved ideas debugging
    - `debug-approved-issue.php` - Approved issue debugging
    - `debug-check-ideas.php` - Ideas debugging
  - **Migration/Fix Scripts Removed**:
    - `add-context-format-fields.sql` - SQL migration script
    - `add-fallback-method.php` - Fallback method addition
    - `add-generation-status-field.php` - Status field migration
    - `ai-blog-fusion-code-fix.php` - Fusion code fix script
    - `apexcharts-fix.php` - ApexCharts fix script
    - `check-post-fusion-code.php` - Post fusion code check
    - `check-schema.php` - Schema verification
    - `cleanup-stuck-generations.php` - Stuck generation cleanup
    - `content-generator-example.php` - Example script
    - `create-personas-table.php` - Personas table creation
    - `create-products-tables.sql` - Products table SQL
    - `fix-contexts-schema.php` - Context schema fix
    - `fix-existing-fusion-posts.php` - Fusion posts fix
    - `fix-fusion-code-post-save.php` - Post save fix
    - `verify-no-early-transactions.php` - Transaction verification
    - `persona-implementation.sql` - Persona SQL implementation
  - **Log Files Removed**:
    - `debug-transaction.log` - Transaction debug log (kept infrastructure for runtime logging)
    - `debug.log` - General debug log
  - **Development Documentation Removed**:
    - `DEBUG.md` - Debugging guide (referenced removed test files)
    - `IMPLEMENTATION_STATUS.md` - Development progress tracking
    - `BLOG_IDEAS_V2_INTEGRATION.md` - Development integration guide
    - `persona-implementation-summary.md` - Development implementation notes
    - `PRODUCT_IMAGES_GUIDE.md` - Corrupted documentation with duplicate content
    - `PRODUCT-IMAGES.md` - Corrupted documentation with duplicate content
  - **Backup Files Removed**:
    - `services/class-anthropic-service.php.backup` - Backup of Anthropic service
    - `controllers/class-blog-controller-backup.php` - Backup of blog controller
  - **Documentation Retained**: Kept all important documentation files including changelog.md, database-structure.md, implementationPlan.md, implementationTechnical.md, readme.txt, and avada-integration.md
  - **Development Config Retained**: Kept `wp-config-dev.example.php` as it's referenced in documentation for developers

- **Updated Plugin Uninstaller**: Enhanced uninstall.php to properly remove all plugin data
  - **Complete Table Removal**: Updated to remove all 13 database tables in correct order respecting foreign key constraints
  - **Transient Cleanup**: Added cleanup for generation queue transients and pattern-based transient removal
  - **Log File Cleanup**: Added removal of generation log files from uploads directory
  - **Cron Job Cleanup**: Added removal of ai_blog_process_single_generation cron hook
  - **Improved Post Deletion**: Added NULL check when getting post IDs to delete

### Changed
- **Drafted Posts Page Complete Redesign**: Completely rewrote the drafted posts page to match the modern Bootstrap 5 style of approved ideas and idea generator pages
  - **Visual Improvements**:
    - Added statistics cards showing draft count, scheduled posts, published today, and total cost
    - Modernized table design with inline action buttons and better category display using badges
    - Added FontAwesome icons throughout the interface for better visual hierarchy
    - Implemented responsive design that works well on mobile devices
    - Applied consistent styling matching other v2 admin pages
  - **Functionality Enhancements**:
    - Implemented real-time AJAX loading and updates without page refresh
    - Added bulk actions for publishing, scheduling, and deleting multiple posts
    - Added status filters to show all posts, drafts only, or scheduled only  
    - Enhanced bulk scheduling with customizable date ranges and time distribution
    - Added modals for bulk operations with better user confirmation
  - **Technical Improvements**:
    - Created new `drafted-posts.js` JavaScript file for enhanced interactivity
    - Added comprehensive AJAX handlers for all operations (get, publish, schedule, bulk operations, delete)
    - Enhanced Blog_Model with count and cost calculation methods for statistics
    - Fixed scheduling functionality to use WordPress native functions directly
    - Added proper error handling and user feedback for all operations

### Fixed
- **Fusion Code JavaScript Corruption**: Fixed issue where Avada and WordPress strip script tags from fusion_code blocks
  - **Problem**: 
    - Avada was wrapping JavaScript code in `<p>` tags and converting line breaks to `<br />` tags
    - WordPress was stripping `<script>` tags as a security measure when saving posts
    - Result: ApexCharts code was saved without script tags, preventing execution
  - **Solution**: Multi-layered approach:
    1. **Pre-processing**: Enhanced `clean_fusion_code_content()` method that:
       - Detects existing script tags to avoid double-wrapping
       - Removes HTML tags (`<p>`, `</p>`, `<br />`) that Avada adds
       - Wraps JavaScript in `<script>` tags if not already present
       - Logs all fusion_code blocks before and after processing to debug-transaction.log
    2. **Post-processing**: Created utilities for fixing fusion_code after save:
       - `fix-fusion-code-post-save.php`: Creates a WordPress filter/plugin to fix fusion_code on save
       - `fix-existing-fusion-posts.php`: Repairs existing posts with missing script tags
       - `check-post-fusion-code.php`: Diagnostic tool to check fusion_code content in posts
  - **Implementation**: 
    - Enhanced logging shows fusion_code content before/after processing
    - Fix script adds proper script tags and decodes HTML entities
    - Can be applied as a plugin or added to theme's functions.php
  - **Result**: ApexCharts and other JavaScript code now properly wrapped in script tags and executes correctly

### Added
- **Hide Completed Filter on Approved Ideas Page**: Added a checkbox filter to automatically hide completed generations
  - **Location**: Approved Ideas V2 page (`/wp-admin/admin.php?page=ai-blog-generator-approved-ideas-v2`)
  - **Default State**: Checkbox is checked by default, hiding completed generations
  - **Functionality**: When checked, filters out ideas with status `generated` from the display
  - **User Experience**: Allows users to focus on pending/active generations without clutter from completed ones
  - **Implementation**: 
    - Added checkbox control "Hide Completed" in the filter bar
    - JavaScript filter applied to `updateIdeasDisplay()` function
    - Preserves all ideas in cache while only filtering display
    - Real-time toggle without requiring page refresh

### Enhanced
- **Context Editor Modal Improvements**: Major enhancements to context editing experience
  - **Larger Modal Size**: Increased to 80% viewport width and height for better code editing
  - **Code Editor Enhancements**: 
    - Increased textarea rows from 15 to 25 for more visible content
    - Added proper code editor styling with monospace font and syntax-friendly formatting
    - Enhanced CSS with better focus states and overflow handling
  - **Content Preservation**: Complete removal of sanitization for context content
    - **Problem**: JavaScript code and complex HTML/shortcodes were being corrupted by `wp_kses_post()` sanitization
    - **Solution**: Removed all content sanitization to preserve exact input
    - **Files Modified**:
      - `models/class-context-model.php` - Removed `wp_kses_post()` from content sanitization
      - `controllers/class-context-controller.php` - Removed content sanitization in create, update, and import methods
    - **Result**: JavaScript code, HTML, Avada shortcodes, and any other content is now saved exactly as entered
  - **UI Improvements**:
    - Better textarea styling for code editing with proper tab sizing
    - Responsive height calculation for optimal viewing
    - Enhanced focus states for better visual feedback

- **Post Title Proper Case Conversion**: Automatically convert generated blog post titles to proper case
  - **Implementation**: Added intelligent title case conversion that:
    - Capitalizes first letter of each major word
    - Keeps articles, conjunctions, and short prepositions lowercase (except when first or last word)
    - Preserves acronyms in uppercase
    - Handles special characters and numbers appropriately
  - **Function Location**: Added `to_proper_case()` static method to Logger utility class
  - **Applied To**:
    - Content Generator service when creating WordPress posts
    - Blog Generator Controller V2 when publishing posts
    - Blog model records to maintain consistency
  - **Example**: "THE BEST WAYS TO USE AI IN MARKETING" becomes "The Best Ways to Use AI in Marketing"

### Fixed
- **CRITICAL: Product Validation Error**: Fixed "Product name is required" error when editing products with filled-out product names
  - **Root Cause**: Mismatch between form field names and validation method expectations
  - **Problem**: Form sends `product_name` but validation checked for `name`, causing false validation failures
  - **Solution**: Updated Product Model validation and all related methods to use correct `product_name` field consistently
  - **Files Modified**: 
    - `models/class-product-model.php` - Fixed validation, sanitization, SQL queries, and fillable fields
    - Updated all database references from `name`/`description` to `product_name`/`product_description`
  - **Result**: Product editing now works correctly with proper field name consistency

- **CRITICAL: JavaScript Code Corruption in Context Editor**: Fixed context editor converting JavaScript code to HTML with `<br/>` tags
  - **Root Cause**: Context content was being HTML-escaped with `esc_textarea()` when loaded into edit form
  - **Problem**: JavaScript code like ApexCharts was corrupted with `<br/>` tags and `<p>` wrappers, making scripts unusable
  - **Solution**: 
    - Removed `esc_textarea()` from Context Controller to preserve raw code formatting
    - Updated context textarea to be code-friendly with monospace font and proper attributes
    - Added `spellcheck="false"`, `autocorrect="off"`, and `white-space: pre` for code editing
  - **Files Modified**:
    - `controllers/class-context-controller.php` - Removed HTML escaping of context content
    - `admin/views/contexts.php` - Enhanced textarea for code editing with proper styling
  - **Result**: JavaScript code blocks now preserve proper formatting without HTML tag corruption

### Added
- **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
  - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
    - Updated all AI prompts to request 120-140 character meta descriptions
    - Updated validation logic to check for 140-character limit instead of 160
    - Applied changes to both Prompt Compiler Service and Anthropic Service
  - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
    - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
    - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
    - Only adds keyphrase if not already present to avoid duplication
    - Applied to both Content Generator and Anthropic Service classes
  - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
    - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
    - Focus keyphrase appears first in filename followed by descriptive keywords
    - Smart deduplication logic prevents overlap between keyphrase and content keywords
    - Automatic propagation from content generation to image requirements
    - Applied to both content images and featured images
  - Ensures all SEO elements meet current best practices

- **Brand Features Management System**: Complete internal linking management with:
  - New database table: `ai_blog_brand_features`
  - Full CRUD operations for brand features (services, pages, documents, etc.)
  - Four category types: informational_page, document, image, video
  - Active/inactive state management for features
  - Real-time search and category filtering
  - Grid layout with modern card-based design
  - Modal-based editing interface
  - Integration ready for AI content generation
  - Comprehensive error handling and logging

### Fixed
- **Brand Features Page Issues**: 
  - Fixed loading message that never disappeared due to missing `self` reference in JavaScript
  - Fixed save button not working due to incorrect nonce verification name
  - Enhanced modal styling to match other admin modals with gradient header and proper form styling
  - Added `ai-blog-form-control` class to all form inputs for consistent styling

- **Base64 Content Decoding Issue**: Fixed issue where base64-encoded content (like ApexCharts JavaScript) was not being decoded before insertion into blog posts
  - Root cause: AI was generating base64-encoded content but the content parsing logic wasn't decoding it
  - Solution: Added `decode_base64_content()` method to detect and decode base64 strings in HTML/Avada content
  - Enhanced all content parsing paths (HTML, HTML_CONTENT, AVADA_CONTENT, CONTENT) to automatically decode base64
  - Added intelligent detection for base64-encoded HTML/JavaScript content (checks for tags like `<script>`, `function`, `ApexCharts`)
  - Result: Charts and other base64-encoded content now properly decode and display in blog posts

- **Enhanced Base64 Content Detection**: Improved base64 detection and decoding to handle ApexCharts and other JavaScript content more reliably
  - Root cause: Previous base64 detection was too restrictive and couldn't handle various content formats (code blocks, line breaks, etc.)
  - Problem: ApexCharts JavaScript code was being returned as base64 strings instead of being decoded into functional code
  - Solution: Implemented multiple detection patterns and enhanced content validation
  - Added support for base64 in code blocks, with line breaks, and standalone strings
  - Expanded content validation to detect more JavaScript patterns (`chart`, `series`, `const`, `let`, `querySelector`, etc.)
  - Added comprehensive logging to track successful base64 decoding attempts
  - Result: ApexCharts and other base64-encoded JavaScript content now properly decodes and displays as functional code in blog posts

- **Context Textarea HTML Parsing Issue**: Fixed critical issue where HTML content in context textareas was being parsed instead of displayed as raw code
  - Root cause: Context content containing HTML/Avada shortcodes was not properly escaped when sent to frontend textarea fields
  - Problem: HTML tags and entities were being interpreted by the browser instead of shown as editable text in the context editor
  - Critical Impact: Users couldn't properly edit contexts containing HTML/Avada shortcodes because the browser was parsing the HTML
  - Solution: Added `esc_textarea()` escaping to context content before sending to frontend
  - Modified `get_context` AJAX handler to create escaped copy of context object for textarea display
  - Result: HTML and Avada shortcodes now display as raw, editable text in context textareas instead of being parsed

- **Log Noise Reduction**: Removed unnecessary repeating log entry for Product AJAX handlers registration
  - Removed "PRODUCT_AJAX_HANDLERS_REGISTERED" log entry that was appearing on every Product Controller initialization
  - This log provided no debugging value and was creating unnecessary log noise
  - Result: Cleaner debug logs with less repetitive initialization messages

- **Image Saving and Alt Text Improvements**: Comprehensive improvements to image filename generation and alt text quality
  - **Descriptive Filenames**: Replaced generic "ai_blog_image" prefixes with meaningful, descriptive filenames based on image content
    - New system extracts 3-4 key words from image prompts (e.g., "classroom-poster-printer-setup" instead of "ai_blog_image_1")
    - Removes AI prompt language and focuses on actual subject matter
    - Includes short hash for uniqueness while keeping names readable
  - **Enhanced Alt Text Generation**: Significantly improved alt text quality and length
    - Removed AI prompt language ("create", "generate", "professional", "high quality", etc.)
    - Increased length limit from 125 to 200 characters (WordPress recommendation)
    - Smart truncation at word boundaries to avoid cutting mid-word
    - Added proper capitalization and cleanup of technical prompt terminology
    - Removed references to "stock photo", "commercial", "marketing" language
  - **Result**: Images now have meaningful filenames and natural, descriptive alt text without AI prompt artifacts

- **Context Content Over-Escaping Issue**: Fixed issue where context content was being overly escaped, showing multiple backslashes in text boxes
  - Root cause: `sanitize_textarea_field` was aggressively escaping quotes and special characters, causing multiple levels of escaping for complex Avada shortcodes
  - Problem: Content like `size=\"1\"` was becoming `size=\\\\\\\"1\\\\\\\"` with multiple escape levels
  - Solution: Replaced `sanitize_textarea_field` with `wp_kses_post( wp_unslash() )` for context content sanitization
  - Fixed in multiple locations: Context Controller's `create_context()`, `update_context()`, `import_contexts()` methods and Context Model's `sanitize_field()` method
  - Result: Complex Avada shortcodes and HTML content now save and display correctly without over-escaping

- **Prompt Compiler Service Unnecessary Initialization**: Fixed Prompt Compiler Service being initialized every 2 seconds during AJAX polling
  - Root cause: Content Generator was eagerly initializing Prompt Compiler Service in constructor, and AJAX status polling was creating Content Generator instances every 2 seconds
  - Problem: Debug logs showed `PROMPT_COMPILER_INIT` and `PRODUCT_AJAX_HANDLERS_REGISTERED` every 2 seconds due to fast refresh mode
  - Solution: Implemented lazy initialization for Prompt Compiler Service - only creates instance when actually needed during blog generation
  - Performance Impact: Eliminates unnecessary service initialization during routine AJAX status checks
  - Result: Prompt Compiler Service now only initializes during actual blog post generation, not during status polling

### UI/UX Improvements
- **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
  - Added proper button styling with colors and hover effects
  - Save button uses primary blue (#2271b1) with hover state
  - Cancel button uses secondary gray (#f0f0f1) with hover state
  - Added consistent padding, border radius, and transitions
  
- **Product Links Pill Design**: Enhanced product links display
  - Added pill-style design with rounded borders and padding
  - Colored type badges with specific colors for each link type
  - Product Page links show green badge
  - Purchase links show orange badge
  - Documentation links show purple badge
  - Other links show gray badge
  - Added hover effects with shadow and transform
  - Fixed link type labels to show proper text instead of database values

### UI/UX Improvements
- **Products Page Redesign**: Applied modern design style to products page matching personas page
  - Enhanced product cards with gradient backgrounds and hover effects
  - Improved search box styling with focus states
  - Modernized product modal with better form styling and section dividers
  - Updated image and link management UI with better visual hierarchy
  - Added colored badges for link types (product page, purchase, documentation)
  - Improved pagination styling with better hover states
  - Enhanced responsive design for mobile devices

- **Contexts Page Redesign**: Applied consistent modern design style to contexts page
  - Enhanced context cards with improved shadows and hover effects
  - Added gradient backgrounds to context type badges
  - Improved badge styling for usage categories and always-include indicators
  - Modernized context edit modal with better form controls
  - Enhanced seed images section with better card design
  - Updated seed image upload modal to match personas modal styling
  - Improved button styling with hover effects and better spacing
  - Added responsive design improvements for mobile devices

- **Layout Consistency**: Made all admin pages full-width
  - Removed max-width restrictions from contexts and products pages
  - All pages now use 100% width like the personas page
  - Consistent layout across all admin sections

- **Icon-Based Actions**: Replaced text buttons with icons on contexts page
  - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
  - Consistent icon style matching personas page design
  - Applied same icon treatment to seed images section
  - Better visual hierarchy and cleaner interface

### Fixed
- **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
  - Added 'layout' as a valid enum option in the database
  - Fixed JavaScript to display type labels instead of database values
  - Added context type labels to JavaScript localization data
  - Context cards now show "Layout Guidelines" instead of "layout" after saving

### Enhanced
- **Context Card Hover Effect**: Added gradient line hover effect to context cards
  - Matches the persona cards' gradient line that appears on hover
  - Uses the same blue-purple-pink gradient for consistency
  - Provides visual feedback when hovering over context cards

- **Product Cards Redesign**: Updated product cards to match personas and contexts style
  - Replaced text buttons with icon buttons (edit and delete)
  - Added gradient line hover effect matching other admin cards
  - Improved typography and spacing consistency
  - Updated color scheme to match modern design language
  - Better visual hierarchy with icon-based actions

### Fixed
- **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
  - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
  - **Solution**: Updated enqueue script patterns to match actual menu slug registration
  - **Files Modified**: 
    - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
    - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
  - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page

- **Drafted Posts Table Width**: Fixed table only using half the screen width
  - **Root Cause**: WordPress default `.wrap` class applies width constraints
  - **Solution**: Added CSS overrides to make the page full width
  - **CSS Changes**: 
    - Override `.wrap` max-width constraint
    - Ensure table and cards use 100% width
    - Scoped WordPress admin overrides to drafted posts page only
  - **Result**: Drafted posts table now uses full available screen width

- **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
  - **Root Cause**: Script was enqueued but not localized with AJAX data
  - **Solution**: Added `wp_localize_script` call for drafted posts script
  - **Files Modified**: 
    - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
  - **Result**: Drafted posts page now loads properly with AJAX functionality working

- **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
  - **Root Cause**: Bootstrap card component was constraining table width
  - **Solution**: Replaced card wrapper with custom div structure
  - **Changes Made**: 
    - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
    - Added custom CSS for table wrapper with full width
    - Also updated filter actions bar to use consistent wrapper approach
  - **Result**: Table now uses full available screen width without Bootstrap card constraints

### Fixed
- **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
  - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
  - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
  - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
  - **Changes Made**:
    - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
    - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
    - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
  - **Result**: Schedule post functionality now works correctly without fatal errors

### Changed
- **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
  - **Files Modified**:
    - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
    - `admin/views/approved-ideas-view-v2.php` - Updated page title 
    - `admin/class-admin-manager.php` - Updated submenu registration
  - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links

### Enhanced
- **Generation Queue Status Visibility**: Improved visibility of generation queue status
  - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
  - **Solution**: Enhanced the queue status indicator UI
  - **Changes Made**:
    - Made queue status indicator more prominent with badges and icons
    - Added list of queued items showing position and title
    - Added notification when items are queued
    - Added automatic queue status fetching after bulk generation
    - Added function to fetch queue status on demand

  - **Result**: Users now clearly see when ideas are queued and their position in the queue

## [1.7.0] - 2024-12-18

### Added
- **Products Management System**: Complete product catalog feature with:
  - Three new database tables: products, product_images, product_links
  - Full CRUD operations for products
  - Multiple images per product with drag-drop ordering
  - Multiple links per product with type categorization
  - WooCommerce product import functionality
  - Media library integration for image management
  - Grid layout with search and pagination
  - Modal-based editing interface
  - Comprehensive error handling and logging

### Changed
- Updated Database Manager to include products tables in creation/deletion
- Added Product_Model with direct wpdb queries for compatibility
- Added Product_Controller with 14 AJAX handlers
- Updated plugin activator to create products tables on activation
- Enhanced admin menu with Products page

### Technical Details
- Resolved multiple implementation issues:
  - Fixed `verify_ajax_request()` method not existing (changed to `verify_ajax_security()`)
  - Fixed modal display issues (changed from jQuery `.show()` to `.addClass('ai-blog-modal-active')`)
  - Fixed Product_Model validation returning array instead of boolean
  - Fixed Database_Manager missing products tables in table map
  - Fixed method signature mismatches by overriding all CRUD methods in Product_Model

### Documentation
- Updated implementationPlan.md with Phase 7: Products Management
- Updated implementationTechnical.md with comprehensive Products Management System section
- Added database schema documentation for all three products tables
- Documented all controller methods and security implementations

## [1.6.8] - 2024-12-18

### Fixed
- **CRITICAL: AJAX Handler Registration Issue**: Fixed AJAX handlers not being registered for WordPress AJAX requests
- **Generation Button Functionality**: Resolved issue where clicking "Generate" button did nothing after confirmation modal
- **Controller Initialization**: Fixed V2 controllers not being properly initialized for AJAX requests

### Technical
- Modified main plugin file to register AJAX handlers outside of admin-only scope
- Ensured controller initialization occurs regardless of `is_admin()` context
- Fixed plugin architecture to support both admin and frontend AJAX functionality

### Root Cause
- AJAX handlers were only registered when `is_admin()` returned `true`
- WordPress AJAX requests to `admin-ajax.php` don't always trigger `is_admin()` to return `true`
- This prevented the V2 controllers from being initialized and their AJAX handlers from being registered

## [1.6.4] - 2025-01-17

### Fixed
- **CRITICAL: Complete Elimination of ALL Automatic Retry Logic**: Completely removed all automatic retry mechanisms that were causing endless generation loops and status update failures
  - **Root Cause**: Multiple automatic retry systems were creating infinite loops when generations failed or timed out
  - **Primary Issue**: Image generation timeouts were NOT failing the generation, allowing stuck processes to remain "active" while new generations started
  - **Secondary Issue**: Queue cleanup logic was automatically removing "stuck" generations and allowing new ones to start
  - **Tertiary Issue**: Multiple automatic queue processing calls were starting new generations when previous ones failed

### Removed Automatic Retry Mechanisms
- **1. Generation Queue Automatic Cleanup**: Removed `get_active_count()` timeout cleanup that was automatically removing "stuck" generations after 30 minutes and allowing new ones to start
- **2. Automatic Queue Processing**: Removed `process_queue()` calls from `mark_failed()`, `mark_complete()`, and `cancel_generation()` methods that were automatically starting new generations
- **3. Scheduler Service Auto-Processing**: Completely disabled `process_approved_ideas_queue()` automatic processing to prevent background automatic generation attempts
- **4. Page Load Cleanup**: Disabled automatic cleanup on Approved Ideas page load that was resetting "stuck" generations to "approved" status
- **5. Image Generation Continuation**: Changed image generation timeout/failure to FAIL the entire generation instead of continuing without images

### Fixed Timeout Handling
- **Image Generation Timeout**: Reduced timeout from 5 minutes to 3 minutes and made ANY image timeout or error fail the ENTIRE generation
  - **Before**: Image timeouts would continue generation without images, leading to stuck processes
  - **After**: Image timeouts immediately fail the generation with proper error status
- **Timeout Error Messages**: Added specific "Failed: Image Timeout" error message for image generation timeouts
- **Multiple Timeout Checks**: Added timeout validation at every stage of image generation process

### System Behavior Changes
- **No Automatic Retries**: Failed generations stay failed until manually retried by user
- **No Background Processing**: All generation must be manually initiated through GUI
- **Stuck Generation Handling**: Stuck generations remain stuck until manually cancelled (no automatic cleanup)
- **Queue Management**: Queue processing is now entirely manual - no automatic progression

### Technical Implementation
- Modified `Generation_Queue::get_active_count()` to remove automatic cleanup logic
- Modified `Generation_Queue::mark_failed()`, `mark_complete()`, and `cancel_generation()` to remove `process_queue()` calls
- Modified `Content_Generator` image generation to fail entire generation on timeout/error instead of continuing
- Disabled `Scheduler_Service::process_approved_ideas_queue()` completely
- Disabled `Approved_Ideas_Controller_V2::cleanup_stuck_generations()` on page load
- Added comprehensive timeout checking throughout image generation process

### User Impact
- ✅ **Status Updates Work**: Generations now properly show "generating" status and update in real-time
- ✅ **No Duplicate Generations**: Only one generation can run per idea at a time
- ✅ **Clear Error States**: Failed generations show specific error reasons (API Overloaded, Image Timeout, etc.)
- ✅ **Manual Control**: Users have complete control over when generations start and retry
- ⚠️ **Manual Intervention Required**: Stuck or failed generations require manual user action to retry or cancel

## [1.6.3] - 2025-01-17

### Fixed
- **CRITICAL: "Idea is already generating" Error on Failed Generations**: Fixed issue where failed generations would show as "Ready" in GUI but throw "Idea is already generating" error when user tried to retry
  - **Root Cause**: Mismatch between frontend display logic and backend generation queue status tracking
  - **Problem**: When generations failed, the active generations transient cache wasn't properly cleaned up, causing queue to think idea was still generating while frontend showed it as "Ready"
  - **Solution**: Enhanced `is_idea_generating()` method to validate both transient cache AND database status for consistency, with automatic cleanup of stale entries
  - **User Impact**: Failed generations now properly allow retry attempts without showing "already generating" error

- **Status Display and Retry Logic Improvements**:
  - **Failed Status Display**: Failed generations now show as "Failed: [Reason] (Retryable)" instead of just "Failed: [Reason]"
  - **Retry Functionality**: Failed ideas now show "Retry" button with refresh icon instead of regular "Generate" button
  - **Consistent Actions**: Both "approved" and "failed" status ideas now have identical action button sets (Generate/Retry, Deny, View Details)
  - **Visual Styling**: Added new `status-retry` badge class with orange background and red border to distinguish retryable failed states
  - **User Experience**: Clear visual indication that failed generations can be retried by the user

- **Generation Queue Status Validation**:
  - **Database Consistency**: Queue now validates transient cache against database status before making decisions
  - **Automatic Cleanup**: Stale generation entries automatically removed when status mismatch detected
  - **Error Handling**: Better error messages distinguishing between different failure types (API Overloaded vs other errors)
  - **Status Updates**: Fixed mark_failed() method to properly set status to "failed" instead of incorrectly resetting to "approved"

### Technical Details
- Enhanced `Generation_Queue::is_idea_generating()` with dual validation (transient + database)
- Updated `Generation_Queue::mark_failed()` to set proper "failed" status with clean error messages
- Added CSS styles for new `status-retry` badge class and improved progress bar styling
- Updated JavaScript `createActionButtons()` to treat failed status same as approved for retry capability
- Added logging for generation status mismatches and cleanup operations

## [1.6.5] - 2025-01-17

### Fixed
- **CRITICAL: Status Updates Not Displaying in GUI**: Fixed the root cause of status updates not appearing in the frontend during blog generation
  - **Root Cause**: Field name mismatch between backend response and frontend expectations
  - **Problem**: Backend `ajax_submit_for_generation` was returning `updated_ideas` field, but JavaScript `handleGenerationResponse` was looking for `ideas` field
  - **Effect**: When generations were submitted, the frontend cached data was never updated with the "generating" status, causing `getGeneratingIdeaIds()` to return an empty array
  - **Result**: Status polling system would find no generating ideas and show "✅ No generating ideas to refresh" instead of fetching actual status updates
  - **Solution**: Changed backend response to use `ideas` field name (consistent with status update endpoint)
  - **User Impact**: Real-time status updates now work correctly, showing progress bars and status changes during generation

### Technical Details
- **Files Modified**: 
  - `controllers/class-approved-ideas-controller-v2.php`: Changed response field from `updated_ideas` to `ideas`
  - JavaScript polling system now correctly identifies generating ideas and fetches status updates
- **Status Flow**: Generation submission → Status cached → Progress bars displayed → Status polling active → Real-time updates shown

## [1.6.6] - 2025-01-17

### Major Improvement: Sequential Image Generation with Real-Time Progress Tracking
- **BREAKING CHANGE**: Replaced batch image generation with sequential individual image generation to eliminate timeouts and failures
  - **Old System**: Generated all images in a single batch call, prone to timeouts and complete failures
  - **New System**: Generates images one by one with detailed progress tracking and status updates
  - **User Experience**: Users now see real-time updates like "Generating image 1 of 3: [prompt preview]...", "Saving image 2 of 3 to media library...", etc.
  - **Reliability**: If one image fails, others continue to generate successfully instead of the entire batch failing
  - **Timeout Management**: Each image has individual timeout protection (3 minutes per image, 9 minutes total maximum)

### Technical Implementation
- **New Method**: `generate_images_sequentially()` in OpenAI Service with progress callback support
- **Progress Tracking**: Real-time status updates showing current image number, progress percentage, and operation stage
- **Detailed Logging**: Enhanced logging with image-specific information for debugging
- **Error Handling**: Improved error handling with specific failure reasons (generation, save, exception)
- **Rate Limiting**: 2-second delay between image requests to prevent API rate limiting
- **Backward Compatibility**: Old `generate_batch_images()` method maintained as deprecated alias

### Status Update Flow
1. **"Generating image 1 of 3: [prompt preview]..."** - Shows which image is being generated
2. **"Saving image 1 of 3 to media library..."** - Shows save progress
3. **"Image 1 of 3 completed successfully!"** - Confirms completion
4. **Process repeats for each image individually**
5. **"Image generation completed: 2 successful, 1 failed"** - Final summary

### Error Recovery
- **Individual Failures**: If one image fails, remaining images continue to generate
- **Detailed Error Messages**: Specific error messages for generation vs. save failures
- **Timeout Protection**: Stops early if overall generation time exceeds 9 minutes
- **No Automatic Retries**: Failed images require manual retry as per system design

## [CRITICAL] - 2025-06-19

### 🚨 CRITICAL FIXES: Transaction Timeout & Hanging Generation Issues

**CRITICAL BUG FIXES - Resolves Row Locking & Process Hanging**

#### Issues Resolved:
- **Row Locking**: Generations hanging during image generation left database transactions open, locking rows and preventing manual updates
- **Process Timeouts**: Long-running generations (especially image generation) hanging indefinitely with no cleanup
- **Database Deadlocks**: Unclosed transactions causing database operations to hang
- **No Recovery**: Failed generations leaving system in bad state with no automatic recovery

#### Database Manager Enhancements:
- **Transaction Timeout Protection**: 15-minute automatic timeout for all database transactions
- **Emergency Cleanup**: Automatic rollback when processes terminate unexpectedly
- **Stale Transaction Detection**: Cleanup of long-running MySQL processes on startup
- **Comprehensive Logging**: Detailed transaction timing and error logging to `debug-transaction.log`

#### Content Generator Fixes:
- **Timeout Monitoring**: Added transaction timeout checks before critical operations
- **Image Generation Limits**: 5-minute timeout for image generation to prevent hanging
- **Enhanced Error Handling**: Better cleanup when API calls timeout or fail
- **Process Recovery**: Automatic status reset when generation fails

#### Background Processor Improvements:
- **Stuck Generation Cleanup**: Automatic detection and cleanup of stuck generations
- **Fatal Error Recovery**: Emergency cleanup when PHP processes crash
- **Lock Management**: Automatic clearing of expired generation locks
- **Status Monitoring**: Real-time monitoring of generation timeouts

#### New Recovery Mechanisms:
- **Automatic Cleanup**: Daily cleanup of stuck generations older than 15 minutes
- **Lock Expiration**: Automatic clearing of expired generation locks
- **Status Recovery**: Automatic reset of failed generations back to 'approved'
- **Emergency Handlers**: Shutdown functions to handle unexpected termination

#### Enhanced Logging & Monitoring:
- **Transaction Tracking**: Complete SQL query logging for debugging
- **Timeout Detection**: Real-time monitoring of process duration
- **Error Classification**: Better categorization of timeout vs generation errors
- **Recovery Logging**: Detailed logging of cleanup and recovery operations
- **🔧 CRITICAL FIX: Binary Data in Debug Logs**: Fixed binary data corruption in debug-transaction.log
  - Sanitized all SQL queries, exception traces, and database data before logging
  - Replaced non-printable characters with safe `?` placeholders
  - Fixed corrupted log files that were unreadable due to binary content
  - Applied fixes to Content Generator, Background Processor, and Blog Ideas Model V2
- **🔧 CRITICAL FIX: WordPress Cron Fatal Error**: Fixed fatal error in WordPress cron system
  - Fixed method name mismatch: `process_approved_ideas` → `process_approved_ideas_queue`
  - Cleared existing problematic cron jobs scheduled with wrong method name
  - Updated main plugin file cron registration to use correct method names
  - Scheduler service now initializes without fatal errors

- **🔧 CRITICAL FIX: SSL Certificate Issue in Local Development**: Fixed "cURL error 60: SSL certificate problem: unable to get local issuer certificate"
  - **Root Cause**: Anthropic API requests failing in local Windows development environments due to SSL certificate validation
  - **Solution**: Enhanced SSL bypass detection to work with CLI execution and local development
  - **Files Modified**: 
    - `ai-blog-generator.php`: Improved local environment detection for SSL bypass
    - `services/class-anthropic-service.php`: Added comprehensive SSL bypass for CLI and local environments
  - **Testing Results**: 
    - ✅ API connectivity test successful
    - ✅ Full blog generation test successful (155.24 seconds)
    - ✅ Post created successfully (ID: 371)
    - ✅ Cost tracking working ($0.125928)
  - **Result**: Blog generation now works reliably in local development environments

### Technical Changes:

#### `models/class-database-manager.php`:
- Added transaction timeout protection (15 minutes)
- Added emergency cleanup for unexpected termination
- Added stale transaction detection and cleanup
- Enhanced commit/rollback with timeout checks
- Added comprehensive transaction logging

#### `services/class-content-generator.php`:
- Added transaction timeout checks before critical operations
- Enhanced image generation with 5-minute timeout limit
- Improved error handling with detailed timeout logging
- Added automatic cleanup on generation failure
- Enhanced status update with error details

#### `services/class-background-processor.php`:
- Added stuck generation detection and cleanup
- Enhanced process timeout protection (15 minutes)
- Added fatal error recovery with emergency cleanup
- Improved generation monitoring and logging
- Added automatic transient cleanup

#### Impact:
- **✅ Row Locking Resolved**: No more locked rows preventing manual updates
- **✅ Hanging Prevention**: Automatic timeout and cleanup of stuck processes
- **✅ Recovery Automation**: Failed generations automatically reset for retry
- **✅ Better Monitoring**: Comprehensive logging for debugging issues
- **✅ System Stability**: Robust error handling prevents system deadlocks

**This resolves the critical issues causing only 1 successful generation out of 6 attempts.**

---

## [Current] - 2025-06-19

### Fixed - Status Update Issue Resolution
- **CRITICAL FIX**: Fixed the issue where idea status was not being properly updated to 'generating' when submitting ideas for generation
- **ADDITIONAL FIX**: Fixed the issue where `generation_status` field remained stuck on "Starting..." during the generation process
- **Root Causes**: 
  1. Services were using the legacy `Idea_Model` class instead of the new `Blog_Ideas_Model_V2` class, causing database update incompatibilities
  2. Background Processor's `update_generation_status` method was only updating transients, not the database `generation_status` field
  3. Content Generator's `update_generation_status` method was using direct database operations instead of the model
- **Changes Made**:
  - Updated `Content_Generator` service to use `Blog_Ideas_Model_V2` instead of `Idea_Model`
  - Updated `Background_Processor` service to use `Blog_Ideas_Model_V2` instead of `Idea_Model`
  - Fixed `Content_Generator.update_generation_status()` to use `Blog_Ideas_Model_V2.update_idea()` instead of direct `wpdb->update()`
  - Fixed `Background_Processor.update_generation_status()` to update both transient AND database `generation_status` field
  - Added compatibility methods to `Blog_Ideas_Model_V2`: `get()`, `update()`, `create()`, and `is_duplicate_title()` for seamless integration
- **Impact**: Ideas will now properly transition through all status updates during generation:
  - **Main Status**: 'approved' → 'generating' → 'generated' ✅
  - **Generation Status**: 'Starting...' → 'Compiling contexts...' → 'Generating content...' → 'Creating post...' → 'Complete!' ✅
- **Testing**: Verified that both status fields now update correctly throughout the entire generation process

### Technical Details
- The issue manifested as:
  1. Blank (empty string) main status fields during generation (fixed in first part)
  2. `generation_status` field stuck on "Starting..." throughout generation (fixed in second part)
- This caused the auto-refresh system to show duplicate entries and prevented proper real-time progress tracking
- All core generation services now use the unified `Blog_Ideas_Model_V2` for consistent database interactions
- Real-time status updates now work correctly, showing detailed progress during each generation phase

---

## [1.6.1] - 2025-01-17

### Fixed
- **CRITICAL: Real-time Status Updates Not Working in GUI**: Fixed generation status not updating in the Approved Ideas V2 page during blog generation
  - **Root Cause**: JavaScript `refreshGeneratingIdeas()` function was designed to only update UI from cached data without making any AJAX calls to check for status updates from the server
  - **Problem**: Status updates were never fetched from the backend, causing progress bars to remain static and users never seeing completion or progress updates  
  - **Solution**: Complete rewrite of status update system:
    - Modified `refreshGeneratingIdeas()` to properly call `ai_blog_v2_get_idea_status_updates` AJAX endpoint
    - Added new `handleStatusUpdateResponse()` function to process status updates and update UI
    - Implemented real-time notifications when generation completes or fails
    - Added automatic fast refresh mode stopping when all generations complete
    - Enhanced error handling for status update failures
  - **Technical Details**:
    - Now calls `ai_blog_v2_get_idea_status_updates` every 5 seconds for generating ideas
    - Updates cached data and refreshes individual table rows with new status
    - Shows success notifications when blog posts are generated
    - Shows error notifications when generation fails with retry options
    - Automatically stops intensive polling when no active generations remain
  - **Result**: Users now see real-time progress updates including status changes from "Ready" → "Generating" → "Complete", with live progress bars and completion notifications

### Technical Improvements
- Enhanced AJAX status polling to only refresh generating ideas for optimal performance
- Added comprehensive error handling for network failures during status checks
- Improved caching system to maintain consistency between server data and UI state
- Added automatic cleanup of UI refresh timers when generations complete

### Files Modified
- `admin/assets/js/approved-ideas-v2.js` - Complete rewrite of status update system with real AJAX calls
- `changelog.md` - Documentation of critical status update fix

## [1.6.0] - 2025-06-17

### Added
- **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
  - Queue management service supporting up to 5 simultaneous generations
  - FIFO queue for excess generation requests
  - Real-time queue status display with active generations and positions
  - Automatic processing when slots become available
  - Individual and bulk generation support
  
- **Live Generation Status Updates**: Real-time progress tracking without page refresh
  - Database `generation_status` field for persistent status storage
  - Immediate UI updates when generation starts
  - Progress bar with detailed status messages
  - Fast refresh mode (2s) during active generations
  
- **Live Log Viewer**: Tail generation logs in real-time
  - Incremental log loading (only new lines)
  - Color-coded log entries (error, warning, info, success)
  - Auto-scroll to bottom for new content
  - Automatic stop when generation completes
  - Shows generation completion status
  
- **Error Recovery Features**: Comprehensive error handling and recovery
  - One-click retry for failed generations
  - Error messages stored in database
  - Automatic cleanup of stuck generations (10+ minutes)
  - Clear error feedback in UI
  
- **Database Schema Updates**: New fields for generation tracking
  - `generation_status` - Current generation progress message
  - `generation_error` - Error message storage
  - `generation_started_at` - Generation start timestamp
  - `generation_completed_at` - Generation completion timestamp
  - Index on `generation_status` for performance

- **New Services**: Generation queue management
  - `services/class-generation-queue.php` - Complete queue management system
  - Supports concurrent processing with queue overflow
  - Automatic retry and error recovery

### Changed
- **Approved Ideas Controller V2**: Major enhancements
  - Integrated Generation_Queue service for all generation operations
  - Enhanced AJAX handlers for bulk operations
  - Added retry and queue status endpoints
  - Live log tailing support

- **JavaScript Architecture**: Complete rewrite for real-time updates
  - Fast refresh mode during active generations
  - Queue status display component
  - Enhanced log viewer with tail support
  - Immediate UI feedback on all actions

### Fixed
- **Live Status Updates Not Working**: Fixed generation status not updating in real-time on Approved Ideas V2 page
  - Root cause: JavaScript was not properly polling for status updates and database `generation_status` field wasn't being updated
  - Solution: Enhanced `update_generation_status()` to update both transients and database field
  - Improved JavaScript polling to fetch full idea data every 5 seconds for generating ideas
  - Added immediate UI updates when generation starts
  - Status now shows real-time progress: Compiling Context → Generating Content → Creating Images → Publishing Post

- **Generation Log Viewer Not Working**: Fixed log viewer not showing generation logs when clicking View button
  - Root cause: JavaScript wasn't properly handling different states for the View button
  - Solution: View button now shows edit modal for approved ideas and log viewer for generating ideas
  - Added formatted log viewer with syntax highlighting and color coding
  - Log viewer auto-refreshes every 3 seconds during generation
  - Added dark theme console-style display for better readability

- **UI Improvements**: Enhanced Approved Ideas V2 page user experience
  - Added animated progress bars for generating status with percentage indicators
  - Added visual feedback when rows update (blue highlight)
  - Added success notification when generation completes
  - Enhanced CSS styling for log viewer with color-coded log levels
  - Fixed responsive layout issues on mobile devices

### Technical Improvements
- Updated `Content_Generator::update_generation_status()` to persist status in database
- Enhanced `refreshGeneratingIdeas()` JavaScript function to properly update UI
- Added comprehensive CSS styling for log viewer and progress bars
- Improved error handling and logging throughout generation process

### Files Modified
- `services/class-content-generator.php`: Fixed generation status database updates
- `admin/assets/js/approved-ideas-v2.js`: Enhanced status polling and log viewer functionality
- `admin/assets/css/admin.css`: Added log viewer and progress bar styling
- `changelog.md`: Documentation of all changes

## [1.5.16] - 2025-01-17

### Fixed
- **CRITICAL: Race Condition in Generation Status Validation**: Fixed generation failing with "Idea is not approved for generation" error after immediate UI update
  - Root cause: AJAX handler updated status to 'generating' before calling Blog_Generator_Controller_V2, which only accepted 'approved' status
  - Solution: Updated Blog_Generator_Controller_V2 to accept both 'approved' and 'generating' statuses, preventing race condition
  - Automatically reset stuck ideas (47, 48) that were trapped in 'generating' status at "Compiling Context" stage
  - Verified fix with comprehensive test script covering full generation flow
- **CRITICAL: Fatal Error in Scheduler Service**: Fixed "Call to undefined method get_approved_ideas()" causing WordPress cron crashes
  - Replaced non-existent `get_approved_ideas()` method with correct `get_by_status('approved')`
  - Added array/object compatibility handling for idea data access
  - Added error protection around scheduler initialization to prevent plugin-wide failures
- **Image Generation Hanging**: Fixed 10+ minute hangs during image generation that prevented blog post completion
  - Added timeout protection (10 minutes max) for image generation process
  - Added individual request timeout protection (8 minutes per batch)
  - Added execution time monitoring and early stopping for long-running image batches
  - Added comprehensive error handling and fallback to continue without images
- **Immediate UI Status Updates**: Fixed generation submission immediately reverting to "Ready" status
  - Added pre-emptive status update to 'generating' before starting generation process
  - Enhanced AJAX response with updated idea data for immediate UI feedback
  - Added status reversion if generation startup fails
  - Improved error handling and user feedback

### Technical Improvements
- Added timeout protection for all image generation operations
- Enhanced scheduler service with proper method calls and error handling
- Improved AJAX response flow for better user experience
- Added comprehensive logging for image generation timing and failures
- Added fail-safe error handling for WordPress cron system
- Protected main plugin functionality from scheduler service errors

### Enhanced
- **Comprehensive OpenAI Service Logging**: Added detailed logging throughout image generation process
  - Enhanced batch image generation with step-by-step progress tracking
  - Added memory usage monitoring at each stage
  - Added detailed timing for individual image generation calls
  - Enhanced API request/response logging with full request details
  - Added logging for wp_remote_request calls to identify hanging points
  - Logs now show exactly where image generation hangs or fails

### Files Modified
- `services/class-scheduler-service.php`: Fixed undefined method and added array/object compatibility
- `services/class-content-generator.php`: Added timeout protection for image generation
- `services/class-openai-service.php`: Added batch timeout protection, timing monitoring, and comprehensive debug logging
- `controllers/class-approved-ideas-controller-v2.php`: Enhanced status update flow and error handling
- `ai-blog-generator.php`: Added error protection for scheduler initialization
- `changelog.md`: Documentation of all changes

## [1.5.15] - 2025-01-17

### Fixed
- **Database Field Validation**: Fixed missing `generation_status` field in Database_Manager allowed fields list, resolving SQL syntax errors during status updates
- **Immediate UI Updates**: Fixed generation submission to immediately update UI status without requiring page refresh
  - Backend now returns updated idea data in AJAX response
  - Frontend uses fresh backend data to update cached idea information and table row instantly
  - Added fallback mechanism for immediate UI updates
- **Real-time Status Tracking**: Ensured generation_status field updates throughout entire generation process, not just at beginning

### Technical Improvements
- Added `generation_status` to Database_Manager allowed fields for ideas table
- Enhanced AJAX response in `ajax_submit_for_generation()` to include updated idea data
- Improved frontend JavaScript to handle immediate UI updates using backend data
- Added comprehensive logging for immediate UI update process

### Files Modified
- `models/class-database-manager.php`: Added generation_status to allowed fields
- `controllers/class-approved-ideas-controller-v2.php`: Enhanced AJAX response with updated idea data
- `admin/assets/js/approved-ideas-v2.js`: Improved immediate UI update handling
- `changelog.md`: Documentation of all changes

## [1.5.14] - 2025-06-17

### Fixed
- **Anthropic API Timeout Increased**: Extended timeout from 5 minutes to 10 minutes for complex blog generation requests
  - Fixed timeout errors that occurred during long content generation processes
  - Prevents `cURL error 28: Operation timed out after 300007 milliseconds` errors
  - Better support for complex blog posts that require longer processing time
  - Modified `services/class-anthropic-service.php` timeout from 300 to 600 seconds

- **Generation Status GUI Updates**: Fixed generation status not updating in GUI during blog generation
  - Modified `update_generation_status()` method to update both WordPress transients AND database `generation_status` field using direct database queries
  - Fixed SQL validation issues that were preventing `generation_status` field updates
  - Added comprehensive status updates at ALL generation stages: initialization, context compilation, AI generation, content validation, image generation, post creation, and completion
  - GUI now properly shows detailed progress updates throughout the entire generation process
  - Fixed issue where ideas remained showing "Ready" status while generation was in progress

- **Generation Logging System**: Replaced debug-transaction.log with individual per-generation log files
  - Integrated Generation_Logger class for detailed per-generation logging
  - Log files now created in `wp-content/uploads/ai-blog-generator-logs/generations/` directory
  - Each generation gets its own timestamped log file for better debugging
  - Replaced 100+ file_put_contents() calls with structured Generation_Logger calls
  - View button in GUI now shows the specific generation log file instead of generic debug log
- **Binary Data Corruption in Debug Logs**: Complete resolution of debug log corruption issues
  - Replaced all `print_r()` and `var_export()` calls with safe string representations throughout codebase
  - Implemented `make_data_safe_for_logging()` method in all model classes (Idea_Model, Blog_Model, Context_Model)
  - Fixed encoding issues that caused Chinese/Japanese characters to appear in logs
  - All debug logs now properly formatted and human-readable
  - Enhanced debugging capability for troubleshooting generation issues

- **SSL Certificate Error for Local Development**: Fixed "cURL error 60: SSL certificate problem" on Windows
  - Enhanced SSL verification bypass to automatically detect local environments
  - Detection includes: localhost, .local domains, 127.0.0.1, and Windows local paths (D:\, C:\, etc.)
  - No longer requires WP_DEBUG to be enabled - works automatically in local environments
  - Also checks for AI_BLOG_GENERATOR_DEBUG constant as fallback
  - Resolves SSL certificate errors that prevented API calls to Anthropic
  - Allows blog generation to work properly on all local development environments

### Technical Details
- Modified all file logging operations to use JSON encoding with UTF-8 support
- Added safe data representation methods that handle objects, arrays, and binary data
- Implemented conditional SSL verification in `make_request()` method of Anthropic_Service
- SSL bypass only activates on local hosts with debug mode enabled

### Impact
- Debug logs remain clean and readable throughout generation process
- Local development environments can now generate blog posts without SSL errors
- Improved debugging capability for identifying generation issues
- Better development experience on Windows systems

#### Files Modified
- `models/class-idea-model.php` - Added safe logging methods
- `models/class-blog-model.php` - Added safe logging methods
- `models/class-context-model.php` - Added safe logging methods
- `models/class-database-manager.php` - Replaced var_export with safe representations
- `services/class-content-generator.php` - Fixed remaining print_r calls
- `services/class-anthropic-service.php` - Added SSL verification bypass for local development

### Fixed - 2025-06-17 - Complete Generation System Fix

#### **🔥 CRITICAL: Fixed race condition causing generation to fail and reset to "Ready" status** ✅ FIXED
- **Root Cause**: Background Processor checked for `status = 'approved'` AFTER Content Generator changed it to `'generating'`
- **Problem Flow**: 
  1. User clicks "Generate" → Content Generator changes status: `approved` → `generating`
  2. Background Processor checks: "Is status = 'approved'?" → **NO, it's 'generating'!**
  3. Process fails with: `"Idea is not approved for generation: generating"`
  4. System resets idea back to `approved` status → GUI shows "Ready"
- **Solution**: Modified status validation to accept both `'approved'` and `'generating'` statuses
- **Technical**: Modified `services/class-background-processor.php` line 202:
  ```php
  // OLD: if ( $idea['status'] !== 'approved' )
  // NEW: if ( ! in_array( $idea['status'], [ 'approved', 'generating' ], true ) )
  ```
- **Result**: Generation now proceeds properly without status conflicts

#### **🔥 CRITICAL: Fixed binary data corruption in debug-transaction.log** ✅ FIXED
- **Root Cause**: Multiple files using `print_r()` on database objects containing binary fields
- **Problem**: Debug log filled with unreadable binary data, making troubleshooting impossible
- **Solution**: Replaced all `print_r()` calls with safe string representations in:
  - `services/class-content-generator.php` (5 instances)
  - `models/class-database-manager.php` (3 instances)
- **Technical Fixes**:
  - Replaced `print_r( $idea, true )` with safe ID/title/status format
  - Replaced `print_r( $usage, true )` with token count summary
  - Replaced `print_r( array_keys(), true )` with `implode(', ', array_keys())`
  - Replaced database result dumps with safe array key listing
- **Result**: Debug log now readable and useful for troubleshooting

## [Version 1.0.29] - 2025-06-24
### Fixed
- **Critical Image Generation Hanging Fix**: Implemented multiple safeguards to prevent hanging on third image
  - Added hard time limit check BEFORE each image API call - skips images if running too long
  - Limited maximum images per post to 2 by default (configurable via `ai_blog_generator_max_images_per_post` option)
  - Added per-image time budget (2m 20s) to ensure generation continues even if one image is slow
  - Images are now skipped rather than hanging the entire generation process
  - Posts will publish successfully even if later images are skipped due to time limits

## [Version 1.0.28] - 2025-06-20
### Fixed
- **CRITICAL FIX**: Image generation timeouts no longer fail entire post generation
  - Changed exception handling to continue with post creation even if all images fail
  - Posts now publish successfully even when image generation times out
  - Prevents posts from disappearing due to image generation issues
  - Individual image timeout set to 2m 30s (150 seconds) as specified
  - Overall timeout for all images set to 8 minutes (allowing for 3 images)
  - Added specific timeout detection and logging
  - Progress messages now clearly indicate when images timeout vs other failures
  - PHP execution time limits properly managed per image with restoration
- **Fixed Missing Logging Output**: Generation logs were not being written to files
  - Fixed missing file write operation in Generation_Logger class
  - Logs are now properly written to `wp-content/uploads/ai-blog-generator-logs/generations/`
  - Each generation creates a timestamped log file for debugging
- **Fixed Undefined Variable Error**: Fixed fatal error when `$images` variable was undefined
  - Initialized `$images` array before image generation block to prevent undefined variable errors
- **Enhanced Image Generation Timeout Handling**: Fixed hanging requests during image generation
  - Added WordPress HTTP API filters to enforce timeouts at multiple levels
  - Added connection timeout of 30 seconds to prevent hanging on connection
  - Added CURL-specific timeout options for better timeout enforcement
  - Added response size limit of 50MB to prevent memory issues
  - Added shutdown handler to detect and log timeout scenarios
  - Added periodic timeout checks during image generation
  - Enhanced logging to track exactly where requests might hang
  - Fixed fatal error with stream_context_set_default() by removing unnecessary stream context manipulation

### Fixed - 2025-06-16 - Critical Generation Imports

#### **CRITICAL FIX: Missing Service Class Imports** ✅ FIXED
- **Root Cause**: Content_Generator class was missing critical import statements for Anthropic_Service and OpenAI_Service
- **Symptom**: Generation would start but immediately fail and revert to "Ready" status
- **Technical Issue**: 
  - Content_Generator constructor tried to instantiate `new Anthropic_Service()` and `new OpenAI_Service()`
  - Without proper `use` statements, PHP threw "Class not found" fatal errors
  - Background_Processor caught these exceptions and reset idea status back to "approved"
- **Solution**: Added missing imports to Content_Generator:
  - `use AI_Blog_Generator\Services\Anthropic_Service;`
  - `use AI_Blog_Generator\Services\OpenAI_Service;` 
- **Result**: Generation now proceeds past initialization without fatal errors

#### **Files Modified**
- `services/class-content-generator.php` - Added missing Anthropic_Service and OpenAI_Service imports

### Fixed - 2025-01-13 - Complete Generation System Restoration

#### **Fatal Error: Missing Methods and Imports** ✅ FIXED
- **Root Cause**: Multiple critical issues in the generation system
- **Fatal Error 1**: `Blog_Generator_Controller_V2` calling non-existent `push_to_queue()` method
- **Fatal Error 2**: `Background_Processor` missing imports for `Content_Generator` and `Budget_Manager`
- **Fatal Error 3**: Wrong method name `clear_generation_lock()` vs `remove_generation_lock()`
- **Solution**: 
  - Replaced queue system with direct `start_generation()` call
  - Added missing imports to Background_Processor
  - Fixed method name inconsistencies
- **Result**: Generation system now works without fatal errors

#### **Generation Process Completely Disabled** ✅ FIXED
- **Root Cause**: `ajax_submit_for_generation()` method was only updating database status but not starting actual generation
- **Critical Issue**: Comment said "Temporarily disable generation - just update status" but actual generation was never re-enabled
- **Result**: Ideas marked as "generating" but no actual generation happening (no Generation_Logger, no log files, stuck forever)
- **Solution**: Re-enabled actual generation by calling `Blog_Generator_Controller_V2->start_generation()`
- **Impact**: Now when users click Generate, actual generation process starts with log files created

#### **Cancel All Generations Enhanced** ✅ FIXED
- **Issue**: Cancel functionality didn't clean up background processes or transients
- **Solution**: Enhanced cancel methods to:
  - Reset idea status from "generating" back to "approved"
  - Clear generation_status field
  - Clean up generation locks and transients
  - Properly stop background processes
- **Methods Enhanced**: `ajax_cancel_generation()`, `ajax_cancel_all_generations()`, `ajax_reset_stuck_generations()`
- **Result**: Manage Generations button now properly cancels and resets stuck ideas

#### **Immediate Stuck Generations Cleanup** ✅ FIXED
- **Problem**: 5 ideas stuck in "generating" status since June 2025 (ideas 54, 55, 58, 59, 60)
- **Solution**: Created and ran cleanup script to:
  - Reset 4 stuck ideas (55, 58, 59, 60) back to "approved" status
  - Clear generation_status fields
  - Clean up any stuck transients/locks
- **Result**: 4 ideas now available for generation, idea 54 successfully started generating with "Compiling Context" status
- **System Status**: ✅ FULLY WORKING - idea 54 is actively generating, others ready for new generations

### Fixed - 2025-01-13 - GUI Status Updates & Generation Logging

#### **GUI Not Updating After Generation Starts** ✅ FIXED
- **Root Cause**: After successful generation start, the frontend row wasn't immediately updating from "Ready" to "Generating" 
- **Technical Issue**: `getGeneratingIdeaIds()` only looked for ideas that already had progress bars, but newly-generating ideas hadn't been updated yet
- **Solution**: 
  - Immediate row update: `handleGenerationResponse()` now immediately updates the cached idea data and calls `updateIdeaRow()` 
  - Enhanced `getGeneratingIdeaIds()`: Now checks both cached data for 'generating' status AND DOM progress bars
  - Improved targeted refresh: Increased delay to 2 seconds and made more reliable
- **Result**: When user clicks Generate, the row immediately changes from "Ready" to "Generating" status bar, then auto-refresh maintains updates

#### **Generation Logs Missing** ✅ FIXED
- **Root Cause**: Generation Logger was completely commented out in Blog_Generator_Controller_V2 class
- **Issue**: Log files weren't being created during generation, so log viewer showed "temporarily disabled" message
- **Solution**: Re-enabled Generation_Logger throughout the generation process:
  - Uncommented `use AI_Blog_Generator\Utilities\Generation_Logger;` import
  - Uncommented `private $generation_logger;` property
  - Re-enabled logger initialization in `start_generation()` and `process_generation()` methods
  - Re-enabled key logging calls for context compilation phase and exception handling
- **Log Location**: `/wp-content/uploads/ai-blog-generator-logs/generations/`
- **Log Files**: Named `idea_{IDEA_ID}_{TIMESTAMP}.log` with comprehensive debugging info
- **Result**: Generation logs are now created and viewable in real-time through the log viewer modal

#### **Generation Log Directory Creation** ✅ FIXED
- **Root Cause**: Generation_Logger had path inconsistencies and missing directory creation
- **Issues**:
  - Mixed `/blog-generator-logs/` and `/ai-blog-generator-logs/` paths in different methods
  - Static method didn't ensure directory exists before log file access
  - No error handling for directory/file creation failures
- **Solution**: 
  - Unified path to `/ai-blog-generator-logs/generations/` everywhere
  - Added directory creation and validation in both instance and static methods
  - Enhanced error handling with WordPress error logging
  - Added write permission validation
- **Result**: Log directory now creates automatically and reliably across all access methods

#### **Files Modified**
- `controllers/class-approved-ideas-controller-v2.php` - **CRITICAL**: Re-enabled actual generation process, enhanced cancel functionality
- `controllers/class-blog-generator-controller-v2.php` - **CRITICAL**: Fixed fatal errors, replaced queue with direct execution, re-enabled Generation_Logger
- `services/class-background-processor.php` - **CRITICAL**: Added missing imports for Content_Generator and Budget_Manager, fixed method names
- `admin/assets/js/approved-ideas-v2.js` - Enhanced generation response handling and row updates  
- `utilities/class-generation-logger.php` - Fixed directory creation and error handling

### Added - 2025-01-13 - Comprehensive Generation Debugging & Management System

#### **Complete Generation Logging & Debugging** ✅ ADDED
- **New Generation Logger Class**: Individual log files for each idea generation with comprehensive debugging
  - Detailed logging at every step including memory usage, execution time, stack traces
  - Phase-based progress tracking with percentage completion
  - API request/response logging with timing and payload details
  - SQL query logging with execution times
  - Exception logging with full stack traces
  - Automatic log file management and cleanup
- **Integration**: Blog Generator Controller V2 now uses Generation Logger throughout entire process
- **Log Location**: WordPress uploads directory `/ai-blog-generator-logs/generations/`
- **Benefits**: Pinpoint exactly where "Compiling Context" gets stuck with detailed debugging info

#### **Dynamic View Button Functionality** ✅ ADDED
- **Context-Sensitive Modals**: View button behavior changes based on idea status
  - **Ready Ideas**: Opens edit modal to modify title, description, and persona
  - **Generating Ideas**: Opens live log viewer showing real-time generation progress
- **Edit Modal Features**:
  - Live form validation and persona selection
  - Prevents editing of ideas currently being generated
  - Real-time updates after successful edit
- **Log Viewer Features**:
  - Real-time log content with auto-scroll
  - Log file size and last modified timestamp
  - Auto-refresh every 3 seconds during generation
  - Dark theme console-style display
  - Individual generation cancellation from log viewer

#### **Generation Management System** ✅ ADDED
- **Bulk Operations**:
  - Cancel all active generations simultaneously
  - Reset stuck generations (10+ minutes without update)
  - Generation Management modal accessible from main toolbar
- **Individual Operations**:
  - Cancel specific generation from log viewer
  - Real-time status updates without full page refresh
  - Seamless transitions between generation states
- **Backend AJAX Endpoints**:
  - `ai_blog_v2_edit_idea` - Edit idea details
  - `ai_blog_v2_get_generation_log` - Get live log content
  - `ai_blog_v2_cancel_generation` - Cancel individual generation
  - `ai_blog_v2_cancel_all_generations` - Cancel all active generations
  - `ai_blog_v2_reset_stuck_generations` - Reset stuck generations

#### **Enhanced Blog Ideas Model V2** ✅ UPDATED
- **New Methods**:
  - `update_idea()` - Update title, description, persona with validation
  - Enhanced `update_status()` - Supports additional data fields for generation_status
- **Improved Data Handling**: Better type validation and error handling

#### **Files Added**
- `utilities/class-generation-logger.php` - Complete generation logging system

#### **Files Modified**
- `controllers/class-blog-generator-controller-v2.php` - Integrated generation logger with detailed debugging
- `controllers/class-approved-ideas-controller-v2.php` - Added edit, log, cancel, and management endpoints
- `models/class-blog-ideas-model-v2.php` - Added update_idea method and enhanced update_status
- `admin/views/approved-ideas-view-v2.php` - Added edit modal, log viewer modal, and generation management modal
- `admin/assets/js/approved-ideas-v2.js` - Complete JavaScript overhaul with dynamic view functionality

### Fixed - 2025-01-13 - Auto-Refresh Performance & User Experience Issue

#### **Inefficient Full Table Refresh Every 5 Seconds** ✅ FIXED
- **Root Cause**: System was refreshing ALL approved ideas every 5 seconds, causing:
  - Entire screen to go white/flash during refresh
  - Poor user experience with constant table rebuilding
  - Unnecessary server load fetching all ideas repeatedly
  - Interruption of user interaction (scrolling, reading, etc.)
- **Solution**: Implemented targeted refresh system that:
  - Only refreshes ideas currently generating (seamless, no flash)
  - Updates individual rows in-place without rebuilding entire table
  - Tracks generating ideas automatically and only queries those specific IDs
  - Provides subtle visual feedback (blue highlight) when rows update
  - Falls back to full refresh only for manual refresh or structural changes
- **Technical Implementation**:
  - Added `ai_blog_v2_get_idea_status_updates` AJAX endpoint for bulk status updates
  - Created `refreshGeneratingIdeas()` function for targeted updates
  - Added `updateIdeaRow()` function for seamless individual row updates
  - Implemented `getGeneratingIdeaIds()` to identify which ideas need refreshing
  - Enhanced auto-refresh to call targeted refresh instead of full reload
- **User Experience Improvements**:
  - No more screen flashing or white screens during auto-refresh
  - Seamless progress updates for generating ideas
  - Uninterrupted user interaction and reading experience
  - Reduced server load and improved performance
  - Manual refresh button still available for full reload when needed
- **Result**: Auto-refresh now works seamlessly in background without disrupting user experience

#### **Files Modified**
- `controllers/class-approved-ideas-controller-v2.php` - Added targeted status update endpoint
- `admin/assets/js/approved-ideas-v2.js` - Complete auto-refresh system overhaul

### Fixed - 2025-01-13 - Ideas Table "Flash and Disappear" Issue

#### **Table Appearing Then Disappearing** ✅ FIXED
- **Root Cause 1**: Script conflict - Blog Ideas V2 script was being force-enqueued on Approved Ideas V2 page due to:
  - Generic pattern `'ideas-v2'` matched both `ai-blog-generator-ideas-v2` AND `ai-blog-generator-approved-ideas-v2`
  - Missing exclusion logic in `localize_scripts()` method
- **Root Cause 2**: Loading state overlay not being properly cleared after table population
- **Solution**: 
  - Removed problematic generic pattern from `localize_scripts()` method
  - Added explicit exclusion for Approved Ideas V2 page in Blog Ideas V2 detection
  - Enhanced table state management to explicitly show table and hide loading overlay
  - Added comprehensive debugging for visibility state tracking
- **Technical**: 
  - Blog Ideas V2 script no longer loads on Approved Ideas V2 page
  - Loading overlay properly clears when table is populated
  - State management ensures only one display state is active at a time
- **Result**: Ideas table now displays correctly without flashing or disappearing

#### **Console Debug Evidence**
- Before: `📊 Table visibility: table=true, loading=true, empty=false` (table hidden by loading overlay)
- After: `📊 Table visibility: table=true, loading=false, empty=false` (table properly visible)

#### **Files Modified**
- `admin/class-admin-manager.php` - Fixed script conflict and pattern matching
- `admin/assets/js/approved-ideas-v2.js` - Enhanced state management and debugging

### Fixed - 2025-01-13 - Approved Ideas V2 Admin Menu Link

#### **Missing Admin Menu Link** ✅ FIXED
- **Root Cause**: Approved Ideas V2 page components were fully implemented but missing from WordPress admin menu
- **Solution**: Added "Approved Ideas V2" submenu item to AI Blog Generator admin menu
- **Technical**: 
  - Added submenu page registration in `admin/class-admin-manager.php`
  - Added `render_approved_ideas_v2_page()` method to load the view
  - Added script enqueuing logic for Bootstrap, FontAwesome, and approved-ideas-v2.js
  - Added script localization for AJAX functionality
  - Initialized Approved Ideas Controller V2 in main plugin file
- **Result**: "Approved Ideas V2" page now accessible via WordPress admin menu

### Fixed
- Fixed database error "Unknown column 'published_at'" by updating `count_published_today()` method to query WordPress post data instead of non-existent column
- Removed invalid `published_at` field updates from `ajax_publish_post()`, `ajax_bulk_publish_posts()`, and `publish_scheduled_posts()` methods
- The plugin now correctly counts published posts based on WordPress post dates rather than tracking separately
- Fixed seed image upload button on contexts page - modal was using incorrect CSS class `.ai-blog-modal-show` instead of `.ai-blog-modal-active`
- Fixed context modal display issues by removing inline styles and using proper CSS classes
- Fixed Content_Generator trying to call non-existent `get_seed_images()` method on Context_Model - now queries seed images table directly

### Added
- Seed images now have a context field for providing specific instructions on how to use each seed image
- When generating images, the seed image context is automatically appended to the image generation prompt to ensure the AI follows the specific instructions for each seed image
- Log viewer shows file metadata including size, last modified time, and filename
- Enhanced log line formatting with color coding for different log levels (error, warning, success, debug)
- Failed status indicator on Approved Ideas page - shows a red (i) icon with tooltip when a previous generation attempt failed
- Automatic categorization of failure reasons (Timeout, Overloaded, API Error, Cancelled, Memory Limit, Network Error)
- Failed status is automatically cleared when a generation succeeds

### Changed
- Replaced "Link to Context" dropdown with a "Context" textarea in seed image upload modal [[memory:1278586984185384671]]
- Updated seed image display to show context instructions instead of context ID
- Enhanced image generation to include seed image context instructions in the prompt
- Seed image context is now included when building image generation prompts for better AI guidance
- Generation log viewer now displays idea-specific log files from `/wp-content/uploads/ai-blog-generator-logs/generations/` instead of global debug transaction log
- Log viewer shows file metadata including size, last modified time, and filename
- Enhanced log line formatting with color coding for different log levels (error, warning, success, debug)

### Changed
- Renamed "Approved Ideas V2" to just "Approved Ideas" throughout the UI while keeping menu slugs unchanged for compatibility
- Generation log viewer now displays idea-specific log files from `/wp-content/uploads/ai-blog-generator-logs/generations/` instead of global debug transaction log [[memory:8404540275128073465]]
- Enhanced error handling and logging for OpenAI image editing with seed images to help diagnose generation failures
- Added detailed logging to track seed image download, temporary file creation, and API response handling
- Added comprehensive error response handling for different HTTP status codes (400, 401, 413, 429, 500+) in image edit requests

## [Unreleased]

### Fixed
- Progress bar now properly updates during blog post generation instead of getting stuck on the first stage
  - Updated status messages in Content_Generator to match expected progress stages
  - Updated OpenAI service progress callback messages to match frontend expectations
  - Progress stages: Compiling Context → Submitting request → Waiting for Content Response → Compiling image prompts → Submitting Images Request → Waiting for images Response → Saving Images → Publishing Post → Complete!
- Fixed keyword selection to filter out image URLs that were being included as target keywords
  - Added filtering in Prompt_Compiler_Service to exclude URLs starting with http:// or https://
  - Added filtering for lines starting with "Image link:"
  - Added filtering for lines ending with image file extensions (.jpg, .jpeg, .png, .gif, .webp, .svg)
  - Keywords are now properly extracted from keyword contexts without including image URLs

### Added
- Download prompts button in drafted posts page to allow users to download the prompts file created during generation
  - Added 4th icon in actions column for downloading prompts
  - Downloads the {idea_id}_prompts.txt file from the generation logs
  - Filename includes idea ID and sanitized title for easier identification

### Changed
- Removed Cost column from drafted posts page
- Removed Total Cost statistic card from drafted posts page
- Adjusted statistics cards layout to use 3 columns instead of 4

## [Latest] - 2024-XX-XX

### Added
- Drafted posts page complete redesign with Bootstrap 5 styling
- Bulk actions for publishing, scheduling, and deleting posts
- Real-time statistics cards showing draft count, scheduled posts, and posts published today
- Status filters for viewing all posts, drafts only, or scheduled only
- Enhanced bulk scheduling with date ranges and time distribution
- Queue visibility enhancements for generation queue
- Download prompts button on drafted posts page with enhanced error handling
- Visual feedback during prompt file downloads
- Better error messages for download failures

### Fixed
- **Plugin Activation Fatal Error**: Fixed "Trait Loggable not found" error during plugin activation
  - **Root Cause**: Traits were being loaded inside the main plugin class constructor, but Database_Manager needed them during activation before the class was instantiated
  - **Solution**: Moved trait loading to the top level of the main plugin file after the autoloader
  - **Result**: Traits are now available whenever the plugin file is loaded, ensuring they're present during activation

- **Undefined Property Warning**: Fixed "Undefined property: stdClass::$description" in Blog Ideas Controller V2
  - **Root Cause**: Code was trying to access `$persona->description` but personas have a `bio` property, not `description`
  - **Solution**: Changed the property access from `$persona->description` to `$persona->bio`
  - **Locations Fixed**: 
    - controllers/class-blog-ideas-controller-v2.php line 675
    - controllers/class-blog-generator-controller-v2.php line 711
  - **Result**: No more warnings when building persona lists for AI prompts

- **Plugin Deactivation Fatal Error**: Fixed "Call to undefined function wp_cache_delete_group()" during plugin deactivation
  - **Root Cause**: The deactivator was calling `wp_cache_delete_group()` which is not a standard WordPress function but specific to certain object cache plugins
  - **Solution**: Added a function_exists() check before calling wp_cache_delete_group()
  - **Location**: includes/class-plugin-deactivator.php line 95
  - **Result**: Plugin now deactivates cleanly regardless of whether object cache plugins are installed

### Security
- **CRITICAL SECURITY FIX - SSL Verification**: Removed all SSL verification disabling code for production deployment
  - **Removed from main plugin file**: Removed global SSL verification filters that were disabling SSL checks
  - **Removed from Anthropic service**: Removed all is_local_environment checks and SSL disabling code
  - **Removed from OpenAI service**: Removed is_local_environment method and all SSL verification bypass code
  - **Impact**: Plugin now properly verifies SSL certificates for all API calls, ensuring secure communication
  - **Note**: For local development requiring SSL bypass, developers should use proper local SSL certificates or configure their environment separately

### Fixed
- **Database Naming Inconsistencies**: Standardized all database table references to use constants
  - **Added Missing Constants**: Added `AI_BLOG_GENERATOR_TABLE_PRODUCT_SEED_IMAGES` and `AI_BLOG_GENERATOR_TABLE_BRAND_FEATURES` to ai-blog-generator.php
  - **Updated All References**: Replaced direct table name concatenations with constant usage in:
    - models/class-product-model.php (5 occurrences)
    - models/class-database-manager.php (8 occurrences in table creation, drop, and mapping)
    - includes/class-plugin-deactivator.php (5 occurrences)
  - **Preserved Direct References**: Kept direct references in uninstall.php as it runs in isolation without access to constants
  - **Result**: Consistent table naming throughout the plugin, making it easier to maintain and reducing potential errors

- **Additional Error and Inconsistency Fixes**: Fixed remaining issues found during comprehensive plugin review
  - **Brand Feature Model**: Fixed search() method using direct table name concatenation instead of constant
    - Changed `$wpdb->prefix . $this->table_name` to `AI_BLOG_GENERATOR_TABLE_BRAND_FEATURES`
  - **Database Manager Field Names**: Fixed incorrect field names in get_allowed_fields() for products table
    - Changed from 'name', 'description' to correct 'product_name', 'product_description'
    - Added missing 'woocommerce_product_id' field
  - **Debug Logging**: Identified excessive error_log statements in Database Manager
    - Note: These are transaction-related debug logs that may be useful for production debugging
    - They are controlled by the AI_BLOG_GENERATOR_DEBUG constant
  - **Deprecated Method**: generate_batch_images() properly marked as deprecated with warning
    - Maintains backward compatibility while encouraging use of generate_images_sequentially()

### Added
- **Data Deletion on Deactivation Setting**: Added new setting to optionally delete all plugin data when deactivating
  - **Location**: Settings page under new "Data Management" section
  - **Default**: Disabled (data is preserved on deactivation)
  - **Functionality**: When enabled, deactivating the plugin will:
    - Drop all database tables (respecting foreign key constraints)
    - Delete all plugin options and settings
    - Remove all transients and temporary data
    - Delete all log files and directories
  - **Warning**: Prominently displayed warning about permanent data deletion
  - **Implementation**: 
    - Added `delete_data_on_deactivation` setting to settings page
    - Updated Admin_Manager to handle the new setting
    - Modified Plugin_Deactivator to check setting and call `delete_all_plugin_data()` if enabled
    - Added comprehensive data deletion method that mirrors uninstall.php functionality
  - **User Benefit**: Gives users control over whether data is preserved or deleted on deactivation

### Changed
- **Approved Ideas Controller V2**: Major enhancements
  - Integrated Generation_Queue service for all generation operations
  - Enhanced AJAX handlers for bulk operations
  - Added retry and queue status endpoints
  - Live log tailing support

- **JavaScript Architecture**: Complete rewrite for real-time updates
  - Fast refresh mode during active generations
  - Queue status display component
  - Enhanced log viewer with tail support
  - Immediate UI feedback on all actions

- **Live Generation Status Updates**: Real-time progress tracking without page refresh
  - Database `generation_status` field for persistent status storage
  - Immediate UI updates when generation starts
  - Progress bar with detailed status messages
  - Fast refresh mode (2s) during active generations
  
- **Live Log Viewer**: Tail generation logs in real-time
  - Incremental log loading (only new lines)
  - Color-coded log entries (error, warning, info, success)
  - Auto-scroll to bottom for new content
  - Automatic stop when generation completes
  - Shows generation completion status
  
- **Error Recovery Features**: Comprehensive error handling and recovery
  - One-click retry for failed generations
  - Error messages stored in database
  - Automatic cleanup of stuck generations (10+ minutes)
  - Clear error feedback in UI
  
- **Database Schema Updates**: New fields for generation tracking
  - `generation_status` - Current generation progress message
  - `generation_error` - Error message storage
  - `generation_started_at` - Generation start timestamp
  - `generation_completed_at` - Generation completion timestamp
  - Index on `generation_status` for performance

- **New Services**: Generation queue management
  - `services/class-generation-queue.php` - Complete queue management system
  - Supports concurrent processing with queue overflow
  - Automatic retry and error recovery

- **Brand Features Management System**: Complete internal linking management with:
  - New database table: `ai_blog_brand_features`
  - Full CRUD operations for brand features (services, pages, documents, etc.)
  - Four category types: informational_page, document, image, video
  - Active/inactive state management for features
  - Real-time search and category filtering
  - Grid layout with modern card-based design
  - Modal-based editing interface
  - Integration ready for AI content generation
  - Comprehensive error handling and logging

- **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
  - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
    - Updated all AI prompts to request 120-140 character meta descriptions
    - Updated validation logic to check for 140-character limit instead of 160
    - Applied changes to both Prompt Compiler Service and Anthropic Service
  - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
    - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
    - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
    - Only adds keyphrase if not already present to avoid duplication
    - Applied to both Content Generator and Anthropic Service classes
  - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
    - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
    - Focus keyphrase appears first in filename followed by descriptive keywords
    - Smart deduplication logic prevents overlap between keyphrase and content keywords
    - Automatic propagation from content generation to image requirements
    - Applied to both content images and featured images
  - Ensures all SEO elements meet current best practices

- **Brand Features Management System**: Complete internal linking management with:
  - New database table: `ai_blog_brand_features`
  - Full CRUD operations for brand features (services, pages, documents, etc.)
  - Four category types: informational_page, document, image, video
  - Active/inactive state management for features
  - Real-time search and category filtering
  - Grid layout with modern card-based design
  - Modal-based editing interface
  - Integration ready for AI content generation
  - Comprehensive error handling and logging

- **UI/UX Improvements**:
  - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
    - Added proper button styling with colors and hover effects
    - Save button uses primary blue (#2271b1) with hover state
    - Cancel button uses secondary gray (#f0f0f1) with hover state
    - Added consistent padding, border radius, and transitions
    
  - **Product Links Pill Design**: Enhanced product links display
    - Added pill-style design with rounded borders and padding
    - Colored type badges with specific colors for each link type
    - Product Page links show green badge
    - Purchase links show orange badge
    - Documentation links show purple badge
    - Other links show gray badge
    - Added hover effects with shadow and transform
    - Fixed link type labels to show proper text instead of database values
    
  - **Products Page Redesign**: Applied modern design style to products page matching personas page
    - Enhanced product cards with gradient backgrounds and hover effects
    - Improved search box styling with focus states
    - Modernized product modal with better form styling and section dividers
    - Updated image and link management UI with better visual hierarchy
    - Added colored badges for link types (product page, purchase, documentation)
    - Improved pagination styling with better hover states
    - Enhanced responsive design for mobile devices
    
  - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
    - Enhanced context cards with improved shadows and hover effects
    - Added gradient backgrounds to context type badges
    - Improved badge styling for usage categories and always-include indicators
    - Modernized context edit modal with better form controls
    - Enhanced seed images section with better card design
    - Updated seed image upload modal to match personas modal styling
    - Improved button styling with hover effects and better spacing
    - Added responsive design improvements for mobile devices
    
  - **Layout Consistency**: Made all admin pages full-width
    - Removed max-width restrictions from contexts and products pages
    - All pages now use 100% width like the personas page
    - Consistent layout across all admin sections
    
  - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
    - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
    - Consistent icon style matching personas page design
    - Applied same icon treatment to seed images section
    - Better visual hierarchy and cleaner interface
    
  - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
    - Added 'layout' as a valid enum option in the database
    - Fixed JavaScript to display type labels instead of database values
    - Added context type labels to JavaScript localization data
    - Context cards now show "Layout Guidelines" instead of "layout" after saving
    
  - **Context Card Hover Effect**: Added gradient line hover effect to context cards
    - Matches the persona cards' gradient line that appears on hover
    - Uses the same blue-purple-pink gradient for consistency
    - Provides visual feedback when hovering over context cards
    
  - **Product Cards Redesign**: Updated product cards to match personas and contexts style
    - Replaced text buttons with icon buttons (edit and delete)
    - Added gradient line hover effect matching other admin cards
    - Improved typography and spacing consistency
    - Updated color scheme to match modern design language
    - Better visual hierarchy with icon-based actions
    
  - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
    - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
    - **Solution**: Updated enqueue script patterns to match actual menu slug registration
    - **Files Modified**: 
      - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
      - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
    - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
    
  - **Drafted Posts Table Width**: Fixed table only using half the screen width
    - **Root Cause**: WordPress default `.wrap` class applies width constraints
    - **Solution**: Added CSS overrides to make the page full width
    - **CSS Changes**: 
      - Override `.wrap` max-width constraint
      - Ensure table and cards use 100% width
      - Scoped WordPress admin overrides to drafted posts page only
    - **Result**: Drafted posts table now uses full available screen width
    
  - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
    - **Root Cause**: Script was enqueued but not localized with AJAX data
    - **Solution**: Added `wp_localize_script` call for drafted posts script
    - **Files Modified**: 
      - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
    - **Result**: Drafted posts page now loads properly with AJAX functionality working
    
  - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
    - **Root Cause**: Bootstrap card component was constraining table width
    - **Solution**: Replaced card wrapper with custom div structure
    - **Changes Made**: 
      - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
      - Added custom CSS for table wrapper with full width
      - Also updated filter actions bar to use consistent wrapper approach
    - **Result**: Table now uses full available screen width without Bootstrap card constraints
    
  - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
    - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
    - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
    - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
    - **Changes Made**:
      - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
      - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
      - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
    - **Result**: Schedule post functionality now works correctly without fatal errors
    
  - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
    - **Files Modified**:
      - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
      - `admin/views/approved-ideas-view-v2.php` - Updated page title 
      - `admin/class-admin-manager.php` - Updated submenu registration
    - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
    
  - **Generation Queue Status Visibility**: Improved visibility of generation queue status
    - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
    - **Solution**: Enhanced the queue status indicator UI
    - **Changes Made**:
      - Made queue status indicator more prominent with badges and icons
      - Added list of queued items showing position and title
      - Added notification when items are queued
      - Added automatic queue status fetching after bulk generation
      - Added function to fetch queue status on demand
    
    - **Result**: Users now clearly see when ideas are queued and their position in the queue
    
  - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
    - Queue management service supporting up to 5 simultaneous generations
    - FIFO queue for excess generation requests
    - Real-time queue status display with active generations and positions
    - Automatic processing when slots become available
    - Individual and bulk generation support
    
  - **Live Generation Status Updates**: Real-time progress tracking without page refresh
    - Database `generation_status` field for persistent status storage
    - Immediate UI updates when generation starts
    - Progress bar with detailed status messages
    - Fast refresh mode (2s) during active generations
    
  - **Live Log Viewer**: Tail generation logs in real-time
    - Incremental log loading (only new lines)
    - Color-coded log entries (error, warning, info, success)
    - Auto-scroll to bottom for new content
    - Automatic stop when generation completes
    - Shows generation completion status
    
  - **Error Recovery Features**: Comprehensive error handling and recovery
    - One-click retry for failed generations
    - Error messages stored in database
    - Automatic cleanup of stuck generations (10+ minutes)
    - Clear error feedback in UI
    
  - **Database Schema Updates**: New fields for generation tracking
    - `generation_status` - Current generation progress message
    - `generation_error` - Error message storage
    - `generation_started_at` - Generation start timestamp
    - `generation_completed_at` - Generation completion timestamp
    - Index on `generation_status` for performance
    
  - **New Services**: Generation queue management
    - `services/class-generation-queue.php` - Complete queue management system
    - Supports concurrent processing with queue overflow
    - Automatic retry and error recovery
    
  - **Brand Features Management System**: Complete internal linking management with:
    - New database table: `ai_blog_brand_features`
    - Full CRUD operations for brand features (services, pages, documents, etc.)
    - Four category types: informational_page, document, image, video
    - Active/inactive state management for features
    - Real-time search and category filtering
    - Grid layout with modern card-based design
    - Modal-based editing interface
    - Integration ready for AI content generation
    - Comprehensive error handling and logging
    
  - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
    - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
      - Updated all AI prompts to request 120-140 character meta descriptions
      - Updated validation logic to check for 140-character limit instead of 160
      - Applied changes to both Prompt Compiler Service and Anthropic Service
    - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
      - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
      - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
      - Only adds keyphrase if not already present to avoid duplication
      - Applied to both Content Generator and Anthropic Service classes
    - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
      - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
      - Focus keyphrase appears first in filename followed by descriptive keywords
      - Smart deduplication logic prevents overlap between keyphrase and content keywords
      - Automatic propagation from content generation to image requirements
      - Applied to both content images and featured images
    - Ensures all SEO elements meet current best practices
    
  - **Brand Features Management System**: Complete internal linking management with:
    - New database table: `ai_blog_brand_features`
    - Full CRUD operations for brand features (services, pages, documents, etc.)
    - Four category types: informational_page, document, image, video
    - Active/inactive state management for features
    - Real-time search and category filtering
    - Grid layout with modern card-based design
    - Modal-based editing interface
    - Integration ready for AI content generation
    - Comprehensive error handling and logging
    
  - **UI/UX Improvements**:
    - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
      - Added proper button styling with colors and hover effects
      - Save button uses primary blue (#2271b1) with hover state
      - Cancel button uses secondary gray (#f0f0f1) with hover state
      - Added consistent padding, border radius, and transitions
      
    - **Product Links Pill Design**: Enhanced product links display
      - Added pill-style design with rounded borders and padding
      - Colored type badges with specific colors for each link type
      - Product Page links show green badge
      - Purchase links show orange badge
      - Documentation links show purple badge
      - Other links show gray badge
      - Added hover effects with shadow and transform
      - Fixed link type labels to show proper text instead of database values
      
    - **Products Page Redesign**: Applied modern design style to products page matching personas page
      - Enhanced product cards with gradient backgrounds and hover effects
      - Improved search box styling with focus states
      - Modernized product modal with better form styling and section dividers
      - Updated image and link management UI with better visual hierarchy
      - Added colored badges for link types (product page, purchase, documentation)
      - Improved pagination styling with better hover states
      - Enhanced responsive design for mobile devices
      
    - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
      - Enhanced context cards with improved shadows and hover effects
      - Added gradient backgrounds to context type badges
      - Improved badge styling for usage categories and always-include indicators
      - Modernized context edit modal with better form controls
      - Enhanced seed images section with better card design
      - Updated seed image upload modal to match personas modal styling
      - Improved button styling with hover effects and better spacing
      - Added responsive design improvements for mobile devices
      
    - **Layout Consistency**: Made all admin pages full-width
      - Removed max-width restrictions from contexts and products pages
      - All pages now use 100% width like the personas page
      - Consistent layout across all admin sections
      
    - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
      - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
      - Consistent icon style matching personas page design
      - Applied same icon treatment to seed images section
      - Better visual hierarchy and cleaner interface
      
    - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
      - Added 'layout' as a valid enum option in the database
      - Fixed JavaScript to display type labels instead of database values
      - Added context type labels to JavaScript localization data
      - Context cards now show "Layout Guidelines" instead of "layout" after saving
      
    - **Context Card Hover Effect**: Added gradient line hover effect to context cards
      - Matches the persona cards' gradient line that appears on hover
      - Uses the same blue-purple-pink gradient for consistency
      - Provides visual feedback when hovering over context cards
      
    - **Product Cards Redesign**: Updated product cards to match personas and contexts style
      - Replaced text buttons with icon buttons (edit and delete)
      - Added gradient line hover effect matching other admin cards
      - Improved typography and spacing consistency
      - Updated color scheme to match modern design language
      - Better visual hierarchy with icon-based actions
      
    - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
      - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
      - **Solution**: Updated enqueue script patterns to match actual menu slug registration
      - **Files Modified**: 
        - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
        - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
      - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
      
    - **Drafted Posts Table Width**: Fixed table only using half the screen width
      - **Root Cause**: WordPress default `.wrap` class applies width constraints
      - **Solution**: Added CSS overrides to make the page full width
      - **CSS Changes**: 
        - Override `.wrap` max-width constraint
        - Ensure table and cards use 100% width
        - Scoped WordPress admin overrides to drafted posts page only
      - **Result**: Drafted posts table now uses full available screen width
      
    - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
      - **Root Cause**: Script was enqueued but not localized with AJAX data
      - **Solution**: Added `wp_localize_script` call for drafted posts script
      - **Files Modified**: 
        - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
      - **Result**: Drafted posts page now loads properly with AJAX functionality working
      
    - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
      - **Root Cause**: Bootstrap card component was constraining table width
      - **Solution**: Replaced card wrapper with custom div structure
      - **Changes Made**: 
        - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
        - Added custom CSS for table wrapper with full width
        - Also updated filter actions bar to use consistent wrapper approach
      - **Result**: Table now uses full available screen width without Bootstrap card constraints
      
    - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
      - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
      - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
      - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
      - **Changes Made**:
        - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
        - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
        - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
      - **Result**: Schedule post functionality now works correctly without fatal errors
      
    - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
      - **Files Modified**:
        - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
        - `admin/views/approved-ideas-view-v2.php` - Updated page title 
        - `admin/class-admin-manager.php` - Updated submenu registration
      - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
      
    - **Generation Queue Status Visibility**: Improved visibility of generation queue status
      - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
      - **Solution**: Enhanced the queue status indicator UI
      - **Changes Made**:
        - Made queue status indicator more prominent with badges and icons
        - Added list of queued items showing position and title
        - Added notification when items are queued
        - Added automatic queue status fetching after bulk generation
        - Added function to fetch queue status on demand
      
      - **Result**: Users now clearly see when ideas are queued and their position in the queue
      
    - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
      - Queue management service supporting up to 5 simultaneous generations
      - FIFO queue for excess generation requests
      - Real-time queue status display with active generations and positions
      - Automatic processing when slots become available
      - Individual and bulk generation support
      
    - **Live Generation Status Updates**: Real-time progress tracking without page refresh
      - Database `generation_status` field for persistent status storage
      - Immediate UI updates when generation starts
      - Progress bar with detailed status messages
      - Fast refresh mode (2s) during active generations
      
    - **Live Log Viewer**: Tail generation logs in real-time
      - Incremental log loading (only new lines)
      - Color-coded log entries (error, warning, info, success)
      - Auto-scroll to bottom for new content
      - Automatic stop when generation completes
      - Shows generation completion status
      
    - **Error Recovery Features**: Comprehensive error handling and recovery
      - One-click retry for failed generations
      - Error messages stored in database
      - Automatic cleanup of stuck generations (10+ minutes)
      - Clear error feedback in UI
      
    - **Database Schema Updates**: New fields for generation tracking
      - `generation_status` - Current generation progress message
      - `generation_error` - Error message storage
      - `generation_started_at` - Generation start timestamp
      - `generation_completed_at` - Generation completion timestamp
      - Index on `generation_status` for performance
      
    - **New Services**: Generation queue management
      - `services/class-generation-queue.php` - Complete queue management system
      - Supports concurrent processing with queue overflow
      - Automatic retry and error recovery
      
    - **Brand Features Management System**: Complete internal linking management with:
      - New database table: `ai_blog_brand_features`
      - Full CRUD operations for brand features (services, pages, documents, etc.)
      - Four category types: informational_page, document, image, video
      - Active/inactive state management for features
      - Real-time search and category filtering
      - Grid layout with modern card-based design
      - Modal-based editing interface
      - Integration ready for AI content generation
      - Comprehensive error handling and logging
      
    - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
      - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
        - Updated all AI prompts to request 120-140 character meta descriptions
        - Updated validation logic to check for 140-character limit instead of 160
        - Applied changes to both Prompt Compiler Service and Anthropic Service
      - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
        - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
        - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
        - Only adds keyphrase if not already present to avoid duplication
        - Applied to both Content Generator and Anthropic Service classes
      - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
        - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
        - Focus keyphrase appears first in filename followed by descriptive keywords
        - Smart deduplication logic prevents overlap between keyphrase and content keywords
        - Automatic propagation from content generation to image requirements
        - Applied to both content images and featured images
      - Ensures all SEO elements meet current best practices
      
    - **Brand Features Management System**: Complete internal linking management with:
      - New database table: `ai_blog_brand_features`
      - Full CRUD operations for brand features (services, pages, documents, etc.)
      - Four category types: informational_page, document, image, video
      - Active/inactive state management for features
      - Real-time search and category filtering
      - Grid layout with modern card-based design
      - Modal-based editing interface
      - Integration ready for AI content generation
      - Comprehensive error handling and logging
        
      
    - **UI/UX Improvements**:
      - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
        - Added proper button styling with colors and hover effects
        - Save button uses primary blue (#2271b1) with hover state
        - Cancel button uses secondary gray (#f0f0f1) with hover state
        - Added consistent padding, border radius, and transitions
        
      - **Product Links Pill Design**: Enhanced product links display
        - Added pill-style design with rounded borders and padding
        - Colored type badges with specific colors for each link type
        - Product Page links show green badge
        - Purchase links show orange badge
        - Documentation links show purple badge
        - Other links show gray badge
        - Added hover effects with shadow and transform
        - Fixed link type labels to show proper text instead of database values
        
      - **Products Page Redesign**: Applied modern design style to products page matching personas page
        - Enhanced product cards with gradient backgrounds and hover effects
        - Improved search box styling with focus states
        - Modernized product modal with better form styling and section dividers
        - Updated image and link management UI with better visual hierarchy
        - Added colored badges for link types (product page, purchase, documentation)
        - Improved pagination styling with better hover states
        - Enhanced responsive design for mobile devices
        
      - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
        - Enhanced context cards with improved shadows and hover effects
        - Added gradient backgrounds to context type badges
        - Improved badge styling for usage categories and always-include indicators
        - Modernized context edit modal with better form controls
        - Enhanced seed images section with better card design
        - Updated seed image upload modal to match personas modal styling
        - Improved button styling with hover effects and better spacing
        - Added responsive design improvements for mobile devices
        
      - **Layout Consistency**: Made all admin pages full-width
        - Removed max-width restrictions from contexts and products pages
        - All pages now use 100% width like the personas page
        - Consistent layout across all admin sections
        
      - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
        - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
        - Consistent icon style matching personas page design
        - Applied same icon treatment to seed images section
        - Better visual hierarchy and cleaner interface
        
      - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
        - Added 'layout' as a valid enum option in the database
        - Fixed JavaScript to display type labels instead of database values
        - Added context type labels to JavaScript localization data
        - Context cards now show "Layout Guidelines" instead of "layout" after saving
        
      - **Context Card Hover Effect**: Added gradient line hover effect to context cards
        - Matches the persona cards' gradient line that appears on hover
        - Uses the same blue-purple-pink gradient for consistency
        - Provides visual feedback when hovering over context cards
        
      - **Product Cards Redesign**: Updated product cards to match personas and contexts style
        - Replaced text buttons with icon buttons (edit and delete)
        - Added gradient line hover effect matching other admin cards
        - Improved typography and spacing consistency
        - Updated color scheme to match modern design language
        - Better visual hierarchy with icon-based actions
        
      - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
        - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
        - **Solution**: Updated enqueue script patterns to match actual menu slug registration
        - **Files Modified**: 
          - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
          - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
        - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
        
      - **Drafted Posts Table Width**: Fixed table only using half the screen width
        - **Root Cause**: WordPress default `.wrap` class applies width constraints
        - **Solution**: Added CSS overrides to make the page full width
        - **CSS Changes**: 
          - Override `.wrap` max-width constraint
          - Ensure table and cards use 100% width
          - Scoped WordPress admin overrides to drafted posts page only
        - **Result**: Drafted posts table now uses full available screen width
        
      - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
        - **Root Cause**: Script was enqueued but not localized with AJAX data
        - **Solution**: Added `wp_localize_script` call for drafted posts script
        - **Files Modified**: 
          - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
        - **Result**: Drafted posts page now loads properly with AJAX functionality working
        
      - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
        - **Root Cause**: Bootstrap card component was constraining table width
        - **Solution**: Replaced card wrapper with custom div structure
        - **Changes Made**: 
          - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
          - Added custom CSS for table wrapper with full width
          - Also updated filter actions bar to use consistent wrapper approach
        - **Result**: Table now uses full available screen width without Bootstrap card constraints
        
      - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
        - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
        - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
        - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
        - **Changes Made**:
          - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
          - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
          - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
        - **Result**: Schedule post functionality now works correctly without fatal errors
        
      - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
        - **Files Modified**:
          - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
          - `admin/views/approved-ideas-view-v2.php` - Updated page title 
          - `admin/class-admin-manager.php` - Updated submenu registration
        - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
        
      - **Generation Queue Status Visibility**: Improved visibility of generation queue status
        - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
        - **Solution**: Enhanced the queue status indicator UI
        - **Changes Made**:
          - Made queue status indicator more prominent with badges and icons
          - Added list of queued items showing position and title
          - Added notification when items are queued
          - Added automatic queue status fetching after bulk generation
          - Added function to fetch queue status on demand
        
        - **Result**: Users now clearly see when ideas are queued and their position in the queue
        
      - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
        - Queue management service supporting up to 5 simultaneous generations
        - FIFO queue for excess generation requests
        - Real-time queue status display with active generations and positions
        - Automatic processing when slots become available
        - Individual and bulk generation support
        
      - **Live Generation Status Updates**: Real-time progress tracking without page refresh
        - Database `generation_status` field for persistent status storage
        - Immediate UI updates when generation starts
        - Progress bar with detailed status messages
        - Fast refresh mode (2s) during active generations
        
      - **Live Log Viewer**: Tail generation logs in real-time
        - Incremental log loading (only new lines)
        - Color-coded log entries (error, warning, info, success)
        - Auto-scroll to bottom for new content
        - Automatic stop when generation completes
        - Shows generation completion status
        
      - **Error Recovery Features**: Comprehensive error handling and recovery
        - One-click retry for failed generations
        - Error messages stored in database
        - Automatic cleanup of stuck generations (10+ minutes)
        - Clear error feedback in UI
        
      - **Database Schema Updates**: New fields for generation tracking
        - `generation_status` - Current generation progress message
        - `generation_error` - Error message storage
        - `generation_started_at` - Generation start timestamp
        - `generation_completed_at` - Generation completion timestamp
        - Index on `generation_status` for performance
        
      - **New Services**: Generation queue management
        - `services/class-generation-queue.php` - Complete queue management system
        - Supports concurrent processing with queue overflow
        - Automatic retry and error recovery
        
      - **Brand Features Management System**: Complete internal linking management with:
        - New database table: `ai_blog_brand_features`
        - Full CRUD operations for brand features (services, pages, documents, etc.)
        - Four category types: informational_page, document, image, video
        - Active/inactive state management for features
        - Real-time search and category filtering
        - Grid layout with modern card-based design
        - Modal-based editing interface
        - Integration ready for AI content generation
        - Comprehensive error handling and logging
        
      - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
        - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
          - Updated all AI prompts to request 120-140 character meta descriptions
          - Updated validation logic to check for 140-character limit instead of 160
          - Applied changes to both Prompt Compiler Service and Anthropic Service
        - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
          - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
          - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
          - Only adds keyphrase if not already present to avoid duplication
          - Applied to both Content Generator and Anthropic Service classes
        - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
          - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
          - Focus keyphrase appears first in filename followed by descriptive keywords
          - Smart deduplication logic prevents overlap between keyphrase and content keywords
          - Automatic propagation from content generation to image requirements
          - Applied to both content images and featured images
        - Ensures all SEO elements meet current best practices
        
      - **Brand Features Management System**: Complete internal linking management with:
        - New database table: `ai_blog_brand_features`
        - Full CRUD operations for brand features (services, pages, documents, etc.)
        - Four category types: informational_page, document, image, video
        - Active/inactive state management for features
        - Real-time search and category filtering
        - Grid layout with modern card-based design
        - Modal-based editing interface
        - Integration ready for AI content generation
        - Comprehensive error handling and logging
        
      - **UI/UX Improvements**:
        - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
          - Added proper button styling with colors and hover effects
          - Save button uses primary blue (#2271b1) with hover state
          - Cancel button uses secondary gray (#f0f0f1) with hover state
          - Added consistent padding, border radius, and transitions
          
        - **Product Links Pill Design**: Enhanced product links display
          - Added pill-style design with rounded borders and padding
          - Colored type badges with specific colors for each link type
          - Product Page links show green badge
          - Purchase links show orange badge
          - Documentation links show purple badge
          - Other links show gray badge
          - Added hover effects with shadow and transform
          - Fixed link type labels to show proper text instead of database values
          
        - **Products Page Redesign**: Applied modern design style to products page matching personas page
          - Enhanced product cards with gradient backgrounds and hover effects
          - Improved search box styling with focus states
          - Modernized product modal with better form styling and section dividers
          - Updated image and link management UI with better visual hierarchy
          - Added colored badges for link types (product page, purchase, documentation)
          - Improved pagination styling with better hover states
          - Enhanced responsive design for mobile devices
          
        - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
          - Enhanced context cards with improved shadows and hover effects
          - Added gradient backgrounds to context type badges
          - Improved badge styling for usage categories and always-include indicators
          - Modernized context edit modal with better form controls
          - Enhanced seed images section with better card design
          - Updated seed image upload modal to match personas modal styling
          - Improved button styling with hover effects and better spacing
          - Added responsive design improvements for mobile devices
          
        - **Layout Consistency**: Made all admin pages full-width
          - Removed max-width restrictions from contexts and products pages
          - All pages now use 100% width like the personas page
          - Consistent layout across all admin sections
          
        - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
          - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
          - Consistent icon style matching personas page design
          - Applied same icon treatment to seed images section
          - Better visual hierarchy and cleaner interface
          
        - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
          - Added 'layout' as a valid enum option in the database
          - Fixed JavaScript to display type labels instead of database values
          - Added context type labels to JavaScript localization data
          - Context cards now show "Layout Guidelines" instead of "layout" after saving
          
        - **Context Card Hover Effect**: Added gradient line hover effect to context cards
          - Matches the persona cards' gradient line that appears on hover
          - Uses the same blue-purple-pink gradient for consistency
          - Provides visual feedback when hovering over context cards
          
        - **Product Cards Redesign**: Updated product cards to match personas and contexts style
          - Replaced text buttons with icon buttons (edit and delete)
          - Added gradient line hover effect matching other admin cards
          - Improved typography and spacing consistency
          - Updated color scheme to match modern design language
          - Better visual hierarchy with icon-based actions
          
        - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
          - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
          - **Solution**: Updated enqueue script patterns to match actual menu slug registration
          - **Files Modified**: 
            - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
            - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
          - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
          
        - **Drafted Posts Table Width**: Fixed table only using half the screen width
          - **Root Cause**: WordPress default `.wrap` class applies width constraints
          - **Solution**: Added CSS overrides to make the page full width
          - **CSS Changes**: 
            - Override `.wrap` max-width constraint
            - Ensure table and cards use 100% width
            - Scoped WordPress admin overrides to drafted posts page only
          - **Result**: Drafted posts table now uses full available screen width
          
        - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
          - **Root Cause**: Script was enqueued but not localized with AJAX data
          - **Solution**: Added `wp_localize_script` call for drafted posts script
          - **Files Modified**: 
            - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
          - **Result**: Drafted posts page now loads properly with AJAX functionality working
          
        - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
          - **Root Cause**: Bootstrap card component was constraining table width
          - **Solution**: Replaced card wrapper with custom div structure
          - **Changes Made**: 
            - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
            - Added custom CSS for table wrapper with full width
            - Also updated filter actions bar to use consistent wrapper approach
          - **Result**: Table now uses full available screen width without Bootstrap card constraints
          
        - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
          - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
          - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
          - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
          - **Changes Made**:
            - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
            - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
            - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
          - **Result**: Schedule post functionality now works correctly without fatal errors
          
        - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
          - **Files Modified**:
            - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
            - `admin/views/approved-ideas-view-v2.php` - Updated page title 
            - `admin/class-admin-manager.php` - Updated submenu registration
          - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
          
        - **Generation Queue Status Visibility**: Improved visibility of generation queue status
          - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
          - **Solution**: Enhanced the queue status indicator UI
          - **Changes Made**:
            - Made queue status indicator more prominent with badges and icons
            - Added list of queued items showing position and title
            - Added notification when items are queued
            - Added automatic queue status fetching after bulk generation
            - Added function to fetch queue status on demand
          
          - **Result**: Users now clearly see when ideas are queued and their position in the queue
          
        - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
          - Queue management service supporting up to 5 simultaneous generations
          - FIFO queue for excess generation requests
          - Real-time queue status display with active generations and positions
          - Automatic processing when slots become available
          - Individual and bulk generation support
          
        - **Live Generation Status Updates**: Real-time progress tracking without page refresh
          - Database `generation_status` field for persistent status storage
          - Immediate UI updates when generation starts
          - Progress bar with detailed status messages
          - Fast refresh mode (2s) during active generations
          
        - **Live Log Viewer**: Tail generation logs in real-time
          - Incremental log loading (only new lines)
          - Color-coded log entries (error, warning, info, success)
          - Auto-scroll to bottom for new content
          - Automatic stop when generation completes
          - Shows generation completion status
          
        - **Error Recovery Features**: Comprehensive error handling and recovery
          - One-click retry for failed generations
          - Error messages stored in database
          - Automatic cleanup of stuck generations (10+ minutes)
          - Clear error feedback in UI
          
        - **Database Schema Updates**: New fields for generation tracking
          - `generation_status` - Current generation progress message
          - `generation_error` - Error message storage
          - `generation_started_at` - Generation start timestamp
          - `generation_completed_at` - Generation completion timestamp
          - Index on `generation_status` for performance
          
        - **New Services**: Generation queue management
          - `services/class-generation-queue.php` - Complete queue management system
          - Supports concurrent processing with queue overflow
          - Automatic retry and error recovery
          
        - **Brand Features Management System**: Complete internal linking management with:
          - New database table: `ai_blog_brand_features`
          - Full CRUD operations for brand features (services, pages, documents, etc.)
          - Four category types: informational_page, document, image, video
          - Active/inactive state management for features
          - Real-time search and category filtering
          - Grid layout with modern card-based design
          - Modal-based editing interface
          - Integration ready for AI content generation
          - Comprehensive error handling and logging
          
        - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
          - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
            - Updated all AI prompts to request 120-140 character meta descriptions
            - Updated validation logic to check for 140-character limit instead of 160
            - Applied changes to both Prompt Compiler Service and Anthropic Service
          - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
            - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
            - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
            - Only adds keyphrase if not already present to avoid duplication
            - Applied to both Content Generator and Anthropic Service classes
          - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
            - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
            - Focus keyphrase appears first in filename followed by descriptive keywords
            - Smart deduplication logic prevents overlap between keyphrase and content keywords
            - Automatic propagation from content generation to image requirements
            - Applied to both content images and featured images
          - Ensures all SEO elements meet current best practices
          
        - **Brand Features Management System**: Complete internal linking management with:
          - New database table: `ai_blog_brand_features`
          - Full CRUD operations for brand features (services, pages, documents, etc.)
          - Four category types: informational_page, document, image, video
          - Active/inactive state management for features
          - Real-time search and category filtering
          - Grid layout with modern card-based design
          - Modal-based editing interface
          - Integration ready for AI content generation
          - Comprehensive error handling and logging
          
        - **UI/UX Improvements**:
          - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
            - Added proper button styling with colors and hover effects
            - Save button uses primary blue (#2271b1) with hover state
            - Cancel button uses secondary gray (#f0f0f1) with hover state
            - Added consistent padding, border radius, and transitions
            
          - **Product Links Pill Design**: Enhanced product links display
            - Added pill-style design with rounded borders and padding
            - Colored type badges with specific colors for each link type
            - Product Page links show green badge
            - Purchase links show orange badge
            - Documentation links show purple badge
            - Other links show gray badge
            - Added hover effects with shadow and transform
            - Fixed link type labels to show proper text instead of database values
            
          - **Products Page Redesign**: Applied modern design style to products page matching personas page
            - Enhanced product cards with gradient backgrounds and hover effects
            - Improved search box styling with focus states
            - Modernized product modal with better form styling and section dividers
            - Updated image and link management UI with better visual hierarchy
            - Added colored badges for link types (product page, purchase, documentation)
            - Improved pagination styling with better hover states
            - Enhanced responsive design for mobile devices
            
          - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
            - Enhanced context cards with improved shadows and hover effects
            - Added gradient backgrounds to context type badges
            - Improved badge styling for usage categories and always-include indicators
            - Modernized context edit modal with better form controls
            - Enhanced seed images section with better card design
            - Updated seed image upload modal to match personas modal styling
            - Improved button styling with hover effects and better spacing
            - Added responsive design improvements for mobile devices
            
          - **Layout Consistency**: Made all admin pages full-width
            - Removed max-width restrictions from contexts and products pages
            - All pages now use 100% width like the personas page
            - Consistent layout across all admin sections
            
          - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
            - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
            - Consistent icon style matching personas page design
            - Applied same icon treatment to seed images section
            - Better visual hierarchy and cleaner interface
            
          - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
            - Added 'layout' as a valid enum option in the database
            - Fixed JavaScript to display type labels instead of database values
            - Added context type labels to JavaScript localization data
            - Context cards now show "Layout Guidelines" instead of "layout" after saving
            
          - **Context Card Hover Effect**: Added gradient line hover effect to context cards
            - Matches the persona cards' gradient line that appears on hover
            - Uses the same blue-purple-pink gradient for consistency
            - Provides visual feedback when hovering over context cards
            
          - **Product Cards Redesign**: Updated product cards to match personas and contexts style
            - Replaced text buttons with icon buttons (edit and delete)
            - Added gradient line hover effect matching other admin cards
            - Improved typography and spacing consistency
            - Updated color scheme to match modern design language
            - Better visual hierarchy with icon-based actions
            
          - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
            - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
            - **Solution**: Updated enqueue script patterns to match actual menu slug registration
            - **Files Modified**: 
              - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
              - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
            - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
            
          - **Drafted Posts Table Width**: Fixed table only using half the screen width
            - **Root Cause**: WordPress default `.wrap` class applies width constraints
            - **Solution**: Added CSS overrides to make the page full width
            - **CSS Changes**: 
              - Override `.wrap` max-width constraint
              - Ensure table and cards use 100% width
              - Scoped WordPress admin overrides to drafted posts page only
            - **Result**: Drafted posts table now uses full available screen width
            
          - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
            - **Root Cause**: Script was enqueued but not localized with AJAX data
            - **Solution**: Added `wp_localize_script` call for drafted posts script
            - **Files Modified**: 
              - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
            - **Result**: Drafted posts page now loads properly with AJAX functionality working
            
          - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
            - **Root Cause**: Bootstrap card component was constraining table width
            - **Solution**: Replaced card wrapper with custom div structure
            - **Changes Made**: 
              - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
              - Added custom CSS for table wrapper with full width
              - Also updated filter actions bar to use consistent wrapper approach
            - **Result**: Table now uses full available screen width without Bootstrap card constraints
            
          - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
            - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
            - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
            - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
            - **Changes Made**:
              - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
              - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
              - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
            - **Result**: Schedule post functionality now works correctly without fatal errors
            
          - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
            - **Files Modified**:
              - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
              - `admin/views/approved-ideas-view-v2.php` - Updated page title 
              - `admin/class-admin-manager.php` - Updated submenu registration
            - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
            
          - **Generation Queue Status Visibility**: Improved visibility of generation queue status
            - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
            - **Solution**: Enhanced the queue status indicator UI
            - **Changes Made**:
              - Made queue status indicator more prominent with badges and icons
              - Added list of queued items showing position and title
              - Added notification when items are queued
              - Added automatic queue status fetching after bulk generation
              - Added function to fetch queue status on demand
            
            - **Result**: Users now clearly see when ideas are queued and their position in the queue
            
          - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
            - Queue management service supporting up to 5 simultaneous generations
            - FIFO queue for excess generation requests
            - Real-time queue status display with active generations and positions
            - Automatic processing when slots become available
            - Individual and bulk generation support
            
          - **Live Generation Status Updates**: Real-time progress tracking without page refresh
            - Database `generation_status` field for persistent status storage
            - Immediate UI updates when generation starts
            - Progress bar with detailed status messages
            - Fast refresh mode (2s) during active generations
            
          - **Live Log Viewer**: Tail generation logs in real-time
            - Incremental log loading (only new lines)
            - Color-coded log entries (error, warning, info, success)
            - Auto-scroll to bottom for new content
            - Automatic stop when generation completes
            - Shows generation completion status
            
          - **Error Recovery Features**: Comprehensive error handling and recovery
            - One-click retry for failed generations
            - Error messages stored in database
            - Automatic cleanup of stuck generations (10+ minutes)
            - Clear error feedback in UI
            
          - **Database Schema Updates**: New fields for generation tracking
            - `generation_status` - Current generation progress message
            - `generation_error` - Error message storage
            - `generation_started_at` - Generation start timestamp
            - `generation_completed_at` - Generation completion timestamp
            - Index on `generation_status` for performance
            
          - **New Services**: Generation queue management
            - `services/class-generation-queue.php` - Complete queue management system
            - Supports concurrent processing with queue overflow
            - Automatic retry and error recovery
            
          - **Brand Features Management System**: Complete internal linking management with:
            - New database table: `ai_blog_brand_features`
            - Full CRUD operations for brand features (services, pages, documents, etc.)
            - Four category types: informational_page, document, image, video
            - Active/inactive state management for features
            - Real-time search and category filtering
            - Grid layout with modern card-based design
            - Modal-based editing interface
            - Integration ready for AI content generation
            - Comprehensive error handling and logging
            
          - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
            - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
              - Updated all AI prompts to request 120-140 character meta descriptions
              - Updated validation logic to check for 140-character limit instead of 160
              - Applied changes to both Prompt Compiler Service and Anthropic Service
            - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
              - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
              - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
              - Only adds keyphrase if not already present to avoid duplication
              - Applied to both Content Generator and Anthropic Service classes
            - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
              - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
              - Focus keyphrase appears first in filename followed by descriptive keywords
              - Smart deduplication logic prevents overlap between keyphrase and content keywords
              - Automatic propagation from content generation to image requirements
              - Applied to both content images and featured images
            - Ensures all SEO elements meet current best practices
            
          - **Brand Features Management System**: Complete internal linking management with:
            - New database table: `ai_blog_brand_features`
            - Full CRUD operations for brand features (services, pages, documents, etc.)
            - Four category types: informational_page, document, image, video
            - Active/inactive state management for features
            - Real-time search and category filtering
            - Grid layout with modern card-based design
            - Modal-based editing interface
            - Integration ready for AI content generation
            - Comprehensive error handling and logging
            
          - **UI/UX Improvements**:
            - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
              - Added proper button styling with colors and hover effects
              - Save button uses primary blue (#2271b1) with hover state
              - Cancel button uses secondary gray (#f0f0f1) with hover state
              - Added consistent padding, border radius, and transitions
              
            - **Product Links Pill Design**: Enhanced product links display
              - Added pill-style design with rounded borders and padding
              - Colored type badges with specific colors for each link type
              - Product Page links show green badge
              - Purchase links show orange badge
              - Documentation links show purple badge
              - Other links show gray badge
              - Added hover effects with shadow and transform
              - Fixed link type labels to show proper text instead of database values
              
            - **Products Page Redesign**: Applied modern design style to products page matching personas page
              - Enhanced product cards with gradient backgrounds and hover effects
              - Improved search box styling with focus states
              - Modernized product modal with better form styling and section dividers
              - Updated image and link management UI with better visual hierarchy
              - Added colored badges for link types (product page, purchase, documentation)
              - Improved pagination styling with better hover states
              - Enhanced responsive design for mobile devices
              
            - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
              - Enhanced context cards with improved shadows and hover effects
              - Added gradient backgrounds to context type badges
              - Improved badge styling for usage categories and always-include indicators
              - Modernized context edit modal with better form controls
              - Enhanced seed images section with better card design
              - Updated seed image upload modal to match personas modal styling
              - Improved button styling with hover effects and better spacing
              - Added responsive design improvements for mobile devices
              
            - **Layout Consistency**: Made all admin pages full-width
              - Removed max-width restrictions from contexts and products pages
              - All pages now use 100% width like the personas page
              - Consistent layout across all admin sections
              
            - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
              - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
              - Consistent icon style matching personas page design
              - Applied same icon treatment to seed images section
              - Better visual hierarchy and cleaner interface
              
            - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
              - Added 'layout' as a valid enum option in the database
              - Fixed JavaScript to display type labels instead of database values
              - Added context type labels to JavaScript localization data
              - Context cards now show "Layout Guidelines" instead of "layout" after saving
              
            - **Context Card Hover Effect**: Added gradient line hover effect to context cards
              - Matches the persona cards' gradient line that appears on hover
              - Uses the same blue-purple-pink gradient for consistency
              - Provides visual feedback when hovering over context cards
              
            - **Product Cards Redesign**: Updated product cards to match personas and contexts style
              - Replaced text buttons with icon buttons (edit and delete)
              - Added gradient line hover effect matching other admin cards
              - Improved typography and spacing consistency
              - Updated color scheme to match modern design language
              - Better visual hierarchy with icon-based actions
              
            - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
              - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
              - **Solution**: Updated enqueue script patterns to match actual menu slug registration
              - **Files Modified**: 
                - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
              - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
              
            - **Drafted Posts Table Width**: Fixed table only using half the screen width
              - **Root Cause**: WordPress default `.wrap` class applies width constraints
              - **Solution**: Added CSS overrides to make the page full width
              - **CSS Changes**: 
                - Override `.wrap` max-width constraint
                - Ensure table and cards use 100% width
                - Scoped WordPress admin overrides to drafted posts page only
              - **Result**: Drafted posts table now uses full available screen width
              
            - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
              - **Root Cause**: Script was enqueued but not localized with AJAX data
              - **Solution**: Added `wp_localize_script` call for drafted posts script
              - **Files Modified**: 
                - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
              - **Result**: Drafted posts page now loads properly with AJAX functionality working
              
            - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
              - **Root Cause**: Bootstrap card component was constraining table width
              - **Solution**: Replaced card wrapper with custom div structure
              - **Changes Made**: 
                - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                - Added custom CSS for table wrapper with full width
                - Also updated filter actions bar to use consistent wrapper approach
              - **Result**: Table now uses full available screen width without Bootstrap card constraints
              
            - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
              - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
              - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
              - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
              - **Changes Made**:
                - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
              - **Result**: Schedule post functionality now works correctly without fatal errors
              
            - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
              - **Files Modified**:
                - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                - `admin/class-admin-manager.php` - Updated submenu registration
              - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
              
            - **Generation Queue Status Visibility**: Improved visibility of generation queue status
              - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
              - **Solution**: Enhanced the queue status indicator UI
              - **Changes Made**:
                - Made queue status indicator more prominent with badges and icons
                - Added list of queued items showing position and title
                - Added notification when items are queued
                - Added automatic queue status fetching after bulk generation
                - Added function to fetch queue status on demand
              
              - **Result**: Users now clearly see when ideas are queued and their position in the queue
              
            - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
              - Queue management service supporting up to 5 simultaneous generations
              - FIFO queue for excess generation requests
              - Real-time queue status display with active generations and positions
              - Automatic processing when slots become available
              - Individual and bulk generation support
              
            - **Live Generation Status Updates**: Real-time progress tracking without page refresh
              - Database `generation_status` field for persistent status storage
              - Immediate UI updates when generation starts
              - Progress bar with detailed status messages
              - Fast refresh mode (2s) during active generations
              
            - **Live Log Viewer**: Tail generation logs in real-time
              - Incremental log loading (only new lines)
              - Color-coded log entries (error, warning, info, success)
              - Auto-scroll to bottom for new content
              - Automatic stop when generation completes
              - Shows generation completion status
              
            - **Error Recovery Features**: Comprehensive error handling and recovery
              - One-click retry for failed generations
              - Error messages stored in database
              - Automatic cleanup of stuck generations (10+ minutes)
              - Clear error feedback in UI
              
            - **Database Schema Updates**: New fields for generation tracking
              - `generation_status` - Current generation progress message
              - `generation_error` - Error message storage
              - `generation_started_at` - Generation start timestamp
              - `generation_completed_at` - Generation completion timestamp
              - Index on `generation_status` for performance
              
            - **New Services**: Generation queue management
              - `services/class-generation-queue.php` - Complete queue management system
              - Supports concurrent processing with queue overflow
              - Automatic retry and error recovery
              
            - **Brand Features Management System**: Complete internal linking management with:
              - New database table: `ai_blog_brand_features`
              - Full CRUD operations for brand features (services, pages, documents, etc.)
              - Four category types: informational_page, document, image, video
              - Active/inactive state management for features
              - Real-time search and category filtering
              - Grid layout with modern card-based design
              - Modal-based editing interface
              - Integration ready for AI content generation
              - Comprehensive error handling and logging
              
            - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
              - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                - Updated all AI prompts to request 120-140 character meta descriptions
                - Updated validation logic to check for 140-character limit instead of 160
                - Applied changes to both Prompt Compiler Service and Anthropic Service
              - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                - Only adds keyphrase if not already present to avoid duplication
                - Applied to both Content Generator and Anthropic Service classes
              - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                - Focus keyphrase appears first in filename followed by descriptive keywords
                - Smart deduplication logic prevents overlap between keyphrase and content keywords
                - Automatic propagation from content generation to image requirements
                - Applied to both content images and featured images
              - Ensures all SEO elements meet current best practices
              
            - **Brand Features Management System**: Complete internal linking management with:
              - New database table: `ai_blog_brand_features`
              - Full CRUD operations for brand features (services, pages, documents, etc.)
              - Four category types: informational_page, document, image, video
              - Active/inactive state management for features
              - Real-time search and category filtering
              - Grid layout with modern card-based design
              - Modal-based editing interface
              - Integration ready for AI content generation
              - Comprehensive error handling and logging
              
            - **UI/UX Improvements**:
              - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                - Added proper button styling with colors and hover effects
                - Save button uses primary blue (#2271b1) with hover state
                - Cancel button uses secondary gray (#f0f0f1) with hover state
                - Added consistent padding, border radius, and transitions
                
              - **Product Links Pill Design**: Enhanced product links display
                - Added pill-style design with rounded borders and padding
                - Colored type badges with specific colors for each link type
                - Product Page links show green badge
                - Purchase links show orange badge
                - Documentation links show purple badge
                - Other links show gray badge
                - Added hover effects with shadow and transform
                - Fixed link type labels to show proper text instead of database values
                
              - **Products Page Redesign**: Applied modern design style to products page matching personas page
                - Enhanced product cards with gradient backgrounds and hover effects
                - Improved search box styling with focus states
                - Modernized product modal with better form styling and section dividers
                - Updated image and link management UI with better visual hierarchy
                - Added colored badges for link types (product page, purchase, documentation)
                - Improved pagination styling with better hover states
                - Enhanced responsive design for mobile devices
                
              - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                - Enhanced context cards with improved shadows and hover effects
                - Added gradient backgrounds to context type badges
                - Improved badge styling for usage categories and always-include indicators
                - Modernized context edit modal with better form controls
                - Enhanced seed images section with better card design
                - Updated seed image upload modal to match personas modal styling
                - Improved button styling with hover effects and better spacing
                - Added responsive design improvements for mobile devices
                
              - **Layout Consistency**: Made all admin pages full-width
                - Removed max-width restrictions from contexts and products pages
                - All pages now use 100% width like the personas page
                - Consistent layout across all admin sections
                
              - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                - Consistent icon style matching personas page design
                - Applied same icon treatment to seed images section
                - Better visual hierarchy and cleaner interface
                
              - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                - Added 'layout' as a valid enum option in the database
                - Fixed JavaScript to display type labels instead of database values
                - Added context type labels to JavaScript localization data
                - Context cards now show "Layout Guidelines" instead of "layout" after saving
                
              - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                - Matches the persona cards' gradient line that appears on hover
                - Uses the same blue-purple-pink gradient for consistency
                - Provides visual feedback when hovering over context cards
                
              - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                - Replaced text buttons with icon buttons (edit and delete)
                - Added gradient line hover effect matching other admin cards
                - Improved typography and spacing consistency
                - Updated color scheme to match modern design language
                - Better visual hierarchy with icon-based actions
                
              - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                - **Files Modified**: 
                  - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                  - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                
              - **Drafted Posts Table Width**: Fixed table only using half the screen width
                - **Root Cause**: WordPress default `.wrap` class applies width constraints
                - **Solution**: Added CSS overrides to make the page full width
                - **CSS Changes**: 
                  - Override `.wrap` max-width constraint
                  - Ensure table and cards use 100% width
                  - Scoped WordPress admin overrides to drafted posts page only
                - **Result**: Drafted posts table now uses full available screen width
                
              - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                - **Root Cause**: Script was enqueued but not localized with AJAX data
                - **Solution**: Added `wp_localize_script` call for drafted posts script
                - **Files Modified**: 
                  - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                - **Result**: Drafted posts page now loads properly with AJAX functionality working
                
              - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                - **Root Cause**: Bootstrap card component was constraining table width
                - **Solution**: Replaced card wrapper with custom div structure
                - **Changes Made**: 
                  - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                  - Added custom CSS for table wrapper with full width
                  - Also updated filter actions bar to use consistent wrapper approach
                - **Result**: Table now uses full available screen width without Bootstrap card constraints
                
              - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                - **Changes Made**:
                  - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                  - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                  - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                - **Result**: Schedule post functionality now works correctly without fatal errors
                
              - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                - **Files Modified**:
                  - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                  - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                  - `admin/class-admin-manager.php` - Updated submenu registration
                - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                
              - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                - **Solution**: Enhanced the queue status indicator UI
                - **Changes Made**:
                  - Made queue status indicator more prominent with badges and icons
                  - Added list of queued items showing position and title
                  - Added notification when items are queued
                  - Added automatic queue status fetching after bulk generation
                  - Added function to fetch queue status on demand
                
                - **Result**: Users now clearly see when ideas are queued and their position in the queue
                
              - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                - Queue management service supporting up to 5 simultaneous generations
                - FIFO queue for excess generation requests
                - Real-time queue status display with active generations and positions
                - Automatic processing when slots become available
                - Individual and bulk generation support
                
              - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                - Database `generation_status` field for persistent status storage
                - Immediate UI updates when generation starts
                - Progress bar with detailed status messages
                - Fast refresh mode (2s) during active generations
                
              - **Live Log Viewer**: Tail generation logs in real-time
                - Incremental log loading (only new lines)
                - Color-coded log entries (error, warning, info, success)
                - Auto-scroll to bottom for new content
                - Automatic stop when generation completes
                - Shows generation completion status
                
              - **Error Recovery Features**: Comprehensive error handling and recovery
                - One-click retry for failed generations
                - Error messages stored in database
                - Automatic cleanup of stuck generations (10+ minutes)
                - Clear error feedback in UI
                
              - **Database Schema Updates**: New fields for generation tracking
                - `generation_status` - Current generation progress message
                - `generation_error` - Error message storage
                - `generation_started_at` - Generation start timestamp
                - `generation_completed_at` - Generation completion timestamp
                - Index on `generation_status` for performance
                
              - **New Services**: Generation queue management
                - `services/class-generation-queue.php` - Complete queue management system
                - Supports concurrent processing with queue overflow
                - Automatic retry and error recovery
                
              - **Brand Features Management System**: Complete internal linking management with:
                - New database table: `ai_blog_brand_features`
                - Full CRUD operations for brand features (services, pages, documents, etc.)
                - Four category types: informational_page, document, image, video
                - Active/inactive state management for features
                - Real-time search and category filtering
                - Grid layout with modern card-based design
                - Modal-based editing interface
                - Integration ready for AI content generation
                - Comprehensive error handling and logging
                
              - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                  - Updated all AI prompts to request 120-140 character meta descriptions
                  - Updated validation logic to check for 140-character limit instead of 160
                  - Applied changes to both Prompt Compiler Service and Anthropic Service
                - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                  - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                  - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                  - Only adds keyphrase if not already present to avoid duplication
                  - Applied to both Content Generator and Anthropic Service classes
                - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                  - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                  - Focus keyphrase appears first in filename followed by descriptive keywords
                  - Smart deduplication logic prevents overlap between keyphrase and content keywords
                  - Automatic propagation from content generation to image requirements
                  - Applied to both content images and featured images
                - Ensures all SEO elements meet current best practices
                
              - **Brand Features Management System**: Complete internal linking management with:
                - New database table: `ai_blog_brand_features`
                - Full CRUD operations for brand features (services, pages, documents, etc.)
                - Four category types: informational_page, document, image, video
                - Active/inactive state management for features
                - Real-time search and category filtering
                - Grid layout with modern card-based design
                - Modal-based editing interface
                - Integration ready for AI content generation
                - Comprehensive error handling and logging
                
              - **UI/UX Improvements**:
                - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                  - Added proper button styling with colors and hover effects
                  - Save button uses primary blue (#2271b1) with hover state
                  - Cancel button uses secondary gray (#f0f0f1) with hover state
                  - Added consistent padding, border radius, and transitions
                  
                - **Product Links Pill Design**: Enhanced product links display
                  - Added pill-style design with rounded borders and padding
                  - Colored type badges with specific colors for each link type
                  - Product Page links show green badge
                  - Purchase links show orange badge
                  - Documentation links show purple badge
                  - Other links show gray badge
                  - Added hover effects with shadow and transform
                  - Fixed link type labels to show proper text instead of database values
                  
                - **Products Page Redesign**: Applied modern design style to products page matching personas page
                  - Enhanced product cards with gradient backgrounds and hover effects
                  - Improved search box styling with focus states
                  - Modernized product modal with better form styling and section dividers
                  - Updated image and link management UI with better visual hierarchy
                  - Added colored badges for link types (product page, purchase, documentation)
                  - Improved pagination styling with better hover states
                  - Enhanced responsive design for mobile devices
                  
                - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                  - Enhanced context cards with improved shadows and hover effects
                  - Added gradient backgrounds to context type badges
                  - Improved badge styling for usage categories and always-include indicators
                  - Modernized context edit modal with better form controls
                  - Enhanced seed images section with better card design
                  - Updated seed image upload modal to match personas modal styling
                  - Improved button styling with hover effects and better spacing
                  - Added responsive design improvements for mobile devices
                  
                - **Layout Consistency**: Made all admin pages full-width
                  - Removed max-width restrictions from contexts and products pages
                  - All pages now use 100% width like the personas page
                  - Consistent layout across all admin sections
                  
                - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                  - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                  - Consistent icon style matching personas page design
                  - Applied same icon treatment to seed images section
                  - Better visual hierarchy and cleaner interface
                  
                - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                  - Added 'layout' as a valid enum option in the database
                  - Fixed JavaScript to display type labels instead of database values
                  - Added context type labels to JavaScript localization data
                  - Context cards now show "Layout Guidelines" instead of "layout" after saving
                  
                - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                  - Matches the persona cards' gradient line that appears on hover
                  - Uses the same blue-purple-pink gradient for consistency
                  - Provides visual feedback when hovering over context cards
                  
                - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                  - Replaced text buttons with icon buttons (edit and delete)
                  - Added gradient line hover effect matching other admin cards
                  - Improved typography and spacing consistency
                  - Updated color scheme to match modern design language
                  - Better visual hierarchy with icon-based actions
                  
                - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                  - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                  - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                  - **Files Modified**: 
                    - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                    - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                  - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                  
                - **Drafted Posts Table Width**: Fixed table only using half the screen width
                  - **Root Cause**: WordPress default `.wrap` class applies width constraints
                  - **Solution**: Added CSS overrides to make the page full width
                  - **CSS Changes**: 
                    - Override `.wrap` max-width constraint
                    - Ensure table and cards use 100% width
                    - Scoped WordPress admin overrides to drafted posts page only
                  - **Result**: Drafted posts table now uses full available screen width
                  
                - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                  - **Root Cause**: Script was enqueued but not localized with AJAX data
                  - **Solution**: Added `wp_localize_script` call for drafted posts script
                  - **Files Modified**: 
                    - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                  - **Result**: Drafted posts page now loads properly with AJAX functionality working
                  
                - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                  - **Root Cause**: Bootstrap card component was constraining table width
                  - **Solution**: Replaced card wrapper with custom div structure
                  - **Changes Made**: 
                    - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                    - Added custom CSS for table wrapper with full width
                    - Also updated filter actions bar to use consistent wrapper approach
                  - **Result**: Table now uses full available screen width without Bootstrap card constraints
                  
                - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                  - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                  - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                  - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                  - **Changes Made**:
                    - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                    - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                    - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                  - **Result**: Schedule post functionality now works correctly without fatal errors
                  
                - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                  - **Files Modified**:
                    - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                    - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                    - `admin/class-admin-manager.php` - Updated submenu registration
                  - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                  
                - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                  - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                  - **Solution**: Enhanced the queue status indicator UI
                  - **Changes Made**:
                    - Made queue status indicator more prominent with badges and icons
                    - Added list of queued items showing position and title
                    - Added notification when items are queued
                    - Added automatic queue status fetching after bulk generation
                    - Added function to fetch queue status on demand
                  
                  - **Result**: Users now clearly see when ideas are queued and their position in the queue
                  
                - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                  - Queue management service supporting up to 5 simultaneous generations
                  - FIFO queue for excess generation requests
                  - Real-time queue status display with active generations and positions
                  - Automatic processing when slots become available
                  - Individual and bulk generation support
                  
                - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                  - Database `generation_status` field for persistent status storage
                  - Immediate UI updates when generation starts
                  - Progress bar with detailed status messages
                  - Fast refresh mode (2s) during active generations
                  
                - **Live Log Viewer**: Tail generation logs in real-time
                  - Incremental log loading (only new lines)
                  - Color-coded log entries (error, warning, info, success)
                  - Auto-scroll to bottom for new content
                  - Automatic stop when generation completes
                  - Shows generation completion status
                  
                - **Error Recovery Features**: Comprehensive error handling and recovery
                  - One-click retry for failed generations
                  - Error messages stored in database
                  - Automatic cleanup of stuck generations (10+ minutes)
                  - Clear error feedback in UI
                  
                - **Database Schema Updates**: New fields for generation tracking
                  - `generation_status` - Current generation progress message
                  - `generation_error` - Error message storage
                  - `generation_started_at` - Generation start timestamp
                  - `generation_completed_at` - Generation completion timestamp
                  - Index on `generation_status` for performance
                  
                - **New Services**: Generation queue management
                  - `services/class-generation-queue.php` - Complete queue management system
                  - Supports concurrent processing with queue overflow
                  - Automatic retry and error recovery
                  
                - **Brand Features Management System**: Complete internal linking management with:
                  - New database table: `ai_blog_brand_features`
                  - Full CRUD operations for brand features (services, pages, documents, etc.)
                  - Four category types: informational_page, document, image, video
                  - Active/inactive state management for features
                  - Real-time search and category filtering
                  - Grid layout with modern card-based design
                  - Modal-based editing interface
                  - Integration ready for AI content generation
                  - Comprehensive error handling and logging
                  
                - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                  - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                    - Updated all AI prompts to request 120-140 character meta descriptions
                    - Updated validation logic to check for 140-character limit instead of 160
                    - Applied changes to both Prompt Compiler Service and Anthropic Service
                  - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                    - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                    - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                    - Only adds keyphrase if not already present to avoid duplication
                    - Applied to both Content Generator and Anthropic Service classes
                  - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                    - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                    - Focus keyphrase appears first in filename followed by descriptive keywords
                    - Smart deduplication logic prevents overlap between keyphrase and content keywords
                    - Automatic propagation from content generation to image requirements
                    - Applied to both content images and featured images
                  - Ensures all SEO elements meet current best practices
                  
                - **Brand Features Management System**: Complete internal linking management with:
                  - New database table: `ai_blog_brand_features`
                  - Full CRUD operations for brand features (services, pages, documents, etc.)
                  - Four category types: informational_page, document, image, video
                  - Active/inactive state management for features
                  - Real-time search and category filtering
                  - Grid layout with modern card-based design
                  - Modal-based editing interface
                  - Integration ready for AI content generation
                  - Comprehensive error handling and logging
                  
                - **UI/UX Improvements**:
                  - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                    - Added proper button styling with colors and hover effects
                    - Save button uses primary blue (#2271b1) with hover state
                    - Cancel button uses secondary gray (#f0f0f1) with hover state
                    - Added consistent padding, border radius, and transitions
                    
                  - **Product Links Pill Design**: Enhanced product links display
                    - Added pill-style design with rounded borders and padding
                    - Colored type badges with specific colors for each link type
                    - Product Page links show green badge
                    - Purchase links show orange badge
                    - Documentation links show purple badge
                    - Other links show gray badge
                    - Added hover effects with shadow and transform
                    - Fixed link type labels to show proper text instead of database values
                    
                  - **Products Page Redesign**: Applied modern design style to products page matching personas page
                    - Enhanced product cards with gradient backgrounds and hover effects
                    - Improved search box styling with focus states
                    - Modernized product modal with better form styling and section dividers
                    - Updated image and link management UI with better visual hierarchy
                    - Added colored badges for link types (product page, purchase, documentation)
                    - Improved pagination styling with better hover states
                    - Enhanced responsive design for mobile devices
                    
                  - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                    - Enhanced context cards with improved shadows and hover effects
                    - Added gradient backgrounds to context type badges
                    - Improved badge styling for usage categories and always-include indicators
                    - Modernized context edit modal with better form controls
                    - Enhanced seed images section with better card design
                    - Updated seed image upload modal to match personas modal styling
                    - Improved button styling with hover effects and better spacing
                    - Added responsive design improvements for mobile devices
                    
                  - **Layout Consistency**: Made all admin pages full-width
                    - Removed max-width restrictions from contexts and products pages
                    - All pages now use 100% width like the personas page
                    - Consistent layout across all admin sections
                    
                  - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                    - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                    - Consistent icon style matching personas page design
                    - Applied same icon treatment to seed images section
                    - Better visual hierarchy and cleaner interface
                    
                  - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                    - Added 'layout' as a valid enum option in the database
                    - Fixed JavaScript to display type labels instead of database values
                    - Added context type labels to JavaScript localization data
                    - Context cards now show "Layout Guidelines" instead of "layout" after saving
                    
                  - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                    - Matches the persona cards' gradient line that appears on hover
                    - Uses the same blue-purple-pink gradient for consistency
                    - Provides visual feedback when hovering over context cards
                    
                  - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                    - Replaced text buttons with icon buttons (edit and delete)
                    - Added gradient line hover effect matching other admin cards
                    - Improved typography and spacing consistency
                    - Updated color scheme to match modern design language
                    - Better visual hierarchy with icon-based actions
                    
                  - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                    - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                    - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                    - **Files Modified**: 
                      - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                      - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                    - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                    
                  - **Drafted Posts Table Width**: Fixed table only using half the screen width
                    - **Root Cause**: WordPress default `.wrap` class applies width constraints
                    - **Solution**: Added CSS overrides to make the page full width
                    - **CSS Changes**: 
                      - Override `.wrap` max-width constraint
                      - Ensure table and cards use 100% width
                      - Scoped WordPress admin overrides to drafted posts page only
                    - **Result**: Drafted posts table now uses full available screen width
                    
                  - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                    - **Root Cause**: Script was enqueued but not localized with AJAX data
                    - **Solution**: Added `wp_localize_script` call for drafted posts script
                    - **Files Modified**: 
                      - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                    - **Result**: Drafted posts page now loads properly with AJAX functionality working
                    
                  - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                    - **Root Cause**: Bootstrap card component was constraining table width
                    - **Solution**: Replaced card wrapper with custom div structure
                    - **Changes Made**: 
                      - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                      - Added custom CSS for table wrapper with full width
                      - Also updated filter actions bar to use consistent wrapper approach
                    - **Result**: Table now uses full available screen width without Bootstrap card constraints
                    
                  - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                    - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                    - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                    - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                    - **Changes Made**:
                      - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                      - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                      - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                    - **Result**: Schedule post functionality now works correctly without fatal errors
                    
                  - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                    - **Files Modified**:
                      - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                      - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                      - `admin/class-admin-manager.php` - Updated submenu registration
                    - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                    
                  - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                    - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                    - **Solution**: Enhanced the queue status indicator UI
                    - **Changes Made**:
                      - Made queue status indicator more prominent with badges and icons
                      - Added list of queued items showing position and title
                      - Added notification when items are queued
                      - Added automatic queue status fetching after bulk generation
                      - Added function to fetch queue status on demand
                    
                    - **Result**: Users now clearly see when ideas are queued and their position in the queue
                    
                  - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                    - Queue management service supporting up to 5 simultaneous generations
                    - FIFO queue for excess generation requests
                    - Real-time queue status display with active generations and positions
                    - Automatic processing when slots become available
                    - Individual and bulk generation support
                    
                  - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                    - Database `generation_status` field for persistent status storage
                    - Immediate UI updates when generation starts
                    - Progress bar with detailed status messages
                    - Fast refresh mode (2s) during active generations
                    
                  - **Live Log Viewer**: Tail generation logs in real-time
                    - Incremental log loading (only new lines)
                    - Color-coded log entries (error, warning, info, success)
                    - Auto-scroll to bottom for new content
                    - Automatic stop when generation completes
                    - Shows generation completion status
                    
                  - **Error Recovery Features**: Comprehensive error handling and recovery
                    - One-click retry for failed generations
                    - Error messages stored in database
                    - Automatic cleanup of stuck generations (10+ minutes)
                    - Clear error feedback in UI
                    
                  - **Database Schema Updates**: New fields for generation tracking
                    - `generation_status` - Current generation progress message
                    - `generation_error` - Error message storage
                    - `generation_started_at` - Generation start timestamp
                    - `generation_completed_at` - Generation completion timestamp
                    - Index on `generation_status` for performance
                    
                  - **New Services**: Generation queue management
                    - `services/class-generation-queue.php` - Complete queue management system
                    - Supports concurrent processing with queue overflow
                    - Automatic retry and error recovery
                    
                  - **Brand Features Management System**: Complete internal linking management with:
                    - New database table: `ai_blog_brand_features`
                    - Full CRUD operations for brand features (services, pages, documents, etc.)
                    - Four category types: informational_page, document, image, video
                    - Active/inactive state management for features
                    - Real-time search and category filtering
                    - Grid layout with modern card-based design
                    - Modal-based editing interface
                    - Integration ready for AI content generation
                    - Comprehensive error handling and logging
                    
                  - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                    - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                      - Updated all AI prompts to request 120-140 character meta descriptions
                      - Updated validation logic to check for 140-character limit instead of 160
                      - Applied changes to both Prompt Compiler Service and Anthropic Service
                    - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                      - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                      - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                      - Only adds keyphrase if not already present to avoid duplication
                      - Applied to both Content Generator and Anthropic Service classes
                    - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                      - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                      - Focus keyphrase appears first in filename followed by descriptive keywords
                      - Smart deduplication logic prevents overlap between keyphrase and content keywords
                      - Automatic propagation from content generation to image requirements
                      - Applied to both content images and featured images
                    - Ensures all SEO elements meet current best practices
                    
                  - **Brand Features Management System**: Complete internal linking management with:
                    - New database table: `ai_blog_brand_features`
                    - Full CRUD operations for brand features (services, pages, documents, etc.)
                    - Four category types: informational_page, document, image, video
                    - Active/inactive state management for features
                    - Real-time search and category filtering
                    - Grid layout with modern card-based design
                    - Modal-based editing interface
                    - Integration ready for AI content generation
                    - Comprehensive error handling and logging
                    
                  - **UI/UX Improvements**:
                    - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                      - Added proper button styling with colors and hover effects
                      - Save button uses primary blue (#2271b1) with hover state
                      - Cancel button uses secondary gray (#f0f0f1) with hover state
                      - Added consistent padding, border radius, and transitions
                      
                    - **Product Links Pill Design**: Enhanced product links display
                      - Added pill-style design with rounded borders and padding
                      - Colored type badges with specific colors for each link type
                      - Product Page links show green badge
                      - Purchase links show orange badge
                      - Documentation links show purple badge
                      - Other links show gray badge
                      - Added hover effects with shadow and transform
                      - Fixed link type labels to show proper text instead of database values
                      
                    - **Products Page Redesign**: Applied modern design style to products page matching personas page
                      - Enhanced product cards with gradient backgrounds and hover effects
                      - Improved search box styling with focus states
                      - Modernized product modal with better form styling and section dividers
                      - Updated image and link management UI with better visual hierarchy
                      - Added colored badges for link types (product page, purchase, documentation)
                      - Improved pagination styling with better hover states
                      - Enhanced responsive design for mobile devices
                      
                    - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                      - Enhanced context cards with improved shadows and hover effects
                      - Added gradient backgrounds to context type badges
                      - Improved badge styling for usage categories and always-include indicators
                      - Modernized context edit modal with better form controls
                      - Enhanced seed images section with better card design
                      - Updated seed image upload modal to match personas modal styling
                      - Improved button styling with hover effects and better spacing
                      - Added responsive design improvements for mobile devices
                      
                    - **Layout Consistency**: Made all admin pages full-width
                      - Removed max-width restrictions from contexts and products pages
                      - All pages now use 100% width like the personas page
                      - Consistent layout across all admin sections
                      
                    - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                      - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                      - Consistent icon style matching personas page design
                      - Applied same icon treatment to seed images section
                      - Better visual hierarchy and cleaner interface
                      
                    - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                      - Added 'layout' as a valid enum option in the database
                      - Fixed JavaScript to display type labels instead of database values
                      - Added context type labels to JavaScript localization data
                      - Context cards now show "Layout Guidelines" instead of "layout" after saving
                      
                    - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                      - Matches the persona cards' gradient line that appears on hover
                      - Uses the same blue-purple-pink gradient for consistency
                      - Provides visual feedback when hovering over context cards
                      
                    - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                      - Replaced text buttons with icon buttons (edit and delete)
                      - Added gradient line hover effect matching other admin cards
                      - Improved typography and spacing consistency
                      - Updated color scheme to match modern design language
                      - Better visual hierarchy with icon-based actions
                      
                    - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                      - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                      - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                      - **Files Modified**: 
                        - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                        - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                      - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                      
                    - **Drafted Posts Table Width**: Fixed table only using half the screen width
                      - **Root Cause**: WordPress default `.wrap` class applies width constraints
                      - **Solution**: Added CSS overrides to make the page full width
                      - **CSS Changes**: 
                        - Override `.wrap` max-width constraint
                        - Ensure table and cards use 100% width
                        - Scoped WordPress admin overrides to drafted posts page only
                      - **Result**: Drafted posts table now uses full available screen width
                      
                    - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                      - **Root Cause**: Script was enqueued but not localized with AJAX data
                      - **Solution**: Added `wp_localize_script` call for drafted posts script
                      - **Files Modified**: 
                        - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                      - **Result**: Drafted posts page now loads properly with AJAX functionality working
                      
                    - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                      - **Root Cause**: Bootstrap card component was constraining table width
                      - **Solution**: Replaced card wrapper with custom div structure
                      - **Changes Made**: 
                        - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                        - Added custom CSS for table wrapper with full width
                        - Also updated filter actions bar to use consistent wrapper approach
                      - **Result**: Table now uses full available screen width without Bootstrap card constraints
                      
                    - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                      - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                      - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                      - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                      - **Changes Made**:
                        - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                        - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                        - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                      - **Result**: Schedule post functionality now works correctly without fatal errors
                      
                    - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                      - **Files Modified**:
                        - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                        - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                        - `admin/class-admin-manager.php` - Updated submenu registration
                      - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                      
                    - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                      - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                      - **Solution**: Enhanced the queue status indicator UI
                      - **Changes Made**:
                        - Made queue status indicator more prominent with badges and icons
                        - Added list of queued items showing position and title
                        - Added notification when items are queued
                        - Added automatic queue status fetching after bulk generation
                        - Added function to fetch queue status on demand
                      
                      - **Result**: Users now clearly see when ideas are queued and their position in the queue
                      
                    - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                      - Queue management service supporting up to 5 simultaneous generations
                      - FIFO queue for excess generation requests
                      - Real-time queue status display with active generations and positions
                      - Automatic processing when slots become available
                      - Individual and bulk generation support
                      
                    - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                      - Database `generation_status` field for persistent status storage
                      - Immediate UI updates when generation starts
                      - Progress bar with detailed status messages
                      - Fast refresh mode (2s) during active generations
                      
                    - **Live Log Viewer**: Tail generation logs in real-time
                      - Incremental log loading (only new lines)
                      - Color-coded log entries (error, warning, info, success)
                      - Auto-scroll to bottom for new content
                      - Automatic stop when generation completes
                      - Shows generation completion status
                      
                    - **Error Recovery Features**: Comprehensive error handling and recovery
                      - One-click retry for failed generations
                      - Error messages stored in database
                      - Automatic cleanup of stuck generations (10+ minutes)
                      - Clear error feedback in UI
                      
                    - **Database Schema Updates**: New fields for generation tracking
                      - `generation_status` - Current generation progress message
                      - `generation_error` - Error message storage
                      - `generation_started_at` - Generation start timestamp
                      - `generation_completed_at` - Generation completion timestamp
                      - Index on `generation_status` for performance
                      
                    - **New Services**: Generation queue management
                      - `services/class-generation-queue.php` - Complete queue management system
                      - Supports concurrent processing with queue overflow
                      - Automatic retry and error recovery
                      
                    - **Brand Features Management System**: Complete internal linking management with:
                      - New database table: `ai_blog_brand_features`
                      - Full CRUD operations for brand features (services, pages, documents, etc.)
                      - Four category types: informational_page, document, image, video
                      - Active/inactive state management for features
                      - Real-time search and category filtering
                      - Grid layout with modern card-based design
                      - Modal-based editing interface
                      - Integration ready for AI content generation
                      - Comprehensive error handling and logging
                      
                    - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                      - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                        - Updated all AI prompts to request 120-140 character meta descriptions
                        - Updated validation logic to check for 140-character limit instead of 160
                        - Applied changes to both Prompt Compiler Service and Anthropic Service
                      - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                        - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                        - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                        - Only adds keyphrase if not already present to avoid duplication
                        - Applied to both Content Generator and Anthropic Service classes
                      - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                        - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                        - Focus keyphrase appears first in filename followed by descriptive keywords
                        - Smart deduplication logic prevents overlap between keyphrase and content keywords
                        - Automatic propagation from content generation to image requirements
                        - Applied to both content images and featured images
                      - Ensures all SEO elements meet current best practices
                      
                    - **Brand Features Management System**: Complete internal linking management with:
                      - New database table: `ai_blog_brand_features`
                      - Full CRUD operations for brand features (services, pages, documents, etc.)
                      - Four category types: informational_page, document, image, video
                      - Active/inactive state management for features
                      - Real-time search and category filtering
                      - Grid layout with modern card-based design
                      - Modal-based editing interface
                      - Integration ready for AI content generation
                      - Comprehensive error handling and logging
                      
                    - **UI/UX Improvements**:
                      - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                        - Added proper button styling with colors and hover effects
                        - Save button uses primary blue (#2271b1) with hover state
                        - Cancel button uses secondary gray (#f0f0f1) with hover state
                        - Added consistent padding, border radius, and transitions
                        
                      - **Product Links Pill Design**: Enhanced product links display
                        - Added pill-style design with rounded borders and padding
                        - Colored type badges with specific colors for each link type
                        - Product Page links show green badge
                        - Purchase links show orange badge
                        - Documentation links show purple badge
                        - Other links show gray badge
                        - Added hover effects with shadow and transform
                        - Fixed link type labels to show proper text instead of database values
                        
                      - **Products Page Redesign**: Applied modern design style to products page matching personas page
                        - Enhanced product cards with gradient backgrounds and hover effects
                        - Improved search box styling with focus states
                        - Modernized product modal with better form styling and section dividers
                        - Updated image and link management UI with better visual hierarchy
                        - Added colored badges for link types (product page, purchase, documentation)
                        - Improved pagination styling with better hover states
                        - Enhanced responsive design for mobile devices
                        
                      - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                        - Enhanced context cards with improved shadows and hover effects
                        - Added gradient backgrounds to context type badges
                        - Improved badge styling for usage categories and always-include indicators
                        - Modernized context edit modal with better form controls
                        - Enhanced seed images section with better card design
                        - Updated seed image upload modal to match personas modal styling
                        - Improved button styling with hover effects and better spacing
                        - Added responsive design improvements for mobile devices
                        
                      - **Layout Consistency**: Made all admin pages full-width
                        - Removed max-width restrictions from contexts and products pages
                        - All pages now use 100% width like the personas page
                        - Consistent layout across all admin sections
                        
                      - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                        - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                        - Consistent icon style matching personas page design
                        - Applied same icon treatment to seed images section
                        - Better visual hierarchy and cleaner interface
                        
                      - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                        - Added 'layout' as a valid enum option in the database
                        - Fixed JavaScript to display type labels instead of database values
                        - Added context type labels to JavaScript localization data
                        - Context cards now show "Layout Guidelines" instead of "layout" after saving
                        
                      - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                        - Matches the persona cards' gradient line that appears on hover
                        - Uses the same blue-purple-pink gradient for consistency
                        - Provides visual feedback when hovering over context cards
                        
                      - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                        - Replaced text buttons with icon buttons (edit and delete)
                        - Added gradient line hover effect matching other admin cards
                        - Improved typography and spacing consistency
                        - Updated color scheme to match modern design language
                        - Better visual hierarchy with icon-based actions
                        
                      - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                        - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                        - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                        - **Files Modified**: 
                          - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                          - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                        - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                        
                      - **Drafted Posts Table Width**: Fixed table only using half the screen width
                        - **Root Cause**: WordPress default `.wrap` class applies width constraints
                        - **Solution**: Added CSS overrides to make the page full width
                        - **CSS Changes**: 
                          - Override `.wrap` max-width constraint
                          - Ensure table and cards use 100% width
                          - Scoped WordPress admin overrides to drafted posts page only
                        - **Result**: Drafted posts table now uses full available screen width
                        
                      - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                        - **Root Cause**: Script was enqueued but not localized with AJAX data
                        - **Solution**: Added `wp_localize_script` call for drafted posts script
                        - **Files Modified**: 
                          - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                        - **Result**: Drafted posts page now loads properly with AJAX functionality working
                        
                      - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                        - **Root Cause**: Bootstrap card component was constraining table width
                        - **Solution**: Replaced card wrapper with custom div structure
                        - **Changes Made**: 
                          - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                          - Added custom CSS for table wrapper with full width
                          - Also updated filter actions bar to use consistent wrapper approach
                        - **Result**: Table now uses full available screen width without Bootstrap card constraints
                        
                      - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                        - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                        - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                        - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                        - **Changes Made**:
                          - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                          - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                          - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                        - **Result**: Schedule post functionality now works correctly without fatal errors
                        
                      - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                        - **Files Modified**:
                          - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                          - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                          - `admin/class-admin-manager.php` - Updated submenu registration
                        - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                        
                      - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                        - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                        - **Solution**: Enhanced the queue status indicator UI
                        - **Changes Made**:
                          - Made queue status indicator more prominent with badges and icons
                          - Added list of queued items showing position and title
                          - Added notification when items are queued
                          - Added automatic queue status fetching after bulk generation
                          - Added function to fetch queue status on demand
                        
                        - **Result**: Users now clearly see when ideas are queued and their position in the queue
                        
                      - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                        - Queue management service supporting up to 5 simultaneous generations
                        - FIFO queue for excess generation requests
                        - Real-time queue status display with active generations and positions
                        - Automatic processing when slots become available
                        - Individual and bulk generation support
                        
                      - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                        - Database `generation_status` field for persistent status storage
                        - Immediate UI updates when generation starts
                        - Progress bar with detailed status messages
                        - Fast refresh mode (2s) during active generations
                        
                      - **Live Log Viewer**: Tail generation logs in real-time
                        - Incremental log loading (only new lines)
                        - Color-coded log entries (error, warning, info, success)
                        - Auto-scroll to bottom for new content
                        - Automatic stop when generation completes
                        - Shows generation completion status
                        
                      - **Error Recovery Features**: Comprehensive error handling and recovery
                        - One-click retry for failed generations
                        - Error messages stored in database
                        - Automatic cleanup of stuck generations (10+ minutes)
                        - Clear error feedback in UI
                        
                      - **Database Schema Updates**: New fields for generation tracking
                        - `generation_status` - Current generation progress message
                        - `generation_error` - Error message storage
                        - `generation_started_at` - Generation start timestamp
                        - `generation_completed_at` - Generation completion timestamp
                        - Index on `generation_status` for performance
                        
                      - **New Services**: Generation queue management
                        - `services/class-generation-queue.php` - Complete queue management system
                        - Supports concurrent processing with queue overflow
                        - Automatic retry and error recovery
                        
                      - **Brand Features Management System**: Complete internal linking management with:
                        - New database table: `ai_blog_brand_features`
                        - Full CRUD operations for brand features (services, pages, documents, etc.)
                        - Four category types: informational_page, document, image, video
                        - Active/inactive state management for features
                        - Real-time search and category filtering
                        - Grid layout with modern card-based design
                        - Modal-based editing interface
                        - Integration ready for AI content generation
                        - Comprehensive error handling and logging
                        
                      - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                        - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                          - Updated all AI prompts to request 120-140 character meta descriptions
                          - Updated validation logic to check for 140-character limit instead of 160
                          - Applied changes to both Prompt Compiler Service and Anthropic Service
                        - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                          - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                          - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                          - Only adds keyphrase if not already present to avoid duplication
                          - Applied to both Content Generator and Anthropic Service classes
                        - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                          - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                          - Focus keyphrase appears first in filename followed by descriptive keywords
                          - Smart deduplication logic prevents overlap between keyphrase and content keywords
                          - Automatic propagation from content generation to image requirements
                          - Applied to both content images and featured images
                        - Ensures all SEO elements meet current best practices
                        
                      - **Brand Features Management System**: Complete internal linking management with:
                        - New database table: `ai_blog_brand_features`
                        - Full CRUD operations for brand features (services, pages, documents, etc.)
                        - Four category types: informational_page, document, image, video
                        - Active/inactive state management for features
                        - Real-time search and category filtering
                        - Grid layout with modern card-based design
                        - Modal-based editing interface
                        - Integration ready for AI content generation
                        - Comprehensive error handling and logging
                        
                      - **UI/UX Improvements**:
                        - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                          - Added proper button styling with colors and hover effects
                          - Save button uses primary blue (#2271b1) with hover state
                          - Cancel button uses secondary gray (#f0f0f1) with hover state
                          - Added consistent padding, border radius, and transitions
                          
                        - **Product Links Pill Design**: Enhanced product links display
                          - Added pill-style design with rounded borders and padding
                          - Colored type badges with specific colors for each link type
                          - Product Page links show green badge
                          - Purchase links show orange badge
                          - Documentation links show purple badge
                          - Other links show gray badge
                          - Added hover effects with shadow and transform
                          - Fixed link type labels to show proper text instead of database values
                          
                        - **Products Page Redesign**: Applied modern design style to products page matching personas page
                          - Enhanced product cards with gradient backgrounds and hover effects
                          - Improved search box styling with focus states
                          - Modernized product modal with better form styling and section dividers
                          - Updated image and link management UI with better visual hierarchy
                          - Added colored badges for link types (product page, purchase, documentation)
                          - Improved pagination styling with better hover states
                          - Enhanced responsive design for mobile devices
                          
                        - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                          - Enhanced context cards with improved shadows and hover effects
                          - Added gradient backgrounds to context type badges
                          - Improved badge styling for usage categories and always-include indicators
                          - Modernized context edit modal with better form controls
                          - Enhanced seed images section with better card design
                          - Updated seed image upload modal to match personas modal styling
                          - Improved button styling with hover effects and better spacing
                          - Added responsive design improvements for mobile devices
                          
                        - **Layout Consistency**: Made all admin pages full-width
                          - Removed max-width restrictions from contexts and products pages
                          - All pages now use 100% width like the personas page
                          - Consistent layout across all admin sections
                          
                        - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                          - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                          - Consistent icon style matching personas page design
                          - Applied same icon treatment to seed images section
                          - Better visual hierarchy and cleaner interface
                          
                        - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                          - Added 'layout' as a valid enum option in the database
                          - Fixed JavaScript to display type labels instead of database values
                          - Added context type labels to JavaScript localization data
                          - Context cards now show "Layout Guidelines" instead of "layout" after saving
                          
                        - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                          - Matches the persona cards' gradient line that appears on hover
                          - Uses the same blue-purple-pink gradient for consistency
                          - Provides visual feedback when hovering over context cards
                          
                        - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                          - Replaced text buttons with icon buttons (edit and delete)
                          - Added gradient line hover effect matching other admin cards
                          - Improved typography and spacing consistency
                          - Updated color scheme to match modern design language
                          - Better visual hierarchy with icon-based actions
                          
                        - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                          - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                          - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                          - **Files Modified**: 
                            - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                            - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                          - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                          
                        - **Drafted Posts Table Width**: Fixed table only using half the screen width
                          - **Root Cause**: WordPress default `.wrap` class applies width constraints
                          - **Solution**: Added CSS overrides to make the page full width
                          - **CSS Changes**: 
                            - Override `.wrap` max-width constraint
                            - Ensure table and cards use 100% width
                            - Scoped WordPress admin overrides to drafted posts page only
                          - **Result**: Drafted posts table now uses full available screen width
                          
                        - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                          - **Root Cause**: Script was enqueued but not localized with AJAX data
                          - **Solution**: Added `wp_localize_script` call for drafted posts script
                          - **Files Modified**: 
                            - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                          - **Result**: Drafted posts page now loads properly with AJAX functionality working
                          
                        - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                          - **Root Cause**: Bootstrap card component was constraining table width
                          - **Solution**: Replaced card wrapper with custom div structure
                          - **Changes Made**: 
                            - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                            - Added custom CSS for table wrapper with full width
                            - Also updated filter actions bar to use consistent wrapper approach
                          - **Result**: Table now uses full available screen width without Bootstrap card constraints
                          
                        - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                          - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                          - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                          - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                          - **Changes Made**:
                            - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                            - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                            - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                          - **Result**: Schedule post functionality now works correctly without fatal errors
                          
                        - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                          - **Files Modified**:
                            - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                            - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                            - `admin/class-admin-manager.php` - Updated submenu registration
                          - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                          
                        - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                          - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                          - **Solution**: Enhanced the queue status indicator UI
                          - **Changes Made**:
                            - Made queue status indicator more prominent with badges and icons
                            - Added list of queued items showing position and title
                            - Added notification when items are queued
                            - Added automatic queue status fetching after bulk generation
                            - Added function to fetch queue status on demand
                          
                          - **Result**: Users now clearly see when ideas are queued and their position in the queue
                          
                        - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                          - Queue management service supporting up to 5 simultaneous generations
                          - FIFO queue for excess generation requests
                          - Real-time queue status display with active generations and positions
                          - Automatic processing when slots become available
                          - Individual and bulk generation support
                          
                        - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                          - Database `generation_status` field for persistent status storage
                          - Immediate UI updates when generation starts
                          - Progress bar with detailed status messages
                          - Fast refresh mode (2s) during active generations
                          
                        - **Live Log Viewer**: Tail generation logs in real-time
                          - Incremental log loading (only new lines)
                          - Color-coded log entries (error, warning, info, success)
                          - Auto-scroll to bottom for new content
                          - Automatic stop when generation completes
                          - Shows generation completion status
                          
                        - **Error Recovery Features**: Comprehensive error handling and recovery
                          - One-click retry for failed generations
                          - Error messages stored in database
                          - Automatic cleanup of stuck generations (10+ minutes)
                          - Clear error feedback in UI
                          
                        - **Database Schema Updates**: New fields for generation tracking
                          - `generation_status` - Current generation progress message
                          - `generation_error` - Error message storage
                          - `generation_started_at` - Generation start timestamp
                          - `generation_completed_at` - Generation completion timestamp
                          - Index on `generation_status` for performance
                          
                        - **New Services**: Generation queue management
                          - `services/class-generation-queue.php` - Complete queue management system
                          - Supports concurrent processing with queue overflow
                          - Automatic retry and error recovery
                          
                        - **Brand Features Management System**: Complete internal linking management with:
                          - New database table: `ai_blog_brand_features`
                          - Full CRUD operations for brand features (services, pages, documents, etc.)
                          - Four category types: informational_page, document, image, video
                          - Active/inactive state management for features
                          - Real-time search and category filtering
                          - Grid layout with modern card-based design
                          - Modal-based editing interface
                          - Integration ready for AI content generation
                          - Comprehensive error handling and logging
                          
                        - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                          - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                            - Updated all AI prompts to request 120-140 character meta descriptions
                            - Updated validation logic to check for 140-character limit instead of 160
                            - Applied changes to both Prompt Compiler Service and Anthropic Service
                          - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                            - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                            - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                            - Only adds keyphrase if not already present to avoid duplication
                            - Applied to both Content Generator and Anthropic Service classes
                          - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                            - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                            - Focus keyphrase appears first in filename followed by descriptive keywords
                            - Smart deduplication logic prevents overlap between keyphrase and content keywords
                            - Automatic propagation from content generation to image requirements
                            - Applied to both content images and featured images
                          - Ensures all SEO elements meet current best practices
                          
                        - **Brand Features Management System**: Complete internal linking management with:
                          - New database table: `ai_blog_brand_features`
                          - Full CRUD operations for brand features (services, pages, documents, etc.)
                          - Four category types: informational_page, document, image, video
                          - Active/inactive state management for features
                          - Real-time search and category filtering
                          - Grid layout with modern card-based design
                          - Modal-based editing interface
                          - Integration ready for AI content generation
                          - Comprehensive error handling and logging
                          
                        - **UI/UX Improvements**:
                          - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                            - Added proper button styling with colors and hover effects
                            - Save button uses primary blue (#2271b1) with hover state
                            - Cancel button uses secondary gray (#f0f0f1) with hover state
                            - Added consistent padding, border radius, and transitions
                            
                          - **Product Links Pill Design**: Enhanced product links display
                            - Added pill-style design with rounded borders and padding
                            - Colored type badges with specific colors for each link type
                            - Product Page links show green badge
                            - Purchase links show orange badge
                            - Documentation links show purple badge
                            - Other links show gray badge
                            - Added hover effects with shadow and transform
                            - Fixed link type labels to show proper text instead of database values
                            
                          - **Products Page Redesign**: Applied modern design style to products page matching personas page
                            - Enhanced product cards with gradient backgrounds and hover effects
                            - Improved search box styling with focus states
                            - Modernized product modal with better form styling and section dividers
                            - Updated image and link management UI with better visual hierarchy
                            - Added colored badges for link types (product page, purchase, documentation)
                            - Improved pagination styling with better hover states
                            - Enhanced responsive design for mobile devices
                            
                          - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                            - Enhanced context cards with improved shadows and hover effects
                            - Added gradient backgrounds to context type badges
                            - Improved badge styling for usage categories and always-include indicators
                            - Modernized context edit modal with better form controls
                            - Enhanced seed images section with better card design
                            - Updated seed image upload modal to match personas modal styling
                            - Improved button styling with hover effects and better spacing
                            - Added responsive design improvements for mobile devices
                            
                          - **Layout Consistency**: Made all admin pages full-width
                            - Removed max-width restrictions from contexts and products pages
                            - All pages now use 100% width like the personas page
                            - Consistent layout across all admin sections
                            
                          - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                            - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                            - Consistent icon style matching personas page design
                            - Applied same icon treatment to seed images section
                            - Better visual hierarchy and cleaner interface
                            
                          - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                            - Added 'layout' as a valid enum option in the database
                            - Fixed JavaScript to display type labels instead of database values
                            - Added context type labels to JavaScript localization data
                            - Context cards now show "Layout Guidelines" instead of "layout" after saving
                            
                          - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                            - Matches the persona cards' gradient line that appears on hover
                            - Uses the same blue-purple-pink gradient for consistency
                            - Provides visual feedback when hovering over context cards
                            
                          - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                            - Replaced text buttons with icon buttons (edit and delete)
                            - Added gradient line hover effect matching other admin cards
                            - Improved typography and spacing consistency
                            - Updated color scheme to match modern design language
                            - Better visual hierarchy with icon-based actions
                            
                          - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                            - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                            - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                            - **Files Modified**: 
                              - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                              - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                            - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                            
                          - **Drafted Posts Table Width**: Fixed table only using half the screen width
                            - **Root Cause**: WordPress default `.wrap` class applies width constraints
                            - **Solution**: Added CSS overrides to make the page full width
                            - **CSS Changes**: 
                              - Override `.wrap` max-width constraint
                              - Ensure table and cards use 100% width
                              - Scoped WordPress admin overrides to drafted posts page only
                            - **Result**: Drafted posts table now uses full available screen width
                            
                          - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                            - **Root Cause**: Script was enqueued but not localized with AJAX data
                            - **Solution**: Added `wp_localize_script` call for drafted posts script
                            - **Files Modified**: 
                              - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                            - **Result**: Drafted posts page now loads properly with AJAX functionality working
                            
                          - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                            - **Root Cause**: Bootstrap card component was constraining table width
                            - **Solution**: Replaced card wrapper with custom div structure
                            - **Changes Made**: 
                              - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                              - Added custom CSS for table wrapper with full width
                              - Also updated filter actions bar to use consistent wrapper approach
                            - **Result**: Table now uses full available screen width without Bootstrap card constraints
                            
                          - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                            - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                            - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                            - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                            - **Changes Made**:
                              - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                              - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                              - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                            - **Result**: Schedule post functionality now works correctly without fatal errors
                            
                          - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                            - **Files Modified**:
                              - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                              - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                              - `admin/class-admin-manager.php` - Updated submenu registration
                            - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                            
                          - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                            - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                            - **Solution**: Enhanced the queue status indicator UI
                            - **Changes Made**:
                              - Made queue status indicator more prominent with badges and icons
                              - Added list of queued items showing position and title
                              - Added notification when items are queued
                              - Added automatic queue status fetching after bulk generation
                              - Added function to fetch queue status on demand
                            
                            - **Result**: Users now clearly see when ideas are queued and their position in the queue
                            
                          - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                            - Queue management service supporting up to 5 simultaneous generations
                            - FIFO queue for excess generation requests
                            - Real-time queue status display with active generations and positions
                            - Automatic processing when slots become available
                            - Individual and bulk generation support
                            
                          - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                            - Database `generation_status` field for persistent status storage
                            - Immediate UI updates when generation starts
                            - Progress bar with detailed status messages
                            - Fast refresh mode (2s) during active generations
                            
                          - **Live Log Viewer**: Tail generation logs in real-time
                            - Incremental log loading (only new lines)
                            - Color-coded log entries (error, warning, info, success)
                            - Auto-scroll to bottom for new content
                            - Automatic stop when generation completes
                            - Shows generation completion status
                            
                          - **Error Recovery Features**: Comprehensive error handling and recovery
                            - One-click retry for failed generations
                            - Error messages stored in database
                            - Automatic cleanup of stuck generations (10+ minutes)
                            - Clear error feedback in UI
                            
                          - **Database Schema Updates**: New fields for generation tracking
                            - `generation_status` - Current generation progress message
                            - `generation_error` - Error message storage
                            - `generation_started_at` - Generation start timestamp
                            - `generation_completed_at` - Generation completion timestamp
                            - Index on `generation_status` for performance
                            
                          - **New Services**: Generation queue management
                            - `services/class-generation-queue.php` - Complete queue management system
                            - Supports concurrent processing with queue overflow
                            - Automatic retry and error recovery
                            
                          - **Brand Features Management System**: Complete internal linking management with:
                            - New database table: `ai_blog_brand_features`
                            - Full CRUD operations for brand features (services, pages, documents, etc.)
                            - Four category types: informational_page, document, image, video
                            - Active/inactive state management for features
                            - Real-time search and category filtering
                            - Grid layout with modern card-based design
                            - Modal-based editing interface
                            - Integration ready for AI content generation
                            - Comprehensive error handling and logging
                            
                          - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                            - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                              - Updated all AI prompts to request 120-140 character meta descriptions
                              - Updated validation logic to check for 140-character limit instead of 160
                              - Applied changes to both Prompt Compiler Service and Anthropic Service
                            - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                              - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                              - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                              - Only adds keyphrase if not already present to avoid duplication
                              - Applied to both Content Generator and Anthropic Service classes
                            - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                              - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                              - Focus keyphrase appears first in filename followed by descriptive keywords
                              - Smart deduplication logic prevents overlap between keyphrase and content keywords
                              - Automatic propagation from content generation to image requirements
                              - Applied to both content images and featured images
                            - Ensures all SEO elements meet current best practices
                            
                          - **Brand Features Management System**: Complete internal linking management with:
                            - New database table: `ai_blog_brand_features`
                            - Full CRUD operations for brand features (services, pages, documents, etc.)
                            - Four category types: informational_page, document, image, video
                            - Active/inactive state management for features
                            - Real-time search and category filtering
                            - Grid layout with modern card-based design
                            - Modal-based editing interface
                            - Integration ready for AI content generation
                            - Comprehensive error handling and logging
                            
                          - **UI/UX Improvements**:
                            - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                              - Added proper button styling with colors and hover effects
                              - Save button uses primary blue (#2271b1) with hover state
                              - Cancel button uses secondary gray (#f0f0f1) with hover state
                              - Added consistent padding, border radius, and transitions
                              
                            - **Product Links Pill Design**: Enhanced product links display
                              - Added pill-style design with rounded borders and padding
                              - Colored type badges with specific colors for each link type
                              - Product Page links show green badge
                              - Purchase links show orange badge
                              - Documentation links show purple badge
                              - Other links show gray badge
                              - Added hover effects with shadow and transform
                              - Fixed link type labels to show proper text instead of database values
                              
                            - **Products Page Redesign**: Applied modern design style to products page matching personas page
                              - Enhanced product cards with gradient backgrounds and hover effects
                              - Improved search box styling with focus states
                              - Modernized product modal with better form styling and section dividers
                              - Updated image and link management UI with better visual hierarchy
                              - Added colored badges for link types (product page, purchase, documentation)
                              - Improved pagination styling with better hover states
                              - Enhanced responsive design for mobile devices
                              
                            - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                              - Enhanced context cards with improved shadows and hover effects
                              - Added gradient backgrounds to context type badges
                              - Improved badge styling for usage categories and always-include indicators
                              - Modernized context edit modal with better form controls
                              - Enhanced seed images section with better card design
                              - Updated seed image upload modal to match personas modal styling
                              - Improved button styling with hover effects and better spacing
                              - Added responsive design improvements for mobile devices
                              
                            - **Layout Consistency**: Made all admin pages full-width
                              - Removed max-width restrictions from contexts and products pages
                              - All pages now use 100% width like the personas page
                              - Consistent layout across all admin sections
                              
                            - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                              - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                              - Consistent icon style matching personas page design
                              - Applied same icon treatment to seed images section
                              - Better visual hierarchy and cleaner interface
                              
                            - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                              - Added 'layout' as a valid enum option in the database
                              - Fixed JavaScript to display type labels instead of database values
                              - Added context type labels to JavaScript localization data
                              - Context cards now show "Layout Guidelines" instead of "layout" after saving
                              
                            - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                              - Matches the persona cards' gradient line that appears on hover
                              - Uses the same blue-purple-pink gradient for consistency
                              - Provides visual feedback when hovering over context cards
                              
                            - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                              - Replaced text buttons with icon buttons (edit and delete)
                              - Added gradient line hover effect matching other admin cards
                              - Improved typography and spacing consistency
                              - Updated color scheme to match modern design language
                              - Better visual hierarchy with icon-based actions
                              
                            - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                              - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                              - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                              - **Files Modified**: 
                                - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                                - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                              - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                              
                            - **Drafted Posts Table Width**: Fixed table only using half the screen width
                              - **Root Cause**: WordPress default `.wrap` class applies width constraints
                              - **Solution**: Added CSS overrides to make the page full width
                              - **CSS Changes**: 
                                - Override `.wrap` max-width constraint
                                - Ensure table and cards use 100% width
                                - Scoped WordPress admin overrides to drafted posts page only
                              - **Result**: Drafted posts table now uses full available screen width
                              
                            - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                              - **Root Cause**: Script was enqueued but not localized with AJAX data
                              - **Solution**: Added `wp_localize_script` call for drafted posts script
                              - **Files Modified**: 
                                - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                              - **Result**: Drafted posts page now loads properly with AJAX functionality working
                              
                            - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                              - **Root Cause**: Bootstrap card component was constraining table width
                              - **Solution**: Replaced card wrapper with custom div structure
                              - **Changes Made**: 
                                - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                                - Added custom CSS for table wrapper with full width
                                - Also updated filter actions bar to use consistent wrapper approach
                              - **Result**: Table now uses full available screen width without Bootstrap card constraints
                              
                            - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                              - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                              - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                              - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                              - **Changes Made**:
                                - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                                - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                                - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                              - **Result**: Schedule post functionality now works correctly without fatal errors
                              
                            - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                              - **Files Modified**:
                                - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                                - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                                - `admin/class-admin-manager.php` - Updated submenu registration
                              - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                              
                            - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                              - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                              - **Solution**: Enhanced the queue status indicator UI
                              - **Changes Made**:
                                - Made queue status indicator more prominent with badges and icons
                                - Added list of queued items showing position and title
                                - Added notification when items are queued
                                - Added automatic queue status fetching after bulk generation
                                - Added function to fetch queue status on demand
                              
                              - **Result**: Users now clearly see when ideas are queued and their position in the queue
                              
                            - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                              - Queue management service supporting up to 5 simultaneous generations
                              - FIFO queue for excess generation requests
                              - Real-time queue status display with active generations and positions
                              - Automatic processing when slots become available
                              - Individual and bulk generation support
                              
                            - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                              - Database `generation_status` field for persistent status storage
                              - Immediate UI updates when generation starts
                              - Progress bar with detailed status messages
                              - Fast refresh mode (2s) during active generations
                              
                            - **Live Log Viewer**: Tail generation logs in real-time
                              - Incremental log loading (only new lines)
                              - Color-coded log entries (error, warning, info, success)
                              - Auto-scroll to bottom for new content
                              - Automatic stop when generation completes
                              - Shows generation completion status
                              
                            - **Error Recovery Features**: Comprehensive error handling and recovery
                              - One-click retry for failed generations
                              - Error messages stored in database
                              - Automatic cleanup of stuck generations (10+ minutes)
                              - Clear error feedback in UI
                              
                            - **Database Schema Updates**: New fields for generation tracking
                              - `generation_status` - Current generation progress message
                              - `generation_error` - Error message storage
                              - `generation_started_at` - Generation start timestamp
                              - `generation_completed_at` - Generation completion timestamp
                              - Index on `generation_status` for performance
                              
                            - **New Services**: Generation queue management
                              - `services/class-generation-queue.php` - Complete queue management system
                              - Supports concurrent processing with queue overflow
                              - Automatic retry and error recovery
                              
                            - **Brand Features Management System**: Complete internal linking management with:
                              - New database table: `ai_blog_brand_features`
                              - Full CRUD operations for brand features (services, pages, documents, etc.)
                              - Four category types: informational_page, document, image, video
                              - Active/inactive state management for features
                              - Real-time search and category filtering
                              - Grid layout with modern card-based design
                              - Modal-based editing interface
                              - Integration ready for AI content generation
                              - Comprehensive error handling and logging
                              
                            - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                              - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                                - Updated all AI prompts to request 120-140 character meta descriptions
                                - Updated validation logic to check for 140-character limit instead of 160
                                - Applied changes to both Prompt Compiler Service and Anthropic Service
                              - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                                - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                                - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                                - Only adds keyphrase if not already present to avoid duplication
                                - Applied to both Content Generator and Anthropic Service classes
                              - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                                - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                                - Focus keyphrase appears first in filename followed by descriptive keywords
                                - Smart deduplication logic prevents overlap between keyphrase and content keywords
                                - Automatic propagation from content generation to image requirements
                                - Applied to both content images and featured images
                              - Ensures all SEO elements meet current best practices
                              
                            - **Brand Features Management System**: Complete internal linking management with:
                              - New database table: `ai_blog_brand_features`
                              - Full CRUD operations for brand features (services, pages, documents, etc.)
                              - Four category types: informational_page, document, image, video
                              - Active/inactive state management for features
                              - Real-time search and category filtering
                              - Grid layout with modern card-based design
                              - Modal-based editing interface
                              - Integration ready for AI content generation
                              - Comprehensive error handling and logging
                              
                            - **UI/UX Improvements**:
                              - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                                - Added proper button styling with colors and hover effects
                                - Save button uses primary blue (#2271b1) with hover state
                                - Cancel button uses secondary gray (#f0f0f1) with hover state
                                - Added consistent padding, border radius, and transitions
                                
                              - **Product Links Pill Design**: Enhanced product links display
                                - Added pill-style design with rounded borders and padding
                                - Colored type badges with specific colors for each link type
                                - Product Page links show green badge
                                - Purchase links show orange badge
                                - Documentation links show purple badge
                                - Other links show gray badge
                                - Added hover effects with shadow and transform
                                - Fixed link type labels to show proper text instead of database values
                                
                              - **Products Page Redesign**: Applied modern design style to products page matching personas page
                                - Enhanced product cards with gradient backgrounds and hover effects
                                - Improved search box styling with focus states
                                - Modernized product modal with better form styling and section dividers
                                - Updated image and link management UI with better visual hierarchy
                                - Added colored badges for link types (product page, purchase, documentation)
                                - Improved pagination styling with better hover states
                                - Enhanced responsive design for mobile devices
                                
                              - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                                - Enhanced context cards with improved shadows and hover effects
                                - Added gradient backgrounds to context type badges
                                - Improved badge styling for usage categories and always-include indicators
                                - Modernized context edit modal with better form controls
                                - Enhanced seed images section with better card design
                                - Updated seed image upload modal to match personas modal styling
                                - Improved button styling with hover effects and better spacing
                                - Added responsive design improvements for mobile devices
                                
                              - **Layout Consistency**: Made all admin pages full-width
                                - Removed max-width restrictions from contexts and products pages
                                - All pages now use 100% width like the personas page
                                - Consistent layout across all admin sections
                                
                              - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                                - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                                - Consistent icon style matching personas page design
                                - Applied same icon treatment to seed images section
                                - Better visual hierarchy and cleaner interface
                                
                              - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                                - Added 'layout' as a valid enum option in the database
                                - Fixed JavaScript to display type labels instead of database values
                                - Added context type labels to JavaScript localization data
                                - Context cards now show "Layout Guidelines" instead of "layout" after saving
                                
                              - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                                - Matches the persona cards' gradient line that appears on hover
                                - Uses the same blue-purple-pink gradient for consistency
                                - Provides visual feedback when hovering over context cards
                                
                              - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                                - Replaced text buttons with icon buttons (edit and delete)
                                - Added gradient line hover effect matching other admin cards
                                - Improved typography and spacing consistency
                                - Updated color scheme to match modern design language
                                - Better visual hierarchy with icon-based actions
                                
                              - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                                - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                                - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                                - **Files Modified**: 
                                  - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                                  - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                                - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                                
                              - **Drafted Posts Table Width**: Fixed table only using half the screen width
                                - **Root Cause**: WordPress default `.wrap` class applies width constraints
                                - **Solution**: Added CSS overrides to make the page full width
                                - **CSS Changes**: 
                                  - Override `.wrap` max-width constraint
                                  - Ensure table and cards use 100% width
                                  - Scoped WordPress admin overrides to drafted posts page only
                                - **Result**: Drafted posts table now uses full available screen width
                                
                              - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                                - **Root Cause**: Script was enqueued but not localized with AJAX data
                                - **Solution**: Added `wp_localize_script` call for drafted posts script
                                - **Files Modified**: 
                                  - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                                - **Result**: Drafted posts page now loads properly with AJAX functionality working
                                
                              - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                                - **Root Cause**: Bootstrap card component was constraining table width
                                - **Solution**: Replaced card wrapper with custom div structure
                                - **Changes Made**: 
                                  - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                                  - Added custom CSS for table wrapper with full width
                                  - Also updated filter actions bar to use consistent wrapper approach
                                - **Result**: Table now uses full available screen width without Bootstrap card constraints
                                
                              - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                                - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                                - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                                - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                                - **Changes Made**:
                                  - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                                  - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                                  - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                                - **Result**: Schedule post functionality now works correctly without fatal errors
                                
                              - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                                - **Files Modified**:
                                  - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                                  - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                                  - `admin/class-admin-manager.php` - Updated submenu registration
                                - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                                
                              - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                                - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                                - **Solution**: Enhanced the queue status indicator UI
                                - **Changes Made**:
                                  - Made queue status indicator more prominent with badges and icons
                                  - Added list of queued items showing position and title
                                  - Added notification when items are queued
                                  - Added automatic queue status fetching after bulk generation
                                  - Added function to fetch queue status on demand
                                
                                - **Result**: Users now clearly see when ideas are queued and their position in the queue
                                
                              - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                                - Queue management service supporting up to 5 simultaneous generations
                                - FIFO queue for excess generation requests
                                - Real-time queue status display with active generations and positions
                                - Automatic processing when slots become available
                                - Individual and bulk generation support
                                
                              - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                                - Database `generation_status` field for persistent status storage
                                - Immediate UI updates when generation starts
                                - Progress bar with detailed status messages
                                - Fast refresh mode (2s) during active generations
                                
                              - **Live Log Viewer**: Tail generation logs in real-time
                                - Incremental log loading (only new lines)
                                - Color-coded log entries (error, warning, info, success)
                                - Auto-scroll to bottom for new content
                                - Automatic stop when generation completes
                                - Shows generation completion status
                                
                              - **Error Recovery Features**: Comprehensive error handling and recovery
                                - One-click retry for failed generations
                                - Error messages stored in database
                                - Automatic cleanup of stuck generations (10+ minutes)
                                - Clear error feedback in UI
                                
                              - **Database Schema Updates**: New fields for generation tracking
                                - `generation_status` - Current generation progress message
                                - `generation_error` - Error message storage
                                - `generation_started_at` - Generation start timestamp
                                - `generation_completed_at` - Generation completion timestamp
                                - Index on `generation_status` for performance
                                
                              - **New Services**: Generation queue management
                                - `services/class-generation-queue.php` - Complete queue management system
                                - Supports concurrent processing with queue overflow
                                - Automatic retry and error recovery
                                
                              - **Brand Features Management System**: Complete internal linking management with:
                                - New database table: `ai_blog_brand_features`
                                - Full CRUD operations for brand features (services, pages, documents, etc.)
                                - Four category types: informational_page, document, image, video
                                - Active/inactive state management for features
                                - Real-time search and category filtering
                                - Grid layout with modern card-based design
                                - Modal-based editing interface
                                - Integration ready for AI content generation
                                - Comprehensive error handling and logging
                                
                              - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                                - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                                  - Updated all AI prompts to request 120-140 character meta descriptions
                                  - Updated validation logic to check for 140-character limit instead of 160
                                  - Applied changes to both Prompt Compiler Service and Anthropic Service
                                - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                                  - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                                  - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                                  - Only adds keyphrase if not already present to avoid duplication
                                  - Applied to both Content Generator and Anthropic Service classes
                                - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                                  - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                                  - Focus keyphrase appears first in filename followed by descriptive keywords
                                  - Smart deduplication logic prevents overlap between keyphrase and content keywords
                                  - Automatic propagation from content generation to image requirements
                                  - Applied to both content images and featured images
                                - Ensures all SEO elements meet current best practices
                                
                              - **Brand Features Management System**: Complete internal linking management with:
                                - New database table: `ai_blog_brand_features`
                                - Full CRUD operations for brand features (services, pages, documents, etc.)
                                - Four category types: informational_page, document, image, video
                                - Active/inactive state management for features
                                - Real-time search and category filtering
                                - Grid layout with modern card-based design
                                - Modal-based editing interface
                                - Integration ready for AI content generation
                                - Comprehensive error handling and logging
                                
                              - **UI/UX Improvements**:
                                - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                                  - Added proper button styling with colors and hover effects
                                  - Save button uses primary blue (#2271b1) with hover state
                                  - Cancel button uses secondary gray (#f0f0f1) with hover state
                                  - Added consistent padding, border radius, and transitions
                                  
                                - **Product Links Pill Design**: Enhanced product links display
                                  - Added pill-style design with rounded borders and padding
                                  - Colored type badges with specific colors for each link type
                                  - Product Page links show green badge
                                  - Purchase links show orange badge
                                  - Documentation links show purple badge
                                  - Other links show gray badge
                                  - Added hover effects with shadow and transform
                                  - Fixed link type labels to show proper text instead of database values
                                  
                                - **Products Page Redesign**: Applied modern design style to products page matching personas page
                                  - Enhanced product cards with gradient backgrounds and hover effects
                                  - Improved search box styling with focus states
                                  - Modernized product modal with better form styling and section dividers
                                  - Updated image and link management UI with better visual hierarchy
                                  - Added colored badges for link types (product page, purchase, documentation)
                                  - Improved pagination styling with better hover states
                                  - Enhanced responsive design for mobile devices
                                  
                                - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                                  - Enhanced context cards with improved shadows and hover effects
                                  - Added gradient backgrounds to context type badges
                                  - Improved badge styling for usage categories and always-include indicators
                                  - Modernized context edit modal with better form controls
                                  - Enhanced seed images section with better card design
                                  - Updated seed image upload modal to match personas modal styling
                                  - Improved button styling with hover effects and better spacing
                                  - Added responsive design improvements for mobile devices
                                  
                                - **Layout Consistency**: Made all admin pages full-width
                                  - Removed max-width restrictions from contexts and products pages
                                  - All pages now use 100% width like the personas page
                                  - Consistent layout across all admin sections
                                  
                                - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                                  - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                                  - Consistent icon style matching personas page design
                                  - Applied same icon treatment to seed images section
                                  - Better visual hierarchy and cleaner interface
                                  
                                - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                                  - Added 'layout' as a valid enum option in the database
                                  - Fixed JavaScript to display type labels instead of database values
                                  - Added context type labels to JavaScript localization data
                                  - Context cards now show "Layout Guidelines" instead of "layout" after saving
                                  
                                - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                                  - Matches the persona cards' gradient line that appears on hover
                                  - Uses the same blue-purple-pink gradient for consistency
                                  - Provides visual feedback when hovering over context cards
                                  
                                - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                                  - Replaced text buttons with icon buttons (edit and delete)
                                  - Added gradient line hover effect matching other admin cards
                                  - Improved typography and spacing consistency
                                  - Updated color scheme to match modern design language
                                  - Better visual hierarchy with icon-based actions
                                  
                                - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                                  - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                                  - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                                  - **Files Modified**: 
                                    - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                                    - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                                  - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                                  
                                - **Drafted Posts Table Width**: Fixed table only using half the screen width
                                  - **Root Cause**: WordPress default `.wrap` class applies width constraints
                                  - **Solution**: Added CSS overrides to make the page full width
                                  - **CSS Changes**: 
                                    - Override `.wrap` max-width constraint
                                    - Ensure table and cards use 100% width
                                    - Scoped WordPress admin overrides to drafted posts page only
                                  - **Result**: Drafted posts table now uses full available screen width
                                  
                                - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error
                                  - **Root Cause**: Script was enqueued but not localized with AJAX data
                                  - **Solution**: Added `wp_localize_script` call for drafted posts script
                                  - **Files Modified**: 
                                    - `admin/class-admin-manager.php` - Added localization in `localize_scripts()` method
                                  - **Result**: Drafted posts page now loads properly with AJAX functionality working
                                  
                                - **Drafted Posts Table Bootstrap Card Constraint**: Fixed table being limited to 520px width
                                  - **Root Cause**: Bootstrap card component was constraining table width
                                  - **Solution**: Replaced card wrapper with custom div structure
                                  - **Changes Made**: 
                                    - Replaced `<div class="card">` wrapper with `<div class="drafted-posts-table-wrapper">`
                                    - Added custom CSS for table wrapper with full width
                                    - Also updated filter actions bar to use consistent wrapper approach
                                  - **Result**: Table now uses full available screen width without Bootstrap card constraints
                                  
                                - **Schedule Post AJAX Error**: Fixed fatal error when scheduling posts in drafted posts page
                                  - **Root Cause**: Duplicate AJAX action registration in Admin_Manager for handlers that don't exist in that class
                                  - **Error**: `class AI_Blog_Generator\Admin\Admin_Manager does not have a method "ajax_schedule_post"`
                                  - **Solution**: Removed duplicate AJAX registrations from Admin_Manager
                                  - **Changes Made**:
                                    - Removed `wp_ajax_ai_blog_schedule_post` registration from Admin_Manager
                                    - Removed `wp_ajax_ai_blog_generate_post` registration (method doesn't exist)
                                    - Removed `wp_ajax_ai_blog_publish_post` registration (duplicate of Blog_Controller)
                                  - **Result**: Schedule post functionality now works correctly without fatal errors
                                  
                                - **Approved Ideas Page Naming**: Simplified the menu item and page title from "Approved Ideas V2" to "Approved Ideas"
                                  - **Files Modified**:
                                    - `controllers/class-approved-ideas-controller-v2.php` - Updated menu configuration
                                    - `admin/views/approved-ideas-view-v2.php` - Updated page title 
                                    - `admin/class-admin-manager.php` - Updated submenu registration
                                  - **Note**: Menu slug remains unchanged (`ai-blog-generator-approved-ideas-v2`) to preserve existing links
                                  
                                - **Generation Queue Status Visibility**: Improved visibility of generation queue status
                                  - **Issue**: Users couldn't see that ideas beyond the 5th were being queued
                                  - **Solution**: Enhanced the queue status indicator UI
                                  - **Changes Made**:
                                    - Made queue status indicator more prominent with badges and icons
                                    - Added list of queued items showing position and title
                                    - Added notification when items are queued
                                    - Added automatic queue status fetching after bulk generation
                                    - Added function to fetch queue status on demand
                                  
                                  - **Result**: Users now clearly see when ideas are queued and their position in the queue
                                  
                                - **Generation Queue System**: Complete overhaul of blog generation with concurrent processing
                                  - Queue management service supporting up to 5 simultaneous generations
                                  - FIFO queue for excess generation requests
                                  - Real-time queue status display with active generations and positions
                                  - Automatic processing when slots become available
                                  - Individual and bulk generation support
                                  
                                - **Live Generation Status Updates**: Real-time progress tracking without page refresh
                                  - Database `generation_status` field for persistent status storage
                                  - Immediate UI updates when generation starts
                                  - Progress bar with detailed status messages
                                  - Fast refresh mode (2s) during active generations
                                  
                                - **Live Log Viewer**: Tail generation logs in real-time
                                  - Incremental log loading (only new lines)
                                  - Color-coded log entries (error, warning, info, success)
                                  - Auto-scroll to bottom for new content
                                  - Automatic stop when generation completes
                                  - Shows generation completion status
                                  
                                - **Error Recovery Features**: Comprehensive error handling and recovery
                                  - One-click retry for failed generations
                                  - Error messages stored in database
                                  - Automatic cleanup of stuck generations (10+ minutes)
                                  - Clear error feedback in UI
                                  
                                - **Database Schema Updates**: New fields for generation tracking
                                  - `generation_status` - Current generation progress message
                                  - `generation_error` - Error message storage
                                  - `generation_started_at` - Generation start timestamp
                                  - `generation_completed_at` - Generation completion timestamp
                                  - Index on `generation_status` for performance
                                  
                                - **New Services**: Generation queue management
                                  - `services/class-generation-queue.php` - Complete queue management system
                                  - Supports concurrent processing with queue overflow
                                  - Automatic retry and error recovery
                                  
                                - **Brand Features Management System**: Complete internal linking management with:
                                  - New database table: `ai_blog_brand_features`
                                  - Full CRUD operations for brand features (services, pages, documents, etc.)
                                  - Four category types: informational_page, document, image, video
                                  - Active/inactive state management for features
                                  - Real-time search and category filtering
                                  - Grid layout with modern card-based design
                                  - Modal-based editing interface
                                  - Integration ready for AI content generation
                                  - Comprehensive error handling and logging
                                  
                                - **SEO Improvements**: Comprehensive SEO enhancements for better search engine optimization
                                  - **Meta Description Length**: Updated from 160 to 140 characters for optimal SEO performance
                                    - Updated all AI prompts to request 120-140 character meta descriptions
                                    - Updated validation logic to check for 140-character limit instead of 160
                                    - Applied changes to both Prompt Compiler Service and Anthropic Service
                                  - **Focus Keyphrase in Image Alt Text**: Automatically include focus keyphrase in all image alt tags for improved SEO
                                    - Modified `generate_alt_text_from_prompt()` methods to accept focus keyphrase parameter
                                    - Smart integration: prepends keyphrase for short alt text, appends naturally for longer alt text
                                    - Only adds keyphrase if not already present to avoid duplication
                                    - Applied to both Content Generator and Anthropic Service classes
                                  - **Focus Keyphrase in Image Filenames**: Include target keyphrase in image filenames for enhanced SEO
                                    - Enhanced `generate_descriptive_filename()` and `generate_filename()` methods in OpenAI_Service
                                    - Focus keyphrase appears first in filename followed by descriptive keywords
                                    - Smart deduplication logic prevents overlap between keyphrase and content keywords
                                    - Automatic propagation from content generation to image requirements
                                    - Applied to both content images and featured images
                                  - Ensures all SEO elements meet current best practices
                                  
                                - **Brand Features Management System**: Complete internal linking management with:
                                  - New database table: `ai_blog_brand_features`
                                  - Full CRUD operations for brand features (services, pages, documents, etc.)
                                  - Four category types: informational_page, document, image, video
                                  - Active/inactive state management for features
                                  - Real-time search and category filtering
                                  - Grid layout with modern card-based design
                                  - Modal-based editing interface
                                  - Integration ready for AI content generation
                                  - Comprehensive error handling and logging
                                  
                                - **UI/UX Improvements**:
                                  - **Product Modal Button Styling**: Fixed Save/Cancel buttons in product modal
                                    - Added proper button styling with colors and hover effects
                                    - Save button uses primary blue (#2271b1) with hover state
                                    - Cancel button uses secondary gray (#f0f0f1) with hover state
                                    - Added consistent padding, border radius, and transitions
                                    
                                  - **Product Links Pill Design**: Enhanced product links display
                                    - Added pill-style design with rounded borders and padding
                                    - Colored type badges with specific colors for each link type
                                    - Product Page links show green badge
                                    - Purchase links show orange badge
                                    - Documentation links show purple badge
                                    - Other links show gray badge
                                    - Added hover effects with shadow and transform
                                    - Fixed link type labels to show proper text instead of database values
                                    
                                  - **Products Page Redesign**: Applied modern design style to products page matching personas page
                                    - Enhanced product cards with gradient backgrounds and hover effects
                                    - Improved search box styling with focus states
                                    - Modernized product modal with better form styling and section dividers
                                    - Updated image and link management UI with better visual hierarchy
                                    - Added colored badges for link types (product page, purchase, documentation)
                                    - Improved pagination styling with better hover states
                                    - Enhanced responsive design for mobile devices
                                    
                                  - **Contexts Page Redesign**: Applied consistent modern design style to contexts page
                                    - Enhanced context cards with improved shadows and hover effects
                                    - Added gradient backgrounds to context type badges
                                    - Improved badge styling for usage categories and always-include indicators
                                    - Modernized context edit modal with better form controls
                                    - Enhanced seed images section with better card design
                                    - Updated seed image upload modal to match personas modal styling
                                    - Improved button styling with hover effects and better spacing
                                    - Added responsive design improvements for mobile devices
                                    
                                  - **Layout Consistency**: Made all admin pages full-width
                                    - Removed max-width restrictions from contexts and products pages
                                    - All pages now use 100% width like the personas page
                                    - Consistent layout across all admin sections
                                    
                                  - **Icon-Based Actions**: Replaced text buttons with icons on contexts page
                                    - Changed Edit, Deactivate/Activate, and Delete buttons to icon buttons
                                    - Consistent icon style matching personas page design
                                    - Applied same icon treatment to seed images section
                                    - Better visual hierarchy and cleaner interface
                                    
                                  - **Context Type Saving**: Fixed "Layout Guidelines" type not saving correctly
                                    - Added 'layout' as a valid enum option in the database
                                    - Fixed JavaScript to display type labels instead of database values
                                    - Added context type labels to JavaScript localization data
                                    - Context cards now show "Layout Guidelines" instead of "layout" after saving
                                    
                                  - **Context Card Hover Effect**: Added gradient line hover effect to context cards
                                    - Matches the persona cards' gradient line that appears on hover
                                    - Uses the same blue-purple-pink gradient for consistency
                                    - Provides visual feedback when hovering over context cards
                                    
                                  - **Product Cards Redesign**: Updated product cards to match personas and contexts style
                                    - Replaced text buttons with icon buttons (edit and delete)
                                    - Added gradient line hover effect matching other admin cards
                                    - Improved typography and spacing consistency
                                    - Updated color scheme to match modern design language
                                    - Better visual hierarchy with icon-based actions
                                    
                                  - **Drafted Posts Page Script and Style Loading**: Fixed scripts and styles not loading on drafted posts page
                                    - **Root Cause**: Menu slug mismatch - page registered as `-drafts` but scripts checking for `-drafted-posts`
                                    - **Solution**: Updated enqueue script patterns to match actual menu slug registration
                                    - **Files Modified**: 
                                      - `admin/class-admin-manager.php` - Fixed pattern detection from '-drafted-posts' to '-drafts'
                                      - `admin/assets/js/drafted-posts.js` - Enhanced dependency verification and Bootstrap modal handling
                                    - **Result**: Bootstrap 5, FontAwesome, and all JavaScript functionality now loads correctly on drafted posts page
                                    
                                  - **Drafted Posts Table Width**: Fixed table only using half the screen width
                                    - **Root Cause**: WordPress default `.wrap` class applies width constraints
                                    - **Solution**: Added CSS overrides to make the page full width
                                    - **CSS Changes**: 
                                      - Override `.wrap` max-width constraint
                                      - Ensure table and cards use 100% width
                                      - Scoped WordPress admin overrides to drafted posts page only
                                    - **Result**: Drafted posts table now uses full available screen width
                                    
                                  - **Drafted Posts JavaScript Localization**: Fixed missing `ai_blog_admin` object error