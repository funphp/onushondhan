import { expect, test } from "@playwright/test";
import { adminLogin, unique, USERS } from "./helpers";

test("Journeys 9 & 10 — complaint reviewed by a moderator; admin acts on a user and sees the audit log", async ({ page, browser }) => {
  const description = `${unique("Privacy")}: the report shows a private home address in the evidence captions.`;

  // A guest files a privacy complaint.
  await page.goto("/search?q=medicines");
  await page.getByRole("link", { name: /Expired medicines/ }).click();
  await page.getByRole("link", { name: "Report a problem with this content" }).click();
  await page.getByLabel("What is the problem?").selectOption("privacy");
  await page.getByLabel("Details").fill(description);
  await page.getByLabel("Your email (so we can follow up)").fill("subject@example.org");
  await page.getByLabel(/accurate and submitted in good faith/).check();
  await page.getByRole("button", { name: "Submit complaint" }).click();
  await expect(page.getByText("Complaint received")).toBeVisible();

  // Moderator reviews it in the admin panel.
  const mod = await browser.newContext();
  const mp = await mod.newPage();
  await adminLogin(mp, USERS.moderator);
  await mp.getByRole("link", { name: "Complaints" }).first().click();
  const row = mp.getByRole("row").filter({ hasText: description.slice(0, 30) });
  await expect(row).toBeVisible();
  await row.getByRole("button", { name: "Update status" }).click();
  const modal = mp.getByRole("dialog");
  await modal.getByLabel("Status").selectOption("action_requested");
  await modal.getByLabel("Internal note (staff only, appended)").fill("Asked the investigator to redact the address.");
  await modal.getByRole("button", { name: "Submit" }).click();
  await expect(row).toContainText("Action requested");
  // Moderators cannot reach role management.
  expect((await mp.goto(`${mp.url().split("/admin")[0]}/admin/roles`))?.status()).toBe(403);
  await mod.close();

  // Admin suspends a user and sees it in the audit log.
  const adm = await browser.newContext();
  const ap = await adm.newPage();
  await adminLogin(ap, USERS.admin);
  await ap.goto(ap.url().replace(/\/admin.*/, "/admin/users"));
  const user = ap.getByRole("row").filter({ hasText: "nadia_n" });
  await user.getByRole("button").last().click();
  await ap.getByRole("button", { name: "Suspend" }).click();
  // Filament keeps other (hidden) modal markup in the DOM, so target the visible controls.
  await ap.locator("textarea:visible").first().fill("E2E: repeated spam");
  await ap.locator("input[type=number]:visible").first().fill("1");
  await ap.getByRole("button", { name: "Confirm" }).and(ap.locator(":visible")).click();
  await expect(user).toContainText("Suspended");
  await ap.goto(ap.url().replace(/\/admin.*/, "/admin/audit-logs"));
  await expect(ap.getByText("moderation.suspend_user").first()).toBeVisible();
  await expect(ap.getByText("complaint.status_changed").first()).toBeVisible();
  await adm.close();
});
