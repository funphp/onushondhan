import { expect, type APIRequestContext, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

/** Local demo accounts from DemoSeeder — NON-PRODUCTION credentials. */
export const DEMO_PASSWORD = "Onushondhan#2026";
export const USERS = {
  reader: "reader@onushondhan.test",
  reader2: "reader2@onushondhan.test",
  investigator: "investigator@onushondhan.test",
  investigator2: "investigator2@onushondhan.test",
  moderator: "moderator@onushondhan.test",
  admin: "admin@onushondhan.test",
} as const;

export const API_URL = process.env.E2E_API_URL ?? "http://127.0.0.1:8001";
export const ADMIN_URL = API_URL.replace("127.0.0.1", "localhost") + "/admin";

/** Form control by its exact label, tolerating the visual "required" asterisk. */
export function field(page: Page, label: string) {
  const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  return page.getByLabel(new RegExp(`^${escaped}( \\*)?$`));
}

export function storageFor(role: keyof typeof USERS): string {
  return path.join(__dirname, ".auth", `${role}.json`);
}

export async function login(page: Page, email: string, password = DEMO_PASSWORD): Promise<void> {
  await page.goto("/login");
  await page.getByLabel("Email").fill(email);
  await field(page, "Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.locator('button[aria-haspopup="menu"]')).toBeVisible();
}

/** Latest link matching `pattern` from the E2E mail log written by the backend. */
export function latestMailLink(pattern: RegExp): string | null {
  const file = process.env.E2E_MAIL_LOG ?? path.resolve(__dirname, "../../backend/storage/logs/e2e-mail.log");
  if (!fs.existsSync(file)) return null;
  const content = fs.readFileSync(file, "utf8").replace(/=\r?\n/g, "").replace(/=3D/g, "=").replace(/&amp;/g, "&");
  const matches = content.match(new RegExp(pattern.source, "g"));
  return matches ? matches[matches.length - 1] : null;
}

export function unique(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}`;
}

/** API GET as the SPA would send it (Origin makes Sanctum treat it as a session request). */
export async function apiGet(request: APIRequestContext, path: string) {
  return request.get(path, { headers: { Origin: "http://localhost:3100", Referer: "http://localhost:3100/", Accept: "application/json" } });
}

export async function adminLogin(page: Page, email: string): Promise<void> {
  await page.goto(`${ADMIN_URL}/login`);
  await page.getByLabel("Email address").fill(email);
  await page.locator("input[type=password]").fill(DEMO_PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(new RegExp(`${ADMIN_URL.replace(/[/.]/g, "\\$&")}/?$`));
}
