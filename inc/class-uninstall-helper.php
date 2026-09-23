<?php
/**
 * Bojaghi Custom Tables
 *
 * @package Bojaghi\CustomTables
 */

declare( strict_types=1 );

namespace Bojaghi\CustomTables;

/**
 * Uninstall helper class
 *
 * Uninstallation callback does not allow class methods.
 */
class Uninstall_Helper {
	/**
	 * Custom_Table instance for uninstallation
	 *
	 * @var array<Custom_Tables|callable>
	 */
	private static array $instances = array();

	/**
	 * Add Custom_Table instance
	 *
	 * @param Custom_Tables|callable $instance Custom_Table instance.
	 *
	 * @return void
	 */
	public static function add_instance( Custom_Tables|callable $instance ): void {
		self::$instances[] = $instance;
	}

	/**
	 * Check if $instance is added
	 *
	 * @param Custom_Tables|callable $instance Instance or callback to check.
	 *
	 * @return bool
	 */
	public static function has_instance( Custom_Tables|callable $instance ): bool {
		return in_array( $instance, self::$instances, true );
	}

	/**
	 * Uninstall hook
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		foreach ( self::$instances as $instance ) {
			if ( $instance instanceof Custom_Tables ) {
				$instance->uninstall();
			} else {
				$instance();
			}
		}
	}
}
