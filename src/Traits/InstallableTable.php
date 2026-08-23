<?php

/**
 */

namespace GlpiPlugin\Zzglpixx\Traits;

use DBConnection;
use Migration;

/**
 * Creates and drops the tables owned by a plugin class, using the charset,
 * collation and primary key sign of the current installation.
 *
 * The using class only declares its columns; the default `uninstall()` drops its
 * table and can be overridden (a class owning several tables, or a table that
 * must survive the uninstall).
 */
trait InstallableTable
{
    /**
     * Primary key sign of the installation, to interpolate into column
     * definitions (`INT {$sign} NOT NULL ...`).
     */
    protected static function keySign(): string
    {
        return DBConnection::getDefaultPrimaryKeySignOption();
    }

    /**
     * Creates the table if it does not exist yet.
     *
     * @param string $columns column and index definitions, without the enclosing parentheses
     *
     * @return bool true if the table was created by this call, which lets the
     *              caller seed default rows only the first time
     */
    protected static function createTableIfMissing(Migration $migration, string $table, string $columns): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        if ($DB->tableExists($table)) {
            return false;
        }

        $migration->displayMessage("Installing $table");

        $DB->doQuery("CREATE TABLE `$table` ($columns)
            ENGINE=InnoDB DEFAULT CHARSET=" . DBConnection::getDefaultCharset() . '
            COLLATE=' . DBConnection::getDefaultCollation() . ' ROW_FORMAT=DYNAMIC;');

        return true;
    }

    protected static function dropTable(Migration $migration, string $table): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $migration->displayMessage("Uninstalling $table");
        $DB->doQuery("DROP TABLE IF EXISTS `$table`");
    }
}
