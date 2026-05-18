import http from "node:http";

const port = Number(process.env.MOCK_BACKEND_PORT ?? 3101);

const category = {
  id: "cat_01",
  parent_id: null,
  name: "أزياء",
  slug: "fashion",
  description: "منتجات يومية للتجربة",
  status: "active",
  sort_order: 1,
};

const product = {
  id: "prod_01",
  category_id: category.id,
  name: "قميص تجريبي",
  slug: "demo-shirt",
  sku: "TSHIRT-DEMO",
  short_description: "منتج تجريبي قابل للطلب السريع.",
  description: "قميص قطني مخصص لاختبار تجربة الطلب السريع داخل الواجهة.",
  status: "active",
  price_minor: 250000,
  compare_at_price_minor: 320000,
  currency: "DZD",
  requires_shipping: true,
  is_featured: true,
  published_at: "2026-04-27T09:00:00.000000Z",
  category,
  images: [],
  inventory: {
    track_quantity: true,
    available_quantity: 12,
    allow_backorders: false,
  },
};

const productDetail = {
  ...product,
  options: [
    {
      id: "opt_size",
      name: "المقاس",
      position: 0,
      values: [
        { id: "val_large", value: "كبير", position: 0 },
        { id: "val_small", value: "صغير", position: 1 },
      ],
    },
    {
      id: "opt_color",
      name: "اللون",
      position: 1,
      values: [
        { id: "val_black", value: "أسود", position: 0 },
        { id: "val_white", value: "أبيض", position: 1 },
      ],
    },
  ],
  variants: [
    {
      id: "var_large_white",
      sku: "TSHIRT-L-WHT",
      title: "كبير / أبيض",
      option_signature: "size=large;color=white",
      price_minor: 280000,
      compare_at_price_minor: 320000,
      effective_price_minor: 280000,
      status: "active",
      sort_order: 0,
      available_quantity: 0,
      is_available: false,
      selected_options: {
        "المقاس": "كبير",
        "اللون": "أبيض",
      },
    },
    {
      id: "var_large_black",
      sku: "TSHIRT-L-BLK",
      title: "كبير / أسود",
      option_signature: "size=large;color=black",
      price_minor: 275000,
      compare_at_price_minor: 320000,
      effective_price_minor: 275000,
      status: "active",
      sort_order: 1,
      available_quantity: 6,
      is_available: true,
      selected_options: {
        "المقاس": "كبير",
        "اللون": "أسود",
      },
    },
    {
      id: "var_small_black",
      sku: "TSHIRT-S-BLK",
      title: "صغير / أسود",
      option_signature: "size=small;color=black",
      price_minor: null,
      compare_at_price_minor: null,
      effective_price_minor: 250000,
      status: "active",
      sort_order: 2,
      available_quantity: null,
      is_available: true,
      selected_options: {
        "المقاس": "صغير",
        "اللون": "أسود",
      },
    },
  ],
};

const secondProduct = {
  ...product,
  id: "prod_02",
  name: "حذاء تجريبي",
  slug: "demo-shoes",
  sku: "SHOES-DEMO",
  is_featured: false,
  published_at: "2026-04-28T09:00:00.000000Z",
  options: [],
  variants: [],
};

const order = {
  id: "order_01",
  order_number: "ORD-1001",
  status: "pending",
  payment_status: "unpaid",
  delivery_type: "home",
  subtotal_minor: 250000,
  shipping_fee_minor: 50000,
  discount_minor: 0,
  total_minor: 300000,
  currency: "DZD",
  customer: {
    full_name: "Ahmed Demo",
    phone: "0555123456",
  },
  coupon: null,
  items: [
    {
      product_id: product.id,
      product_name: product.name,
      product_sku: product.sku,
      quantity: 1,
      unit_price_minor: product.price_minor,
      total_minor: product.price_minor,
    },
  ],
  created_at: "2026-04-27T09:10:00.000000Z",
};

