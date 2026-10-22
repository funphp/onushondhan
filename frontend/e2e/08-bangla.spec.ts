import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { bn } from "../src/i18n/messages/bn";
import { en } from "../src/i18n/messages/en";
import { apiGet, login, USERS } from "./helpers";

/**
 * The interface is Bangla by default with an English option. Only interface text changes —
 * investigators' reports are shown exactly as written (the demo reports are in English).
 */
test.describe("Bangla interface and language switching", () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test("defaults to Bangla, keeps report text as written, and switches to English and back", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("html")).toHaveAttribute("lang", "bn");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(bn.home.heroTitle);
    await expect(page.getByText(bn.brand.disclaimer).first()).toBeVisible();

    await page.getByRole("search").first().getByLabel(bn.nav.searchLabel).fill("tannery");
    await page.keyboard.press("Enter");
    await page.getByRole("link", { name: /Tannery effluent in the Dhaleshwari/ }).click();
    // Report content is untranslated; the surrounding interface is Bangla.
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Tannery effluent");
    await expect(page.getByRole("link", { name: bn.investigation.sendTip })).toBeVisible();
    await expect(page.getByRole("button", { name: bn.share.copy })).toBeVisible();

    const group = page.getByRole("group", { name: bn.language.label });
    await group.getByRole("button", { name: "English" }).click();
    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect(page.getByRole("link", { name: en.investigation.sendTip })).toBeVisible();
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Tannery effluent");

    // The choice survives navigation and reloads.
    await page.goto("/");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(en.home.heroTitle);
    await page.getByRole("group", { name: en.language.label }).getByRole("button", { name: "বাংলা" }).click();
    await expect(page.locator("html")).toHaveAttribute("lang", "bn");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(bn.home.heroTitle);
  });

  test("shows Bangla category names and API messages, and canonicalises slugs", async ({ page }) => {
    await page.goto("/category/environment-climate");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText("পরিবেশ ও জলবায়ু");

    await page.goto("/search?q=tannery");
    await page.getByRole("link", { name: /Tannery effluent/ }).click();
    await expect(page).toHaveURL(/\/investigation\/INV-/);
    const canonical = new URL(page.url()).pathname;
    const id = canonical.split("/")[2];
    // A wrong (here: Bangla) slug redirects to the canonical URL.
    await page.goto(`/investigation/${id}/${encodeURIComponent("ভুল-শিরোনাম")}`);
    await expect(page).toHaveURL(new RegExp(`${canonical}$`));

    await page.goto("/login");
    await page.getByLabel(bn.auth.email).fill("nobody@example.org");
    await page.getByLabel(new RegExp(`^${bn.auth.password}`)).fill("Wrong-pass-123");
    await page.getByRole("button", { name: bn.auth.signIn }).click();
    await expect(page.getByText("ইমেইল বা পাসওয়ার্ড আমাদের রেকর্ডের সঙ্গে মিলছে না।")).toBeVisible();
  });

  test("Bangla home page has no serious accessibility violations", async ({ page }) => {
    await page.goto("/");
    const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
    expect(results.violations.filter((v) => v.impact === "serious" || v.impact === "critical").map((v) => v.id)).toEqual([]);
  });
});

test.describe("signed-in language preference", () => {
  test("is saved to the account (used for emails)", async ({ page }) => {
    // Fresh sign-in: the moderation journey suspends/reinstates reader2, which revokes saved sessions.
    await login(page, USERS.reader2);
    await page.goto("/account");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(en.account.settingsTitle);
    const saved = page.waitForResponse((r) => r.url().includes("/me/profile") && r.request().method() === "PATCH");
    await page.getByRole("group", { name: en.language.label }).first().getByRole("button", { name: "বাংলা" }).click();
    await saved;
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(bn.account.settingsTitle);
    expect((await (await apiGet(page.request, "/api/v1/auth/me")).json()).data.locale).toBe("bn");

    const back = page.waitForResponse((r) => r.url().includes("/me/profile") && r.request().method() === "PATCH");
    await page.getByRole("group", { name: bn.language.label }).first().getByRole("button", { name: "English" }).click();
    await back;
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(en.account.settingsTitle);
  });
});
