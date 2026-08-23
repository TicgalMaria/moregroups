/**
 * glpi.ts — GLPI-specific helpers for the More Groups manual pipeline.
 *
 * Verified against docker.io/glpi/glpi:11.0.8 (the 65123 stack in ~/containers/testing) on
 * 2026-08-19: login field names (login_name/login_password), the /front/group.form.php?id=
 * route (Group is a core itemtype, not ported to a Symfony route), and the plugin's own
 * /plugins/moregroups/GroupAction route (src/Controller/GroupActionController.php).
 *
 * Place in tools/manual-generator/lib/.
 */
import type { Page } from '@playwright/test';

export const DOC_USERS: Record<string, { user: string; pass: string }> = {
  en_GB: { user: 'manual_en', pass: 'ManualDocs2026' },
  es_ES: { user: 'manual_es', pass: 'ManualDocs2026' },
};

/** Kills every source of pixel jitter that isn't the UI itself. */
export async function stabilise(page: Page): Promise<void> {
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation: none !important;
        transition: none !important;
        caret-color: transparent !important;
      }
      html { scrollbar-width: none; }
      ::-webkit-scrollbar { display: none; }
    `,
  });
}

/** Hide or neutralise regions that change between runs. */
export async function hideVolatile(page: Page): Promise<void> {
  await page.addStyleTag({
    content: `
      .glpi-version, footer .copyright { visibility: hidden !important; }
      #debug-toolbar, .debug-toolbar { display: none !important; }
    `,
  });
}

export async function login(page: Page, locale: string): Promise<void> {
  const creds = DOC_USERS[locale];
  if (!creds) throw new Error(`no documentation user configured for locale ${locale}`);

  // Trap 9/10: right after fixtures/restore.sh's cache:clear, GLPI's first real hit compiles
  // Twig lazily and can take much longer than the default action timeout, even though the
  // restore script's own curl warm-up already logged in once — the browser's own first
  // request still pays a slow render here. Wait generously before the fill/click below.
  await page.goto('/', { timeout: 60_000 });
  const loginName = page.locator('input[name="login_name"]');
  await loginName.waitFor({ state: 'visible', timeout: 60_000 });
  await loginName.fill(creds.user);
  await page.locator('input[name="login_password"]').fill(creds.pass);
  await page.locator('button[name="submit"], input[name="submit"]').first().click();

  await page.waitForLoadState('networkidle');
  await stabilise(page);
  await hideVolatile(page);
}

/**
 * Plugin route helper. Group is a core GLPI itemtype (front/group.form.php), not a
 * plugin-owned page — the plugin only injects a tab and the GroupAction POST endpoint.
 */
export const routes = {
  group: (id: number | string) => `/front/group.form.php?id=${id}`,
};
