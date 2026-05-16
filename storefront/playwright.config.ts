import { defineConfig, devices } from "@playwright/test";

const mockBackendUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:3101";
const storefrontBaseUrl = process.env.STOREFRONT_BASE_URL ?? "http://127.0.0.1:3100";
const storefrontEnv = Object.entries({
  NEXT_PUBLIC_API_BASE_URL: mockBackendUrl,
  NEXT_PUBLIC_ASSET_BASE_URL: process.env.NEXT_PUBLIC_ASSET_BASE_URL ?? mockBackendUrl,
  NEXT_PUBLIC_DEFAULT_STORE: process.env.NEXT_PUBLIC_DEFAULT_STORE ?? "demo-store",
  DEFAULT_STORE_IDENTIFIER: process.env.DEFAULT_STORE_IDENTIFIER ?? "demo-store",
  NEXT_PUBLIC_STOREFRONT_BASE_URL: process.env.NEXT_PUBLIC_STOREFRONT_BASE_URL ?? storefrontBaseUrl,
  STOREFRONT_BASE_URL: storefrontBaseUrl,
})
  .map(([key, value]) => `${key}=${value}`)
  .join(" ");

export default defineConfig({
  testDir: "./tests/e2e",
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: true,
  reporter: [["list"]],
  workers: process.env.CI ? 1 : undefined,
  use: {
    baseURL: storefrontBaseUrl,
    trace: "on-first-retry",
  },
  webServer: [
    {
      command: "node tests/e2e/mock-backend.mjs",
      url: `${mockBackendUrl}/health`,
      reuseExistingServer: !process.env.CI,
      timeout: 30_000,
    },
    {
      command:
        `${storefrontEnv} pnpm exec next start --hostname 127.0.0.1 --port 3100`,
      url: "http://127.0.0.1:3100",
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
  ],
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
