<?php

namespace Bojaghi\CustomTables;

use Bojaghi\Contract\Module;
use Bojaghi\Helper\Helper;

class CustomTables implements Module
{
    private string $versionName;
    private string $version;

    private array|string $tableConf;

    public function __construct(array|string $conf = '', array|string $tableConf = '')
    {
        $this->versionName = '';
        $this->version     = '';
        $this->tableConf   = $tableConf;

        $configDefault = [
            'version_name' => '',    // Optional
            'version'      => '',    // Optional
            'is_theme'     => false, // Optional, defaults to false.
            'main_file'    => '',    // Optional, defaults to blank.
            'activation'   => false, // Optional, defaults to false. Create tables on activation.
            'deactivation' => false, // Optional, defaults to false. Delete tables on deactivation.
            'uninstall'    => false, // Optional, defaults to false. Delete tables on uninstall.
        ];

        $this->setup(wp_parse_args(Helper::loadConfig($conf), $configDefault));
    }

    private function setup(array $configuration): void
    {
        $versionName  = $configuration['version_name'];
        $version      = $configuration['version'];
        $isTheme      = $configuration['is_theme'];
        $mainFile     = $configuration['main_file'];
        $activation   = $configuration['activation'];
        $deactivation = $configuration['deactivation'];
        $uninstall    = $configuration['uninstall'];

        if ($versionName && $version) {
            $this->versionName = $versionName;
            $this->version     = $version;
            add_action('init', [$this, 'checkTableVersion']);
        }

        if ($isTheme) {
            if ($activation) {
                add_action('after_switch_theme', [$this, 'activate'], 10, 2);
            }
            if ($deactivation) {
                add_action('switch_theme', [$this, 'deactivate'], 10, 3);
            }
            if ($uninstall) {
                add_action('delete_theme', [$this, 'uninstall']);
            }
        } else {
            if ($activation) {
                register_activation_hook($mainFile, [$this, 'activate']);
            }
            if ($deactivation) {
                register_deactivation_hook($mainFile, [$this, 'deactivate']);
            }
            if ($uninstall) {
                UninstallHelper::addInstance($this);
                register_uninstall_hook($mainFile, [UninstallHelper::class, 'uninstall']);
            }
        }
    }

    private function loadTableConf(): array
    {
        return Helper::loadConfig($this->tableConf);
    }

    public function activate(): void
    {
        $this->createTables();
    }

    public function createTables(): void
    {
        global $wpdb;

        $tableConf = $this->loadTableConf();
        foreach ($tableConf as $table) {
            $wpdb->query($this->getTableQuery($table));
        }
        update_option($this->versionName, $this->version);
    }

    public function deactivate(): void
    {
        $this->deleteTables();
    }

    public function deleteTables(): void
    {
        global $wpdb;

        $tableConf = $this->loadTableConf();
        foreach ($tableConf as $table) {
            $tableName = $table['table_name'] ?? '';
            if ($tableName) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->query("DROP TABLE IF EXISTS $tableName");
            }
        }
        delete_option($this->versionName);
    }

    public function uninstall(): void
    {
        $this->deleteTables();
    }

    public function checkTableVersion(): void
    {
        if ($this->versionName && $this->version) {
            $version = get_option($this->versionName);
            if ($version && version_compare($this->version, $version, '>')) {
                $this->updateTables();
            };
        }
    }

    public function updateTables(): void
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $tableConf = $this->loadTableConf();
        foreach ($tableConf as $table) {
            dbDelta($this->getTableQuery($table));
        }
        update_option($this->versionName, $this->version);
    }

    private function getTableQuery(array $table): string
    {
        global $wpdb;

        $table = wp_parse_args(
            $table,
            [
                'name'    => '',
                'field'   => [],
                'index'   => [],
                'engine'  => '',
                'charset' => '',
                'collate' => '',
            ]
        );

        $tableName = $table['table_name'];
        $field     = implode(",\n", $table['field']);
        $index     = implode(",\n", $table['index']);
        $engine    = $table['engine'] ?: 'InnoDB';
        $charset   = $table['charset'] ?: $wpdb->charset;
        $collate   = $table['collate'] ?: $wpdb->collate;

        $sql = '';

        if ($tableName && $field) {
            $sql = "CREATE TABLE $tableName (\n" .
                "$field" . ($index ? ",\n$index" : '') .
                "\n) ENGINE=$engine DEFAULT CHARSET=$charset COLLATE=$collate;";
        }

        return $sql;
    }
}
