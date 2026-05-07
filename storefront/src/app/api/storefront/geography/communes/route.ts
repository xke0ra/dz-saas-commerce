import { NextRequest, NextResponse } from "next/server";
import { getCommunes, StorefrontApiError } from "@/lib/api";

export async function GET(request: NextRequest) {
  const wilayaId = Number(request.nextUrl.searchParams.get("wilaya_id"));

  if (!Number.isInteger(wilayaId) || wilayaId <= 0) {
    return NextResponse.json({ message: "wilaya_id مطلوب" }, { status: 422 });
  }

  try {
    return NextResponse.json(await getCommunes(wilayaId));
  } catch (error) {
    if (error instanceof StorefrontApiError) {
      return NextResponse.json(error.payload, { status: error.status });
    }

    return NextResponse.json({ message: "تعذر تحميل البلديات" }, { status: 500 });
  }
}
