<?php

namespace Bojaghi\CustomTAbles\Tests;

use Bojaghi\CustomTables\Uninstall_Helper;
use ReflectionException;
use WP_UnitTestCase;
use Bojaghi\CustomTables\Custom_Tables;

class Custom_Tables_Test extends WP_UnitTestCase {
	/**
	 * Test __construct() method and setup() method
	 *
	 * @return void
	 */
	public function test_construct_and_setup(): void {
		$plugin = new Custom_Tables(
			array(
				'version_name'      => 'ctt_plugin_version',
				'version'           => '1.1.0',
				'is_theme'          => false,
				'main_file'         => WP_PLUGIN_DIR . '/ctt-plugin.php',
				'activation'        => true,
				'deactivation'      => true,
				'uninstall'         => true,
				'suppress_errors'   => true,
				'enable_update_log' => true,
			),
		);

		$theme = new Custom_Tables(
			array(
				'version_name'      => 'ctt_theme_version',
				'version'           => '1.1.1',
				'is_theme'          => true,
				'main_file'         => 'ctt-theme',
				'activation'        => true,
				'deactivation'      => true,
				'uninstall'         => true,
				'suppress_errors'   => false,
				'enable_update_log' => false,
			),
		);

		// Ensure that all properties are valid.
		$version_name   = get_accessible_property( Custom_Tables::class, 'version_name' );
		$version        = get_accessible_property( Custom_Tables::class, 'version' );
		$suppress_error = get_accessible_property( Custom_Tables::class, 'suppress_errors' );
		$enable_update  = get_accessible_property( Custom_Tables::class, 'enable_update_log' );

		$this->assertEquals( 'ctt_plugin_version', $version_name->getValue( $plugin ) );
		$this->assertEquals( '1.1.0', $version->getValue( $plugin ) );
		$this->assertTrue( $suppress_error->getValue( $plugin ) );
		$this->assertTrue( $enable_update->getValue( $plugin ) );

		$this->assertEquals( 'ctt_theme_version', $version_name->getValue( $theme ) );
		$this->assertEquals( '1.1.1', $version->getValue( $theme ) );
		$this->assertFalse( $suppress_error->getValue( $theme ) );
		$this->assertFalse( $enable_update->getValue( $theme ) );

		// Ensure that init callback is set.
		$this->assertTrue( has_action( 'init', array( $plugin, 'check_table_version' ), 10 ) );
		$this->assertTrue( has_action( 'init', array( $theme, 'check_table_version' ), 10 ) );

		// Ensure that activation, and deactivation hook is alright.
		$this->assertTrue( has_action( 'activate_ctt-plugin.php', array( $plugin, 'activate' ), 10 ) );
		$this->assertTrue( has_action( 'deactivate_ctt-plugin.php', array( $plugin, 'deactivate' ), 10 ) );
		$this->assertTrue( Uninstall_Helper::has_instance( $plugin ) );
		$uninstallable_plugins = (array) get_option( 'uninstall_plugins' );
		$this->assertArrayHasKey( 'ctt-plugin.php', $uninstallable_plugins );

		$this->assertTrue( has_action( 'after_switch_theme', array( $theme, 'activate' ), 10 ) );
		$this->assertTrue( has_action( 'switch_theme', array( $theme, 'deactivate' ), 10 ) );
		$this->assertTrue( has_action( 'delete_theme', array( $theme, 'uninstall' ), 10 ) );
	}

