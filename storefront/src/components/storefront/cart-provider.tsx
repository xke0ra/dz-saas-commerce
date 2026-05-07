"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";

export type CartProductInput = {
  id: string;
  name: string;
  slug: string;
  sku: string | null;
  price_minor: number;
  currency: string;
  image_url: string | null;
};

export type CartItem = CartProductInput & {
  quantity: number;
};

type CartContextValue = {
  items: CartItem[];
  totalQuantity: number;
  addItem: (product: CartProductInput, quantity?: number) => void;
  updateQuantity: (productId: string, quantity: number) => void;
  removeItem: (productId: string) => void;
  clearCart: () => void;
};

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({
  storeId,
  children,
}: {
  storeId: string;
  children: ReactNode;
}) {
  const storageKey = `dz-saas-commerce:cart:${storeId}`;
  const [items, setItems] = useState<CartItem[]>([]);
  const [isLoaded, setIsLoaded] = useState(false);
  const itemsRef = useRef<CartItem[]>([]);

  useEffect(() => {
    setIsLoaded(false);

    try {
      const raw = window.localStorage.getItem(storageKey);
      const parsed = raw ? (JSON.parse(raw) as CartItem[]) : [];
      const sanitizedItems = sanitizeItems(parsed);

      itemsRef.current = sanitizedItems;
      setItems(sanitizedItems);
    } catch {
      itemsRef.current = [];
      setItems([]);
    } finally {
      setIsLoaded(true);
    }
  }, [storageKey]);

  useEffect(() => {
    if (!isLoaded) {
      return;
    }

    window.localStorage.setItem(storageKey, JSON.stringify(items));
  }, [isLoaded, items, storageKey]);

  const commitItems = useCallback(
    (nextItems: CartItem[]): void => {
      itemsRef.current = nextItems;
      setItems(nextItems);
      persistItems(storageKey, nextItems, isLoaded);
    },
    [isLoaded, storageKey],
  );

  const addItem = useCallback(
    (product: CartProductInput, quantity = 1): void => {
      const currentItems = itemsRef.current;
      const safeQuantity = clampQuantity(quantity);
      const existing = currentItems.find((item) => item.id === product.id);
      const nextItems = existing
        ? currentItems.map((item) =>
            item.id === product.id
              ? {
                  ...item,
                  ...product,
                  quantity: clampQuantity(item.quantity + safeQuantity),
                }
              : item,
          )
        : [
            ...currentItems,
            {
              ...product,
              quantity: safeQuantity,
            },
          ];

      commitItems(nextItems);
    },
    [commitItems],
  );

  const updateQuantity = useCallback(
    (productId: string, quantity: number): void => {
      const safeQuantity = clampQuantity(quantity);
      const nextItems = itemsRef.current.map((item) =>
        item.id === productId ? { ...item, quantity: safeQuantity } : item,
      );

      commitItems(nextItems);
    },
    [commitItems],
  );

  const removeItem = useCallback(
    (productId: string): void => {
      commitItems(itemsRef.current.filter((item) => item.id !== productId));
    },
    [commitItems],
  );

  const clearCart = useCallback((): void => {
    commitItems([]);
  }, [commitItems]);

  const value = useMemo<CartContextValue>(
    () => ({
      items,
      totalQuantity: items.reduce((total, item) => total + item.quantity, 0),
      addItem,
      updateQuantity,
      removeItem,
      clearCart,
    }),
    [addItem, clearCart, items, removeItem, updateQuantity],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);

  if (context === null) {
    throw new Error("useCart must be used inside CartProvider.");
  }

  return context;
}

function sanitizeItems(items: CartItem[]): CartItem[] {
  if (!Array.isArray(items)) {
    return [];
  }

  return items
    .filter((item) => typeof item?.id === "string" && typeof item.name === "string")
    .map((item) => ({
      id: item.id,
      name: item.name,
      slug: typeof item.slug === "string" ? item.slug : item.id,
      sku: typeof item.sku === "string" ? item.sku : null,
      price_minor: Number.isFinite(item.price_minor) ? item.price_minor : 0,
      currency: typeof item.currency === "string" ? item.currency : "DZD",
      image_url: typeof item.image_url === "string" ? item.image_url : null,
      quantity: clampQuantity(item.quantity),
    }));
}

function persistItems(storageKey: string, items: CartItem[], isLoaded: boolean): void {
  if (!isLoaded) {
    return;
  }

  window.localStorage.setItem(storageKey, JSON.stringify(items));
}

function clampQuantity(quantity: number): number {
  if (!Number.isFinite(quantity)) {
    return 1;
  }

  return Math.max(1, Math.min(99, Math.trunc(quantity)));
}
