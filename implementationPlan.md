# AI Blog Generator WordPress Plugin - Implementation Plan

## Project Overview
A WordPress plugin that leverages Claude Opus 4 (Anthropic) for content generation and GPT-Image-1 (OpenAI) for image creation to automatically generate, schedule, and publish SEO-optimized blog posts.

## Plugin Structure

### Directory Structure
```
ai-blog-generator/
├── ai-blog-generator.php (main plugin file)
├── includes/
│   ├── class-plugin-activator.php
│   ├── class-plugin-deactivator.php
│   ├── class-plugin-loader.php
│   └── class-plugin-core.php
├── admin/
│   ├── class-admin-manager.php
│   ├── views/
│   │   ├── settings-page.php
│   │   ├── blog-ideas-page.php
│   │   ├── blog-ideas-view-v2.php (Idea Generator page)
│   │   ├── approved-ideas-view-v2.php
│   │   ├── approved-blogs-page.php
│   │   ├── drafted-posts-page.php
│   │   ├── published-posts-page.php
│   │   ├── contexts-page.php
│   │   ├── personas-page.php
│   │   ├── products-page.php
│   │   ├── brand-features-page.php
│   │   ├── logs-page.php
│   │   └── costs-dashboard-page.php
│   └── assets/
│       ├── css/
│       └── js/
├── controllers/
│   ├── class-blog-controller.php
│   ├── class-idea-controller.php
│   ├── class-image-controller.php
│   ├── class-context-controller.php
│   └── class-analytics-controller.php
├── models/
│   ├── class-database-manager.php
│   ├── class-blog-model.php
│   ├── class-idea-model.php
│   ├── class-context-model.php
│   ├── class-log-model.php
│   └── class-cost-model.php
├── services/
│   ├── class-anthropic-service.php
│   ├── class-openai-service.php
│   ├── class-content-generator.php
│   ├── class-image-generator.php
│   ├── class-scheduler-service.php
│   └── class-seo-service.php
├── utilities/
│   ├── class-logger.php
│   ├── class-cost-calculator.php
│   └── class-validator.php
├── templates/
│   └── blog-post-template.php
├── readme.txt
├── changelog.md
├── implementationPlan.md
└── implementationTechnical.md
```

## Database Schema

### Tables to Create

#### 1. blog_ideas
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- title (VARCHAR 255)
- description (TEXT)
- category_id (INT)
- persona_id (INT, FOREIGN KEY)
- status (ENUM: 'pending', 'approved', 'denied', 'generated')
- created_at (DATETIME)
- updated_at (DATETIME)

#### 2. generated_posts
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- idea_id (INT, FOREIGN KEY)
- persona_id (INT, FOREIGN KEY)
- post_id (INT, WordPress post ID)
- scheduled_time (DATETIME)
- status (ENUM: 'draft', 'scheduled', 'published')
- cost (DECIMAL 10,4)
- created_at (DATETIME)

#### 3. contexts
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- name (VARCHAR 100)
- type (ENUM: 'general', 'product', 'seo', 'image')
- content (TEXT)
- active (BOOLEAN)
- created_at (DATETIME)
- updated_at (DATETIME)

#### 4. logs
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- action (VARCHAR 100)
- message (TEXT)
- level (ENUM: 'info', 'warning', 'error')
- context (TEXT)
- created_at (DATETIME)

#### 5. cost_analytics
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- service (ENUM: 'anthropic', 'openai')
- action (VARCHAR 100)
- tokens_used (INT)
- cost (DECIMAL 10,4)
- created_at (DATETIME)

#### 6. seed_images
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- product_name (VARCHAR 100)
- image_url (VARCHAR 500)
- context_id (INT, FOREIGN KEY)
- created_at (DATETIME)

#### 7. personas
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- name (VARCHAR 100)
- bio (TEXT)
- expertise (TEXT)
- writing_style (TEXT)
- tone (ENUM: 'professional', 'friendly', 'analytical', 'inspirational', 'casual', 'academic')
- active (BOOLEAN)
- created_at (DATETIME)
- updated_at (DATETIME)

