
--
-- Create table `psec_ai_blog_contexts`
--
CREATE TABLE psec_ai_blog_contexts (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description text DEFAULT NULL,
  type enum ('general', 'products', 'seo', 'keywords', 'image', 'layout') DEFAULT 'general',
  content text DEFAULT NULL,
  seed_image_id bigint(20) DEFAULT NULL,
  active tinyint(1) DEFAULT 1,
  priority int(11) DEFAULT 50,
  usage_flags varchar(255) DEFAULT 'ideas,content,images',
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
  created_at datetime DEFAULT current_timestamp,
  updated_at datetime DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
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

