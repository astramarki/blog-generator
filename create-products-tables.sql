-- Create Products Tables for AI Blog Generator
-- Replace 'wp_' with your actual WordPress table prefix

-- Products Table
CREATE TABLE IF NOT EXISTS `wp_ai_blog_generator_products` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `product_name` varchar(255) NOT NULL,
    `product_description` text DEFAULT NULL,
    `ideal_uses` text DEFAULT NULL,
    `woocommerce_product_id` bigint(20) unsigned DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_woocommerce_product_id` (`woocommerce_product_id`),
    KEY `idx_product_name` (`product_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Images Table
CREATE TABLE IF NOT EXISTS `wp_ai_blog_generator_product_images` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `product_id` bigint(20) unsigned NOT NULL,
    `attachment_id` bigint(20) unsigned NOT NULL,
    `display_order` int(11) DEFAULT 0,
    `is_primary` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_attachment_id` (`attachment_id`),
    KEY `idx_display_order` (`display_order`),
    CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `wp_ai_blog_generator_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Links Table
CREATE TABLE IF NOT EXISTS `wp_ai_blog_generator_product_links` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `product_id` bigint(20) unsigned NOT NULL,
    `link_url` varchar(500) NOT NULL,
    `link_text` varchar(255) DEFAULT NULL,
    `link_type` enum('product_page','purchase','documentation','other') DEFAULT 'product_page',
    `display_order` int(11) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_link_type` (`link_type`),
    KEY `idx_display_order` (`display_order`),
    CONSTRAINT `fk_product_links_product` FOREIGN KEY (`product_id`) REFERENCES `wp_ai_blog_generator_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 