#### 8. products
- id (BIGINT, PRIMARY KEY, AUTO_INCREMENT)
- name (VARCHAR 255)
- description (TEXT)
- ideal_uses (TEXT)
- created_at (DATETIME)
- updated_at (DATETIME)

#### 9. product_images
- id (BIGINT, PRIMARY KEY, AUTO_INCREMENT)
- product_id (BIGINT, FOREIGN KEY)
- attachment_id (BIGINT)
- image_url (VARCHAR 500)
- is_primary (BOOLEAN)
- display_order (INT)
- created_at (DATETIME)

#### 10. product_links
- id (BIGINT, PRIMARY KEY, AUTO_INCREMENT)
- product_id (BIGINT, FOREIGN KEY)
- link_type (ENUM: 'product_page', 'purchase', 'documentation', 'other')
- link_text (VARCHAR 255)
- link_url (VARCHAR 500)
- created_at (DATETIME)

#### 11. Product Seed Images Table (`wp_ai_blog_generator_product_seed_images`)
- id (BIGINT, PRIMARY KEY, AUTO_INCREMENT)
- product_id (BIGINT, FOREIGN KEY)
- attachment_id (BIGINT)
- image_url (VARCHAR 500)
- display_order (INT)
- created_at (DATETIME)

#### 12. Brand Features Table (`wp_ai_blog_brand_features`)
- id (BIGINT, PRIMARY KEY, AUTO_INCREMENT)
- name (VARCHAR 255)
- description (TEXT)
- category (ENUM: 'informational_page', 'document', 'image', 'video')
- url (VARCHAR 500)
- active (BOOLEAN)
- created_at (DATETIME)
- updated_at (DATETIME)

## Implementation Phases

### Phase 1: Core Infrastructure (Days 1-2)
1. **Plugin Activation/Deactivation**
   - Create database tables
   - Set up default options
   - Initialize cron jobs

2. **Database Manager**
   - Implement CRUD operations for all tables
   - Create query builders for complex operations
   - Add transaction support

3. **Admin Menu Structure**
   - Register all admin pages
   - Set up routing
   - Create base admin page class

### Phase 2: API Integration (Days 3-4)
1. **Anthropic Service**
   - API key validation
   - Content generation methods
   - Error handling and retries
   - Token counting and cost calculation

2. **OpenAI Service**
   - API key validation
   - Image generation with seed images
   - Error handling
   - Cost calculation

3. **Settings Page**
   - API key input and storage
   - Connection testing
   - General plugin settings

### Phase 3: Idea Generation System (Days 5-6)
1. **Idea Controller**
   - Daily idea generation cron job
   - Duplicate checking against existing posts
   - Category balancing algorithm
   - Denied ideas filtering

2. **Blog Ideas Page**
   - Display pending ideas
   - Approve/Deny functionality
   - Bulk actions support
   - AJAX updates

### Phase 4: Content Generation (Days 7-9)
1. **Content Generator Service**
   - Context compilation
   - Prompt construction
   - HTML generation with image tokens
   - SEO metadata generation

2. **Image Generator Service**
   - Image requirement parsing
   - Seed image matching
   - Batch image generation
   - Media library integration

3. **Blog Controller**
   - Full blog post assembly
   - WordPress post creation
   - Scheduling logic

### Phase 5: Contexts Management 
**Status: COMPLETED ✅**

#### Context Management System (COMPLETED ✅)
- ✅ **Database Table**: `ai_blog_contexts` with fields: id, name, type, content, seed_image_id, active, timestamps, always_include_avada, always_include_html
- ✅ **Context Types**: general, products, seo, keywords, image, layout
- ✅ **CRUD Operations**: Create, read, update, delete contexts via admin interface
- ✅ **Active/Inactive States**: Toggle context availability for content generation
- ✅ **Context Integration**: Use contexts to enhance AI prompts and provide consistent messaging
- ✅ **Format-Specific Requirements**: Contexts can be marked as required for Avada or HTML formats

