<?php 
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/db.php';

final class DBTest extends TestCase {

	/**
	 * @var \Ari\DB
	 */
	protected static $db;

	/**
	 * Set up the database.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		if ( file_exists( __DIR__ . '/db.sqlite' ) ) {
			unlink( __DIR__ . '/db.sqlite' );
		}

		self::$db = new \Ari\DB( [
			'path'   => __DIR__ . '/db.sqlite',
			'tables' => [
				'test' => [
					'id' => [
						'type' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
						'bind_type' => PDO::PARAM_INT,
						'default' => '',
					],
					'name' => [
						'type' => 'TEXT NOT NULL',
						'bind_type' => PDO::PARAM_STR,
						'default' => '',
					],
					'email' => [
						'type' => 'TEXT',
						'bind_type' => PDO::PARAM_STR,
						'default' => '',
					],
				],
			],		
		] );
	}

	/**
	 * Test that the database is created.
	 */
	public function test_db_can_be_created(): void {
		$this->assertInstanceOf( \Ari\DB::class, self::$db );
		$this->assertFileExists( __DIR__ . '/db.sqlite' );
	}

	/**
	 * Test that the database can insert columns.
	 */
	public function test_db_can_insert_columns(): void {
		self::$db = new \Ari\DB( [
			'path'   => __DIR__ . '/db.sqlite',
			'tables' => [
				'test' => [
					'id' => [
						'type' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
						'bind_type' => PDO::PARAM_INT,
						'default' => '',
					],
					'name' => [
						'type' => 'TEXT NOT NULL',
						'bind_type' => PDO::PARAM_STR,
						'default' => '',
					],
					'email' => [
						'type' => 'TEXT',
						'bind_type' => PDO::PARAM_STR,
						'default' => '',
					],
					'age' => [
						'type' => 'INTEGER',
						'bind_type' => PDO::PARAM_INT,
						'default' => '',
					],
				],
			],		
		] );
		$this->assertTrue( self::$db->column_exists( 'test', 'age' ) );
	}

	/**
	 * Test that the database can insert data.
	 */
	public function test_db_can_insert_data(): void {
		self::$db->insert( 'test', [
			'name' => 'John Doe',
			'email' => 'john.doe@example.com',
		] );

		$data = self::$db->get( 'test', [ 'name' => 'John Doe' ] );

		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertArrayHasKey( 'name', $data[0] );
		$this->assertArrayHasKey( 'email', $data[0] );
		$this->assertSame( 'John Doe', $data[0]['name'] );
		$this->assertSame( 'john.doe@example.com', $data[0]['email'] );
	}

	/**
	 * Test that the database can update data.
	 */
	public function test_db_can_update_data(): void {
		self::$db->update( 
			'test', 
			[ 'name' => 'John Doe' ], 
			[ 'email' => 'jane.doe@example.com', 'age' => 25 ] 
		);
		self::$db->update( 
			'test', 
			[ 'email' => 'jane.doe@example.com' ], 
			[ 'name' => 'Jane Doe' ] 
		);

		$data = self::$db->get( 'test', [] );

		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertArrayHasKey( 'name', $data[0] );
		$this->assertArrayHasKey( 'email', $data[0] );
		$this->assertArrayHasKey( 'age', $data[0] );
		$this->assertSame( 'Jane Doe', $data[0]['name'] );
		$this->assertSame( 'jane.doe@example.com', $data[0]['email'] );
		$this->assertSame( 25, $data[0]['age'] );
	}

	/**
	 * Test that the database can delete data.
	 */
	public function test_db_can_delete_data(): void {
		self::$db->delete( 'test', 'name', 'John Doe' );

		$data = self::$db->get( 'test', [ 'name' => 'John Doe' ] );
		$this->assertEmpty( $data );
	}

	/**
	 * Delete the database on tearDown.
	 */
	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();
		unlink( __DIR__ . '/db.sqlite' );
	}
}