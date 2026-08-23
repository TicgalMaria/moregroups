/**
 * fixtures.ts — the golden fixture's fixed, known-in-advance demo data.
 *
 * Frozen state (see tools/manual-generator/fixtures/CHECKLIST.md for how it was built and
 * tools/manual-generator/fixtures/dump.sh for how it's captured). Never derive these values
 * from a live query in a spec — hardcode them here so a spec reads like documentation of the
 * fixture, not a live discovery of it.
 */

export const GROUP = {
  id: 1,
  name: 'Support Team',
};

/**
 * Group members present when the suite starts, before any spec acts on them.
 * `display` is GLPI's own "Lastname Firstname" rendering — confirmed against the
 * running fixture, not derived from `name`.
 */
export const MEMBERS = {
  manager: { id: 9, groupUserId: 1, name: 'alice.martin', display: 'Martin Alice' },
  toDeactivate: { id: 10, groupUserId: 2, name: 'bruno.silva', display: 'Silva Bruno' },
  bystander: { id: 11, groupUserId: 3, name: 'carla.dubois', display: 'Dubois Carla' },
};

/** Already deactivated in the fixture, so the panel has content on first view. */
export const ALREADY_DEACTIVATED = {
  id: 12,
  name: 'diego.fernandez',
  display: 'Fernández Diego',
};
