import { expect, test } from "@playwright/test";
import { field, latestMailLink, unique } from "./helpers";

test("Journey 2 — registration, email verification, logout and login", async ({ page }) => {
  const username = unique("e2e").replace(/-/g, "_");
  const email = `${username}@example.org`;
  const password = "E2e-Password-2026";

  await page.goto("/register");
  await page.getByLabel("Full name").fill("E2E Person");
  await page.getByLabel("Public username (optional)").fill(username);
  await page.getByLabel("Email").fill(email);
  await field(page, "Password").fill(password);
  await page.getByLabel("Confirm password").fill(password);
  await page.getByLabel(/I agree to the Terms of Use/).check();
  await page.getByRole("button", { name: "Create account" }).click();

  await expect(page).toHaveURL(/\/verify-email\?sent=1/);
  await expect(page.getByText(/We sent a verification link/)).toBeVisible();

  let link: string | null = null;
  await expect.poll(() => (link = latestMailLink(new RegExp(`http://localhost:3100/verify-email\\?[^\\s"'<>)]+`))), { timeout: 15_000 }).not.toBeNull();
  await page.goto(link!.replace("http://localhost:3100", ""));
  await expect(page.getByText("Email verified")).toBeVisible();

  await page.getByRole("button", { name: new RegExp(username) }).click();
  await page.getByRole("menuitem", { name: "Sign out" }).click();
  await expect(page.getByRole("link", { name: "Sign in" })).toBeVisible();

  await page.goto("/login");
  await page.getByLabel("Email").fill(email);
  await field(page, "Password").fill("wrong-Password-1");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByText(/do not match our records/)).toBeVisible();
  await field(page, "Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByRole("button", { name: new RegExp(username) })).toBeVisible();
});
