import { expect, test } from "@playwright/test";
import { storageFor, unique } from "./helpers";

const INVESTIGATION = "/search?q=tannery";

async function openInvestigation(page: import("@playwright/test").Page) {
  await page.goto(INVESTIGATION);
  await page.getByRole("link", { name: /Tannery effluent in the Dhaleshwari/ }).click();
  await expect(page.getByRole("heading", { level: 1 })).toContainText("Tannery effluent");
}

test.describe("Journeys 3 & 4 — community", () => {
  test.use({ storageState: storageFor("reader") });

  test("comment, reply, edit, report", async ({ page, browser }) => {
    await openInvestigation(page);
    const text = unique("A careful question about sampling");
    await page.getByLabel("Add a comment").fill(text);
    await page.getByRole("button", { name: "Post comment" }).click();
    const mine = page.getByRole("article", { name: /Comment by rafiq_reads/ }).filter({ hasText: text });
    await expect(mine).toBeVisible();

    await mine.getByRole("button", { name: "Edit" }).click();
    await page.getByLabel("Edit comment").fill(`${text} (edited)`);
    await page.getByRole("button", { name: "Save" }).click();
    await expect(page.getByText(`${text} (edited)`)).toBeVisible();

    // A second user replies and reports.
    const ctx = await browser.newContext({ storageState: storageFor("reader2") });
    const p2 = await ctx.newPage();
    await openInvestigation(p2);
    const target = p2.getByRole("article").filter({ hasText: `${text} (edited)` });
    await target.getByRole("button", { name: "Reply" }).click();
    await p2.getByLabel("Your reply").fill("Replying to your question.");
    await p2.getByRole("button", { name: "Reply", exact: true }).last().click();
    await expect(p2.getByText("Replying to your question.")).toBeVisible();

    await target.getByRole("button", { name: "Upvote" }).click();
    await expect(target.getByLabel("Score 1")).toBeVisible();

    await target.getByRole("button", { name: "Report" }).click();
    await p2.getByLabel("Reason").selectOption("off_topic");
    await p2.getByRole("button", { name: "Submit report" }).click();
    await expect(p2.getByText("Our moderators will review this comment.")).toBeVisible();
    await ctx.close();
  });

  test("bookmark, react, follow and support (once)", async ({ page }) => {
    await page.goto("/investigators/rahima-akter");
    await page.getByRole("link", { name: /School stipend payments/ }).first().click();
    await expect(page.getByRole("heading", { level: 1 })).toContainText("School stipend");

    const bookmark = page.getByRole("button", { name: /^Bookmark/ });
    await bookmark.click();
    await expect(page.getByRole("button", { name: /^Bookmarked/ })).toBeVisible();
    await page.getByRole("button", { name: "Useful" }).click();
    await expect(page.getByRole("button", { name: "Useful" })).toHaveAttribute("aria-pressed", "true");

    // Reader already follows/supports Rahima from the demo seed: unfollow → follow.
    const following = page.getByRole("button", { name: /^Following/ });
    await following.click();
    await expect(page.getByRole("button", { name: /^Follow investigator/ })).toBeVisible();
    await page.getByRole("button", { name: /^Follow investigator/ }).click();
    await expect(page.getByRole("button", { name: /^Following/ })).toBeVisible();

    const supporting = page.getByRole("button", { name: /^Supporting · (\d+)/ });
    const before = Number((await supporting.textContent())?.match(/(\d+)$/)?.[1]);
    await supporting.click(); // revoke
    await page.getByRole("button", { name: /^Support investigator/ }).click(); // immediate re-support hits the cooldown
    await expect(page.getByText("You recently withdrew support")).toBeVisible();
    await expect(page.getByRole("button", { name: /^Support investigator · / })).toContainText(String(before - 1));

    await page.goto("/account/bookmarks");
    await expect(page.getByRole("link", { name: /School stipend payments/ })).toBeVisible();
  });
});
