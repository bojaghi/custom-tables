<?php
/**
 * Bojaghi Custom Tables
 *
 * @package Bojaghi\CustomTables
 */

declare( strict_types=1 );

namespace Bojaghi\CustomTables;

/**
 * Table schema class
 */
abstract class Table_Schema {
	/**
	 * 테이블 이름을 반환한다.
	 *
	 * @return string
	 */
	abstract public static function get_table_name(): string;

	/**
	 * 테이블 설명을 가져온다
	 *
	 * @return string
	 */
	public static function get_table_comment(): string {
		return '';
	}

	/**
	 * 테이블 필드 선언을 리턴한다.
	 *
	 * @return array
	 */
	abstract public static function get_table_fields(): array;

	/**
	 * 테이블 인덱스 선언을 리턴한다.
	 *
	 * @return array
	 */
	abstract public static function get_table_indices(): array;

	/**
	 * 테이블의 엔진을 가져온다
	 *
	 * 기본은 MySQL의 InnoDB 엔진을 쓴다.
	 *
	 * @return string
	 */
	public static function get_table_engine(): string {
		return 'InnoDB';
	}

	/**
	 * 테이블의 캐릭터셋 정의를 가져온다
	 *
	 * 기본은 빈 문자열, 워드프레스 기본값을 쓴다.
	 *
	 * @return string
	 */
	public static function get_charset(): string {
		return '';
	}

	/**
	 * 테이블의 콜레이션 정의를 가져온다
	 *
	 * 기본은 빈 문자열, 워드프레스 기본값을 쓴다.
	 *
	 * @return string
	 */
	public function get_collate(): string {
		return '';
	}
}
