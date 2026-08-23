<?php

/*
 -------------------------------------------------------------------------
 More Groups plugin for GLPI
 Copyright (c) 2022-2026 by the TICGAL Team.
 https://www.tic.gal
 -------------------------------------------------------------------------
 LICENSE
 This file is part of the More Groups plugin.
 More Groups plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.
 More Groups plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.
 You should have received a copy of the GNU Affero General Public License
 along with More Groups. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   More Groups
 @author    the TICGAL team
 @copyright Copyright (c) 2022-2026 TICGAL team
 @license   AGPL License 3.0 or (at your option) any later version
				http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://www.tic.gal
 @since     2022
 ----------------------------------------------------------------------
*/

// This test suite must run from inside a working GLPI 11 checkout, with
// this plugin at <glpi>/plugins/moregroups. GLPI 11's own test kernel
// (Glpi\Kernel\Kernel, booted in Environment::TESTING) and test base
// classes (Glpi\Tests\GLPITestCase / Glpi\Tests\DbTestCase) live under
// <glpi>/tests, so the plugin suite boots by requiring GLPI core's own
// tests/bootstrap.php (which needs a `database:install --env=testing`
// test DB already set up) rather than reimplementing that boot sequence.

// GLPI core's own vendor/autoload.php defines the GLPI_ROOT constant itself
// (src/autoload/constants.php); do not predefine it here or the include
// below fatals with "Constant GLPI_ROOT already defined".
$glpi_root = getenv('GLPI_ROOT') ?: dirname(__DIR__, 3);

require_once __DIR__ . '/../vendor/autoload.php';
require_once $glpi_root . '/tests/bootstrap.php';

require_once Plugin::getPhpDir('moregroups') . '/setup.php';

$plugin = new Plugin();
$plugin->checkStates(true);
$plugin->getFromDBbyDir('moregroups');

if (!$plugin->isInstalled('moregroups')) {
	call_user_func([$plugin, 'install'], $plugin->getID());
	$plugin->getFromDBbyDir('moregroups');
}

if (!$plugin->isActivated('moregroups')) {
	call_user_func([$plugin, 'activate'], $plugin->getID());
}
