"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2, Loader2, Send } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { currencyLocale, formatMoney, orderStatusLabel, paymentStatusLabel } from "@/lib/format";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";
import type { Commune, Order, Wilaya } from "@/lib/types";

type QuickOrderValues = {
  full_name: string;
  phone: string;
  wilaya_id: number;
  commune_id: number;
  address: string;
  delivery_type: "home" | "desk";
  quantity?: number;
  coupon_code?: string;
  note?: string;
};

type CheckoutLineItem = {
  product_id: string;
  product_variant_id?: string;
  quantity: number;
};

function makeQuickOrderSchema(copy: ReturnType<typeof getStorefrontCopy>, requiresQuantity: boolean) {
  const quantityRule = z.coerce.number().int().min(1).max(99);

  return z.object({
    full_name: z.string().min(2, copy.quickOrder.validation.fullName).max(255),
    phone: z.string().regex(/^(\+213|0)(5|6|7)[0-9]{8}$/, copy.quickOrder.validation.phone),
    wilaya_id: z.coerce.number().int().positive(copy.quickOrder.validation.wilaya),
    commune_id: z.coerce.number().int().positive(copy.quickOrder.validation.commune),
    address: z.string().min(5, copy.quickOrder.validation.address).max(1000),
    delivery_type: z.enum(["home", "desk"]),
    quantity: requiresQuantity ? quantityRule : quantityRule.optional(),
    coupon_code: z.string().trim().max(64, copy.quickOrder.validation.couponLong).optional(),
    note: z.string().max(1000).optional(),
  });
}

