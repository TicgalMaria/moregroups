<?php

/**
 */

namespace GlpiPlugin\Zzglpixx;

use CommonDropdown;
use GlpiPlugin\Zzglpixx\Traits\InstallableTable;
use Migration;
use Session;

class Dropdown extends CommonDropdown
{
    use InstallableTable;

    public static $rightname = "dropdown";

    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return _n('0GLPIXO', '0GLPIXOs', $nb, '0GLPIxx');
    }

    /**
     * {@inheritDoc}
     */
    public function getAdditionalFields(): array
    {
        return [
            [
                'name'  => 'fieldname',
                'label' => __('Name'),
                'type'  => 'text',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function rawSearchOptions(): array
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'        => 'n', // n over 2, name, comment as default fields
            'table'     => self::getTable(),
            'field'     => 'fieldname',
            'name'      => __('Name'),
            'datatype'  => 'text',
        ];

        return $tab;
    }

    /**
     * install
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        // `name` and `comment` are the columns CommonDropdown expects; `fieldname`
        // is the example extra column declared in getAdditionalFields() and
        // rawSearchOptions() above. Replace it with the real ones.
        self::createTableIfMissing($migration, self::getTable(), '
            `id` INT ' . self::keySign() . ' NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) DEFAULT NULL,
            `comment` TEXT,
            `fieldname` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`)
        ');

        // * Remove this line when the plugin is ready
        Session::addMessageAfterRedirect(
            sprintf(
                __('Plugin 0GLPIXO: %s not configured', '0GLPIxx'),
                self::getTable(),
            ),
            false,
            WARNING,
        );
    }

    /**
     * uninstall
     *
     * @param  Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        self::dropTable($migration, self::getTable());
    }
}
