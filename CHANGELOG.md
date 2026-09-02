# Changelog

## [Unreleased]

## 2.0.3 - 01/09/2026
### Bugs
- Fix safety warnings

## 2.0.2 - 26/08/2026
### Bugs
- Fix a security issue that let a user with only read access to a group activate or deactivate other users' group memberships
- Fix a redirect after activating/deactivating a group membership that could send the browser to an external site
- Fix a security issue where the activate/deactivate group membership action could be triggered from another website without the user's intent

## 2.0.1 - 18/08/2026
### Features
- Published an online user manual
### Bugs
- Fix the license shown in the plugin list, which still displayed GPLv3 instead of AGPLv3

## 2.0.0 - 18/08/2026
### Features
- Update to version 11
- Modernize the deactivated users panel to match GLPI's native look
- Highlight deactivated-user rows in red to make them visually distinct from active members
### Bugs
- Remove debug output and add missing rights check in the group activate/deactivate form
- Fix a user re-added to a group still appearing in the deactivated users list
- Fix the activate/deactivate buttons failing with an error
- Prevent activating/deactivating group memberships outside of the user's entity
- Fix plugin data not being removed when the plugin is uninstalled
- Fix the deactivated users panel header being misaligned with the table below it


## 1.0.1 - 28/10/2024
### Features
- Delete fields active

## 1.0.0 - 17/07/2024
### Features
- Deactivate users
- Activate users
