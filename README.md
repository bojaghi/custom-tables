# Custom Tables

커스텀 테이블을 위한 유틸리티.

워드프레스 기능을 더 확장하기 위해 플러그인이나 테마는 고유의 테이블을 별도로 생성할 수 있습니다.
이 때 활용되는 상용구적인 패턴을 재활용할 수 있게 하기 위해 이 유틸리티를 제공합니다.

## 설정하기

예시:

```php
use Bojaghi\CustomTables\CustomTables;

new CustomTableFactory(
    [ /*...*/ ], // Enter configuration array.    설정을 담은 배열을 넣거나.
    [ /*...*/ ], // Enter table definition array. 테이블 설정을 담은 배열을 넣거나.
)

/* OR */

new CustomTables(
    '/path/to/settings',  // Enter path to configuration file.    설정을 담은 파일 경로를 문자열로.
    '/path/to/table-def', // Enter path to table definition file. 테이블 설정을 담은 파일 경로를 문자열로.
)
```

이 객체는 가능한 액션과 필터의 콜백에서 생성하지 말고, 플러그인/테마 코드 실행시 생성되게 해 주세요.

### 설정 파일의 예제

```php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'version_name' => 'my_version_name', // Optional
    'version'      => '1.0.0',           // Optional
    'is_theme'     => false,             // Optional, defaults to false.
    'main_file'    => __FILE__,          // Optional, defaults to blank.
    'activation'   => false,             // Optional, defaults to false. Create tables on activation.
    'deactivation' => false,             // Optional, defaults to false. Delete tables on deactivation.
    'uninstall'    => false,             // Optional, defaults to false. Delete tables on uninstall.
];
```

### 테이블 설정 파일의 예제

```php
if (!defined('ABSPATH')) {
    exit;
}


/**
 * How to create table
 *
 * This method uses dbDelta() function.
 *
 * Please keep in mind that dbDelta() is rather demanding:
 * - You must put each field on its own line in your SQL statement.
 * - You must have two spaces between the words PRIMARY KEY and the definition of your primary key.
 * - You must use the key word KEY rather than its synonym INDEX and you must include at least one KEY.
 * - KEY must be followed by a SINGLE SPACE then the key name then a space then open parenthesis with the field name
 *   then a closed parenthesis.
 * - You must not use any apostrophes or backticks around field names.
 * - Field types must be all lowercase.
 * - SQL keywords, like CREATE TABLE and UPDATE, must be uppercase.
 * - You must specify the length of all fields that accept a length parameter. int(11), for example.
 * - Use 'UNIQUE KEY', not just 'UNIQUE'. Likewise, use 'FULLTEXT KEY', and 'SPATIAL KEY'.
 *
 * @return array
 * @link   https://codex.wordpress.org/Creating_Tables_with_Plugins
 */

return [
    [
        'name'    => 'table_name',
        'field'   => [
            'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
            'name varchar(100) NOT NULL',
            'count bigint(20) unsigned NOT NULL',
        ],
        'index'   => [
            'PRIMARY KEY  (id)',            // Two spaces after 'PRIMARY KEY'. 'PRIMARY KEY' 다음 두 개의 공백.
            'UNIQUE KEY uni_name (name)',   // Just as-is, from here.          여기부터는 그대로.
            'FULLTEXT KEY idx_name (name)',
            'KEY idx_count (count)',   
        ],  
        'engine'  => 'InnoDB', // Optional, defaults to 'InnoDB'.
        'charset' => '',       // Optional, leave blank to use the default value of $wpdb.
        'collate' => '',       // Optional, leave blank to use the default value of $wpdb.
    ],
    /* ... */
];
```

### `version_name`, `version`의 의미

`version_name`, `version`이 설정되어 있으면 테이블 생성 후 옵션 테이블에 기록된 버전 `version`을 기록합니다.
이 때 옵션 이름으로 `version_name`을 사용합니다. 그러므로 이름은 고유한 값을 가질 수 있도록 해 주세요.
`version`값으로 PHP `version_compare()` 함수가 인식할 수 있는 버전 문자열을 사용하세요. 

이렇게 값이 설정되면 데이터베이스 업데이트 시, 굳이 플러그인을 활성화/비활성화 하지 않아도 자동으로 데이터베이스를 업그레이드 할 수 있습니다.
이 기능을 사용하지 않으려면 값을 비워 두거나 키-값 쌍을 삭제하세요.

### 플러그인, 테마 기반 구분

플러그인의 경우 `main_file` 값을 넣는 것이 좋습니다. 그래야 활성화/비활성화/삭제시 테이블 생성, 제거 작업이 가능합니다.
만약 테마 기반에서 실행한다면 `is_theme` 값을 참으로 설정해 둡니다. 그러면 `main_file`은 무시됩니다.

### 활성화/비활성화/삭제

아래는 모두 기본값이 `false`입니다.

- `activation`: 플러그인/테마 활성화 시 테이블을 자동으로 만듭니다. 
- `deactivation`: 플러그인/테마 비활성화 시 테이블을 자동으로 삭제합니다.
- `uninstall`: 플러그인/테마 삭제 시 테이블을 자동으로 삭제합니다.