#### Admin Interface (COMPLETED ✅)
- ✅ **Contexts Page**: Full admin interface at `/wp-admin/admin.php?page=ai-blog-generator-contexts`
- ✅ **Context Cards**: Visual grid layout showing context information
- ✅ **Modal Editing**: AJAX-powered modals for editing contexts
- ✅ **Real-time Updates**: Dynamic UI updates without page refreshes
- ✅ **Status Management**: Toggle active/inactive status with visual feedback
- ✅ **Bulk Operations**: Import/export contexts functionality

#### Seed Images Management (COMPLETED ✅)
- ✅ **Database Table**: `ai_blog_seed_images` with fields: id, product_name, image_url, context_id, created_at
- ✅ **PNG File Upload**: Validation for PNG files only (up to 10MB)
- ✅ **WordPress Integration**: Images stored in WordPress media library
- ✅ **Product Names**: Each seed image requires a descriptive product name
- ✅ **Context Linking**: Optional linking of seed images to specific contexts
- ✅ **AJAX Operations**: Upload, edit, delete seed images without page refresh
- ✅ **Image Preview**: Real-time preview during upload and editing
- ✅ **File Validation**: Client-side and server-side validation for PNG format and file size

#### Technical Implementation (COMPLETED ✅)
- ✅ **Context_Controller**: Complete AJAX handlers for contexts and seed images
- ✅ **Context_Model**: Database operations with proper validation and sanitization
- ✅ **JavaScript Framework**: `contexts.js` with comprehensive seed image functionality
- ✅ **Security**: Nonce verification, capability checking, input sanitization
- ✅ **Error Handling**: Comprehensive error handling and user feedback
- ✅ **Logging**: Detailed logging for all operations using plugin's logging system

### Phase 6: Persona Management (Days 12-13)
1. **Persona Model & Controller**
   - CRUD operations for personas
   - Active/inactive status management
   - Validation for required fields
   - AJAX handlers for admin operations

2. **Personas Admin Page**
   - Display all personas in card layout
   - Add/Edit persona modal
   - Delete with confirmation
   - Toggle active status
   - Default personas seeding
   - Mutually exclusive content format selection (HTML or Avada)

3. **Persona Selection Algorithm**
   - Analyze idea content and keywords
   - Match against persona expertise
   - Score personas based on relevance
   - Select best matching persona
   - Store selection with idea

4. **Content Generation Integration**
   - Include persona context in prompts
   - Adapt writing style based on persona
   - Maintain persona voice consistency
   - Track which persona wrote what
   - Generate content in selected format (HTML or Avada)
   - Respect persona's `number_of_images` setting:
     - 0 = Only featured image generated
     - 1+ = That many content images plus featured image
     - Default is 2 content images if not specified

### Phase 7: Products Management 
**Status: COMPLETED ✅**

#### Product Management System (COMPLETED ✅)
- ✅ **Database Tables**: Four tables for products, images, links, and seed images
  - `ai_blog_generator_products`: Core product information (name, description, ideal uses)
  - `ai_blog_generator_product_images`: Multiple images per product with ordering
  - `ai_blog_generator_product_links`: Multiple links per product with types
  - `ai_blog_generator_product_seed_images`: PNG seed images for AI image generation
- ✅ **Product Types**: Manual creation and WooCommerce import
- ✅ **CRUD Operations**: Full create, read, update, delete functionality
- ✅ **Image Management**: Multiple images with primary designation and drag-drop ordering
- ✅ **Link Management**: Multiple links with types (product_page, purchase, documentation, other)
- ✅ **Seed Image Management**: Product-specific seed images for AI generation (PNG only)

#### Admin Interface (COMPLETED ✅)
- ✅ **Products Page**: Grid layout at `/wp-admin/admin.php?page=ai-blog-generator-products`
- ✅ **Product Cards**: Visual cards showing product info, primary image, and action buttons
- ✅ **Modal System**: AJAX-powered modals for adding/editing products
- ✅ **Image Upload**: Media library integration with multi-image selection
- ✅ **Link Management**: Dynamic link addition/removal in edit modal
- ✅ **Search & Filter**: Real-time search and pagination
- ✅ **WooCommerce Import**: One-click import of WooCommerce products

