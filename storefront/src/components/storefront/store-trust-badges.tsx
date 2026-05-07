import { FileText, PhoneCall, ShieldCheck, Truck } from "lucide-react";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";

const icons = [ShieldCheck, Truck, PhoneCall, FileText] as const;

export function StoreTrustBadges({ locale = "ar" }: { locale?: StoreLocale | string }) {
  const copy = getStorefrontCopy(locale);

  return (
    <section className="mt-10">
      <div className="mb-4">
        <p className="text-sm font-bold text-primary">{copy.home.badge}</p>
        <h2 className="text-2xl font-extrabold">{copy.home.trustTitle}</h2>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {copy.home.trustBadges.map((badge, index) => {
          const Icon = icons[index] ?? ShieldCheck;

          return (
            <article key={badge.title} className="rounded-md border border-border bg-white p-4 shadow-sm">
              <div className="flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                <Icon size={19} aria-hidden="true" />
              </div>
              <h3 className="mt-3 font-extrabold">{badge.title}</h3>
              <p className="mt-1 text-sm leading-6 text-muted-foreground">{badge.detail}</p>
            </article>
          );
        })}
      </div>
    </section>
  );
}
