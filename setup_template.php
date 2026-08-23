<?php

/**
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Zzglpixx\Config as PluginConfig;
use GlpiPlugin\Zzglpixx\Profile as PluginProfile;

define('PLUGIN_0GLPIXX_VERSION', '0.1.0');
define('PLUGIN_0GLPIXX_MIN_GLPI', '11.0.0');
define('PLUGIN_0GLPIXX_MAX_GLPI', '11.0.99');

/**
 * Plugin_Version_0GLPIxx
 *
 * @return array
 */
function plugin_version_0GLPIxx(): array
{
    return [
        'name'          => '0GLPIXO',
        'version'       => PLUGIN_0GLPIXX_VERSION,
        'author'        => '<a href="https://tic.gal">TICGAL</a>',
        'homepage'      => 'https://tic.gal',
        'license'       => 'AGPLv3+',
        'requirements'  => [
            'glpi' => [
                'min' => PLUGIN_0GLPIXX_MIN_GLPI,
                'max' => PLUGIN_0GLPIXX_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Plugin_Init_0GLPIxx
 *
 * @return void
 */
function plugin_init_0GLPIxx(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    if (Plugin::isPluginActive('0GLPIxx')) {
        Plugin::registerClass(PluginConfig::class, ['addtabon' => Config::class]);

        Plugin::registerClass(PluginProfile::class, ['addtabon' => Profile::class]);
    }

    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['0GLPIxx'] = 'front/config.form.php';
}
