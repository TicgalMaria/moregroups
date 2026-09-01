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

use Glpi\Plugin\Hooks;

define('PLUGIN_MOREGROUPS_VERSION', '2.0.3');
define('PLUGIN_MOREGROUPS_MIN_GLPI', '11.0.0');
define('PLUGIN_MOREGROUPS_MAX_GLPI', '11.0.99');

function plugin_version_moregroups()
{
	return [
		'name' => 'More Groups',
		'version' => PLUGIN_MOREGROUPS_VERSION,
		'author' => '<a href="https://tic.gal">TICGAL</a>',
		'homepage' => 'https://tic.gal',
		'license' => 'AGPLv3+',
		'requirements' => [
			'glpi' => [
				'min' => PLUGIN_MOREGROUPS_MIN_GLPI,
				'max' => PLUGIN_MOREGROUPS_MAX_GLPI,
			]
		]
	];
}

function plugin_init_moregroups()
{
	global $PLUGIN_HOOKS;

	$PLUGIN_HOOKS['csrf_compliant']['moregroups'] = true;

	$plugin = new Plugin();
	if ($plugin->isActivated('moregroups')) {
		$PLUGIN_HOOKS['use_massive_action']['moregroups'] = 1;

		$PLUGIN_HOOKS[Hooks::POST_SHOW_TAB]['moregroups'] = 'plugin_moregroups_post_show_tab';

		$PLUGIN_HOOKS['item_add']['moregroups'] = [
			'Group_User' => 'plugin_moregroups_group_user_add',
		];
	}
}
