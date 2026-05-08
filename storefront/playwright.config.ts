import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: true,
  reporter: [["list"]],
  workers: process.env.CI ? 1 : undefined,
  use: {
    baseURL: "http://127.0.0.1:3100",
    trace: "on-first-retry",
  },
  webServer: [
    {
      command: "node tests/e2e/mock-backend.mjs",
      url: "http://127.0.0.1:3101/health",
      reuseExistingServer: !process.env.CI,
      timeout: 30_000,
    },
    {
      command:
        "NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:3101 NEXT_PUBLIC_ASSET_BASE_URL=http://127.0.0.1:3101 NEXT_PUBLIC_DEFAULT_STORE=demo-store DEFAULT_STORE_IDENTIFIER=demo-store NEXT_PUBLIC_STOREFRONT_BASE_URL=http://127.0.0.1:3100 STOREFRONT_BASE_URL=http://127.0.0.1:3100 pnpm next dev --hostname 127.0.0.1 --port 3100",
      url: "http://127.0.0.1:3100",
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
    },
  ],
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
