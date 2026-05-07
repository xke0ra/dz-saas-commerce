import Link from "next/link";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { getStorefrontCopy } from "@/lib/i18n";

export default function NotFound() {
  const copy = getStorefrontCopy("ar");

  return (
    <StoreUnavailable
      title={copy.common.pageNotFound}
      detail={
        <>
          <span>{copy.common.pageNotFoundDetail}</span>{" "}
          <Link className="font-bold text-primary hover:underline" href="/">
            {copy.common.backToStore}
          </Link>
        </>
      }
    />
  );
}