function store(locale = "ar") {
  return {
    id: "store_01",
    name: locale === "fr" ? "Boutique Demo DZ" : "متجر تجريبي DZ",
    slug: "demo-store",
    domain: null,
    subdomain: "demo",
    status: "active",
    locale,
    currency: "DZD",
    settings: {},
    store_setting: {
      seller_name: "Demo Merchant",
      seller_address: "Alger Centre",
      commercial_registration_number: "16/00-0000000A00",
      tax_identification_number: "000000000000000",
      public_email: "contact@example.test",
      public_phone: "0555123456",
      support_phone: "0555123456",
      whatsapp_phone: "0555123456",
      seo_title: locale === "fr" ? "Boutique Demo DZ" : "متجر تجريبي DZ",
      seo_description: "Demo storefront",
      announcement_text: locale === "fr" ? "Livraison partout en Algerie" : "توصيل داخل الجزائر",
      legal_pages: {
        terms: true,
        privacy: true,
        returns: true,
        shipping: true,
      },
      legal_content: {
        terms: "Demo terms",
        privacy: "Demo privacy",
        returns: "Demo returns",
        shipping: "Demo shipping",
      },
      social_links: {},
    },
    theme_setting: {
      theme_name: "default",
      primary_color: "#107062",
      accent_color: "#b54836",
      background_color: "#f7f9fa",
      foreground_color: "#161c24",
      heading_font: null,
      body_font: null,
      logo_path: null,
      favicon_path: null,
      hero_image_path: null,
      hero_title: locale === "fr" ? "Boutique Demo DZ" : "متجر تجريبي DZ",
      hero_subtitle: null,
      product_card_style: "standard",
      layout_settings: {},
    },
  };
}

const server = http.createServer(async (request, response) => {
  const url = new URL(request.url ?? "/", `http://${request.headers.host}`);
  const path = url.pathname;
  const locale = url.searchParams.get("host")?.startsWith("fr.") ? "fr" : "ar";

  if (path === "/health") {
    return json(response, { ok: true });
  }

  if (path === "/api/storefront/resolve") {
    return json(response, { data: store(locale) });
  }

  if (path === "/api/storefront/geography/wilayas") {
    return json(response, {
      data: [
        { id: 16, name_ar: "الجزائر", name_fr: "Alger" },
        { id: 31, name_ar: "وهران", name_fr: "Oran" },
      ],
    });
  }

  if (path === "/api/storefront/geography/communes") {
    return json(response, {
      data: [
        { id: 1601, wilaya_id: 16, name_ar: "الجزائر الوسطى", name_fr: "Alger Centre", postal_code: "16000" },
        { id: 1602, wilaya_id: 16, name_ar: "باب الوادي", name_fr: "Bab El Oued", postal_code: "16009" },
      ],
    });
  }

  if (path === "/api/storefront/demo-store/home") {
    return json(response, {
      store: store("ar"),
      categories: { data: [category] },
      featured_products: { data: [product] },
    });
  }

  if (path === "/api/storefront/demo-store/products") {
    const page = Number(url.searchParams.get("page") ?? 1);
    const data = page > 1 ? [secondProduct] : [product];

    return json(response, {
      data,
      meta: {
        current_page: page,
        last_page: 2,
        per_page: 1,
        total: 2,
      },
    });
  }

  if (path === "/api/storefront/demo-store/products/demo-shirt") {
    return json(response, { data: productDetail });
  }

  if (path === "/api/storefront/demo-store/products/demo-shoes") {
    return json(response, { data: secondProduct });
  }

  if (path === "/api/storefront/demo-store/categories") {
    return json(response, { data: [category] });
  }

  if (path === "/api/storefront/demo-store/categories/fashion") {
    return json(response, { data: category });
  }

  if (path === "/api/storefront/demo-store/search") {
    return json(response, { data: [product] });
  }

  if (path === "/api/storefront/demo-store/checkout" && request.method === "POST") {
    await readBody(request);

    return json(response, { data: order }, 201);
  }

  if (path === "/api/storefront/demo-store/track-order") {
    return json(response, { data: order });
  }

  return json(response, { message: "Not found" }, 404);
});

server.listen(port, "127.0.0.1", () => {
  console.log(`Mock storefront backend listening on http://127.0.0.1:${port}`);
});

function json(response, payload, status = 200) {
  response.writeHead(status, {
    "Content-Type": "application/json",
    "Cache-Control": "no-store",
  });
  response.end(JSON.stringify(payload));
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = "";

    request.setEncoding("utf8");
    request.on("data", (chunk) => {
      body += chunk;
    });
    request.on("end", () => resolve(body));
    request.on("error", reject);
  });
}