#### Technical Implementation (COMPLETED ✅)
- ✅ **Product_Controller**: Complete AJAX handlers for all operations
- ✅ **Product_Model**: Database operations with direct wpdb queries
- ✅ **JavaScript Framework**: `products.js` with comprehensive functionality
- ✅ **CSS Styling**: Extensive styling for grid, cards, modals
- ✅ **Security**: Nonce verification, capability checking, input sanitization
- ✅ **Error Handling**: Comprehensive error handling and user feedback

### Phase 8: Analytics & Monitoring (Days 14-15)
1. **Logging System**
   - Comprehensive action logging
   - Error tracking
   - Debug mode support

2. **Cost Dashboard**
   - Real-time cost tracking
   - Historical analytics
   - Budget alerts
   - Usage limits

3. **Scheduler Service**
   - Random time distribution
   - Daily post limits
   - Queue management

### Phase 8: Brand Features Management
**Status: COMPLETED ✅**

#### Brand Features System (COMPLETED ✅)
- ✅ **Database Table**: `ai_blog_brand_features` with fields: id, name, description, category, url, active, timestamps
- ✅ **Feature Categories**: informational_page, document, image, video
- ✅ **CRUD Operations**: Full create, read, update, delete functionality
- ✅ **Active/Inactive States**: Toggle feature availability for internal linking
- ✅ **Search & Filter**: Real-time search and category filtering

#### Admin Interface (COMPLETED ✅)
- ✅ **Brand Features Page**: Grid layout at `/wp-admin/admin.php?page=ai-blog-generator-brand-features`
- ✅ **Feature Cards**: Visual cards showing feature info, category, and URL
- ✅ **Modal System**: AJAX-powered modals for adding/editing features
- ✅ **Category Badges**: Color-coded badges for each category type
- ✅ **Search Functionality**: Real-time search across names and descriptions
- ✅ **Category Filter**: Filter features by category type

#### Technical Implementation (COMPLETED ✅)
- ✅ **Brand_Feature_Controller**: Complete AJAX handlers for all operations
- ✅ **Brand_Feature_Model**: Database operations with validation and sanitization
- ✅ **JavaScript Framework**: `brand-features.js` with comprehensive functionality
- ✅ **CSS Styling**: Modern card-based design with gradient hover effects
- ✅ **Security**: Nonce verification, capability checking, input sanitization
- ✅ **Error Handling**: Comprehensive error handling and user feedback

### Phase 9: Testing & Optimization (Days 16-17)
1. **Unit Tests**
   - Service layer tests
   - Controller tests
   - Model tests

2. **Integration Tests**
   - API integration tests
   - WordPress integration tests
   - Full workflow tests

3. **Performance Optimization**
   - Database query optimization
   - Caching implementation
   - Batch processing

### Phase 10: Prompt Compiler Service
**Status: COMPLETED ✅**

#### Prompt Compilation Abstraction (COMPLETED ✅)
- ✅ **Service Creation**: `services/class-prompt-compiler-service.php` - Centralized prompt generation
- ✅ **System Prompt Compilation**: Builds comprehensive system prompts including:
  - Persona biography, expertise, writing style, and tone
  - Layout styles and rules from persona settings
  - Chart usage instructions based on persona configuration
  - All contexts with "always_include_content" = 1
  - Persona-specific contexts
  - Format-specific contexts (HTML or Avada based on persona settings)
  - Product promotion requirements (minimum 2 products)
  - Previously used keyphrases to avoid duplication
  - Brand features for internal linking
- ✅ **User Prompt Construction**: Creates user prompts with:
  - 2 randomly selected target keywords
  - Image placeholders based on persona settings (minimum 2)
  - SEO requirements (focus keyphrase, meta description)
  - Output format requirements (title, tags, content)
  - Specific formatting instructions for Avada or HTML
