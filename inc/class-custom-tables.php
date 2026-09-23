<?php
/**
 * Bojaghi Custom Tables
 *
 * @package Bojaghi\CustomTables
 */

declare( strict_types=1 );

namespace Bojaghi\CustomTables;

use Bojaghi\Contract\Module;
use Bojaghi\Helper\Helper;

/**
 * Custom Table class
 */
class Custom_Tables implements Module {
	/**
	 * Table version name string.
	 *
	 * This string is used for option name of version info.
	 * So Custom_Tables will call like `get_option( $custom_table->version_name );`,
	 * to get current table version information.
	 *
	 * @var string
	 */
	private string $version_name;

	/**
	 * Table version string.
	 *
	 * Version of this class.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Array or string of table configuration
	 *
	 * @var array|string
	 */
	private array|string $table_config;

	/**
	 * Flag to suppress database errors
	 *
	 * @var bool
	 */
	private bool $suppress_errors;

	/**
	 * Stashed flag value.
	 *
	 * @var bool|null
	 */
	private bool|null $old_suppress_errors;

	/**
	 * List of error messages
	 *
	 * @var array<string>
	 */
	private array $query_errors;

	/**
	 * Log dbDelta result when update table, defaults to true
	 *
	 * Option name will be ${version_name}_update_log
	 *
	 * @var bool
	 */
	private bool $enable_update_log;

	/**
	 * Constructor
	 *
	 * @param array|string $config       Config for Custom_Table class.
	 * @param array|string $table_config Table schema configruation.
	 */
	public function __construct( array|string $config = '', array|string $table_config = '' ) {
		$this->version_name        = '';
		$this->version             = '';
		$this->table_config        = $table_config;
		$this->old_suppress_errors = null;
		$this->query_errors        = array();
		$this->enable_update_log   = true;

		$default_config = array(
			'version_name'      => '',    // Optional.
			'version'           => '',    // Optional.
			'is_theme'          => false, // Optional, defaults to false.
			'main_file'         => '',    // Optional, defaults to blank.
			'activation'        => false, // Optional, defaults to false. Create tables on activation.
			'deactivation'      => false, // Optional, defaults to false. Delete tables on deactivation.
			'uninstall'         => false, // Optional, defaults to false. Delete tables on uninstallation.
			'suppress_errors'   => false, // Optional, defaults to false.
			'enable_update_log' => true,  // Optional, defaults to true.
		);

		$this->setup( wp_parse_args( Helper::load_config( $config ), $default_config ) );
	}

