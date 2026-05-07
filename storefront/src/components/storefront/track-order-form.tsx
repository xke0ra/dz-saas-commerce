"use client";

import { useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2, PackageSearch } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { currencyLocale, formatMoney, orderStatusLabel, paymentStatusLabel } from "@/lib/format";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";
import type { Order } from "@/lib/types";

type TrackOrderValues = {
  order_number: string;
  phone: string;
};

function makeTrackOrderSchema(copy: ReturnType<typeof getStorefrontCopy>) {
  return z.object({
    order_number: z.string().min(2, copy.trackOrder.validation.orderNumber),
    phone: z.string().min(9, copy.trackOrder.validation.phone),
  });
}

export function TrackOrderForm({ storeIdentifier, locale = "ar" }: { storeIdentifier: string; locale?: StoreLocale }) {
  const copy = getStorefrontCopy(locale);
  const [order, setOrder] = useState<Order | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<TrackOrderValues>({
    resolver: zodResolver(makeTrackOrderSchema(copy)),
  });

  async function onSubmit(values: TrackOrderValues) {
    setFormError(null);
    setOrder(null);

    const params = new URLSearchParams(values);
    const response = await fetch(`/api/storefront/${encodeURIComponent(storeIdentifier)}/track-order?${params.toString()}`, {
      cache: "no-store",
    });
    const payload = (await response.json()) as { data?: Order; message?: string; errors?: Record<string, string[]> };

    if (!response.ok || !payload.data) {
      const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
      setFormError(firstError ?? payload.message ?? copy.trackOrder.notFound);
      return;
    }

    setOrder(payload.data);
  }

  return (
    <section className="mx-auto max-w-2xl rounded-md border border-border bg-white p-5 shadow-sm">
      <div className="mb-5 flex items-center gap-3">
        <span className="flex h-11 w-11 items-center justify-center rounded-md bg-primary/10 text-primary">
          <PackageSearch size={22} aria-hidden="true" />
        </span>
        <div>
          <h1 className="text-2xl font-extrabold">{copy.trackOrder.title}</h1>
          <p className="text-sm text-muted-foreground">{copy.trackOrder.detail}</p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="grid gap-4 sm:grid-cols-[1fr_1fr_auto]">
        <label className="grid gap-2 text-sm font-semibold">
          {copy.trackOrder.fields.orderNumber}
          <Input dir="ltr" {...register("order_number")} />
          {errors.order_number?.message ? (
            <span className="text-xs font-medium text-danger">{errors.order_number.message}</span>
          ) : null}
        </label>
        <label className="grid gap-2 text-sm font-semibold">
          {copy.trackOrder.fields.phone}
          <Input dir="ltr" inputMode="tel" {...register("phone")} />
          {errors.phone?.message ? <span className="text-xs font-medium text-danger">{errors.phone.message}</span> : null}
        </label>
        <Button type="submit" className="self-end" disabled={isSubmitting}>
          {isSubmitting ? <Loader2 className="animate-spin" size={18} aria-hidden="true" /> : null}
          {copy.trackOrder.submit}
        </Button>
      </form>

      {formError ? <p className="mt-4 rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">{formError}</p> : null}

      {order ? (
        <dl className="mt-5 grid gap-4 rounded-md border border-border bg-background p-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm text-muted-foreground">{copy.trackOrder.result.orderNumber}</dt>
            <dd className="font-bold">{order.order_number}</dd>
          </div>
          <div>
            <dt className="text-sm text-muted-foreground">{copy.trackOrder.result.status}</dt>
            <dd>{orderStatusLabel(order.status, locale)}</dd>
          </div>
          <div>
            <dt className="text-sm text-muted-foreground">{copy.trackOrder.result.payment}</dt>
            <dd>{paymentStatusLabel(order.payment_status, locale)}</dd>
          </div>
          <div>
            <dt className="text-sm text-muted-foreground">{copy.trackOrder.result.total}</dt>
            <dd className="font-bold">{formatMoney(order.total_minor, order.currency, currencyLocale(locale))}</dd>
          </div>
        </dl>
      ) : null}
    </section>
  );
}