export function QuickOrderForm({
  storeIdentifier,
  productId,
  productName,
  locale = "ar",
  productVariantId,
  checkoutItems,
  onOrderCreated,
  submitLabel,
  checkoutDisabledReason,
}: {
  storeIdentifier: string;
  productId?: string;
  productVariantId?: string | null;
  productName: string;
  locale?: StoreLocale | string;
  checkoutItems?: CheckoutLineItem[];
  onOrderCreated?: (order: Order) => void;
  submitLabel?: string;
  checkoutDisabledReason?: string | null;
}) {
  const copy = getStorefrontCopy(locale);
  const isCartCheckout = checkoutItems !== undefined;
  const [wilayas, setWilayas] = useState<Wilaya[]>([]);
  const [communes, setCommunes] = useState<Commune[]>([]);
  const [createdOrder, setCreatedOrder] = useState<Order | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [loadingWilayas, setLoadingWilayas] = useState(true);

  const {
    register,
    watch,
    handleSubmit,
    resetField,
    formState: { errors, isSubmitting },
  } = useForm<QuickOrderValues>({
    resolver: zodResolver(makeQuickOrderSchema(copy, !isCartCheckout)),
    defaultValues: {
      delivery_type: "home",
      quantity: isCartCheckout ? undefined : 1,
    },
  });

  const selectedWilaya = watch("wilaya_id");

  useEffect(() => {
    let ignore = false;

    async function loadWilayas() {
      try {
        const response = await fetch("/api/storefront/geography/wilayas", { cache: "no-store" });
        const payload = (await response.json()) as Wilaya[] | { data?: Wilaya[] };

        if (!response.ok) {
          throw new Error(copy.quickOrder.loadingWilayasError);
        }

        if (!ignore) {
          setWilayas(Array.isArray(payload) ? payload : payload.data ?? []);
        }
      } catch (error) {
        if (!ignore) {
          setFormError(error instanceof Error ? error.message : copy.quickOrder.loadingWilayasError);
        }
      } finally {
        if (!ignore) {
          setLoadingWilayas(false);
        }
      }
    }

    loadWilayas();

    return () => {
      ignore = true;
    };
  }, []);

  useEffect(() => {
    let ignore = false;

    async function loadCommunes() {
      if (!selectedWilaya) {
        setCommunes([]);
        return;
      }

      resetField("commune_id");

      try {
        const response = await fetch(`/api/storefront/geography/communes?wilaya_id=${selectedWilaya}`, {
          cache: "no-store",
        });
        const payload = (await response.json()) as Commune[] | { data?: Commune[] };

        if (!response.ok) {
          throw new Error(copy.quickOrder.loadingCommunesError);
        }

        if (!ignore) {
          setCommunes(Array.isArray(payload) ? payload : payload.data ?? []);
        }
      } catch (error) {
        if (!ignore) {
          setFormError(error instanceof Error ? error.message : copy.quickOrder.loadingCommunesError);
        }
      }
    }

    loadCommunes();

    return () => {
      ignore = true;
    };
  }, [resetField, selectedWilaya]);

  const selectedWilayaName = useMemo(
    () => wilayas.find((wilaya) => wilaya.id === Number(selectedWilaya))?.name_ar,
    [selectedWilaya, wilayas],
  );

  async function onSubmit(values: QuickOrderValues) {
    setFormError(null);
    setCreatedOrder(null);

    const items = (checkoutItems ?? []).filter((item) => item.quantity > 0);

    if (isCartCheckout && items.length === 0) {
      setFormError(copy.quickOrder.genericSubmitError);
      return;
    }

    if (!isCartCheckout && !productId) {
      setFormError(copy.quickOrder.genericSubmitError);
      return;
    }

    if (!isCartCheckout && checkoutDisabledReason) {
      setFormError(checkoutDisabledReason);
      return;
    }

    const idempotencyKey = createIdempotencyKey();

    const response = await fetch(`/api/storefront/${encodeURIComponent(storeIdentifier)}/checkout`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "Idempotency-Key": idempotencyKey,
      },
      body: JSON.stringify({
        ...values,
        note: values.note || null,
        coupon_code: values.coupon_code || null,
        ...(isCartCheckout
          ? { items }
          : {
              product_id: productId,
              ...(productVariantId ? { product_variant_id: productVariantId } : {}),
              quantity: values.quantity ?? 1,
            }),
      }),
    });

    const payload = (await response.json()) as { data?: Order; message?: string; errors?: Record<string, string[]> };

    if (!response.ok || !payload.data) {
      const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
      setFormError(firstError ?? payload.message ?? copy.quickOrder.genericSubmitError);
      return;
    }

    setCreatedOrder(payload.data);
    onOrderCreated?.(payload.data);
  }

  return (
    <section className="rounded-md border border-border bg-white p-4 shadow-sm md:p-5">
      <div className="mb-5">
        <p className="text-sm font-semibold text-primary">{copy.quickOrder.kicker}</p>
        <h2 className="mt-1 text-xl font-extrabold">{productName}</h2>
      </div>

      {createdOrder ? (
        <div className="rounded-md border border-primary/30 bg-primary/5 p-4">
          <div className="flex items-center gap-2 font-bold text-primary">
            <CheckCircle2 size={20} aria-hidden="true" />
            {copy.quickOrder.successTitle}
          </div>
          <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-muted-foreground">{copy.quickOrder.result.orderNumber}</dt>
              <dd className="font-bold">{createdOrder.order_number}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{copy.quickOrder.result.status}</dt>
              <dd>{orderStatusLabel(createdOrder.status, locale)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{copy.quickOrder.result.payment}</dt>
              <dd>{paymentStatusLabel(createdOrder.payment_status, locale)}</dd>
            </div>
            {createdOrder.coupon ? (
              <div>
                <dt className="text-muted-foreground">{copy.quickOrder.result.coupon}</dt>
                <dd dir="ltr" className="font-bold">
                  {createdOrder.coupon.code}
                </dd>
              </div>
            ) : null}
            {createdOrder.discount_minor > 0 ? (
              <div>
                <dt className="text-muted-foreground">{copy.quickOrder.result.discount}</dt>
                <dd className="font-bold text-primary">
                  {formatMoney(createdOrder.discount_minor, createdOrder.currency, currencyLocale(locale))}
                </dd>
              </div>
            ) : null}
            <div>
              <dt className="text-muted-foreground">{copy.quickOrder.result.total}</dt>
              <dd className="font-bold">{formatMoney(createdOrder.total_minor, createdOrder.currency, currencyLocale(locale))}</dd>
            </div>
          </dl>
        </div>
      ) : null}

      <form onSubmit={handleSubmit(onSubmit)} className="mt-5 grid gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={copy.quickOrder.fields.fullName} error={errors.full_name?.message}>
            <Input autoComplete="name" {...register("full_name")} />
          </Field>
          <Field label={copy.quickOrder.fields.phone} error={errors.phone?.message}>
            <Input dir="ltr" inputMode="tel" placeholder={copy.quickOrder.placeholders.phone} autoComplete="tel" {...register("phone")} />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={copy.quickOrder.fields.wilaya} error={errors.wilaya_id?.message}>
            <Select disabled={loadingWilayas} {...register("wilaya_id")}>
              <option value="">{copy.quickOrder.placeholders.chooseWilaya}</option>
              {wilayas.map((wilaya) => (
                <option key={wilaya.id} value={wilaya.id}>
                  {wilaya.id} - {wilaya.name_ar} / {wilaya.name_fr}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={copy.quickOrder.fields.commune} error={errors.commune_id?.message}>
            <Select disabled={!selectedWilaya || communes.length === 0} {...register("commune_id")}>
              <option value="">
                {selectedWilayaName
                  ? copy.quickOrder.placeholders.communesFor(selectedWilayaName)
                  : copy.quickOrder.placeholders.chooseWilayaFirst}
              </option>
              {communes.map((commune) => (
                <option key={commune.id} value={commune.id}>
                  {commune.name_ar} / {commune.name_fr}
                </option>
              ))}
            </Select>
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={copy.quickOrder.fields.deliveryType} error={errors.delivery_type?.message}>
            <Select {...register("delivery_type")}>
              <option value="home">{copy.quickOrder.deliveryTypes.home}</option>
              <option value="desk">{copy.quickOrder.deliveryTypes.desk}</option>
            </Select>
          </Field>
          {!isCartCheckout ? (
            <Field label={copy.quickOrder.fields.quantity} error={errors.quantity?.message}>
              <Input min={1} max={99} inputMode="numeric" type="number" {...register("quantity")} />
            </Field>
          ) : null}
        </div>

        <Field label={copy.quickOrder.fields.couponCode} error={errors.coupon_code?.message}>
          <Input dir="ltr" autoComplete="off" placeholder={copy.quickOrder.placeholders.couponCode} {...register("coupon_code")} />
        </Field>

        <Field label={copy.quickOrder.fields.address} error={errors.address?.message}>
          <Textarea rows={3} {...register("address")} />
        </Field>

        <Field label={copy.quickOrder.fields.note} error={errors.note?.message}>
          <Textarea rows={2} {...register("note")} />
        </Field>

        {checkoutDisabledReason ? (
          <p className="rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">{checkoutDisabledReason}</p>
        ) : null}

        {formError ? <p className="rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">{formError}</p> : null}

        <Button type="submit" size="lg" disabled={isSubmitting || Boolean(checkoutDisabledReason)} className="w-full">
          {isSubmitting ? <Loader2 className="animate-spin" size={18} aria-hidden="true" /> : <Send size={18} aria-hidden="true" />}
          {submitLabel ?? copy.quickOrder.submit}
        </Button>
      </form>
    </section>
  );
}

function createIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <label className="grid gap-2 text-sm font-semibold">
      <span>{label}</span>
      {children}
      {error ? <span className="text-xs font-medium text-danger">{error}</span> : null}
    </label>
  );
}
