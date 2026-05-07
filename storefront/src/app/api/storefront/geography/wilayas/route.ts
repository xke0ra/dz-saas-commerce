import { NextResponse } from "next/server";
import { getWilayas, StorefrontApiError } from "@/lib/api";

export async function GET() {
  try {
    return NextResponse.json(await getWilayas());
  } catch (error) {
    if (error instanceof StorefrontApiError) {
      return NextResponse.json(error.payload, { status: error.status });
    }

    return NextResponse.json({ message: "تعذر تحميل الولايات" }, { status: 500 });
  }
}
