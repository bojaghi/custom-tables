<?php

namespace Bojaghi\CustomTables\Tests;

use Bojaghi\CustomTables\CustomTables;
use Bojaghi\CustomTables\UninstallHelper;
use WP_UnitTestCase;

class TestCustomTables extends WP_UnitTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function test_createUpdateDelete(): void
    {
        global $wpdb;

        $conf = [
            'version_name' => 'test_name',
            'version'      => '1.0.0',
            'is_theme'     => false,
            'main_file'    => WP_PLUGIN_DIR . '/custom-tables/custom-tables.php',
            'activation'   => true,
            'deactivation' => true,
            'uninstall'    => true,
        ];

        $tableConf = [
            [
                'table_name' => 'test_table',
                'field'      => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'name varchar(100) NOT NULL',
                    'count bigint(20) unsigned NOT NULL',
                ],
                'index'      => [
                    'PRIMARY KEY  (id)',
                    'UNIQUE KEY uni_name (name)',
                    // NOTE: you cannot carete fulltext index in unittest.
                    'KEY idx_count (count)',
                ],
                'engine'     => 'InnoDB',
            ]
        ];

        $ct = new CustomTables($conf, $tableConf);

        // Test getTableQuery
        $getTableQuery = getAccessibleMethod($ct::class, 'getTableQuery');
        $actual        = $getTableQuery->invoke($ct, $tableConf[0]);
        $expected      = "CREATE TABLE test_table (\n" .
            "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n" .
            "name varchar(100) NOT NULL,\n" .
            "count bigint(20) unsigned NOT NULL,\n" .
            "PRIMARY KEY  (id),\n" .
            "UNIQUE KEY uni_name (name),\n" .
            "KEY idx_count (count)\n" .
            ") ENGINE=InnoDB DEFAULT CHARSET=$wpdb->charset COLLATE=$wpdb->collate;";

        $this->assertEquals($expected, $actual);

        // Create.
        $ct->createTables();
        $this->assertEmpty($wpdb->last_error);
        // Insert and get
        $inserted = $wpdb->insert($tableConf[0]['table_name'], ['name' => 'created', 'count' => 100]);
        $this->assertEquals(1, $inserted);
        $row = $wpdb->get_row("SELECT * FROM {$tableConf[0]['table_name']} WHERE name='created'");
        $this->assertEquals('100', $row->count);
        // Version
        $this->assertEquals($conf['version'], get_option($conf['version_name']));

        // assert activation hook can be found.
        $pluginBaseName = plugin_basename($conf['main_file']);
        $result         = has_action('activate_' . $pluginBaseName, [$ct, 'activate']);;
        $this->assertEquals(10, $result);

        // assert deactivation hook can be found.
        $result = has_action('deactivate_' . $pluginBaseName, [$ct, 'deactivate']);;;
        $this->assertEquals(10, $result);

        $plugins = get_option('uninstall_plugins');
        $this->assertArrayHasKey($pluginBaseName, $plugins);
        $this->assertEquals([UninstallHelper::class, 'uninstall'], $plugins[$pluginBaseName]);

        // Update
        $updatedConf      = [
            'version_name' => 'test_name',
            'version'      => '1.1.0',
            'is_theme'     => false,
            'main_file'    => WP_PLUGIN_DIR . '/custom-tables/custom-tables.php',
        ];
        $updatedTableConf = [
            [
                'table_name' => 'test_table',
                'field'      => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'name varchar(100) NOT NULL',
                    'added bigint(20) NOT NULL', // Here.
                    'count bigint(20) unsigned NOT NULL',
                ],
                'index'      => [
                    'PRIMARY KEY  (id)',
                    'UNIQUE KEY uni_name (name)',
                    'KEY idx_count (count)',
                ],
                'engine'     => 'InnoDB',
            ]
        ];

        $updated = new CustomTables($updatedConf, $updatedTableConf);
        $updated->updateTables();

        // Insert and get.
        $inserted = $wpdb->insert($tableConf[0]['table_name'], ['name' => 'updated', 'added' => 35, 'count' => 100]);
        $this->assertEquals(1, $inserted);
        $row = $wpdb->get_row("SELECT * FROM {$tableConf[0]['table_name']} WHERE name='updated'");
        $this->assertEquals('35', $row->added);
        $this->assertEquals('100', $row->count);
        // version check
        $this->assertEquals($updatedConf['version'], get_option($updatedConf['version_name']));

        // Delete
        $ct->deleteTables();
        $this->assertEmpty($wpdb->last_error);
        // Tables are gone.
        $wpdb->suppress_errors();
        $wpdb->query("SELECT 1 AS val FROM {$tableConf[0]['table_name']}");
        $this->assertNotEmpty($wpdb->last_error);
        $this->assertFalse(get_option($conf['version_name']));;
    }
}