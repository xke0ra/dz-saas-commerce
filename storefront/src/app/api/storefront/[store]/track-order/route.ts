import { NextRequest, NextResponse } from "next/server";
import { StorefrontApiError, trackOrder } from "@/lib/api";

export async function GET(request: NextRequest, context: { params: Promise<{ store: string }> }) {
  const { store } = await context.params;
  const orderNumber = request.nextUrl.searchParams.get("order_number");
  const phone = request.nextUrl.searchParams.get("phone");

  if (!orderNumber || !phone) {
    return NextResponse.json({ message: "رقم الطلب والهاتف مطلوبان" }, { status: 422 });
  }

  try {
    const order = await trackOrder(store, orderNumber, phone);

    return NextResponse.json({ data: order });
  } catch (error) {
    if (error instanceof StorefrontApiError) {
      return NextResponse.json(error.payload, { status: error.status });
    }

    return NextResponse.json({ message: "لم يتم العثور على الطلب" }, { status: 500 });
  }
}
