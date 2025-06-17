-- Create Personas Table
CREATE TABLE IF NOT EXISTS `wp_ai_blog_personas` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `bio` text NOT NULL,
  `expertise` text,
  `writing_style` text,
  `tone` varchar(50) DEFAULT 'professional',
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add persona_id to ideas table
ALTER TABLE `wp_ai_blog_ideas` 
ADD COLUMN `persona_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `category_id`,
ADD KEY `idx_persona` (`persona_id`);

-- Add persona_id to generated posts table
ALTER TABLE `wp_ai_blog_generated_posts` 
ADD COLUMN `persona_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `idea_id`,
ADD KEY `idx_persona` (`persona_id`);

-- Insert default personas
INSERT INTO `wp_ai_blog_personas` (`name`, `bio`, `expertise`, `writing_style`, `tone`) VALUES
('John', 'John is a professional blog writer who has a PhD in education. He is a copywriting expert with over 15 years of experience in creating engaging, educational content.', 'Education, Copywriting, Academic Writing, SEO Optimization, Content Strategy', 'Academic yet accessible, uses data and research to support points, includes practical examples, structured and logical flow', 'professional'),
('Ginny', 'Ginny is a PTA president and community organizer looking for creative ways to fund school projects like their poster maker. She brings a parent\'s perspective and grassroots fundraising experience.', 'Community Organizing, Fundraising, Parent Engagement, School Activities, Event Planning', 'Conversational and relatable, uses personal anecdotes, focuses on community and collaboration, practical tips and real-world examples', 'friendly'),
('Marcus', 'Marcus is a digital marketing specialist with expertise in e-commerce and conversion optimization. He focuses on data-driven strategies and ROI.', 'Digital Marketing, E-commerce, Analytics, Conversion Optimization, Social Media Marketing', 'Data-focused, uses statistics and case studies, action-oriented, includes metrics and KPIs', 'analytical'),
('Sarah', 'Sarah is a wellness coach and lifestyle blogger who specializes in holistic health and work-life balance. She has certifications in nutrition and mindfulness.', 'Wellness, Nutrition, Mindfulness, Work-Life Balance, Holistic Health', 'Empathetic and encouraging, uses inclusive language, focuses on practical wellness tips, incorporates mindfulness concepts', 'inspirational'); 