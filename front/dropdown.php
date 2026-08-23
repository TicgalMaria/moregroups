<?php

/**
 */

use GlpiPlugin\Zzglpixx\Dropdown;

include("../../../inc/includes.php");

if (!Plugin::isPluginActive('0GLPIxx')) {
    Html::displayNotFoundError();
}

Session::checkCentralAccess();

$dropdown = new Dropdown();
include(GLPI_ROOT . "/front/dropdown.common.php");
