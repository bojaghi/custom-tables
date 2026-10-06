<?php
/**
 * Bojaghi Custom Tables
 *
 * @package Bojaghi\CustomTables
 */

declare( strict_types=1 );

namespace Bojaghi\CustomTables;

interface Table_Schema {
	/**
	 * 테이블 이름을 반환한다.
	 *
	 * @return string
	 */
	public static function get_table_name(): string;

	/**
	 * 테이블 필드 선언을 리턴한다.
	 *
	 * @return array
	 */
	public static function get_table_fields(): array;

	/**
	 * 테이블 인덱스 선언을 리턴한다.
	 *
	 * @return array
	 */
	public static function get_table_indices(): array;
}