	/**
	 * Setup the class
	 *
	 * @param array $class_config Class configuration array.
	 *
	 * @return void
	 */
	private function setup( array $class_config ): void {
		$version_name      = $class_config['version_name'];
		$version           = $class_config['version'];
		$is_theme          = $class_config['is_theme'];
		$main_file         = $class_config['main_file'];
		$activation        = $class_config['activation'];
		$deactivation      = $class_config['deactivation'];
		$uninstall         = $class_config['uninstall'];
		$enable_update_log = $class_config['enable_update_log'];

		if ( $version_name && $version ) {
			$this->version_name = $version_name;
			$this->version      = $version;
			add_action( 'init', array( $this, 'check_table_version' ) );
		}

		if ( $is_theme ) {
			if ( $activation ) {
				add_action( 'after_switch_theme', array( $this, 'activate' ), 10, 2 );
			}
			if ( $deactivation ) {
				add_action( 'switch_theme', array( $this, 'deactivate' ), 10, 3 );
			}
			if ( $uninstall ) {
				add_action( 'delete_theme', array( $this, 'uninstall' ) );
			}
		} else {
			if ( $activation ) {
				register_activation_hook( $main_file, array( $this, 'activate' ) );
			}
			if ( $deactivation ) {
				register_deactivation_hook( $main_file, array( $this, 'deactivate' ) );
			}
			if ( $uninstall ) {
				Uninstall_Helper::add_instance( $this );
				register_uninstall_hook( $main_file, array( Uninstall_Helper::class, 'uninstall' ) );
			}
		}

		$this->suppress_errors   = filter_var( $class_config['suppress_errors'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$this->enable_update_log = $enable_update_log;
	}

	/**
	 * Get table schema configuration
	 *
	 * @return array
	 */
	private function get_table_config(): array {
		return Helper::load_config( $this->table_config );
	}

	/**
	 * Ensure upgrade.php is included.
	 *
	 * @return void
	 */
	public function ensure_include(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
	}

	/**
	 * Callback method of plugin activation, and theme switching.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->create_tables();
	}

	/**
	 * Create tables by given configuration
	 *
	 * @return void
	 */
	public function create_tables(): void {
		$this->ensure_include();
		$this->set_suppress_errors();
		$this->query_errors = array();

		/**
		 * Action before creating all tables.
		 *
		 * @var string $version_name Version name.
		 */
		do_action( 'bojaghi_custom_tables_before_create_tables', $this->version_name );

		$table_config = $this->get_table_config();

		foreach ( $table_config as $table ) {
			$query = $this->get_table_query( $table );

			if ( $query ) {
				/**
				 * Filter query before calling dbDelta
				 *
				 * @var string $query        Query string.
				 * @var string $table        Table name.
				 * @var string $version_name Version name.
				 */
				$query = apply_filters( 'bojaghi_custom_tables_before_create_table', $query, $table, $this->version_name );

				$result = dbDelta( $query );

				$this->check_query_errors();

				/**
				 * Action after calling dbDelta
				 *
				 * @var string   $table        Table name.
				 * @var string   $version_name Version name.
				 * @var string[] $result       dbDelta result.
				 */
				do_action( 'bojaghi_custom_tables_after_create_table', $table, $this->version_name, $result );
			}
		}

		/**
		 * Action after creating all tables.
		 *
		 * @var string $version_name Version name.
		 * @var array  $query_errors Errors
		 */
		do_action( 'bojaghi_custom_tables_after_create_tables', $this->version_name, $this->query_errors );

		if ( ! $this->has_query_errors() ) {
			$this->set_version_table();
		}

		$this->reset_suppress_errors();
	}

	/**
	 * Callback method of plugin deactivation, and theme switching.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->delete_tables();
	}

	/**
	 * Delete tables when plugin or theme uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->delete_tables();
	}

	/**
	 * Delete tables by given information.
	 *
	 * @return void
	 */
	public function delete_tables(): void {
		global $wpdb;

		$this->set_suppress_errors();
		$this->query_errors = array();

		/**
		 * Action before deleting all tables.
		 *
		 * @var string $version_name Version name.
		 */
		do_action( 'bojaghi_custom_tables_before_delete_tables', $this->version_name );

		$table_conf = $this->get_table_config();

		foreach ( $table_conf as $table ) {
			$table_name = $table['table_name'] ?? '';

			if ( $table_name ) {
				/**
				 * Filter query before querying.
				 *
				 * @var string $query        Query string.
				 * @var string $table        Table name.
				 * @var string $version_name Version name.
				 */
				$query = "DROP TABLE IF EXISTS `$table_name`";
				$query = apply_filters( 'bojaghi_custom_tables_before_delete_table', $query, $table, $this->version_name );

				// phpcs:ignore
				$wpdb->query( $query );

				$this->check_query_errors();

				/**
				 * Action after calling dbDelta
				 *
				 * @var string   $table_name   Table name.
				 * @var string   $version_name Version name.
				 * @var string[] $result       dbDelta result.
				 */
				do_action( 'bojaghi_custom_tables_after_delete_table', $table_name, $this->version_name, $result );
			}
		}

		/**
		 * Action after deleting all tables.
		 *
		 * @var string $version_name Version name.
		 * @var array  $query_errors Errors.
		 */
		do_action( 'bojaghi_custom_tables_after_delete_tables', $this->version_name, $this->query_errors );

		if ( ! $this->has_query_errors() ) {
			$this->clear_version_table();
		}

		$this->reset_suppress_errors();
	}

	/**
	 * Check table version, and update tables
	 *
	 * @return void
	 */
	public function check_table_version(): void {
		if ( $this->version_name && $this->get_version_setup() ) {
			$version = $this->get_version_table();
			if ( false === $version || version_compare( $this->version, $version, '>' ) ) {
				$this->update_tables();
			}
		}
	}

	/**
	 * Update tables by using dbDelta() function.
	 *
	 * @return void
	 */
	public function update_tables(): void {
		$update_log = array();
		$this->ensure_include();
		$this->set_suppress_errors();
		$this->query_errors = array();

		/**
		 * Action before updating all tables.
		 *
		 * @var string $version_name Version name.
		 */
		do_action( 'bojaghi_custom_tables_before_update_tables', $this->version_name );

		$table_conf = $this->get_table_config();

		foreach ( $table_conf as $table ) {
			$query = $this->get_table_query( $table );

			if ( $query ) {
				/**
				 * Filter query before calling dbDelta
				 *
				 * @var string $query        Query string.
				 * @var string $table        Table name.
				 * @var string $version_name Version name.
				 */
				$query = apply_filters( 'bojaghi_custom_tables_before_update_table', $query, $table, $this->version_name );

				$result = dbDelta( $query );

				$this->check_query_errors();

				if ( $result ) {
					$update_log = array_merge( $update_log, $result );
				}

				/**
				 * Action after calling dbDelta
				 *
				 * @var string   $table        Table name.
				 * @var string   $version_name Version name.
				 * @var string[] $result       dbDelta result.
				 */
				do_action( 'bojaghi_custom_tables_after_update_table', $table, $this->version_name, $result );
			}
		}

		/**
		 * Action after updating all tables.
		 *
		 * @var string $version_name Version name.
		 * @var array  $query_errors Errors
		 */
		do_action( 'bojaghi_custom_tables_after_update_tables', $this->version_name, $this->query_errors );

		if ( ! $this->has_query_errors() ) {
			$this->set_version_table();
		}

		// Write every dbDelta result to option table.
		$this->set_update_log( $update_log );

		$this->reset_suppress_errors();
	}

	/**
	 * Return query errors
	 *
	 * @return string[]
	 */
	public function get_query_errors(): array {
		return $this->query_errors;
	}

	/**
	 * Get the latest update log.
	 *
	 * @return string
	 */
	public function get_update_log(): string {
		$output = '';

		if ( $this->version_name ) {
			$output = get_option( "bojaghi_custom_tables_{$this->version_name}_update_log", '' );
		}

		return $output;
	}

	/**
	 * Backup update log
	 *
	 * @param string[] $update_log Update log to backup.
	 *
	 * @return void
	 */
	protected function set_update_log( array $update_log ): void {
		if ( $this->enable_update_log && $this->version_name ) {
			$text = $this->version . "\n" . implode( "\n", array_filter( $update_log ) );
			update_option( "bojaghi_custom_tables_{$this->version_name}_update_log", $text, autoload: false );
		}
	}

	/**
	 * Remove table version string in option table.
	 *
	 * An error might occur, and table update query must be called once again.
	 * To do so, remove table version string written in option table.
	 *
	 * @return void
	 */
	public function clear_version_table(): void {
		if ( $this->version_name ) {
			delete_option( "bojaghi_custom_tables_$this->version_name", '', true );
		}
	}

	/**
	 * Get the version in setup array
	 *
	 * @return string
	 */
	public function get_version_setup(): string {
		return $this->version;
	}

	/**
	 * Get the version written in option table
	 *
	 * @return string|false
	 */
	public function get_version_table(): string|false {
		return get_option( "bojaghi_custom_tables_$this->version_name" );
	}

	/**
	 * Write planned version to the option table.
	 *
	 * @return void
	 */
	protected function set_version_table(): void {
		if ( $this->version_name ) {
			update_option( "bojaghi_custom_tables_$this->version_name", $this->version, true );
		}
	}

	/**
	 * Make 'CREATE TABLE` query
	 *
	 * @param array $table Table schema setup.
	 *
	 * @return string
	 */
	private function get_table_query( array $table ): string {
		global $wpdb;

		$table = wp_parse_args(
			$table,
			array(
				'table_name'    => '',
				'table_comment' => '',
				'field'         => array(),
				'index'         => array(),
				'engine'        => '',
				'charset'       => '',
				'collate'       => '',
			),
		);

		$add_tabs = fn( $input ) => "\t$input";

		$table_name = $table['table_name'];
		$field      = implode( ",\n", array_map( $add_tabs, $table['field'] ) );
		$index      = implode( ",\n", array_map( $add_tabs, $table['index'] ) );
		$engine     = $table['engine'] ? $table['engine'] : 'InnoDB';
		$charset    = $table['charset'] ? $table['charset'] : $wpdb->charset;
		$collate    = $table['collate'] ? $table['collate'] : $wpdb->collate;
		$comment    = $wpdb->prepare( '%s', ( $table['table_comment'] ? $table['table_comment'] : '' ) );

		$sql = '';

		if ( $table_name && $field ) {
			// @formatter:off
			$sql = "CREATE TABLE $table_name (\n" .
					"$field" . ( $index ? ",\n$index" : '' ) . "\n" .
					")\n" .
					"ENGINE=$engine\n" .
					"DEFAULT CHARSET=$charset COLLATE=$collate\n" .
					"COMMENT=$comment;";
			// @formatter:on
		}

		/**
		 * Filter CREATE TABLE ... query
		 *
		 * @var string $sql        SQL query to filter.
		 * @var string $table_name table name.
		 */
		return apply_filters( 'bojaghi_custom_tables_get_table_query', $sql, $table_name );
	}

	/**
	 * Set $wpdb->suppress_errors
	 *
	 * @return void
	 */
	private function set_suppress_errors(): void {
		global $wpdb;

		if ( $this->suppress_errors !== $wpdb->suppress_errors ) {
			$this->old_suppress_errors = $wpdb->suppress_errors;
			$wpdb->suppress_errors     = $this->suppress_errors;
		}
	}

	/**
	 * Reset $wpdb suppress_errors to default.
	 *
	 * @return void
	 */
	private function reset_suppress_errors(): void {
		global $wpdb;

		if ( ! is_null( $this->old_suppress_errors ) ) {
			$wpdb->suppress_errors     = $this->old_suppress_errors;
			$this->old_suppress_errors = null;
		}
	}

	/**
	 * Check last error and if error found, keep the error message.
	 *
	 * @return void
	 */
	private function check_query_errors(): void {
		global $wpdb;

		if ( $wpdb->last_error ) {
			$this->query_errors[] = $wpdb->last_error;
		}
	}

	/**
	 * Check if an error has occurred.
	 *
	 * @return bool
	 */
	private function has_query_errors(): bool {
		return ! empty( $this->query_errors );
	}
}
