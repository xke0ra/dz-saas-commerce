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
  await expect(page.getByRole("button", { name: "اختر خصائص المنتج أولا." }).first()).toBeDisabled();
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
  expect(sitemap).toContain("<loc>http://127.0.0.1:3100/products/demo-shoes</loc>");
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

test("renders and updates product variant choices on product detail", async ({ page }) => {
  await page.goto("/products/demo-shirt");

  await expect(page.getByRole("button", { name: "كبير" })).toHaveAttribute("aria-pressed", "true");
  await expect(page.getByRole("button", { name: "أسود" })).toHaveAttribute("aria-pressed", "true");
  await expect(page.getByTestId("variant-sku")).toContainText("TSHIRT-L-BLK");
  await expect(page.getByTestId("variant-availability")).toContainText("متوفر: 6");

  await page.getByRole("button", { name: "صغير" }).click();
  await expect(page.getByTestId("variant-sku")).toContainText("TSHIRT-S-BLK");
  await expect(page.getByTestId("variant-availability")).toContainText("متوفر");

  await page.getByRole("button", { name: "أبيض" }).click();
  await expect(page.getByTestId("variant-availability")).toContainText("هذه التركيبة غير متاحة");
  await expect(page.getByRole("button", { name: "تأكيد الطلب" })).toBeDisabled();

  await page.getByRole("button", { name: "كبير" }).click();
  await expect(page.getByTestId("variant-sku")).toContainText("TSHIRT-L-WHT");
  await expect(page.getByTestId("variant-availability")).toContainText("غير متوفر");
  await expect(page.getByRole("button", { name: "تأكيد الطلب" })).toBeDisabled();
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
  let checkoutPayload: Record<string, unknown> | null = null;

  await page.route("**/api/storefront/demo-store/checkout", async (route) => {
    checkoutPayload = route.request().postDataJSON() as Record<string, unknown>;

    await route.fulfill({
      status: 201,
      contentType: "application/json",
      body: JSON.stringify({ data: checkoutOrder() }),
    });
  });

  await page.goto("/products/demo-shirt");

  await expect(page.getByRole("heading", { level: 1, name: "قميص تجريبي" })).toBeVisible();
  await expect(page.getByTestId("variant-sku")).toContainText("TSHIRT-L-BLK");
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
  expect(checkoutPayload).toMatchObject({
    product_id: "prod_01",
    product_variant_id: "var_large_black",
    quantity: 1,
  });
});

test("creates a simple product quick order without product_variant_id", async ({ page }) => {
  let checkoutPayload: Record<string, unknown> | null = null;

  await page.route("**/api/storefront/demo-store/checkout", async (route) => {
    checkoutPayload = route.request().postDataJSON() as Record<string, unknown>;

    await route.fulfill({
      status: 201,
      contentType: "application/json",
      body: JSON.stringify({ data: checkoutOrder("ORD-1002") }),
    });
  });

  await page.goto("/products/demo-shoes");

  await expect(page.getByRole("heading", { level: 1, name: "حذاء تجريبي" })).toBeVisible();
  await page.getByLabel("الاسم الكامل").fill("Ahmed Simple");
  await page.getByLabel("الهاتف").fill("0555123456");
  await page.locator('select[name="wilaya_id"]').selectOption("16");
  await expect(page.locator('select[name="commune_id"]')).toBeEnabled();
  await page.locator('select[name="commune_id"]').selectOption("1601");
  await page.getByLabel("العنوان").fill("Alger Centre, Simple Demo");
  await page.getByRole("button", { name: "تأكيد الطلب" }).click();

  await expect(page.getByText("ORD-1002")).toBeVisible();
  expect(checkoutPayload).toMatchObject({
    product_id: "prod_02",
    quantity: 1,
  });
  expect(checkoutPayload).not.toHaveProperty("product_variant_id");
});

