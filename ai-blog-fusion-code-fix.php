<?php
/**
 * Plugin Name: AI Blog Fusion Code Fix
 * Description: Ensures JavaScript in fusion_code blocks is properly wrapped in script tags
 * Version: 1.0
 * Author: AI Blog Generator
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fix fusion_code blocks by ensuring JavaScript is wrapped in script tags
 * This runs when content is saved to database
 */
function ai_blog_fix_fusion_code_content( $content ) {
    // Only process if content has fusion_code blocks
    if ( strpos( $content, '[fusion_code]' ) === false ) {
        return $content;
    }
    
    // Find all fusion_code blocks
    $content = preg_replace_callback(
        '/\[fusion_code\](.*?)\[\/fusion_code\]/s',
        function( $matches ) {
            $code_content = $matches[1];
            
            // Decode HTML entities first
            $code_content = html_entity_decode( $code_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            
            // Check if already has script tags
            if ( preg_match( '/<script[^>]*>.*<\/script>/is', $code_content ) ) {
                // Already has script tags, just return cleaned content
                return '[fusion_code]' . $code_content . '[/fusion_code]';
            }
            
            // Check if this is JavaScript code
            $js_indicators = ['function', 'var ', 'const ', 'let ', 'document.', 'window.', 'new ', 'ApexCharts'];
            $is_javascript = false;
            
            foreach ( $js_indicators as $indicator ) {
                if ( stripos( $code_content, $indicator ) !== false ) {
                    $is_javascript = true;
                    break;
                }
            }
            
            // If JavaScript, wrap in script tags
            if ( $is_javascript ) {
                $code_content = '<script>' . "\n" . trim( $code_content ) . "\n" . '</script>';
            }
            
            return '[fusion_code]' . $code_content . '[/fusion_code]';
        },
        $content
    );
    
    return $content;
}

// Add filter with high priority to run after other filters
add_filter( 'content_save_pre', 'ai_blog_fix_fusion_code_content', 9999 );
