import "server-only";

import { headers } from "next/headers";
import { getHome, resolveStore } from "@/lib/api";
import type { Store } from "@/lib/types";

export type ActiveStoreContext = {
  identifier: string;
  store: Store;
};

export async function getActiveStoreContext(): Promise<ActiveStoreContext | null> {
  const host = await requestHost();
  const defaultIdentifier = process.env.NEXT_PUBLIC_DEFAULT_STORE ?? process.env.DEFAULT_STORE_IDENTIFIER;

  if (host) {
    const context = await tryResolveHost(host);

    if (context !== null) {
      return context;
    }
  }

  if (defaultIdentifier) {
    const context = await tryResolveIdentifier(defaultIdentifier);

    if (context !== null) {
      return context;
    }
  }

  return null;
}

function storeIdentifier(store: Store, fallback: string) {
  return store.slug || store.subdomain || store.domain || store.id || fallback;
}

async function tryResolveHost(host: string): Promise<ActiveStoreContext | null> {
  try {
    const store = await resolveStore(host);

    return {
      identifier: storeIdentifier(store, host),
      store,
    };
  } catch {
    return null;
  }
}

async function tryResolveIdentifier(identifier: string): Promise<ActiveStoreContext | null> {
  try {
    const home = await getHome(identifier);

    return {
      identifier: storeIdentifier(home.store, identifier),
      store: home.store,
    };
  } catch {
    return null;
  }
}

async function requestHost() {
  const requestHeaders = await headers();
  const forwardedHost = requestHeaders.get("x-forwarded-host");
  const host = forwardedHost ?? requestHeaders.get("host");

  return host?.split(",")[0]?.trim() ?? null;
}
