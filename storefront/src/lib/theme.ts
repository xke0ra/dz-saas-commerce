import type { CSSProperties } from "react";
import { unwrapResource } from "@/lib/api";
import type { Store, StoreSetting, ThemeSetting } from "@/lib/types";

type StoreThemeStyle = CSSProperties & Record<"--primary" | "--accent" | "--background" | "--foreground", string>;

export function storeSetting(store: Store): StoreSetting | null {
  return store.store_setting ? unwrapResource(store.store_setting) : null;
}

export function themeSetting(store: Store): ThemeSetting | null {
  return store.theme_setting ? unwrapResource(store.theme_setting) : null;
}

export function storeThemeStyle(store: Store): StoreThemeStyle | undefined {
  const theme = themeSetting(store);

  if (!theme) {
    return undefined;
  }

  return {
    "--primary": hexToRgb(theme.primary_color, "16 112 98"),
    "--accent": hexToRgb(theme.accent_color, "181 72 54"),
    "--background": hexToRgb(theme.background_color, "247 249 250"),
    "--foreground": hexToRgb(theme.foreground_color, "22 28 36"),
  };
}

export function storeSeo(store: Store) {
  const setting = storeSetting(store);

  return {
    title: setting?.seo_title || store.name,
    description: setting?.seo_description || `تسوق من ${store.name} داخل الجزائر.`,
  };
}

function hexToRgb(hex: string | null | undefined, fallback: string) {
  if (!hex || !/^#[0-9A-Fa-f]{6}$/.test(hex)) {
    return fallback;
  }

  const value = hex.slice(1);
  const red = Number.parseInt(value.slice(0, 2), 16);
  const green = Number.parseInt(value.slice(2, 4), 16);
  const blue = Number.parseInt(value.slice(4, 6), 16);

  return `${red} ${green} ${blue}`;
}
