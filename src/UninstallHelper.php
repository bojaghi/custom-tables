<?php

namespace Bojaghi\CustomTables;

class UninstallHelper
{
    /**
     * @var CustomTables[]
     */
    private static array $instances = [];

    public static function addInstance(CustomTables $instance): void
    {
        self::$instances[] = $instance;
    }

    public static function uninstall(): void
    {
        foreach (self::$instances as $instance) {
            $instance->uninstall();
        }
    }
}
