import Link from "next/link";
import { FileText, Mail, MapPin, Phone } from "lucide-react";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { storeSetting } from "@/lib/theme";
import type { LegalPageKey, Store } from "@/lib/types";

export function StoreContactStrip({ store }: { store: Store }) {
  const locale = storeLocale(store);
  const copy = getStorefrontCopy(locale);
  const setting = storeSetting(store);
  const legalPages = Object.entries(setting?.legal_pages ?? {})
    .filter(([, enabled]) => enabled)
    .map(([page]) => page as LegalPageKey);

  return (
    <section className="mt-10 rounded-md border border-border bg-foreground p-5 text-white shadow-sm md:p-6">
      <div className="grid gap-5 lg:grid-cols-[.9fr_1.1fr] lg:items-start">
        <div>
          <p className="text-sm font-bold text-white/70">{store.name}</p>
          <h2 className="mt-1 text-2xl font-extrabold">{copy.home.contactTitle}</h2>
          <p className="mt-2 leading-7 text-white/72">{copy.home.contactDetail}</p>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          {setting?.public_phone ? (
            <ContactItem icon={Phone} label={copy.home.contactPhone} value={setting.public_phone} dir="ltr" />
          ) : null}
          {setting?.public_email ? <ContactItem icon={Mail} label={copy.home.contactEmail} value={setting.public_email} /> : null}
          {setting?.seller_address ? <ContactItem icon={MapPin} label={copy.home.contactAddress} value={setting.seller_address} /> : null}
          {legalPages.length > 0 ? (
            <div className="rounded-md bg-white/10 p-3">
              <div className="flex items-center gap-2 text-sm font-bold text-white/70">
                <FileText size={16} aria-hidden="true" />
                {copy.home.contactLegal}
              </div>
              <div className="mt-2 flex flex-wrap gap-2">
                {legalPages.map((page) => (
                  <Link key={page} href={`/legal/${page}`} className="rounded-md bg-white/10 px-2.5 py-1.5 text-sm font-bold hover:bg-white/16">
                    {copy.legal.shortLabels[page]}
                  </Link>
                ))}
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </section>
  );
}

function ContactItem({
  icon: Icon,
  label,
  value,
  dir,
}: {
  icon: typeof Phone;
  label: string;
  value: string;
  dir?: "ltr" | "rtl";
}) {
  return (
    <div className="rounded-md bg-white/10 p-3">
      <div className="flex items-center gap-2 text-sm font-bold text-white/70">
        <Icon size={16} aria-hidden="true" />
        {label}
      </div>
      <p dir={dir} className="mt-1 break-words font-semibold">
        {value}
      </p>
    </div>
  );
}
