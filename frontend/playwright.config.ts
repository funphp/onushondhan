import { defineConfig, devices } from "@playwright/test";

/**
 * E2E journeys run against:
 *  - an isolated Laravel API (scripts/e2e-backend.sh, fresh `onushondhan_e2e` DB with demo seed)
 *    reachable at E2E_API_URL (default http://127.0.0.1:8001), and
 *  - a production build of this app on port 3100 proxying /api to that API.
 */
const API = process.env.E2E_API_URL ?? "http://127.0.0.1:8001";
const PORT = Number(process.env.E2E_PORT ?? 3100);
// Set E2E_BASE_URL to run against an already-deployed site (e.g. http://onusondhan.local);
// then no local server is started. E2E_HOST_RULES can map hostnames (Chromium --host-resolver-rules).
const EXTERNAL = process.env.E2E_BASE_URL;

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [["list"], ["html", { open: "never" }]],
  use: {
    baseURL: EXTERNAL ?? `http://localhost:${PORT}`,
    launchOptions: process.env.E2E_HOST_RULES ? { args: [`--host-resolver-rules=${process.env.E2E_HOST_RULES}`] } : undefined,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    { name: "setup", testMatch: /auth\.setup\.ts/ },
    { name: "chromium", use: { ...devices["Desktop Chrome"] }, dependencies: ["setup"] },
  ],
  webServer: EXTERNAL ? undefined : {
    // The E2E DB is re-seeded per run, so never reuse cached API responses from a previous run.
    command: `rm -rf .next/cache/fetch-cache && npm run build && npx next start -p ${PORT}`,
    url: `http://localhost:${PORT}`,
    reuseExistingServer: false,
    timeout: 240_000,
    env: {
      API_INTERNAL_URL: API,
      NEXT_PUBLIC_SITE_URL: `http://localhost:${PORT}`,
      NEXT_PUBLIC_ADMIN_URL: `${API.replace("127.0.0.1", "localhost")}/admin`,
    },
  },
});