	public function test_create_table_delete_table(): void {
		global $wpdb;

		$ct = new Custom_Tables(
			array(
				'version_name'      => 'ctt_plugin_version',
				'version'           => '1.0.0',
				'is_theme'          => false,
				'main_file'         => 'ctt-plugin.php',
				'activation'        => false,
				'deactivation'      => false,
				'uninstall'         => false,
				'suppress_errors'   => false,
				'enable_update_log' => false,
			),
			array(
				array(
					'table_name'    => "{$wpdb->prefix}ctt_table_001",
					'table_comment' => 'Test 001',
					'field'         => array(
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'name varchar(100) NOT NULL',
						'cnt int(10) NULL DEFAULT NULL',
					),
					'index'         => array(
						'PRIMARY KEY  (id)',
						'UNIQUE KEY uni_name (name)',
						'KEY idx_cnt (cnt)',
					),
					'engine'        => 'InnoDB',
				),
			),
		);

		// Ensure that the table is not present now.
		$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ctt_%'" );
		$this->assertNotContains( "{$wpdb->prefix}ctt_table_001", $tables );
		$this->assertFalse( $ct->get_version_table() );

		// Create tables now.
		$ct->create_tables();

		// Ensure that table is properly created.
		$col = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ctt_%'" );
		$this->assertContains( "{$wpdb->prefix}ctt_table_001", $col );
		$this->assertEquals( '1.0.0', $ct->get_version_setup() );
		$this->assertEquals( '1.0.0', $ct->get_version_table() );
		$this->assertEmpty( $ct->get_query_errors() );

		// Test insert and get query.
		$wpdb->insert(
			"{$wpdb->prefix}ctt_table_001",
			array(
				'id'   => 10,
				'name' => 'ctt-test',
				'cnt'  => 48,
			),
			array(
				'id'   => '%d',
				'name' => '%s',
				'cnt'  => '%d',
			),
		);

		$record = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ctt_table_001 WHERE name='ctt-test' LIMIT 1" );
		$this->assertIsObject( $record );
		$this->assertObjectHasProperty( 'id', $record );
		$this->assertObjectHasProperty( 'name', $record );
		$this->assertObjectHasProperty( 'cnt', $record );
		$this->assertEquals( '10', $record->id );
		$this->assertEquals( 'ctt-test', $record->name );
		$this->assertEquals( '48', $record->cnt );

		// Delete tables now.
		$ct->delete_tables();

		// Ensure that tables are gone.
		$col = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ctt_%'" );
		$this->assertNotContains( "{$wpdb->prefix}ctt_table_001", $col );

		// Ensure that version string in option table is also gone.
		$this->assertFalse( $ct->get_version_table() );
	}

	public function test_create_table_error(): void {
		global $wpdb;

		$ct = new Custom_Tables(
			array(
				'version_name'      => 'ctt_plugin_version',
				'version'           => '1.0.0',
				'is_theme'          => false,
				'main_file'         => 'ctt-plugin.php',
				'activation'        => false,
				'deactivation'      => false,
				'uninstall'         => false,
				'suppress_errors'   => true,
				'enable_update_log' => false,
			),
			array(
				array(
					'table_name'    => "{$wpdb->prefix}ctt_table_002",
					'table_comment' => 'Test 002',
					'field'         => array(
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'name varchar(100) NOT NULL',
						' int(10) NULL DEFAULT 0', // Error: field name is missing.
					),
					'index'         => array(
						'PRIMARY KEY  (id)',
						'UNIQUE KEY uni_name (name)',
						'KEY idx_count ()', // Error index name is missing.
					),
					'engine'        => 'MyISAM',
				),
			),
		);

		// Ensure that the table is not present now.
		$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ctt_%'" );
		$this->assertNotContains( "{$wpdb->prefix}ctt_table_002", $tables );
		$this->assertFalse( $ct->get_version_table() );

		// Try to create now.
		$ct->create_tables();

		// Ensure query has errors.
		$errors = $ct->get_query_errors();
		$this->assertNotEmpty( $errors );
		$this->assertStringStartsWith( 'You have an error in your SQL syntax;', $errors[0] );
		$this->assertStringContainsString( 'int(10) NULL DEFAULT 0', $errors[0] );

		// Ensure db version is not written.
		$this->assertFalse( $ct->get_version_table() );
	}

