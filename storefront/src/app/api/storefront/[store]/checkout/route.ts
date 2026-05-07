import { NextRequest, NextResponse } from "next/server";
import { StorefrontApiError, submitCheckout } from "@/lib/api";
import type { CheckoutPayload } from "@/lib/types";

export async function POST(request: NextRequest, context: { params: Promise<{ store: string }> }) {
  const { store } = await context.params;

  try {
    const payload = (await request.json()) as CheckoutPayload;
    const idempotencyKey = request.headers.get("Idempotency-Key") ?? undefined;
    const order = await submitCheckout(store, payload, { idempotencyKey });

    return NextResponse.json({ data: order }, { status: 201 });
  } catch (error) {
    if (error instanceof StorefrontApiError) {
      return NextResponse.json(error.payload, { status: error.status });
    }

    return NextResponse.json({ message: "تعذر إرسال الطلب" }, { status: 500 });
  }
}
