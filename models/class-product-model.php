<?php
/**
 * Product Model Class
 *
 * @package AI_Blog_Generator
 */

namespace AI_Blog_Generator\Models;

use AI_Blog_Generator\Utilities\Logger;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Model Class
 *
 * Handles database operations for products.
 */
class Product_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'ai_blog_generator_products';
	
	/**
	 * Create a new product.
	 *
	 * @param array $data Product data.
	 * @return int|false Product ID on success, false on failure.
	 */
	public function create( $data ) {
		// Prepare data
		$data = $this->prepare_data( $data );
		
		// Validate
		if ( ! $this->validate( $data ) ) {
			return false;
		}
		
		// Insert directly using wpdb
		global $wpdb;
		$result = $wpdb->insert( AI_BLOG_GENERATOR_TABLE_PRODUCTS, $data );
		
		if ( false === $result ) {
			Logger::error( 'product_create_failed', 'Failed to insert product', [
				'error' => $wpdb->last_error,
				'data' => $data
			] );
			return false;
		}
		
		return $wpdb->insert_id;
	}
	
	/**
	 * Update a product.
	 *
	 * @param int   $id   Product ID.
	 * @param array $data Product data.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		// Prepare data
		$data = $this->prepare_data( $data );
		
		// Validate
		if ( ! $this->validate( $data, $id ) ) {
			return false;
		}
		
		// Update directly using wpdb
		global $wpdb;
		$result = $wpdb->update( 
			AI_BLOG_GENERATOR_TABLE_PRODUCTS, 
			$data, 
			[ 'id' => $id ],
			null,
			[ '%d' ]
		);
		
		if ( false === $result ) {
			Logger::error( 'product_update_failed', 'Failed to update product', [
				'error' => $wpdb->last_error,
				'id' => $id,
				'data' => $data
			] );
			return false;
		}
		
		return true;
	}
	
	/**
	 * Delete a product.
	 *
	 * @param int $id Product ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ) {
		global $wpdb;
		
		$result = $wpdb->delete( 
			AI_BLOG_GENERATOR_TABLE_PRODUCTS, 
			[ 'id' => $id ],
			[ '%d' ]
		);
		
		if ( false === $result ) {
			Logger::error( 'product_delete_failed', 'Failed to delete product', [
				'error' => $wpdb->last_error,
				'id' => $id
			] );
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get a product by ID.
	 *
	 * @param int $id Product ID.
	 * @return object|null Product object or null if not found.
	 */
	public function get( $id ) {
		global $wpdb;
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_PRODUCTS . " WHERE id = %d",
			$id
		) );
	}
	
	/**
	 * Get all products.
	 *
	 * @param array  $where    Where conditions.
	 * @param string $order_by Order by clause.
	 * @param string $limit    Limit clause.
	 * @return array Array of product objects.
	 */
	public function get_all( $where = [], $order_by = '', $limit = '' ) {
		global $wpdb;
		
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_PRODUCTS;
		
		// Handle where conditions
		if ( ! empty( $where ) ) {
			$conditions = [];
			
			// Handle search
			if ( isset( $where['search'] ) ) {
				$search = '%' . $wpdb->esc_like( $where['search'] ) . '%';
				$conditions[] = $wpdb->prepare( "(name LIKE %s OR description LIKE %s)", $search, $search );
			}
			
			// Add other where conditions here if needed
			
			if ( ! empty( $conditions ) ) {
				$sql .= " WHERE " . implode( ' AND ', $conditions );
			}
		}
		
		// Add order by
		if ( ! empty( $order_by ) ) {
			$sql .= " ORDER BY " . $order_by;
		} else {
			$sql .= " ORDER BY name ASC";
		}
		
		// Add limit
		if ( ! empty( $limit ) ) {
			$sql .= " LIMIT " . $limit;
		}
		
		return $wpdb->get_results( $sql );
	}
	
	/**
	 * Get all products with custom arguments.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of product objects.
	 */
	public function get_all_with_args( $args = [] ) {
		global $wpdb;
		
		$defaults = [
			'orderby' => 'product_name',
			'order' => 'ASC',
			'limit' => 0,
			'offset' => 0,
		];
		
		$args = wp_parse_args( $args, $defaults );
		
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_PRODUCTS;
		
		// Add search
		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sql .= $wpdb->prepare( " WHERE product_name LIKE %s OR product_description LIKE %s", $search, $search );
		}
		
		// Add order
		$sql .= " ORDER BY {$args['orderby']} {$args['order']}";
		
		// Add limit
		if ( $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( " LIMIT %d", $args['limit'] );
			
			if ( $args['offset'] > 0 ) {
				$sql .= $wpdb->prepare( " OFFSET %d", $args['offset'] );
			}
		}
		
		return $wpdb->get_results( $sql );
	}
	
	/**
	 * Count products.
	 *
	 * @param array $where Where conditions.
	 * @return int Number of products.
	 */
	public function count( $where = [] ) {
		global $wpdb;
		
		$sql = "SELECT COUNT(*) FROM " . AI_BLOG_GENERATOR_TABLE_PRODUCTS;
		
		// Handle where conditions
		if ( ! empty( $where ) ) {
			$conditions = [];
			
			// Handle search
			if ( isset( $where['search'] ) ) {
				$search = '%' . $wpdb->esc_like( $where['search'] ) . '%';
				$conditions[] = $wpdb->prepare( "(product_name LIKE %s OR product_description LIKE %s)", $search, $search );
			}
			
			// Add other where conditions here if needed
			
			if ( ! empty( $conditions ) ) {
				$sql .= " WHERE " . implode( ' AND ', $conditions );
			}
		}
		
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Primary key.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'product_name',
		'product_description',
		'ideal_uses',
		'woocommerce_product_id',
	];

	/**
	 * Get a product with its images and links.
	 *
	 * @param int $id Product ID.
	 * @return object|null Product object with images and links or null if not found.
	 */
	public function get_with_relations( $id ) {
		$product = $this->get( $id );
		
		if ( ! $product ) {
			return null;
		}
		
		// Get images
		$product->images = $this->get_product_images( $id );
		
		// Get links
		$product->links = $this->get_product_links( $id );
		
		// Get seed images
		$product->seed_images = $this->get_product_seed_images( $id );
		
		return $product;
	}

	/**
	 * Get all products with their images and links.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of products.
	 */
	public function get_all_with_relations( $args = [] ) {
		$products = $this->get_all_with_args( $args );
		
		foreach ( $products as $product ) {
			$product->images = $this->get_product_images( $product->id );
			$product->links = $this->get_product_links( $product->id );
		}
		
		return $products;
	}

	/**
	 * Get product images.
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of image objects.
	 */
	public function get_product_images( $product_id ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES;
		
		$images = $wpdb->get_results( $wpdb->prepare(
			"SELECT pi.*, p.guid as image_url, p.post_title as image_title
			FROM {$table_name} pi
			LEFT JOIN {$wpdb->posts} p ON pi.attachment_id = p.ID
			WHERE pi.product_id = %d
			ORDER BY pi.display_order ASC, pi.id ASC",
			$product_id
		) );
		
		// Add thumbnail URLs
		foreach ( $images as $image ) {
			if ( $image->attachment_id ) {
				$image->thumbnail_url = wp_get_attachment_image_url( $image->attachment_id, 'thumbnail' );
				$image->medium_url = wp_get_attachment_image_url( $image->attachment_id, 'medium' );
				$image->full_url = wp_get_attachment_image_url( $image->attachment_id, 'full' );
			}
		}
		
		return $images;
	}

	/**
	 * Get product links.
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of link objects.
	 */
	public function get_product_links( $product_id ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS;
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name}
			WHERE product_id = %d
			ORDER BY display_order ASC, id ASC",
			$product_id
		) );
	}

	/**
	 * Add image to product.
	 *
	 * @param int  $product_id Product ID.
	 * @param int  $attachment_id WordPress attachment ID.
	 * @param bool $is_primary Whether this is the primary image.
	 * @param int  $display_order Display order.
	 * @return int|false Insert ID or false on failure.
	 */
	public function add_image( $product_id, $attachment_id, $is_primary = false, $display_order = 0 ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES;
		
		// If setting as primary, unset other primary images
		if ( $is_primary ) {
			$wpdb->update(
				$table_name,
				[ 'is_primary' => 0 ],
				[ 'product_id' => $product_id ]
			);
		}
		
		$result = $wpdb->insert(
			$table_name,
			[
				'product_id' => $product_id,
				'attachment_id' => $attachment_id,
				'is_primary' => $is_primary ? 1 : 0,
				'display_order' => $display_order,
			],
			[ '%d', '%d', '%d', '%d' ]
		);
		
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Remove image from product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $attachment_id Attachment ID.
	 * @return bool Success status.
	 */
	public function remove_image( $product_id, $attachment_id ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES;
		
		$result = $wpdb->delete(
			$table_name,
			[
				'product_id' => $product_id,
				'attachment_id' => $attachment_id,
			],
			[ '%d', '%d' ]
		);
		
		return $result !== false;
	}

	/**
	 * Update image order.
	 *
	 * @param int $product_id Product ID.
	 * @param array $image_order Array of attachment IDs in desired order.
	 * @return bool Success status.
	 */
	public function update_image_order( $product_id, $image_order ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES;
		
		foreach ( $image_order as $order => $attachment_id ) {
			$wpdb->update(
				$table_name,
				[ 'display_order' => $order ],
				[
					'product_id' => $product_id,
					'attachment_id' => $attachment_id,
				],
				[ '%d' ],
				[ '%d', '%d' ]
			);
		}
		
		return true;
	}

	/**
	 * Add link to product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $link_url Link URL.
	 * @param string $link_text Link text (optional).
	 * @param string $link_type Link type.
	 * @param int    $display_order Display order.
	 * @return int|false Insert ID or false on failure.
	 */
	public function add_link( $product_id, $link_url, $link_text = '', $link_type = 'product_page', $display_order = 0 ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS;
		
		$result = $wpdb->insert(
			$table_name,
			[
				'product_id' => $product_id,
				'link_url' => $link_url,
				'link_text' => $link_text,
				'link_type' => $link_type,
				'display_order' => $display_order,
			],
			[ '%d', '%s', '%s', '%s', '%d' ]
		);
		
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Remove link from product.
	 *
	 * @param int $link_id Link ID.
	 * @return bool Success status.
	 */
	public function remove_link( $link_id ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS;
		
		$result = $wpdb->delete(
			$table_name,
			[ 'id' => $link_id ],
			[ '%d' ]
		);
		
		return $result !== false;
	}

	/**
	 * Update link order.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $link_order Array of link IDs in desired order.
	 * @return bool Success status.
	 */
	public function update_link_order( $product_id, $link_order ) {
		global $wpdb;
		
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS;
		
		foreach ( $link_order as $order => $link_id ) {
			$wpdb->update(
				$table_name,
				[ 'display_order' => $order ],
				[ 'id' => $link_id ],
				[ '%d' ],
				[ '%d' ]
			);
		}
		
		return true;
	}

	/**
	 * Import product from WooCommerce.
	 *
	 * @param int $wc_product_id WooCommerce product ID.
	 * @return int|false Product ID or false on failure.
	 */
	public function import_from_woocommerce( $wc_product_id ) {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			Logger::error( 'woocommerce_not_active', 'WooCommerce is not active' );
			return false;
		}
		
		// Get WooCommerce product
		$wc_product = wc_get_product( $wc_product_id );
		if ( ! $wc_product ) {
			Logger::error( 'wc_product_not_found', 'WooCommerce product not found', [ 'product_id' => $wc_product_id ] );
			return false;
		}
		
		// Check if already imported
		global $wpdb;
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCTS;
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE woocommerce_product_id = %d",
			$wc_product_id
		) );
		if ( $existing ) {
			Logger::info( 'product_already_imported', 'Product already imported', [ 'product_id' => $existing->id ] );
			return $existing->id;
		}
		
		// Create product
		$product_data = [
			'product_name' => $wc_product->get_name(),
			'product_description' => $wc_product->get_short_description(),
			'woocommerce_product_id' => $wc_product_id,
		];
		
		$product_id = $this->create( $product_data );
		
		if ( ! $product_id ) {
			Logger::error( 'product_import_failed', 'Failed to import product', [ 'wc_product_id' => $wc_product_id ] );
			return false;
		}
		
		// Import images
		$image_ids = $wc_product->get_gallery_image_ids();
		$featured_image_id = $wc_product->get_image_id();
		
		// Add featured image first
		if ( $featured_image_id ) {
			$this->add_image( $product_id, $featured_image_id, true, 0 );
		}
		
		// Add gallery images
		foreach ( $image_ids as $order => $image_id ) {
			$this->add_image( $product_id, $image_id, false, $order + 1 );
		}
		
		// Add product page link
		$product_url = get_permalink( $wc_product_id );
		if ( $product_url ) {
			$this->add_link( $product_id, $product_url, 'View Product', 'product_page', 0 );
		}
		
		Logger::info( 'product_imported', 'Product imported successfully', [
			'product_id' => $product_id,
			'wc_product_id' => $wc_product_id,
			'images_count' => count( $image_ids ) + ( $featured_image_id ? 1 : 0 ),
		] );
		
		return $product_id;
	}

	/**
	 * Get available WooCommerce products for import.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of WooCommerce products.
	 */
	public function get_woocommerce_products( $args = [] ) {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}
		
		$defaults = [
			'limit' => 20,
			'orderby' => 'name',
			'order' => 'ASC',
			'status' => 'publish',
		];
		
		$args = wp_parse_args( $args, $defaults );
		
		// Get already imported product IDs
		global $wpdb;
		$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCTS;
		$imported_ids = $wpdb->get_col(
			"SELECT woocommerce_product_id FROM {$table_name} WHERE woocommerce_product_id IS NOT NULL"
		);
		
		// Exclude already imported products
		if ( ! empty( $imported_ids ) ) {
			$args['exclude'] = $imported_ids;
		}
		
		$products = wc_get_products( $args );
		
		// Format for display
		$formatted_products = [];
		foreach ( $products as $product ) {
			$formatted_products[] = [
				'id' => $product->get_id(),
				'name' => $product->get_name(),
				'description' => $product->get_short_description(),
				'image_url' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'price' => $product->get_price_html(),
				'permalink' => get_permalink( $product->get_id() ),
			];
		}
		
		return $formatted_products;
	}

	/**
	 * Validate product data.
	 *
	 * @param array $data Product data.
	 * @param int   $id   Product ID for updates.
	 * @return bool True if valid, false if not.
	 */
	protected function validate( $data, $id = null ) {
		// Product name is required
		if ( empty( $data['product_name'] ) ) {
			Logger::error( 'product_validation_failed', 'Product name is required', [ 'data' => $data ] );
			return false;
		}
		
		// Check for duplicate product names
		if ( ! empty( $data['product_name'] ) ) {
			global $wpdb;
			$table_name = AI_BLOG_GENERATOR_TABLE_PRODUCTS;
			
			$query = $wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE product_name = %s",
				$data['product_name']
			);
			
			if ( $id ) {
				$query .= $wpdb->prepare( " AND id != %d", $id );
			}
			
			$existing = $wpdb->get_row( $query );
			
			if ( $existing ) {
				Logger::error( 'product_validation_failed', 'A product with this name already exists', [ 
					'product_name' => $data['product_name'],
					'existing_id' => $existing->id 
				] );
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return mixed Sanitized value.
	 */
	protected function sanitize_field( $field, $value ) {
		switch ( $field ) {
			case 'product_name':
				return sanitize_text_field( $value );
			
			case 'product_description':
			case 'ideal_uses':
				return wp_kses_post( $value );
			
			case 'woocommerce_product_id':
				return absint( $value );
			
			default:
				return parent::sanitize_field( $field, $value );
		}
	}

	/**
	 * Get product seed images.
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of seed image objects.
	 */
	public function get_product_seed_images( $product_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_blog_generator_product_seed_images';
		
		$images = $wpdb->get_results( $wpdb->prepare(
			"SELECT psi.*, p.guid as image_url, p.post_title as image_title
			FROM {$table_name} psi
			LEFT JOIN {$wpdb->posts} p ON psi.attachment_id = p.ID
			WHERE psi.product_id = %d
			ORDER BY psi.display_order ASC, psi.id ASC",
			$product_id
		) );
		
		// Add thumbnail URLs
		foreach ( $images as $image ) {
			if ( $image->attachment_id ) {
				$image->thumbnail_url = wp_get_attachment_image_url( $image->attachment_id, 'thumbnail' );
				$image->medium_url = wp_get_attachment_image_url( $image->attachment_id, 'medium' );
				$image->full_url = wp_get_attachment_image_url( $image->attachment_id, 'full' );
			}
		}
		
		return $images;
	}

	/**
	 * Add seed image to product.
	 *
	 * @param int  $product_id Product ID.
	 * @param int  $attachment_id WordPress attachment ID.
	 * @param int  $display_order Display order.
	 * @return int|false Insert ID or false on failure.
	 */
	public function add_seed_image( $product_id, $attachment_id, $display_order = 0 ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_blog_generator_product_seed_images';
		
		// Verify it's a PNG file
		$mime_type = get_post_mime_type( $attachment_id );
		if ( $mime_type !== 'image/png' ) {
			Logger::error( 'invalid_seed_image_format', 'Seed images must be PNG files', [
				'attachment_id' => $attachment_id,
				'mime_type' => $mime_type
			] );
			return false;
		}
		
		// Get image URL
		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			Logger::error( 'seed_image_url_not_found', 'Could not get URL for attachment', [
				'attachment_id' => $attachment_id
			] );
			return false;
		}
		
		$result = $wpdb->insert(
			$table_name,
			[
				'product_id' => $product_id,
				'attachment_id' => $attachment_id,
				'image_url' => $image_url,
				'display_order' => $display_order,
			],
			[ '%d', '%d', '%s', '%d' ]
		);
		
		if ( $result ) {
			Logger::info( 'seed_image_added', 'Seed image added to product', [
				'product_id' => $product_id,
				'attachment_id' => $attachment_id
			] );
		}
		
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Remove seed image from product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $attachment_id Attachment ID.
	 * @return bool Success status.
	 */
	public function remove_seed_image( $product_id, $attachment_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_blog_generator_product_seed_images';
		
		$result = $wpdb->delete(
			$table_name,
			[
				'product_id' => $product_id,
				'attachment_id' => $attachment_id,
			],
			[ '%d', '%d' ]
		);
		
		if ( $result !== false ) {
			Logger::info( 'seed_image_removed', 'Seed image removed from product', [
				'product_id' => $product_id,
				'attachment_id' => $attachment_id
			] );
		}
		
		return $result !== false;
	}

	/**
	 * Update seed image order.
	 *
	 * @param int $product_id Product ID.
	 * @param array $image_order Array of attachment IDs in desired order.
	 * @return bool Success status.
	 */
	public function update_seed_image_order( $product_id, $image_order ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_blog_generator_product_seed_images';
		
		foreach ( $image_order as $order => $attachment_id ) {
			$wpdb->update(
				$table_name,
				[ 'display_order' => $order ],
				[
					'product_id' => $product_id,
					'attachment_id' => $attachment_id,
				],
				[ '%d' ],
				[ '%d', '%d' ]
			);
		}
		
		Logger::info( 'seed_image_order_updated', 'Seed image order updated', [
			'product_id' => $product_id,
			'new_order' => $image_order
		] );
		
		return true;
	}

	/**
	 * Get all seed images with product information.
	 *
	 * @return array Array of seed images with product details.
	 */
	public function get_all_seed_images() {
		global $wpdb;
		
		$seed_table = $wpdb->prefix . 'ai_blog_generator_product_seed_images';
		$products_table = AI_BLOG_GENERATOR_TABLE_PRODUCTS;
		
		$sql = $wpdb->prepare(
			"SELECT psi.*, p.product_name as name, p.product_description as description, att.guid as image_url
			FROM {$seed_table} psi
			LEFT JOIN {$products_table} p ON psi.product_id = p.id
			LEFT JOIN {$wpdb->posts} att ON psi.attachment_id = att.ID
			ORDER BY p.product_name ASC, psi.display_order ASC"
		);
		
		$images = $wpdb->get_results( $sql );
		
		// Add thumbnail URLs
		foreach ( $images as $image ) {
			if ( $image->attachment_id ) {
				$image->thumbnail_url = wp_get_attachment_image_url( $image->attachment_id, 'thumbnail' );
				$image->medium_url = wp_get_attachment_image_url( $image->attachment_id, 'medium' );
				$image->full_url = wp_get_attachment_image_url( $image->attachment_id, 'full' );
			}
		}
		
		return $images;
	}

	/**
	 * Get all active products.
	 *
	 * @return array Array of active products.
	 */
	public function get_active_products() {
		return $this->get_all( [ 'active' => 1 ], 'product_name ASC' );
	}
} 