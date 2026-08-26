# More Groups

<img src="https://github.com/ticgal/moregroups/blob/multimedia/moregroups.png" alt="More Groups Logo" height="250px" width="250px" class="js-lazy-loaded">

[![License](https://img.shields.io/badge/License-GNU%20AGPLv3-blue.svg?style=flat-square)](https://github.com/TICGAL-Dev/moregroups/blob/develop/LICENSE)
[![X](https://img.shields.io/twitter/follow/ticgalcom?style=flat-square&logo=x&label=Follow)](https://twitter.com/ticgalcom)
[![Web](https://img.shields.io/badge/Web-TICGAL-blue.svg?style=flat-square)](https://tic.gal/)
[![Localazy](https://img.shields.io/badge/Translate-Localazy-cyan)](https://localazy.com/p/more-groups)
[![Manual](https://img.shields.io/badge/Doc-Manuals-blue.svg?style=flat-square)](https://docs.tic.gal/books/more-groups)
[![Marketplace](https://img.shields.io/badge/GLPI-Marketplace-orange.svg?style=flat-square)](https://plugins.glpi-project.org/#/plugins/moregroups)

Fast group-membership management for GLPI: deactivate a group member without
losing their membership details, and reactivate them later in one click.

### Setup
Install it from the [GLPI Marketplace](https://plugins.glpi-project.org/#/plugins/moregroups)
(Setup > Plugins), or download and install it in the plugin folder.

### How to use
On a **Group**'s Users tab, use the deactivate button (or the matching
massive action) to move a member out of the group without deleting their
membership. They appear in the **Deactivated users** panel, where a single
click on the activate button restores them to the group with their original
dynamic, manager and delegatee flags.

Re-adding a previously deactivated user through GLPI's own "Add user to
group" form clears their stale deactivated record automatically.

### Additional features
- Deactivate/activate as a massive action across several members at once
- Deactivated users tracked per group in a dedicated panel
