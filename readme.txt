=== AI Blog Generator ===
Contributors: yourname
Tags: ai, blog, automation, claude, openai, content generation, seo
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate and publish SEO-optimized blog posts using Claude Opus 4 for content and OpenAI for images.

== Description ==

AI Blog Generator revolutionizes content creation by leveraging advanced AI models to automatically generate, schedule, and publish high-quality blog posts. Using Claude Opus 4 for intelligent content generation and OpenAI's GPT-Image-1 for stunning visuals, this plugin creates engaging, SEO-optimized content that drives traffic and engagement.

= Key Features =

* **Automated Idea Generation**: Generate 5 unique blog post ideas daily
* **Intelligent Content Creation**: Uses Claude Opus 4 to write comprehensive, SEO-friendly articles
* **Smart Image Generation**: Creates relevant images using OpenAI with seed image support for product consistency
* **SEO Optimization**: Automatically generates meta descriptions, focus keyphrases, and proper HTML structure
* **Flexible Scheduling**: Randomly distributes posts throughout the day for natural publishing patterns
* **Context Management**: Define reusable contexts for consistent brand voice and product promotion
* **Cost Tracking**: Monitor API usage and costs with detailed analytics
* **Comprehensive Logging**: Track all generation activities and debug issues easily

= How It Works =

1. **Daily Idea Generation**: The plugin automatically generates 5 blog post ideas each day, ensuring no duplicates with existing content
2. **Review & Approve**: Admins review and approve ideas from the WordPress dashboard
3. **Automatic Generation**: Approved ideas are transformed into full blog posts with images
4. **Smart Publishing**: Posts are scheduled and published automatically at optimal times

= Perfect For =

* E-commerce sites needing regular product-focused content
* Affiliate marketers requiring consistent blog posts
* Digital agencies managing multiple client blogs
* Any website wanting to maintain an active blog with minimal effort

== Installation ==

1. Upload the `ai-blog-generator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'AI Blog Generator' > 'Settings' in your admin menu
4. Enter your Anthropic and OpenAI API keys
5. Test the connections to ensure everything is working
6. Configure your contexts and SEO keywords
7. The plugin will start generating ideas automatically!

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Valid Anthropic API key (for Claude Opus 4)
* Valid OpenAI API key (for image generation)
* MySQL 5.6 or higher

== Frequently Asked Questions ==

= How much does it cost to run this plugin? =

The plugin uses pay-per-use APIs. Costs depend on usage:
- Claude Opus 4: ~$0.015 per 1K input tokens, $0.075 per 1K output tokens
- OpenAI Images: ~$0.04 per image
- Average blog post: ~$0.30-$0.50

The plugin includes detailed cost tracking so you can monitor expenses.

= Can I customize the writing style? =

Yes! Use the Contexts feature to define your brand voice, target audience, and specific requirements. The AI will follow these guidelines for all content.

= How do I ensure product images look consistent? =

Upload seed images (transparent PNGs) of your products. The AI will use these to generate contextual images while maintaining product appearance.

= Can I edit posts before publishing? =

Absolutely. All generated posts start as drafts. You can review, edit, and schedule them manually or let the plugin handle publishing automatically.

= What categories and tags are supported? =

The plugin works with your existing WordPress categories and can generate relevant tags automatically. It also balances content across categories.

= Is the content SEO-friendly? =

Yes! The plugin generates:
- SEO-optimized titles
- Meta descriptions
- Focus keyphrases
- Proper heading structure
- Mobile-responsive HTML
- Alt text for images

= Can I control how many posts are published daily? =

Yes, you can set limits between 1-5 posts per day. The plugin will distribute them evenly throughout your specified hours.

== Screenshots ==

1. Settings page with API configuration
2. Blog Ideas dashboard showing pending approvals
3. Context management interface
4. Generated blog post preview
5. Cost analytics dashboard
6. Publishing schedule overview

== Changelog ==

= 1.0.0 =
* Initial release
* Core blog generation functionality
* Anthropic Claude Opus 4 integration
* OpenAI GPT-Image-1 integration
* Admin interface for all features
* Cost tracking and analytics
* Comprehensive logging system
* Smart scheduling with random distribution
* SEO optimization features
* Context management system
* Seed image support for products

== Upgrade Notice ==

= 1.0.0 =
First release of AI Blog Generator. Automatically generate and publish SEO-optimized blog posts with AI.

== API Requirements ==

This plugin requires active API accounts with:

* **Anthropic**: For Claude Opus 4 access (https://anthropic.com)
* **OpenAI**: For GPT-Image-1 access (https://openai.com)

Both services charge per use. Monitor costs through the plugin's analytics dashboard.

== Privacy Policy ==

This plugin sends data to external services:

* **Anthropic API**: Sends your contexts and prompts to generate blog content
* **OpenAI API**: Sends image descriptions and seed images to generate visuals

No personal user data is collected or transmitted. All API communications are secure and encrypted.

== Support ==

For support, feature requests, or bug reports, please visit [your support URL] or email [your support email].