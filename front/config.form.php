<?php

/**
 */

use GlpiPlugin\Zzglpixx\Config;

include('../../../inc/includes.php');

if (!Plugin::isPluginActive('0GLPIxx')) {
    Html::displayNotFoundError();
}

Session::checkRight(Config::$rightname, UPDATE);

$config = new Config();

if (isset($_POST["update"])) {
    $config->update($_POST);
    Html::back();
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

$redirect = $CFG_GLPI["root_doc"] . "/front/config.form.php";
$redirect .= "?forcetab=" . urlencode(Config::class . '$1');
Html::redirect($redirect);