test("treats a legacy product payload without type as simple", async ({ page }) => {
  let checkoutPayload: Record<string, unknown> | null = null;

  await page.route("**/api/storefront/demo-store/checkout", async (route) => {
    checkoutPayload = route.request().postDataJSON() as Record<string, unknown>;

    await route.fulfill({
      status: 201,
      contentType: "application/json",
      body: JSON.stringify({ data: checkoutOrder("ORD-1004") }),
    });
  });

  await page.goto("/products/legacy-product");

  await expect(page.getByRole("heading", { level: 1, name: "منتج قديم" })).toBeVisible();
  await page.getByLabel("الاسم الكامل").fill("Ahmed Legacy");
  await page.getByLabel("الهاتف").fill("0555123456");
  await page.locator('select[name="wilaya_id"]').selectOption("16");
  await expect(page.locator('select[name="commune_id"]')).toBeEnabled();
  await page.locator('select[name="commune_id"]').selectOption("1601");
  await page.getByLabel("العنوان").fill("Alger Centre, Legacy Demo");
  await page.getByRole("button", { name: "تأكيد الطلب" }).click();

  await expect(page.getByText("ORD-1004")).toBeVisible();
  expect(checkoutPayload).toMatchObject({
    product_id: "prod_legacy",
    quantity: 1,
  });
  expect(checkoutPayload).not.toHaveProperty("product_variant_id");
});

test("creates a cart COD order with item payloads", async ({ page }) => {
  let checkoutPayload: Record<string, unknown> | null = null;

  await page.route("**/api/storefront/demo-store/checkout", async (route) => {
    checkoutPayload = route.request().postDataJSON() as Record<string, unknown>;

    await route.fulfill({
      status: 201,
      contentType: "application/json",
      body: JSON.stringify({ data: checkoutOrder("ORD-1003") }),
    });
  });

  await page.goto("/products");
  await expect
    .poll(() => page.evaluate(() => window.localStorage.getItem("dz-saas-commerce:cart:store_01")))
    .toBe("[]");

  await page.getByRole("button", { name: "أضف للسلة" }).first().click();
  await page.getByRole("link", { name: "فتح سلة التسوق" }).click();

  await expect(page).toHaveURL(/\/cart$/);
  await expect(page.getByRole("heading", { name: "منتجاتك المختارة" })).toBeVisible();
  await expect(page.getByText("حذاء تجريبي").first()).toBeVisible();

  await page.getByLabel("الاسم الكامل").fill("Ahmed Cart");
  await page.getByLabel("الهاتف").fill("0555123456");
  await page.locator('select[name="wilaya_id"]').selectOption("16");
  await expect(page.locator('select[name="commune_id"]')).toBeEnabled();
  await page.locator('select[name="commune_id"]').selectOption("1601");
  await page.getByLabel("العنوان").fill("Alger Centre, Cart Demo");
  await page.getByRole("button", { name: "تأكيد طلب السلة" }).click();

  await expect(page.getByText("تم تسجيل الطلب")).toBeVisible();
  await expect(page.getByText("ORD-1003")).toBeVisible();
  expect(checkoutPayload).toMatchObject({
    items: [
      {
        product_id: "prod_02",
        quantity: 1,
      },
    ],
  });
  const payload = checkoutPayload as { items?: Array<Record<string, unknown>> } | null;
  expect(payload?.items?.[0]).not.toHaveProperty("product_variant_id");
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

function checkoutOrder(orderNumber = "ORD-1001") {
  return {
    id: `order_${orderNumber}`,
    order_number: orderNumber,
    status: "pending",
    payment_status: "unpaid",
    delivery_type: "home",
    subtotal_minor: 250000,
    shipping_fee_minor: 50000,
    discount_minor: 0,
    total_minor: 300000,
    currency: "DZD",
    customer: undefined,
    coupon: null,
    items: [],
    created_at: "2026-04-27T09:10:00.000000Z",
  };
}
