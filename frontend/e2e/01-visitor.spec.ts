import { expect, test } from "@playwright/test";

test.describe("Journey 1 — visitor discovers and reads a published investigation", () => {
  test("home → search → investigation → share/tip controls, with disclaimer and SEO metadata", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Independent investigations");
    await expect(page.getByText("does not mean onusondhan.com has verified").first()).toBeVisible();

    await page.getByRole("search").first().getByLabel("Search investigations").fill("tannery");
    await page.keyboard.press("Enter");
    await expect(page).toHaveURL(/\/search\?q=tannery/);
    const result = page.getByRole("link", { name: /Tannery effluent in the Dhaleshwari/ });
    await expect(result).toBeVisible();
    await result.click();

    await expect(page.getByRole("heading", { level: 1 })).toContainText("Tannery effluent");
    await expect(page.getByLabel("Responsibility notice")).toContainText("The investigator is responsible for the report");
    await expect(page.getByText("River Delta Watch").first()).toBeVisible();
    // Confidential source must never be published.
    await expect(page.getByText("Interview notes (confidential)")).toHaveCount(0);
    await expect(page.getByRole("link", { name: "Send a tip" })).toBeVisible();
    await expect(page.getByRole("button", { name: "Copy link" })).toBeVisible();

    const canonical = await page.locator('link[rel="canonical"]').getAttribute("href");
    expect(canonical).toMatch(/\/investigation\/INV-\d{4}-[0-9A-Z]{8}\/tannery-effluent/);
    await expect(page.locator('meta[property="og:type"]')).toHaveAttribute("content", "article");
    const jsonLd = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(jsonLd.join()).toContain('"@type":"Article"');
  });

  test("browses categories, investigator profiles, leaderboards and policy pages", async ({ page }) => {
    await page.goto("/category/environment-climate");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText("Environment & Climate");
    await page.goto("/investigators/river-delta-watch");
    await expect(page.getByText("Publishes under a protected public identity.")).toBeVisible();
    await page.goto("/leaderboards");
    await expect(page.getByText(/not a measure of accuracy/)).toBeVisible();
    await page.goto("/terms");
    await expect(page).toHaveURL(/\/pages\/terms-of-use/);
    await expect(page.getByText("Template pending legal review")).toBeVisible();
    const sitemap = await page.request.get("/sitemap.xml");
    expect(await sitemap.text()).toContain("/investigation/INV-");
  });

  test("drafts and unknown ids return 404", async ({ page }) => {
    const res = await page.goto("/investigation/INV-2000-NOPE0000");
    expect(res?.status()).toBe(404);
  });
});
