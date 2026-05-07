import Link from "next/link";
import { PackageSearch, Search, ShoppingBag } from "lucide-react";
import { CartNavLink } from "@/components/storefront/cart-nav-link";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { assetUrl } from "@/lib/api";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { storeSetting, themeSetting } from "@/lib/theme";
import type { Store } from "@/lib/types";

export function StoreHeader({ store }: { store: Store }) {
  const theme = themeSetting(store);
  const setting = storeSetting(store);
  const locale = storeLocale(store);
  const copy = getStorefrontCopy(locale);
  const logoUrl = assetUrl(theme?.logo_path);

  return (
    <header className="sticky top-0 z-20 border-b border-border bg-white/92 backdrop-blur">
      {setting?.announcement_text ? (
        <div className="bg-foreground px-4 py-2 text-center text-sm font-semibold text-white">
          {setting.announcement_text}
        </div>
      ) : null}
      <div className="mx-auto grid max-w-6xl gap-3 px-4 py-3 md:grid-cols-[auto_minmax(260px,1fr)_auto] md:items-center">
        <Link href="/" className="flex min-w-0 items-center gap-3">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
            {logoUrl ? <img src={logoUrl} alt="" className="h-full w-full rounded-md object-cover" /> : <ShoppingBag size={20} aria-hidden="true" />}
          </span>
          <span className="min-w-0">
            <span className="block truncate text-base font-bold">{store.name}</span>
            <span className="block text-xs text-muted-foreground">{store.currency}</span>
          </span>
        </Link>

        <form action="/search" className="flex w-full gap-2 md:max-w-md md:justify-self-center">
          <Input name="q" type="search" placeholder={copy.common.searchPlaceholder} className="bg-background" />
          <Button type="submit" size="icon" aria-label={copy.common.searchAria} className="shrink-0">
            <Search size={18} aria-hidden="true" />
          </Button>
        </form>

        <nav className="-mx-1 flex w-full items-center gap-1 overflow-x-auto px-1 pb-1 text-sm font-medium md:mx-0 md:w-auto md:justify-self-end md:overflow-visible md:px-0 md:pb-0">
          <Link className="shrink-0 whitespace-nowrap rounded-md px-3 py-2 hover:bg-muted/70" href="/products">
            {copy.common.allProducts}
          </Link>
          <Link className="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 hover:bg-muted/70" href="/track-order">
            <PackageSearch size={17} aria-hidden="true" />
            {copy.common.trackOrder}
          </Link>
          <CartNavLink locale={locale} />
        </nav>
      </div>
    </header>
  );
}
