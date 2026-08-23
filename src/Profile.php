<?php

/**
 */

namespace GlpiPlugin\Zzglpixx;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Migration;
use Profile as CoreProfile;
use ProfileRight;
use Session;

class Profile extends CoreProfile
{
    public static $rightname = "profile";

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string|array
    {
        switch ($item::getType()) {
            case CoreProfile::getType():
                return self::createTabEntry(self::getTypeName(1));
        }

        return '';
    }

    /**
     * getStandardCRUD
     *
     * @return array
     */
    private function getStandardCRUD(): array
    {
        return [
            READ    => __('Read'),
            UPDATE  => __('Update'),
            CREATE  => __('Create'),
            DELETE  => __('Delete'),
            PURGE   => __('Purge'),
        ];
    }

    /**
     * getAllRights
     *
     * @return array
     */
    public function getAllRights(): array
    {
        return [
            [
                'rights'    => self::getStandardCRUD(),
                'label'     => __('0GLPIXO', '0GLPIxx'),
                'itemtype'  => Config::class,
                'field'     => 'plugin_0GLPIxx_config',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof CoreProfile) {
            return self::displayProfileForm($item);
        }

        return false;
    }

    /**
     * displayProfileForm
     *
     * @param  CoreProfile $profile
     * @return bool
     */
    public static function displayProfileForm(CoreProfile $profile): bool
    {
        TemplateRenderer::getInstance()->display('@0GLPIxx/pages/profile.html.twig', [
            'item'            => $profile,
            'rights'          => (new self())->getAllRights(),
            'title'           => '0GLPIXO',
            'rights_editable' => Session::haveRight(self::$rightname, UPDATE),
        ]);

        return true;
    }

    /**
     * install
     *
     * Declares the plugin rights for every profile and grants them to the
     * super-admin (profile 4), so the plugin is usable right after install.
     * Without this the rights matrix has nothing to write to and every
     * Session::haveRight() on them returns false.
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $migration->displayMessage("Adding profile rights");

        foreach ((new self())->getAllRights() as $data) {
            $existing = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => ProfileRight::getTable(),
                'WHERE'  => ['name' => $data['field']],
            ]);

            if (count($existing) === 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }

            $DB->update(
                ProfileRight::getTable(),
                ['rights' => READ | CREATE | UPDATE | DELETE | PURGE],
                ['profiles_id' => 4, 'name' => $data['field']],
            );
        }
    }

    /**
     * uninstall
     *
     * @param  Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration)
    {
        $migration->displayMessage("Removing profile rights");
        $profile = new self();
        foreach ($profile->getAllRights() as $data) {
            ProfileRight::deleteProfileRights([$data['field']]);
        }
    }
}
