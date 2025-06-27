--
-- Create table `psec_ai_blog_contexts`
--
CREATE TABLE psec_ai_blog_contexts (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description text DEFAULT NULL,
  type enum ('general', 'products', 'seo', 'keywords', 'image', 'layout') DEFAULT 'general',
  content text DEFAULT NULL,
  
  active tinyint(1) DEFAULT 1,
  priority int(11) DEFAULT 50,
  usage_flags varchar(255) DEFAULT 'content',
  always_include_content tinyint(1) DEFAULT 0,
  always_include_images tinyint(1) DEFAULT 0,
  always_include_avada tinyint(1) DEFAULT 0,
  always_include_html tinyint(1) DEFAULT 0,
  created_at datetime DEFAULT current_timestamp,
  updated_at datetime DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 6,
AVG_ROW_LENGTH = 3276,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_520_ci;

--
-- Create index `idx_type` on table `psec_ai_blog_contexts`
--
ALTER TABLE psec_ai_blog_contexts
ADD INDEX idx_type (type);

--
-- Create index `idx_active` on table `psec_ai_blog_contexts`
--
ALTER TABLE psec_ai_blog_contexts
ADD INDEX idx_active (active);

### psec_ai_blog_contexts
Stores content contexts that provide information and rules for blog generation.

**Note on Keywords Context**: The 'keywords' type context may contain mixed content including both keywords and image URLs. The Prompt_Compiler_Service automatically filters out URLs and image links when selecting target keywords, keeping only valid keyword phrases.

--
-- Create table `psec_ai_blog_cost_analytics`
--
CREATE TABLE psec_ai_blog_cost_analytics (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  service enum ('anthropic', 'openai') NOT NULL,
  action varchar(100) NOT NULL,
  tokens_used int(11) DEFAULT 0,
  cost decimal(10, 4) NOT NULL,
  created_at datetime DEFAULT current_timestamp,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 33,
AVG_ROW_LENGTH = 1170,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_520_ci;

--
-- Create index `idx_service` on table `psec_ai_blog_cost_analytics`
--
ALTER TABLE psec_ai_blog_cost_analytics
ADD INDEX idx_service (service);

--
-- Create index `idx_action` on table `psec_ai_blog_cost_analytics`
--
ALTER TABLE psec_ai_blog_cost_analytics
ADD INDEX idx_action (action);

--
-- Create index `idx_created` on table `psec_ai_blog_cost_analytics`
--
ALTER TABLE psec_ai_blog_cost_analytics
ADD INDEX idx_created (created_at);


--
-- Create table `psec_ai_blog_generated_posts`
--
CREATE TABLE psec_ai_blog_generated_posts (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  idea_id bigint(20) UNSIGNED NOT NULL,
  persona_id bigint(20) UNSIGNED DEFAULT NULL,
  post_id bigint(20) UNSIGNED DEFAULT NULL,
  scheduled_time datetime DEFAULT NULL,
  status enum ('draft', 'scheduled', 'published') DEFAULT 'draft',
  cost decimal(10, 4) DEFAULT 0.0000,
  created_at datetime DEFAULT current_timestamp,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 2,
AVG_ROW_LENGTH = 16384,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_520_ci;

--
-- Create index `idx_idea` on table `psec_ai_blog_generated_posts`
--
ALTER TABLE psec_ai_blog_generated_posts
ADD INDEX idx_idea (idea_id);

--
-- Create index `idx_post` on table `psec_ai_blog_generated_posts`
--
ALTER TABLE psec_ai_blog_generated_posts
ADD INDEX idx_post (post_id);

--
-- Create index `idx_status` on table `psec_ai_blog_generated_posts`
--
ALTER TABLE psec_ai_blog_generated_posts
ADD INDEX idx_status (status);

--
-- Create index `idx_scheduled` on table `psec_ai_blog_generated_posts`
--
ALTER TABLE psec_ai_blog_generated_posts
ADD INDEX idx_scheduled (scheduled_time);

--
-- Create index `idx_persona` on table `psec_ai_blog_generated_posts`
--
ALTER TABLE psec_ai_blog_generated_posts
ADD INDEX idx_persona (persona_id);


--
-- Create table `psec_ai_blog_ideas`
--
CREATE TABLE psec_ai_blog_ideas (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL,
  description text DEFAULT NULL,
  category_id bigint(20) UNSIGNED DEFAULT NULL,
  persona_id bigint(20) UNSIGNED DEFAULT NULL,
  status enum ('pending', 'approved', 'denied', 'generated', 'generating') DEFAULT 'pending',
  failed_status varchar(255) DEFAULT NULL, -- Stores the last failure reason (e.g., "Failed - Timeout", "Failed - Overloaded")
  created_at datetime DEFAULT current_timestamp,
  updated_at datetime DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  generation_status text DEFAULT NULL,
  generation_error text DEFAULT NULL,
  generation_started_at datetime DEFAULT NULL,
  generation_completed_at datetime DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 11,
AVG_ROW_LENGTH = 6553,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_520_ci;

--
-- Create index `idx_category` on table `psec_ai_blog_ideas`
--
ALTER TABLE psec_ai_blog_ideas
ADD INDEX idx_category (category_id);

--
-- Create index `idx_persona` on table `psec_ai_blog_ideas`
--
ALTER TABLE psec_ai_blog_ideas
ADD INDEX idx_persona (persona_id);

--
-- Create index `idx_status` on table `psec_ai_blog_ideas`
--
ALTER TABLE psec_ai_blog_ideas
ADD INDEX idx_status (status);

--
-- Create table `psec_ai_blog_logs`
--
CREATE TABLE psec_ai_blog_logs (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  action varchar(100) NOT NULL,
  message text DEFAULT NULL,
  level enum ('info', 'warning', 'error') DEFAULT 'info',
  context text DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 6404,
AVG_ROW_LENGTH = 493,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_520_ci;

--
-- Create index `idx_level` on table `psec_ai_blog_logs`
--
ALTER TABLE psec_ai_blog_logs
ADD INDEX idx_level (level);

--
-- Create index `idx_action` on table `psec_ai_blog_logs`
--
ALTER TABLE psec_ai_blog_logs
ADD INDEX idx_action (action);


--
-- Create table `psec_ai_blog_personas`
--
CREATE TABLE psec_ai_blog_personas (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  bio text NOT NULL,
  expertise text DEFAULT NULL,
  writing_style text DEFAULT NULL,
  tone varchar(50) DEFAULT 'professional',
  active tinyint(1) DEFAULT 1,
  created_at datetime DEFAULT current_timestamp,
  updated_at datetime DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 5,
AVG_ROW_LENGTH = 4096,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_ci;

--
-- Create index `idx_active` on table `psec_ai_blog_personas`
--
ALTER TABLE psec_ai_blog_personas
ADD INDEX idx_active (active);

--
-- Create index `idx_name` on table `psec_ai_blog_personas`
--
ALTER TABLE psec_ai_blog_personas
ADD INDEX idx_name (name);

--
-- Create table `psec_ai_blog_seed_images`
--
CREATE TABLE psec_ai_blog_seed_images (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  product_name varchar(100) NOT NULL,
  image_url varchar(500) NOT NULL,
  context_id bigint(20) UNSIGNED DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 6,
AVG_ROW_LENGTH = 4096,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_unicode_520_ci;

--
-- Create index `idx_product` on table `psec_ai_blog_seed_images`
--
ALTER TABLE psec_ai_blog_seed_images
ADD INDEX idx_product (product_name);

--
-- Create index `idx_context` on table `psec_ai_blog_seed_images`
--
ALTER TABLE psec_ai_blog_seed_images
ADD INDEX idx_context (context_id);

## Products Table

```sql
CREATE TABLE `wp_ai_blog_generator_products` (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    product_name varchar(255) NOT NULL,
    product_description text DEFAULT NULL,
    ideal_uses text DEFAULT NULL,
    woocommerce_product_id bigint(20) unsigned DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_woocommerce_product_id (woocommerce_product_id),
    KEY idx_product_name (product_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Fields:
- **id**: Primary key
- **product_name**: Name of the product (required)
- **product_description**: Detailed description of the product
- **ideal_uses**: Text describing ideal use cases for the product
- **woocommerce_product_id**: Optional reference to WooCommerce product for imported products
- **created_at**: Timestamp when product was created
- **updated_at**: Timestamp when product was last updated

## Product Images Table

```sql
CREATE TABLE `wp_ai_blog_generator_product_images` (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    product_id bigint(20) unsigned NOT NULL,
    attachment_id bigint(20) unsigned NOT NULL,
    display_order int(11) DEFAULT 0,
    is_primary tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_id (product_id),
    KEY idx_attachment_id (attachment_id),
    KEY idx_display_order (display_order),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES wp_ai_blog_generator_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Fields:
- **id**: Primary key
- **product_id**: Foreign key to products table
- **attachment_id**: WordPress media library attachment ID
- **display_order**: Order for displaying images (0 = first)
- **is_primary**: Whether this is the primary/featured image
- **created_at**: Timestamp when image was linked

## Product Links Table

```sql
CREATE TABLE `wp_ai_blog_generator_product_links` (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    product_id bigint(20) unsigned NOT NULL,
    link_url varchar(500) NOT NULL,
    link_text varchar(255) DEFAULT NULL,
    link_type enum('product_page','purchase','documentation','other') DEFAULT 'product_page',
    display_order int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_id (product_id),
    KEY idx_link_type (link_type),
    KEY idx_display_order (display_order),
    CONSTRAINT fk_product_links_product FOREIGN KEY (product_id) REFERENCES wp_ai_blog_generator_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Fields:
- **id**: Primary key
- **product_id**: Foreign key to products table
- **link_url**: URL of the link
- **link_text**: Display text for the link (optional)
- **link_type**: Type of link (product_page, purchase, documentation, other)
- **display_order**: Order for displaying links
- **created_at**: Timestamp when link was added

