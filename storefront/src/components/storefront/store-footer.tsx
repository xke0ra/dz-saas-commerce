import Link from "next/link";
import { Mail, MapPin, Phone } from "lucide-react";
import type { LegalPageKey, Store } from "@/lib/types";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { storeSetting } from "@/lib/theme";

export function StoreFooter({ store }: { store: Store }) {
  const setting = storeSetting(store);
  const copy = getStorefrontCopy(storeLocale(store));
  const legalPages = Object.entries(setting?.legal_pages ?? {})
    .filter(([, enabled]) => enabled)
    .map(([page]) => page as LegalPageKey);

  return (
    <footer className="mt-12 border-t border-border bg-white">
      <div className="mx-auto grid max-w-6xl gap-6 px-4 py-8 md:grid-cols-[1.2fr_.8fr]">
        <div>
          <p className="text-lg font-extrabold">{store.name}</p>
          {setting?.seller_name ? <p className="mt-2 text-sm text-muted-foreground">{setting.seller_name}</p> : null}
          <div className="mt-4 grid gap-2 text-sm text-muted-foreground">
            {setting?.public_phone ? (
              <p className="flex items-center gap-2">
                <Phone size={16} aria-hidden="true" />
                <span dir="ltr">{setting.public_phone}</span>
              </p>
            ) : null}
            {setting?.public_email ? (
              <p className="flex items-center gap-2">
                <Mail size={16} aria-hidden="true" />
                <span>{setting.public_email}</span>
              </p>
            ) : null}
            {setting?.seller_address ? (
              <p className="flex items-center gap-2">
                <MapPin size={16} aria-hidden="true" />
                <span>{setting.seller_address}</span>
              </p>
            ) : null}
          </div>
        </div>

        <div>
          <p className="font-bold">{copy.legal.infoTitle}</p>
          <div className="mt-3 flex flex-wrap gap-2 text-sm">
            {legalPages.length > 0 ? (
              legalPages.map((page) => (
                <Link key={page} className="rounded-md border border-border px-3 py-2 hover:bg-muted/70" href={`/legal/${page}`}>
                  {copy.legal.shortLabels[page]}
                </Link>
              ))
            ) : (
              <span className="text-muted-foreground">{copy.legal.noPages}</span>
            )}
          </div>
        </div>
      </div>
    </footer>
  );
}
