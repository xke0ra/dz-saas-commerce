export type Store = {
  id: string;
  name: string;
  slug: string;
  domain: string | null;
  subdomain: string | null;
  status: string;
  locale: "ar" | "fr" | string;
  currency: string;
  settings: Record<string, unknown> | null;
  store_setting?: StoreSetting | ApiResource<StoreSetting> | null;
  theme_setting?: ThemeSetting | ApiResource<ThemeSetting> | null;
};

export type StoreSetting = {
  seller_name: string | null;
  seller_address: string | null;
  commercial_registration_number: string | null;
  tax_identification_number: string | null;
  public_email: string | null;
  public_phone: string | null;
  support_phone: string | null;
  whatsapp_phone: string | null;
  seo_title: string | null;
  seo_description: string | null;
  announcement_text: string | null;
  legal_pages: Partial<Record<LegalPageKey, boolean>>;
  legal_content: Partial<Record<LegalPageKey, string | null>>;
  social_links: Record<string, string> | [];
};

export type ThemeSetting = {
  theme_name: string;
  primary_color: string;
  accent_color: string;
  background_color: string;
  foreground_color: string;
  heading_font: string | null;
  body_font: string | null;
  logo_path: string | null;
  favicon_path: string | null;
  hero_image_path: string | null;
  hero_title: string | null;
  hero_subtitle: string | null;
  product_card_style: string;
  layout_settings: Record<string, unknown> | [];
};

export type LegalPageKey = "terms" | "privacy" | "returns" | "shipping";

export type Category = {
  id: string;
  parent_id: string | null;
  name: string;
  slug: string;
  description: string | null;
  status: string;
  sort_order: number;
};

export type ProductImage = {
  id: string;
  path: string;
  alt: string | null;
  sort_order: number;
  is_primary: boolean;
};

export type ProductInventory = {
  track_quantity: boolean;
  available_quantity: number;
  allow_backorders: boolean;
};

export type ProductOptionValue = {
  id: string;
  value: string;
  position: number;
};

export type ProductOption = {
  id: string;
  name: string;
  position: number;
  values: ProductOptionValue[];
};

export type ProductVariant = {
  id: string;
  sku: string | null;
  title: string | null;
  option_signature: string;
  price_minor: number | null;
  compare_at_price_minor: number | null;
  effective_price_minor: number;
  status: string;
  sort_order: number;
  available_quantity: number | null;
  is_available: boolean;
  selected_options: Record<string, string>;
};

export type Product = {
  id: string;
  category_id: string | null;
  name: string;
  slug: string;
  sku: string | null;
  short_description: string | null;
  description: string | null;
  status: string;
  type?: "simple" | "variable" | string;
  price_minor: number;
  compare_at_price_minor: number | null;
  currency: string;
  requires_shipping: boolean;
  is_featured: boolean;
  published_at: string | null;
  category?: Category | ApiResource<Category> | null;
  images?: ProductImage[] | ApiCollection<ProductImage>;
  inventory?: ProductInventory | null;
  variants?: ProductVariant[];
  options?: ProductOption[];
};

export type Wilaya = {
  id: number;
  name_ar: string;
  name_fr: string;
};

export type Commune = {
  id: number;
  wilaya_id: number;
  name_ar: string;
  name_fr: string;
  postal_code: string | null;
};

export type OrderItem = {
  product_id: string;
  product_name: string;
  product_sku: string | null;
  quantity: number;
  unit_price_minor: number;
  total_minor: number;
};

export type Order = {
  id: string;
  order_number: string;
  status: string;
  payment_status: string;
  delivery_type: string;
  subtotal_minor: number;
  shipping_fee_minor: number;
  discount_minor: number;
  total_minor: number;
  currency: string;
  customer?: {
    full_name: string;
    phone: string;
  };
  coupon?: {
    id: string;
    code: string;
    name: string | null;
  } | null;
  items?: OrderItem[];
  created_at: string | null;
};

export type CheckoutItemPayload = {
  product_id: string;
  product_variant_id?: string;
  quantity: number;
};

export type ApiResource<T> = {
  data: T;
};

export type ApiCollection<T> = {
  data: T[];
  links?: Record<string, unknown>;
  meta?: Record<string, unknown>;
};

export type HomePayload = {
  store: Store;
  categories: Category[];
  featured_products: Product[];
};

export type CheckoutPayload = {
  full_name: string;
  phone: string;
  wilaya_id: number;
  commune_id: number;
  address: string;
  delivery_type: "home" | "desk";
  note?: string | null;
  coupon_code?: string | null;
  product_id?: string;
  product_variant_id?: string;
  quantity?: number;
  items?: CheckoutItemPayload[];
};
