<?php

/**
 */

namespace GlpiPlugin\Zzglpixx;

use CommonDBTM;
use CommonGLPI;
use Config as CoreConfig;
use Glpi\Application\View\TemplateRenderer;
use GLPIKey;
use GlpiPlugin\Zzglpixx\Traits\InstallableTable;
use Log;
use Migration;
use Session;

class Config extends CommonDBTM
{
    use InstallableTable;

    public static $rightname = 'config';

    private static ?self $instance = null;

    /**
     * {@inheritDoc}
     */
    public function __construct()
    {
        /** @var \DBmysql $DB */
        global $DB;

        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return '0GLPIXO';
    }

    /**
     * getInstance
     *
     * @param  int $n
     * @return Config
     */
    public static function getInstance(int $n = 1): Config
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!isset(self::$instance)) {
            self::$instance = new self();

            // The table only exists once install() below is uncommented, and
            // both getFromDB() and getEmpty() query it, so neither can run
            // before that: the instance stays field-less on purpose.
            if ($DB->tableExists(self::getTable()) && !self::$instance->getFromDB($n)) {
                self::$instance->getEmpty();
            }
        }

        return self::$instance;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareInputForUpdate($input): false|array
    {
        // Handle password fields

        // Log update fields in history manually
        foreach ($this->fields as $key => $value) {
            if (isset($input[$key]) && $input[$key] != $value) {
                Log::history(1, CoreConfig::class, [1, $key . ' ' . $value, $input[$key]]);
            }
        }

        return $input;
    }

    /**
     * @param  array $input
     * @param  string $key
     *
     * @return array
     */
    private static function blankPassword(array $input, string $key): array
    {
        if (isset($input[$key])) {
            if (!empty($input[$key])) {
                $input[$key] = (new GLPIKey())->encrypt($input[$key]);
            } else {
                unset($input[$key]);
            }
        }
        if (isset($input["_blank_{$key}"]) && $input["_blank_{$key}"]) {
            $input[$key] = '';
        }

        return $input;
    }

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string|array
    {
        switch ($item::getType()) {
            case CoreConfig::getType():
                return self::createTabEntry(self::getTypeName(1));
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        switch ($item::getType()) {
            case CoreConfig::getType():
                return self::showFormConfig();
        }

        return false;
    }

    /**
     * Display the configuration form
     */
    public static function showFormConfig(): bool
    {
        $config = self::getInstance();
        $template = "@0GLPIxx/pages/config.html.twig";
        TemplateRenderer::getInstance()->display($template, [
            'item' => $config,
        ]);

        return true;
    }

    /**
     * install
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        // The table has to exist even before the plugin declares any setting of
        // its own: `components/form/header.html.twig` reads the item's columns,
        // so the configuration tab cannot render without it. Add the settings as
        // columns here, then drop the warning below.
        $created = self::createTableIfMissing($migration, self::getTable(), '
            `id` INT ' . self::keySign() . ' NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
        ');

        if ($created) {
            (new self())->add(['id' => 1]);
        }

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
