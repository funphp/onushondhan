import { test as setup } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { login, storageFor, USERS } from "./helpers";

setup.beforeAll(() => fs.mkdirSync(path.join(__dirname, ".auth"), { recursive: true }));

for (const role of ["reader", "reader2", "investigator", "investigator2"] as const) {
  setup(`authenticate ${role}`, async ({ page }) => {
    await login(page, USERS[role]);
    await page.context().storageState({ path: storageFor(role) });
  });
}
