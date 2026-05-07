import { AlertTriangle } from "lucide-react";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";

export function StoreUnavailable({
  title,
  detail,
  locale = "ar",
}: {
  title?: string;
  detail?: React.ReactNode;
  locale?: StoreLocale | string;
}) {
  const copy = getStorefrontCopy(locale);

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <section className="w-full max-w-lg rounded-md border border-border bg-white p-6 text-center shadow-soft">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-md bg-muted text-accent">
          <AlertTriangle size={24} aria-hidden="true" />
        </div>
        <h1 className="text-xl font-bold">{title ?? copy.common.unavailableTitle}</h1>
        <p className="mt-3 leading-7 text-muted-foreground">{detail ?? copy.common.unavailableDetail}</p>
      </section>
    </main>
  );
}
