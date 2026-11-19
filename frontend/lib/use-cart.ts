"use client";

import { useParams } from "next/navigation";
import { useSyncExternalStore } from "react";

import {
  type AddResult,
  addToLines,
  type CartLine,
  countItems,
  parseLines,
  removeFromLines,
  setLineQuantity,
} from "@/lib/cart";

/**
 * The shop's cart for Client Components, saved in the browser's localStorage (one cart
 * per shop, key "biponon-cart:{shop}").
 *
 * localStorage is an "external store": it lives outside React. useSyncExternalStore reads
 * it, re-renders every component using the cart when it changes (also when another tab
 * changes it, via the "storage" event), and uses the server snapshot (an empty cart) while
 * hydrating, so the server's HTML and the browser's first render match.
 *
 * Every component calling useCart() reads the same store, so the header icon, the picker
 * and the cart page always agree; no Context provider is needed.
 *
 * useParams() makes this hook wait for the URL, so components that use it must sit inside
 * <Suspense> (see app/[shop]/layout.tsx and the pages); otherwise the build stops with
 * "blocking-prerender-client-hook".
 */
export function useCart() {
  const { shop } = useParams<{ shop: string }>();
  const key = `biponon-cart:${shop}`;

  const lines = useSyncExternalStore(
    subscribe,
    () => readLines(key),
    () => NO_LINES,
  );
  /** False on the server and during hydration: the saved cart hasn't been read yet. */
  const ready = useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );

  return {
    shop,
    lines,
    ready,
    count: countItems(lines),
    /** One more of a variant; nothing changes unless the result is "added". */
    add(line: Omit<CartLine, "quantity">): AddResult {
      const next = addToLines(readLines(key), line);
      if (next.result === "added") writeLines(key, next.lines);
      return next.result;
    },
    setQuantity(variantId: number, quantity: number) {
      writeLines(key, setLineQuantity(readLines(key), variantId, quantity));
    },
    remove(variantId: number) {
      writeLines(key, removeFromLines(readLines(key), variantId));
    },
    clear() {
      writeLines(key, []);
    },
  };
}

// ---- The store: localStorage + the components to tell about changes ----

const NO_LINES: CartLine[] = [];

const listeners = new Set<() => void>();

function subscribe(listener: () => void) {
  listeners.add(listener);
  window.addEventListener("storage", listener);

  return () => {
    listeners.delete(listener);
    window.removeEventListener("storage", listener);
  };
}

/**
 * useSyncExternalStore needs the *same* array back while nothing changed, or it re-renders
 * forever. So parsed carts are remembered by their raw JSON text.
 */
let cached: { key: string; json: string | null; lines: CartLine[] } | null = null;

function readLines(key: string): CartLine[] {
  const json = storageGet(key);
  if (cached && cached.key === key && cached.json === json) return cached.lines;

  const lines = parseLines(json);
  cached = { key, json, lines };
  return lines;
}

function writeLines(key: string, lines: CartLine[]) {
  storageSet(key, JSON.stringify(lines));
  listeners.forEach((listener) => listener());
}

// localStorage can be missing or throw (private mode, storage full, blocked site data):
// the cart then simply doesn't keep anything.

function storageGet(key: string): string | null {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

function storageSet(key: string, value: string) {
  try {
    window.localStorage.setItem(key, value);
  } catch {
    // Nothing to do: see above.
  }
}
