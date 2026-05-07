import { expect, test } from "@playwright/test";

test("renders the tenant storefront home and product listing", async ({ page }) => {
  await page.goto("/");

  await expect(page.getByRole("heading", { name: "متجر تجريبي DZ" })).toBeVisible();
  await expect(page.getByRole("link", { name: "قميص تجريبي" }).first()).toBeVisible();
  await expect(page.getByRole("link", { name: "أزياء" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "تجربة طلب مصممة للجزائر" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "معلومات المتجر" })).toBeVisible();
  await expect(page.getByText("الدفع عند الاستلام")).toBeVisible();

  await page.getByRole("link", { name: "كل المنتجات" }).first().click();
  await expect(page).toHaveURL(/\/products$/);
  await expect(page.getByRole("heading", { name: "كل المنتجات المتاحة" })).toBeVisible();
});

test("exposes storefront SEO metadata and crawl routes", async ({ page, request }) => {
  const [sitemapResponse, robotsResponse] = await Promise.all([
    request.get("/sitemap.xml"),
    request.get("/robots.txt"),
  ]);
  const [sitemap, robots] = await Promise.all([sitemapResponse.text(), robotsResponse.text()]);

  expect(sitemapResponse.ok()).toBeTruthy();
  expect(sitemap).toContain("<loc>http://127.0.0.1:3100/</loc>");
  expect(sitemap).toContain("<loc>http://127.0.0.1:3100/products/demo-shirt</loc>");
  expect(sitemap).toContain("<loc>http://127.0.0.1:3100/categories/fashion</loc>");
  expect(sitemap).toContain("<loc>http://127.0.0.1:3100/legal/terms</loc>");
  expect(robotsResponse.ok()).toBeTruthy();
  expect(robots).toContain("Sitemap: http://127.0.0.1:3100/sitemap.xml");
  expect(robots).toContain("Disallow: /cart");
  expect(robots).toContain("Disallow: /track-order");

  await page.goto("/products/demo-shirt");

  await expect(page).toHaveTitle("قميص تجريبي | متجر تجريبي DZ");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    "href",
    "http://127.0.0.1:3100/products/demo-shirt",
  );
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
    "content",
    "قميص تجريبي | متجر تجريبي DZ",
  );
  await expect(page.locator('meta[property="og:type"]')).toHaveAttribute("content", "article");

  const jsonLd = (await page.locator('script[type="application/ld+json"]').allTextContents()).join("\n");

  expect(jsonLd).toContain('"@type":"Product"');
  expect(jsonLd).toContain('"priceCurrency":"DZD"');
  expect(jsonLd).toContain('"@type":"BreadcrumbList"');
});

test("keeps storefront navigation usable on mobile", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/products");

  await expect(page.getByRole("searchbox", { name: "ابحث عن منتج" })).toBeVisible();
  await expect(page.getByRole("link", { name: "فتح سلة التسوق" })).toBeVisible();

  await page.getByRole("button", { name: "أضف للسلة" }).first().click();

  await expect(page.getByRole("link", { name: "فتح سلة التسوق" })).toContainText("1");

  await page.getByRole("link", { name: "فتح سلة التسوق" }).click();

  await expect(page.getByRole("link", { name: "إكمال بيانات الطلب" })).toBeVisible();
  await expect(page.getByText("1 منتج في السلة")).toBeVisible();
});

test("creates a quick COD order from a product page", async ({ page }) => {
  await page.goto("/products/demo-shirt");

  await expect(page.getByRole("heading", { level: 1, name: "قميص تجريبي" })).toBeVisible();
  await page.getByLabel("الاسم الكامل").fill("Ahmed Demo");
  await page.getByLabel("الهاتف").fill("0555123456");
  await page.locator('select[name="wilaya_id"]').selectOption("16");
  await expect(page.locator('select[name="commune_id"]')).toBeEnabled();
  await page.locator('select[name="commune_id"]').selectOption("1601");
  await page.getByLabel("العنوان").fill("Alger Centre, Rue Demo");
  await page.getByRole("button", { name: "تأكيد الطلب" }).click();

  await expect(page.getByText("تم تسجيل الطلب")).toBeVisible();
  await expect(page.getByText("ORD-1001")).toBeVisible();
  await expect(page.getByText("المجموع").last()).toBeVisible();
});

test("creates a cart COD order with item payloads", async ({ page }) => {
  await page.goto("/products");

  await page.getByRole("button", { name: "أضف للسلة" }).first().click();
  await page.getByRole("link", { name: "فتح سلة التسوق" }).click();

  await expect(page).toHaveURL(/\/cart$/);
  await expect(page.getByRole("heading", { name: "منتجاتك المختارة" })).toBeVisible();
  await expect(page.getByText("قميص تجريبي").first()).toBeVisible();

  await page.getByLabel("الاسم الكامل").fill("Ahmed Cart");
  await page.getByLabel("الهاتف").fill("0555123456");
  await page.locator('select[name="wilaya_id"]').selectOption("16");
  await expect(page.locator('select[name="commune_id"]')).toBeEnabled();
  await page.locator('select[name="commune_id"]').selectOption("1601");
  await page.getByLabel("العنوان").fill("Alger Centre, Cart Demo");
  await page.getByRole("button", { name: "تأكيد طلب السلة" }).click();

  await expect(page.getByText("تم تسجيل الطلب")).toBeVisible();
  await expect(page.getByText("ORD-1001")).toBeVisible();
});

test("tracks an existing order", async ({ page }) => {
  await page.goto("/track-order");

  await page.getByLabel("رقم الطلب").fill("ORD-1001");
  await page.getByLabel("الهاتف").fill("0555123456");
  await page.getByRole("button", { name: "بحث" }).last().click();

  await expect(page.getByText("ORD-1001")).toBeVisible();
  await expect(page.getByText("قيد الانتظار")).toBeVisible();
  await expect(page.getByText("غير مدفوع")).toBeVisible();
});