- ✅ **Prompt Logging**: Writes compiled prompts to `<idea_id>_prompts.txt` in uploads directory
- ✅ **Image Prompt Generation**: Handles image generation prompts with seed image support

#### Service Interface (COMPLETED ✅)
- ✅ **Main Methods**:
  - `generate_content_prompts($idea_id)` - Compiles all prompts for content generation
  - `build_system_prompts($idea, $persona, $contexts, $brand_features, $keywords, $products)` - Constructs system prompt array
  - `build_user_prompt($idea, $target_keywords, $persona, $products)` - Creates user prompt
  - `generate_image_prompts($image_requirements, $persona)` - Generates image prompts
- ✅ **Helper Methods**:
  - `get_persona_prompt($persona)` - Formats persona information
  - `get_layout_prompt($persona)` - Generates layout instructions
  - `get_chart_prompt($persona)` - Creates chart usage guidelines
  - `get_context_prompts($contexts)` - Compiles context information
  - `get_format_prompt($persona, $contexts)` - Format-specific instructions
  - `get_product_prompt($products)` - Product promotion requirements
  - `get_keyphrase_prompt($keywords)` - Previous keyphrase avoidance
  - `get_brand_features_prompt($brand_features)` - Brand feature linking

#### AI Service Updates (COMPLETED ✅)
- ✅ **Anthropic Service**: New `generate_content($prompts)` method accepting pre-compiled prompts
- ✅ **Content Generator**: Updated to use Prompt Compiler Service instead of inline prompt building
- ✅ **Product Model**: Added `get_active_products()` method for retrieving active products

#### Benefits Achieved (COMPLETED ✅)
- ✅ **Separation of Concerns**: Prompt logic completely separated from AI service implementation
- ✅ **Maintainability**: Single location for all prompt-related changes
- ✅ **Flexibility**: Easy to add new prompt types or modify existing ones
- ✅ **Debugging**: Comprehensive prompt logging for troubleshooting
- ✅ **Extensibility**: Simple to add support for new AI providers

## Key Implementation Details

### Blog Generation Workflow
1. **Idea Generation (Daily Cron)**
   - Query existing post titles
   - Query denied ideas
   - Analyze category distribution
   - Generate 5 unique ideas via Anthropic
   - For each idea, select best matching persona based on:
     - Topic relevance to persona expertise
     - Required writing tone
     - Content type and audience
   - Store persona selection with each idea

2. **Approval Process**
   - Admin reviews ideas with assigned personas
   - Can manually change persona if desired
   - Approved ideas queued for generation
   - Denied ideas stored for filtering

3. **Content Generation**
   - Compile all active contexts
   - Include selected persona's full profile:
     - Name, bio, expertise
     - Writing style and tone
     - Personality traits
   - Generate HTML with Claude Opus 4 writing as the persona
   - Parse image requirements
   - Generate images with GPT-Image-1
   - Replace tokens with media URLs
   - Create WordPress draft with persona attribution

4. **Publishing**
   - Admin reviews drafts
   - Shows which persona "wrote" the content
   - Schedule or publish immediately
   - Track in published posts with persona data

### SEO Implementation
- Meta title generation
- Meta description creation
- Focus keyphrase selection
- Structured data markup
- Mobile-responsive HTML
- Image alt text optimization

### Cost Management
- Per-request cost tracking
- Monthly cost summaries
- Budget threshold alerts
- Usage optimization suggestions

## Security Considerations
- Sanitize all inputs
- Validate API responses
- Secure API key storage
- Nonce verification for AJAX
- Capability checks for all actions

## Performance Considerations
- Implement caching for contexts
- Batch API requests where possible
- Optimize database queries
- Use WordPress transients
- Implement rate limiting

## Maintenance Features
- Automatic log cleanup (30 days)
- Database optimization routines
- Failed job retry mechanism
- Health check dashboard

## Future Enhancements
- Multi-language support
- A/B testing for titles
- Social media integration
- Advanced scheduling rules
- Content revision tracking
- Performance analytics