import type {
  ApiCollection,
  ApiResource,
  Category,
  CheckoutPayload,
  Commune,
  HomePayload,
  Order,
  Product,
  ProductImage,
  Store,
  Wilaya,
} from "@/lib/types";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000";
const ASSET_BASE_URL = process.env.NEXT_PUBLIC_ASSET_BASE_URL ?? API_BASE_URL;

export class StorefrontApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly payload: unknown,
  ) {
    super(message);
  }
}

type FetchOptions = RequestInit & {
  query?: Record<string, string | number | boolean | null | undefined>;
};

export function backendUrl(path: string, query?: FetchOptions["query"]) {
  const cleanBase = API_BASE_URL.replace(/\/+$/, "");
  const cleanPath = path.startsWith("/") ? path : `/${path}`;
  const url = new URL(cleanPath, cleanBase);

  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== "") {
      url.searchParams.set(key, String(value));
    }
  });

  return url.toString();
}

export async function storefrontFetch<T>(path: string, options: FetchOptions = {}): Promise<T> {
  const { query, headers: inputHeaders, ...init } = options;
  const headers = new Headers(inputHeaders);

  headers.set("Accept", "application/json");

  if (init.body !== undefined && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(backendUrl(path, query), {
    ...init,
    headers,
    cache: "no-store",
  });

  const payload = await readJson(response);

  if (!response.ok) {
    throw new StorefrontApiError(extractErrorMessage(payload), response.status, payload);
  }

  return payload as T;
}

export function unwrapResource<T>(payload: ApiResource<T> | T): T {
  if (isRecord(payload) && "data" in payload) {
    return payload.data as T;
  }

  return payload as T;
}

export function unwrapCollection<T>(payload: ApiCollection<T> | T[] | null | undefined): T[] {
  if (payload === null || payload === undefined) {
    return [];
  }

  if (Array.isArray(payload)) {
    return payload;
  }

  if (isRecord(payload) && Array.isArray(payload.data)) {
    return payload.data;
  }

  return [];
}

export async function resolveStore(host: string): Promise<Store> {
  return unwrapResource(
    await storefrontFetch<ApiResource<Store>>("/api/storefront/resolve", {
      query: { host },
    }),
  );
}

export async function getHome(store: string): Promise<HomePayload> {
  const payload = await storefrontFetch<{
    store: Store | ApiResource<Store>;
    categories: Category[] | ApiCollection<Category>;
    featured_products: Product[] | ApiCollection<Product>;
  }>(`/api/storefront/${encodeURIComponent(store)}/home`);

  return {
    store: unwrapResource(payload.store),
    categories: unwrapCollection(payload.categories),
    featured_products: unwrapCollection(payload.featured_products),
  };
}

export async function getProducts(
  store: string,
  query: { category?: string; q?: string; per_page?: number } = {},
): Promise<Product[]> {
  const payload = await storefrontFetch<ApiCollection<Product>>(
    `/api/storefront/${encodeURIComponent(store)}/products`,
    { query },
  );

  return unwrapCollection(payload);
}

export async function getProduct(store: string, slug: string): Promise<Product> {
  return unwrapResource(
    await storefrontFetch<ApiResource<Product>>(
      `/api/storefront/${encodeURIComponent(store)}/products/${encodeURIComponent(slug)}`,
    ),
  );
}

export async function getCategories(store: string): Promise<Category[]> {
  return unwrapCollection(
    await storefrontFetch<ApiCollection<Category>>(`/api/storefront/${encodeURIComponent(store)}/categories`),
  );
}

export async function getCategory(store: string, slug: string): Promise<Category> {
  return unwrapResource(
    await storefrontFetch<ApiResource<Category>>(
      `/api/storefront/${encodeURIComponent(store)}/categories/${encodeURIComponent(slug)}`,
    ),
  );
}

export async function searchProducts(store: string, q: string): Promise<Product[]> {
  return unwrapCollection(
    await storefrontFetch<ApiCollection<Product>>(`/api/storefront/${encodeURIComponent(store)}/search`, {
      query: { q },
    }),
  );
}

export async function getWilayas(): Promise<Wilaya[]> {
  return unwrapCollection(await storefrontFetch<ApiCollection<Wilaya>>("/api/storefront/geography/wilayas"));
}

export async function getCommunes(wilayaId: number): Promise<Commune[]> {
  return unwrapCollection(
    await storefrontFetch<ApiCollection<Commune>>("/api/storefront/geography/communes", {
      query: { wilaya_id: wilayaId },
    }),
  );
}

export async function submitCheckout(
  store: string,
  payload: CheckoutPayload,
  options: { idempotencyKey?: string } = {},
): Promise<Order> {
  return unwrapResource(
    await storefrontFetch<ApiResource<Order>>(`/api/storefront/${encodeURIComponent(store)}/checkout`, {
      method: "POST",
      headers: options.idempotencyKey ? { "Idempotency-Key": options.idempotencyKey } : undefined,
      body: JSON.stringify(payload),
    }),
  );
}

export async function trackOrder(store: string, orderNumber: string, phone: string): Promise<Order> {
  return unwrapResource(
    await storefrontFetch<ApiResource<Order>>(`/api/storefront/${encodeURIComponent(store)}/track-order`, {
      query: { order_number: orderNumber, phone },
    }),
  );
}

export function productImages(product: Product): ProductImage[] {
  return unwrapCollection(product.images);
}

export function assetUrl(path: string | null | undefined): string | null {
  if (!path) {
    return null;
  }

  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const cleanBase = ASSET_BASE_URL.replace(/\/+$/, "");
  const cleanPath = path.replace(/^\/+/, "");

  if (cleanPath.startsWith("storage/")) {
    return `${cleanBase}/${cleanPath}`;
  }

  return `${cleanBase}/storage/${cleanPath}`;
}

async function readJson(response: Response): Promise<unknown> {
  const text = await response.text();

  if (text === "") {
    return null;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return { message: text };
  }
}

function extractErrorMessage(payload: unknown): string {
  if (isRecord(payload)) {
    if (typeof payload.message === "string") {
      return payload.message;
    }

    if (isRecord(payload.errors)) {
      const firstError = Object.values(payload.errors).flat().find((value) => typeof value === "string");

      if (typeof firstError === "string") {
        return firstError;
      }
    }
  }

  return "تعذر تنفيذ الطلب. حاول مرة أخرى.";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
