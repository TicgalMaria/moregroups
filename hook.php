<?php

/**
*/

use GlpiPlugin\Zzglpixx\Config;
use GlpiPlugin\Zzglpixx\Dropdown;
use GlpiPlugin\Zzglpixx\Profile;

/**
 * Classes owning a table, in installation order (catalogs first, relations
 * last). The uninstall walks the list backwards.
 *
 * @return list<class-string>
 */
function plugin_0GLPIxx_installable_classes(): array
{
    return [
        Dropdown::class,
        Config::class,
        Profile::class,
    ];
}

/**
 * Call all install methods of the plugin
 *
 * @return bool
 */
function plugin_0GLPIxx_install(): bool
{
    $migration = new Migration(PLUGIN_0GLPIXX_VERSION);

    foreach (plugin_0GLPIxx_installable_classes() as $classname) {
        if (method_exists($classname, 'install')) {
            $classname::install($migration);
        }
    }

    $migration->executeMigration();

    return true;
}

/**
 * Call all uninstall methods of the plugin
 *
 * @return bool
 */
function plugin_0GLPIxx_uninstall(): bool
{
    $migration = new Migration(PLUGIN_0GLPIXX_VERSION);

    foreach (array_reverse(plugin_0GLPIxx_installable_classes()) as $classname) {
        if (method_exists($classname, 'uninstall')) {
            $classname::uninstall($migration);
        }
    }

    $migration->executeMigration();

    return true;
}
