<?php
/**
 * Base Model Class
 *
 * @package AI_Blog_Generator
 * @subpackage Models
 */

namespace AI_Blog_Generator\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base Model Class
 *
 * Abstract base class for all model classes.
 * Provides common functionality and database integration.
 *
 * @since 1.0.0
 */
abstract class Model {

	/**
	 * Database manager instance.
	 *
	 * @var Database_Manager
	 */
	protected $db;

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Primary key field name.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Fields that can be filled.
	 *
	 * @var array
	 */
	protected $fillable = [];

	/**
	 * Fields that should be hidden.
	 *
	 * @var array
	 */
	protected $hidden = [];

	/**
	 * Validation rules.
	 *
	 * @var array
	 */
	protected $rules = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = Database_Manager::get_instance();
	}

	/**
	 * Find a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return object|null
	 */
	public function find( $id ) {
		return $this->db->get( $this->table, [ $this->primary_key => $id ] );
	}

	/**
	 * Get a record by ID (alias for find).
	 *
	 * @param int $id Record ID.
	 * @return object|null
	 */
	public function get( $id ) {
		return $this->find( $id );
	}

	/**
	 * Get all records (alias for find_all).
	 *
	 * @param array  $where    Where conditions.
	 * @param string $order_by Order by clause.
	 * @param string $limit    Limit clause.
	 * @return array
	 */
	public function get_all( $where = [], $order_by = '', $limit = '' ) {
		return $this->find_all( $where, $order_by, $limit );
	}

	/**
	 * Find all records.
	 *
	 * @param array  $where    Where conditions.
	 * @param string $order_by Order by clause.
	 * @param string $limit    Limit clause.
	 * @return array
	 */
	public function find_all( $where = [], $order_by = '', $limit = '' ) {
		return $this->db->get_all( $this->table, $where, $order_by, $limit );
	}

	/**
	 * Find one record.
	 *
	 * @param array $where Where conditions.
	 * @return object|null
	 */
	public function find_one( $where ) {
		return $this->db->get( $this->table, $where );
	}

	/**
	 * Create a new record.
	 *
	 * @param array $data Data to insert.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		$data = $this->prepare_data( $data );
		
		if ( ! $this->validate( $data ) ) {
			return false;
		}

		return $this->db->insert( $this->table, $data );
	}

	/**
	 * Update a record.
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$data = $this->prepare_data( $data );
		
		if ( ! $this->validate( $data, $id ) ) {
			return false;
		}

		$result = $this->db->update( $this->table, $data, [ $this->primary_key => $id ] );
		return false !== $result;
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ) {
		$result = $this->db->delete( $this->table, [ $this->primary_key => $id ] );
		return false !== $result;
	}

	/**
	 * Count records.
	 *
	 * @param array $where Where conditions.
	 * @return int
	 */
	public function count( $where = [] ) {
		$records = $this->find_all( $where );
		return count( $records );
	}

	/**
	 * Check if record exists.
	 *
	 * @param array $where Where conditions.
	 * @return bool
	 */
	public function exists( $where ) {
		$record = $this->find_one( $where );
		return null !== $record;
	}

	/**
	 * Prepare data for database operations.
	 *
	 * @param array $data Raw data.
	 * @return array Prepared data.
	 */
	protected function prepare_data( $data ) {
		// Filter only fillable fields.
		if ( ! empty( $this->fillable ) ) {
			$data = array_intersect_key( $data, array_flip( $this->fillable ) );
		}

		// Remove hidden fields.
		if ( ! empty( $this->hidden ) ) {
			$data = array_diff_key( $data, array_flip( $this->hidden ) );
		}

		// Sanitize data.
		foreach ( $data as $key => $value ) {
			$data[ $key ] = $this->sanitize_field( $key, $value );
		}

		return $data;
	}

	/**
	 * Sanitize a field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return mixed Sanitized value.
	 */
	protected function sanitize_field( $field, $value ) {
		// Override in child classes for specific sanitization.
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}
		return $value;
	}

	/**
	 * Validate data.
	 *
	 * @param array    $data Data to validate.
	 * @param int|null $id   Record ID for updates.
	 * @return bool
	 */
	protected function validate( $data, $id = null ) {
		// Override in child classes for specific validation.
		return true;
	}

	/**
	 * Format a record for output.
	 *
	 * @param object $record Raw record.
	 * @return array Formatted record.
	 */
	public function format( $record ) {
		if ( ! $record ) {
			return null;
		}

		$formatted = (array) $record;

		// Remove hidden fields.
		if ( ! empty( $this->hidden ) ) {
			$formatted = array_diff_key( $formatted, array_flip( $this->hidden ) );
		}

		return $formatted;
	}

	/**
	 * Format multiple records for output.
	 *
	 * @param array $records Raw records.
	 * @return array Formatted records.
	 */
	public function format_many( $records ) {
		return array_map( [ $this, 'format' ], $records );
	}

	/**
	 * Perform a transaction.
	 *
	 * @param callable $callback Transaction callback.
	 * @return mixed Result of callback or false on failure.
	 */
	protected function transaction( $callback ) {
		$this->db->start_transaction();

		try {
			$result = $callback();
			$this->db->commit();
			return $result;
		} catch ( \Exception $e ) {
			$this->db->rollback();
			return false;
		}
	}
} 