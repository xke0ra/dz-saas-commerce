import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight } from "lucide-react";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { getHome } from "@/lib/api";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { buildStorefrontMetadata } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";
import { storeSetting } from "@/lib/theme";
import type { LegalPageKey } from "@/lib/types";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ page: string }>;
}): Promise<Metadata> {
  const context = await getActiveStoreContext();

  if (context === null) {
    return {};
  }

  const { page } = await params;

  if (!isLegalPage(page)) {
    return {};
  }

  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);

  return buildStorefrontMetadata({
    store: context.store,
    path: `/legal/${page}`,
    title: `${copy.legal.labels[page]} | ${context.store.name}`,
    description: copy.legal.labels[page],
  });
}

export default async function LegalPage({
  params,
}: {
  params: Promise<{ page: string }>;
}) {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const { page } = await params;

  if (!isLegalPage(page)) {
    notFound();
  }

  const home = await getHome(context.identifier).catch(() => null);

  if (home === null) {
    return <StoreUnavailable />;
  }

  const setting = storeSetting(home.store);
  const locale = storeLocale(home.store);
  const copy = getStorefrontCopy(locale);
  const content = setting?.legal_content?.[page];

  if (!content) {
    return <StoreUnavailable locale={locale} title={copy.legal.unavailableTitle} detail={copy.legal.unavailableDetail} />;
  }

  return (
    <StoreShell store={home.store}>
      <Link href="/" className="mb-5 inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline">
        <ArrowRight size={16} aria-hidden="true" />
        {copy.common.backToStore}
      </Link>
      <article className="rounded-md border border-border bg-white p-6 shadow-sm md:p-8">
        <h1 className="text-3xl font-extrabold">{copy.legal.labels[page]}</h1>
        <div className="mt-6 whitespace-pre-line leading-8 text-muted-foreground">{content}</div>
      </article>
    </StoreShell>
  );
}

function isLegalPage(page: string): page is LegalPageKey {
  return page === "terms" || page === "privacy" || page === "returns" || page === "shipping";
}
