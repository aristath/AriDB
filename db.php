<?php
/**
 * A class to help us manage a custom SQLite database.
 *
 * @package AriDB
 */

namespace Ari;

use PDO;

/**
 * Database class.
 */
class DB {

	/**
	 * An array of columns and their types.
	 *
	 * @var array<string, array<string, string>>
	 */
	protected $columns = [];

	/**
	 * Columns and their types.
	 *
	 * @var array<string, array<string, int>>
	 */
	protected $bind_types = [];

	/**
	 * The database connection.
	 *
	 * @var \PDO
	 */
	protected $db;

	/**
	 * The database file path.
	 *
	 * @var string
	 */
	protected $db_file_path;

	/**
	 * The tables.
	 *
	 * @var array
	 */
	protected $tables = [];

	/**
	 * Constructor.
	 *
	 * @param array $args The arguments.
	 */
	public function __construct( $args = [] ) {
		$this->db_file_path = $args['path'] ?? '';
		$this->tables       = $args['tables'] ?? [];

		// Init the database. If it doesn't exist, it will be created.
		$this->db = new PDO( 'sqlite:' . $this->db_file_path, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] ); // phpcs:ignore WordPress.DB.RestrictedClasses

		// Create the necessary tables.
		$this->create_tables();
	}

	/**
	 * Create the necessary tables in the database.
	 *
	 * @return void
	 */
	protected function create_tables() {
		foreach ( $this->tables as $table_name => $table_columns ) {
			$columns = [];
			foreach ( $table_columns as $column => $column_data ) {
				$columns[] = "$column {$column_data['type']}";
			}
			// Create the table.
			$this->db->exec( 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' ( ' . \implode( ', ', $columns ) . ' )' );

			// Add columns if they don't exist. Used if the table already exists and we need to add new columns.
			foreach ( $table_columns as $column => $column_data ) {
				$this->add_column( $table_name, $column, $column_data['type'], $column_data['default'] );
			}
		}
	}

	/**
	 * Get defaults for entries.
	 *
	 * @param string $table_name The name of the table.
	 *
	 * @return array
	 */
	protected function get_defaults( $table_name ) {
		$table_data = $this->tables[ $table_name ] ?? [];
		$defaults   = [];
		foreach ( $table_data as $column => $column_data ) {
			$defaults[ $column ] = $column_data['default'] ?? '';
		}
		return $defaults;
	}

	/**
	 * Insert a new entry into the database.
	 *
	 * @param string $table_name The name of the table.
	 * @param array  $data The data to insert.
	 *
	 * @return void
	 */
	public function insert( $table_name, $data ) {
		$data = \array_merge( $this->get_defaults( $table_name ), $data );

		$columns = \implode( ', ', \array_keys( $data ) );
		$values  = \implode( ', ', \array_map( fn( $v ) => ":$v", \array_keys( $data ) ) );
		$stmt    = $this->db->prepare(
			"INSERT INTO $table_name ($columns) VALUES ($values)"
		);
		foreach ( array_keys( $data ) as $key ) {
			$stmt->bindParam( ":$key", $data[ $key ], $this->tables[ $table_name ][ $key ]['bind_type'] );
		}
		$stmt->execute();
		$stmt->closeCursor();
	}

	/**
	 * Delete an entry from the database.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $column The column to delete by.
	 * @param string $value The value to delete by.
	 *
	 * @return void
	 */
	public function delete( $table_name, $column, $value ) {
		$stmt = $this->db->prepare( "DELETE FROM $table_name WHERE $column = :$column" );
		$stmt->bindParam( ":$column", $value, $this->tables[ $table_name ][ $column ]['bind_type'] );
		$stmt->execute();
		$stmt->closeCursor();
	}

	/**
	 * Get entries from the database.
	 *
	 * @param string $table_name The name of the table.
	 * @param array  $data   The data to search for.
	 * @param int    $limit  How many results to show. Use -1 for all.
	 * @param int    $offset The offset of the results.
	 * @param string $order_by The column to order by.
	 *
	 * @return array
	 */
	public function get( $table_name, $data = [], $limit = -1, $offset = 0, $order_by = '' ) {
		$where = '1=1';
		$binds = [];

		// If no data is provided dont build the WHERE clause.
		if ( ! empty( $data ) ) {
			$where = [];

			// Build the WHERE clause.
			foreach ( $data as $key => $value ) {
				$where[] = ( is_array( $value ) && isset( $value['operator'] ) )
				? "$key {$value['operator']} :$key"
				: "$key = :$key";

				$binds[ $key ] = ( is_array( $value ) && isset( $value['value'] ) )
				? $value['value']
				: $value;
			}

			$where = implode( ' AND ', $where );
		}

		// Limit the results if > -1.
		$limit_query = $limit > -1 ? " LIMIT $limit" : '';

		if ( $offset > 0 ) {
			$limit_query .= " OFFSET $offset";
		}

		$order_by_query = ! empty( $order_by ) ? " ORDER BY $order_by" : '';

		// Prepare the statement.
		$stmt = $this->db->prepare( "SELECT * FROM {$table_name} WHERE {$where}{$order_by_query}{$limit_query}" );

		// Bind the parameters.
		foreach ( array_keys( $binds ) as $key ) {
			$stmt->bindParam( ":$key", $binds[ $key ], $this->tables[ $table_name ][ $key ]['bind_type'] );
		}

		$stmt->execute();

		$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$stmt->closeCursor();

		return empty( $results ) ? [] : $results;
	}

	/**
	 * Get all entries from the database.
	 *
	 * @param string $table_name The name of the table.
	 *
	 * @return array
	 */
	public function get_all( $table_name ) {
		$stmt = $this->db->prepare( "SELECT * FROM $table_name" );
		$stmt->execute();

		$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$stmt->closeCursor();

		return empty( $results ) ? [] : $results;
	}

	/**
	 * Update an entry in the database.
	 *
	 * @param string $table_name The name of the table.
	 * @param array  $where      The data to use in the WHERE clause.
	 * @param array  $data       The data to update.
	 *
	 * @return void
	 */
	public function update( $table_name, $where, $data ) {
		$set   = [];
		$whr   = '1=1'; // So we can update all rows in the table.
		$binds = [];

		// Build the SET clause.
		foreach ( $data as $key => $value ) {
			$set[]         = "$key = :$key";
			$binds[ $key ] = $value;
		}

		// Build the WHERE clause.
		if ( ! empty( $where ) ) {
			$whr = [];
			foreach ( $where as $key => $value ) {
				$whr[]         = "$key = :$key";
				$binds[ $key ] = $value;
			}

			$whr = implode( ' AND ', $whr );
		}

		$set = implode( ', ', $set );

		// Prepare the statement.
		$stmt = $this->db->prepare( "UPDATE $table_name SET $set WHERE $whr" );

		// Bind the parameters.
		foreach ( array_keys( $binds ) as $key ) {
			$stmt->bindParam( ":$key", $binds[ $key ], $this->tables[ $table_name ][ $key ]['bind_type'] );
		}

		$stmt->execute();
		$stmt->closeCursor();
	}

	/**
	 * Check if the column exists.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $column The column to check.
	 *
	 * @return bool
	 */
	public function column_exists( $table_name, $column ) {
		$query = $this->db->query( "PRAGMA table_info($table_name)" );

		if ( ! $query ) {
			return false;
		}

		$columns = $query->fetchAll( PDO::FETCH_ASSOC );

		foreach ( $columns as $col ) {
			if ( $column === $col['name'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add a column if it doesn't exist.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $column     The column to add.
	 * @param string $type       The type of the column.
	 * @param string $default_value The default value of the column.
	 *
	 * @return void
	 */
	public function add_column( $table_name, $column, $type = 'TEXT', $default_value = '""' ) {
		if ( $this->column_exists( $table_name, $column ) ) {
			return;
		}

		$default_value = '' === $default_value ? '""' : $default_value;

		$this->db->exec( "ALTER TABLE $table_name ADD COLUMN $column $type DEFAULT $default_value" );
	}
}
