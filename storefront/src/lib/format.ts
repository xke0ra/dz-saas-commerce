import { localeTag, normalizeStoreLocale, type StoreLocale } from "@/lib/i18n";

export function formatMoney(minor: number, currency = "DZD", locale: StoreLocale | string = "ar") {
  return new Intl.NumberFormat(locale, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(minor / 100);
}

export function currencyLocale(locale: StoreLocale | string | null | undefined) {
  return localeTag(locale);
}

const orderStatusLabels = {
  ar: {
    draft: "مسودة",
    pending: "قيد الانتظار",
    confirmed: "مؤكد",
    processing: "قيد المعالجة",
    packed: "مغلف",
    shipped: "تم الشحن",
    out_for_delivery: "قيد التوصيل",
    delivered: "تم التسليم",
    failed_delivery: "فشل التوصيل",
    returned: "مرتجع",
    cancelled: "ملغى",
    refunded: "مسترجع",
  },
  fr: {
    draft: "Brouillon",
    pending: "En attente",
    confirmed: "Confirmee",
    processing: "En preparation",
    packed: "Emballee",
    shipped: "Expediee",
    out_for_delivery: "En livraison",
    delivered: "Livree",
    failed_delivery: "Echec de livraison",
    returned: "Retournee",
    cancelled: "Annulee",
    refunded: "Remboursee",
  },
} as const;

const paymentStatusLabels = {
  ar: {
    unpaid: "غير مدفوع",
    pending: "قيد المراجعة",
    paid: "مدفوع",
    failed: "فشل الدفع",
    refunded: "مسترجع",
    partially_refunded: "مسترجع جزئيا",
  },
  fr: {
    unpaid: "Non paye",
    pending: "En verification",
    paid: "Paye",
    failed: "Paiement echoue",
    refunded: "Rembourse",
    partially_refunded: "Partiellement rembourse",
  },
} as const;

export function orderStatusLabel(status: string, locale: StoreLocale | string | null | undefined = "ar") {
  return orderStatusLabels[normalizeStoreLocale(locale)][status as keyof typeof orderStatusLabels.ar] ?? status;
}

export function paymentStatusLabel(status: string, locale: StoreLocale | string | null | undefined = "ar") {
  return paymentStatusLabels[normalizeStoreLocale(locale)][status as keyof typeof paymentStatusLabels.ar] ?? status;
}