	public function test_update_table(): void {
		global $wpdb;

		$ct = new Custom_Tables(
			array(
				'version_name'      => 'ctt_plugin_version',
				'version'           => '1.0.0',
				'is_theme'          => false,
				'main_file'         => 'ctt-plugin.php',
				'activation'        => false,
				'deactivation'      => false,
				'uninstall'         => false,
				'suppress_errors'   => true,
				'enable_update_log' => true,
			),
			array(
				array(
					'table_name'    => "{$wpdb->prefix}ctt_test_update",
					'table_comment' => 'Update test table',
					'field'         => array(
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					),
					'index'         => array(
						'PRIMARY KEY  (id)',
					),
					'engine'        => 'InnoDB',
				),
			),
		);

		$ct->create_tables();

		// For update
		$ct = new Custom_Tables(
			array(
				'version_name'      => 'ctt_plugin_version',
				'version'           => '1.1.0', // Version changed.
				'is_theme'          => false,
				'main_file'         => 'ctt-plugin.php',
				'activation'        => false,
				'deactivation'      => false,
				'uninstall'         => false,
				'suppress_errors'   => true,
				'enable_update_log' => true,
			),
			array(
				array(
					'table_name'    => "{$wpdb->prefix}ctt_test_update",
					'table_comment' => 'Update test table',
					'field'         => array(
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'name varchar(100) NOT NULL',
					),
					'index'         => array(
						'PRIMARY KEY  (id)',
						'KEY idx_name (name)',
					),
					'engine'        => 'InnoDB',
				),
			),
		);

		$ct->check_table_version();

		// Ensure that update is okay.
		$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ctt_%'" );
		$this->assertContains( "{$wpdb->prefix}ctt_test_update", $tables );

		// Ensure that field ass added.
		$result = $wpdb->get_results( "DESCRIBE `{$wpdb->prefix}ctt_test_update`" );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'id', $result[0]->Field );
		$this->assertEquals( 'PRI', $result[0]->Key );
		$this->assertEquals( 'bigint(20) unsigned', $result[0]->Type );
		$this->assertEquals( 'name', $result[1]->Field );
		$this->assertEquals( 'MUL', $result[1]->Key );
		$this->assertEquals( 'varchar(100)', $result[1]->Type );

		// Ensure dbDelta splits out some messages.
		$log = $ct->get_update_log();
		$this->assertNotEmpty( $log );
		$this->assertIsString( $log );
		$this->assertStringContainsString( "Added column {$wpdb->prefix}ctt_test_update.name", $log );
		$this->assertStringContainsString( "Added index {$wpdb->prefix}ctt_test_update KEY `idx_name` (`name`)", $log );
	}

	/**
	 * Test get_table_query()
	 *
	 * @dataProvider provider_get_table_query
	 *
	 * @param array  $test_data Test data array.
	 * @param string $expected  Expected output.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function test_get_table_query( array $test_data, string $expected ): void {
		$ct     = new Custom_Tables();
		$method = get_accessible_method( Custom_Tables::class, 'get_table_query' );

		// Private method
		$actual = $method->invoke( $ct, $test_data );

		// Ensure get_table_query() make proper SQL queries.
		$this->assertEquals( $expected, $actual );
	}

	protected function provider_get_table_query(): array {
		global $wpdb;

		return array(
			"{$wpdb->prefix}ctt_table_001" => array(
				// Test data -----------------------------
				array(
					'table_name'    => "{$wpdb->prefix}ctt_table_001",
					'table_comment' => 'Test 001',
					'field'         => array(
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'name varchar(100) NOT NULL',
						'cnt int(10) NULL DEFAULT NULL',
					),
					'index'         => array(
						'PRIMARY KEY  (id)',
						'UNIQUE KEY uni_name (name)',
						'KEY idx_cnt (cnt)',
					),
					'engine'        => 'InnoDB',
				),
				// Expected query ------------------------
				<<<EOD
CREATE TABLE {$wpdb->prefix}ctt_table_001 (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(100) NOT NULL,
	cnt int(10) NULL DEFAULT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY uni_name (name),
	KEY idx_cnt (cnt)
)
ENGINE=InnoDB
DEFAULT CHARSET=$wpdb->charset COLLATE=$wpdb->collate
COMMENT='Test 001';
EOD,
			),
		);
	}

	public static function setUpBeforeClass(): void {
		self::drop_ctt_tables();
	}

	protected function tearDown(): void {
		self::drop_ctt_tables();
	}

	protected static function drop_ctt_tables(): void {
		// Drop all tables.
		global $wpdb;

		$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ctt_%'" );
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
		}

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'bojaghi_custom_tables_%'" );
	}
}
