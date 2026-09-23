import { expect, test } from "@playwright/test";
import { apiGet, storageFor, unique } from "./helpers";

test("Journeys 7 & 8 — anonymous guest tip, investigator processes and replies, guest reads reply", async ({ page, browser }) => {
  const subject = unique("Night discharge schedule");

  // Guest (no account) sends a tip from the investigation page.
  await page.goto("/search?q=tannery");
  await page.getByRole("link", { name: /Tannery effluent in the Dhaleshwari/ }).click();
  await page.getByRole("link", { name: "Send a tip" }).click();
  await expect(page.getByText("Sending as a guest")).toBeVisible();
  await page.getByLabel("Subject").fill(subject);
  await page.getByLabel("Your tip").fill("The discharge happens after midnight on Fridays from the second outlet.");
  await page.getByLabel(/I have read the Content Policy/).check();
  await page.getByRole("button", { name: "Send tip" }).click();
  const code = (await page.getByLabel("Access code").textContent())!.trim();
  expect(code).toMatch(/^TIP-/);

  // Investigator reads, marks important and replies; sender is anonymous.
  const inv = await browser.newContext({ storageState: storageFor("investigator2") });
  const ip = await inv.newPage();
  await ip.goto("/dashboard/tips");
  await ip.getByRole("link", { name: subject }).click();
  await expect(ip.getByText("From an anonymous guest (no account)")).toBeVisible();
  await ip.getByRole("button", { name: "Mark important" }).click();
  await expect(ip.getByText("Important", { exact: true })).toBeVisible();
  await ip.getByLabel("Reply").fill("Thank you. Could you share which week this started?");
  await ip.getByRole("button", { name: "Send reply" }).click();
  await expect(ip.getByText("Could you share which week")).toBeVisible();

  // Another investigator cannot open it.
  const other = await browser.newContext({ storageState: storageFor("investigator") });
  const tipId = ip.url().split("/").pop();
  expect((await apiGet(other.request, `/api/v1/me/inbox/tips/${tipId}`)).status()).toBe(404);
  await other.close();
  await inv.close();

  // Guest returns with the access code.
  await page.goto("/tips/access");
  await page.getByLabel("Access code").fill(code);
  await page.getByRole("button", { name: "Open" }).click();
  await expect(page.getByText("Could you share which week")).toBeVisible();
  await page.getByLabel("Reply").fill("It started in the second week of March.");
  await page.getByRole("button", { name: "Send reply" }).click();
  await expect(page.getByText("second week of March")).toBeVisible();
});
