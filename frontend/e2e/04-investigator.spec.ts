import path from "node:path";
import { expect, test } from "@playwright/test";
import { apiGet, field, storageFor, unique } from "./helpers";

test.describe("Journeys 5 & 6 — investigator publishes with evidence; private evidence stays private", () => {
  test.use({ storageState: storageFor("investigator") });

  test("create draft, autosave, upload evidence, preview, confirm responsibility, publish", async ({ page, browser }) => {
    const title = `Ferry fare subsidies audit ${unique("x")}`;
    await page.goto("/dashboard/investigations/new");
    await page.getByLabel("Working title").fill(title);
    await page.getByRole("button", { name: "Create draft" }).click();
    await expect(page).toHaveURL(/\/dashboard\/investigations\/INV-/);
    const id = page.url().split("/").pop()!;

    // Header image: uploaded on its own, shown in the editor straight away.
    await page.locator('input[type="file"][accept^="image/"]').setInputFiles(path.join(__dirname, "fixtures", "cover.jpg"));
    await expect(page.getByRole("img", { name: "Header image of the report" })).toBeVisible();

    // Rich-text report body: formatting comes from the toolbar and is stored as Markdown.
    const report = page.getByRole("textbox", { name: "Report" });
    await report.click();
    await page.getByRole("button", { name: "Heading", exact: true }).click();
    await page.keyboard.type("Background");
    await page.keyboard.press("Enter");
    await page.keyboard.type("An audit of how ferry fare subsidies were distributed across three river routes in 2025. ");
    await page.getByRole("button", { name: "Bold" }).click();
    await page.keyboard.type("Subsidy records were obtained");
    await page.getByRole("button", { name: "Bold" }).click();
    await page.keyboard.type(" and compared with operator logs. ".repeat(3));
    await expect(page.getByText(/^Saved /)).toBeVisible({ timeout: 15_000 });
    // The summary is generated from the report itself.
    await expect(page.getByRole("region", { name: "Summary" })).toContainText("An audit of how ferry fare subsidies were distributed");
    const saved = (await (await apiGet(page.request, `/api/v1/me/investigations/${id}`)).json()).data;
    expect(saved.body).toContain("## Background");
    expect(saved.body).toContain("**Subsidy records were obtained**");

    await page.getByRole("tab", { name: "Findings & recommendations" }).click();
    await page.getByRole("button", { name: "Add finding" }).click();
    await field(page, "Finding").fill("Two routes received subsidies without logs");

    await page.getByRole("tab", { name: "Sources" }).click();
    await page.getByRole("button", { name: "Add source" }).click();
    await page.getByLabel("Title").fill("BIWTA subsidy circular");
    await page.getByLabel("URL (http/https)").fill("https://example.org/circular");

    await page.getByRole("tab", { name: "Classification & settings" }).click();
    await field(page, "Category").selectOption("infrastructure-development");
    await page.getByLabel("Tags (comma separated)").fill("ferries, subsidies");
    await expect(page.getByText(/^Saved /)).toBeVisible({ timeout: 15_000 });

    // Evidence: one public PDF and one private note.
    await page.getByRole("tab", { name: /Evidence/ }).click();
    await page.getByLabel("File").setInputFiles({ name: "logs.pdf", mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.4\n% ferry logs " + Date.now() + "\n%%EOF") });
    await page.getByLabel("Caption").first().fill("Operator logs");
    await page.getByLabel("Visibility").first().selectOption("public");
    await page.getByRole("button", { name: "Upload evidence" }).click();
    await expect(page.getByText("logs.pdf")).toBeVisible();
    await page.getByLabel("File").setInputFiles({ name: "private-notes.txt", mimeType: "text/plain", buffer: Buffer.from("confidential source notes " + Date.now()) });
    // The upload form resets to private after each upload.
    await expect(page.getByLabel("Visibility").first()).toHaveValue("private");
    await page.getByRole("button", { name: "Upload evidence" }).click();
    await expect(page.getByText("private-notes.txt")).toBeVisible();

    // Malicious upload is rejected server-side.
    await page.getByLabel("File").setInputFiles({ name: "shell.pdf", mimeType: "application/pdf", buffer: Buffer.from("<?php system('id'); ?>") });
    await page.getByRole("button", { name: "Upload evidence" }).click();
    await expect(page.getByRole("status").or(page.getByRole("alert")).filter({ hasText: /not allowed|does not match/ })).toBeVisible();

    await page.getByRole("link", { name: "Preview" }).click();
    await expect(page.getByText("Preview — not published")).toBeVisible();
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(title);
    await expect(page.getByText("Operator logs").first()).toBeVisible();
    await expect(page.getByText("private-notes")).toHaveCount(0);
    await page.getByRole("link", { name: "Back to the editor" }).click();

    await page.getByRole("button", { name: "Review & publish" }).click();
    const dialog = page.getByRole("dialog");
    const publish = dialog.getByRole("button", { name: "Publish" });
    await expect(publish).toBeDisabled();
    await expect(dialog.getByRole("checkbox")).toHaveCount(4);
    for (const box of await dialog.getByRole("checkbox").all()) await box.check();
    await publish.click();
    await expect(page.getByText("Published version 1.")).toBeVisible();

    // Public page shows the report and only public evidence.
    await page.getByRole("link", { name: "View public page" }).click();
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(title);
    const cover = page.locator("article figure img");
    await expect(cover).toBeVisible();
    await expect.poll(() => cover.evaluate((img: HTMLImageElement) => img.complete ? img.naturalWidth : 0)).toBeGreaterThan(0);
    await expect(page.locator("article strong", { hasText: "Subsidy records were obtained" })).toBeVisible();
    await expect(page.getByText("Operator logs").first()).toBeVisible();

    // Another investigator cannot access the private evidence or the working copy.
    const me = await apiGet(page.request, `/api/v1/me/investigations/${id}/evidence`);
    const privateItem = (await me.json()).data.find((e: { original_name: string }) => e.original_name === "private-notes.txt");
    expect(privateItem.visibility).toBe("private");
    const other = await browser.newContext({ storageState: storageFor("investigator2") });
    expect((await apiGet(other.request, `/api/v1/evidence/${privateItem.id}/download`)).status()).toBe(404);
    expect((await apiGet(other.request, `/api/v1/me/investigations/${id}`)).status()).toBe(404);
    const guest = await browser.newContext();
    expect((await guest.request.get(`/api/v1/evidence/${privateItem.id}/download`)).status()).toBe(404);
    expect((await apiGet(page.request, `/api/v1/evidence/${privateItem.id}/download`)).status()).toBe(200);
    await other.close();
    await guest.close();
  });
});
