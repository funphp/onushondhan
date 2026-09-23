import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { storageFor } from "./helpers";

const PUBLIC_PAGES = ["/", "/search?q=river", "/investigators", "/leaderboards", "/pages/about", "/login", "/register", "/forgot-password", "/tips/access"];

async function scan(page: import("@playwright/test").Page) {
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  return results.violations.filter((v) => v.impact === "serious" || v.impact === "critical").map((v) => `${v.id}: ${v.help} (${v.nodes.length})`);
}

test.describe("Accessibility (axe, WCAG 2.x A/AA — serious & critical)", () => {
  for (const path of PUBLIC_PAGES) {
    test(`public page ${path}`, async ({ page }) => {
      await page.goto(path);
      expect(await scan(page)).toEqual([]);
    });
  }

  test("investigation detail page", async ({ page }) => {
    await page.goto("/search?q=tannery");
    await page.getByRole("link", { name: /Tannery effluent/ }).click();
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    expect(await scan(page)).toEqual([]);
  });

  test.describe("signed-in forms", () => {
    test.use({ storageState: storageFor("investigator") });
    for (const path of ["/account", "/dashboard", "/dashboard/investigations", "/dashboard/profile", "/dashboard/tips"]) {
      test(`page ${path}`, async ({ page }) => {
        await page.goto(path);
        await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
        expect(await scan(page)).toEqual([]);
      });
    }
  });
});
