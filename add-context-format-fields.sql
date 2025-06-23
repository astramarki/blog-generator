-- Add fields for format-specific context inclusion
ALTER TABLE wp_ai_blog_contexts 
ADD COLUMN always_include_avada TINYINT(1) DEFAULT 0 AFTER always_include_images,
ADD COLUMN always_include_html TINYINT(1) DEFAULT 0 AFTER always_include_avada;

-- Add indexes for better query performance
ALTER TABLE wp_ai_blog_contexts
ADD INDEX idx_always_avada (always_include_avada),
ADD INDEX idx_always_html (always_include_html); 