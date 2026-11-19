import "server-only";

import { cacheLife } from "next/cache";

/**
 * The only place that talks to the Biponon (Laravel) shop API.
 *
 * Every function is cached with "use cache" for about a minute (the
 * "minutes" profile), matching the API's own Cache-Control: max-age=60.
 * The arguments (shop slug, search, page) are part of the cache key, so
 * each shop, search and page is cached separately.
 *
 * A shop or product that does not exist answers null instead of throwing,
 * so the page decides what to show (notFound(), a "closed" message...).
 */

const API_URL = process.env.BIPONON_API_URL ?? "http://localhost:8000/api/v1";

// ---- Types: mirror the Laravel API resources (app/Http/Resources/Shop) ----

export type Shop = {
  slug: string;
  name: string;
  open: boolean;
  currency: string;
  contact: {
    email: string | null;
    phone: string | null;
    address: string | null;
  };
};

/** One product in the grid. Prices are strings like "1250.00". */
export type ProductCard = {
  slug: string;
  name: string;
  image: string | null;
  price: { min: string; max: string };
  in_stock: boolean;
};

/** One orderable size × color. Stock is only yes/no. */
export type ProductVariant = {
  id: number;
  sku: string;
  size_id: number;
  color_id: number;
  price: string;
  in_stock: boolean;
};

export type ProductSize = { id: number; name: string };

export type ProductColor = { id: number; name: string; hex: string | null };

/** A product page: everything on the card plus description, all photos (main first), sizes, colors and variants. */
export type ProductDetail = ProductCard & {
  description: string | null;
  images: string[];
  sizes: ProductSize[];
  colors: ProductColor[];
  variants: ProductVariant[];
};

/** Laravel's paginated resource collection. */
export type Paginated<T> = {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

// ---- Requests ----

/** GET a JSON path from the API; null on 404, an error for anything else. */
async function getJson<T>(path: string): Promise<T | null> {
  const response = await fetch(`${API_URL}${path}`, {
    headers: { Accept: "application/json" },
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`Biponon API ${path} answered ${response.status}`);
  }

  return response.json() as Promise<T>;
}

/** The shop front: name, open or closed, currency, contact details. */
export async function getShop(slug: string): Promise<Shop | null> {
  "use cache";
  cacheLife("minutes");

  const body = await getJson<{ data: Shop }>(`/shops/${encodeURIComponent(slug)}`);

  return body?.data ?? null;
}

/** One page of the product grid (24 per page), optionally searched by name. Null when the shop is closed. */
export async function getProducts(
  slug: string,
  { search = "", page = 1 }: { search?: string; page?: number } = {},
): Promise<Paginated<ProductCard> | null> {
  "use cache";
  cacheLife("minutes");

  const query = new URLSearchParams();
  if (search) query.set("search", search);
  if (page > 1) query.set("page", String(page));
  const queryString = query.size > 0 ? `?${query}` : "";

  return getJson<Paginated<ProductCard>>(`/shops/${encodeURIComponent(slug)}/products${queryString}`);
}

/** What POST /orders needs. (The `website` honeypot is checked in the Server Action before this.) */
export type OrderRequest = {
  name: string;
  phone: string;
  email: string | null;
  address: string;
  note: string | null;
  lines: { variant_id: number; quantity: number }[];
};

export type OrderResult =
  | { ok: true; order: { number: string; total: string; currency: string } }
  /** 422: Laravel's messages by field, e.g. { "phone": [...], "lines.0.quantity": [...] }. */
  | { ok: false; reason: "invalid"; message: string; errors: Record<string, string[]> }
  | { ok: false; reason: "too-many" | "not-found" | "failed" };

/**
 * Place a cash-on-delivery order; it lands in Biponon as a draft sales order.
 * Never cached ("use cache" is only for reading). Answers instead of throwing, so the
 * checkout can always show a message.
 */
export async function placeOrder(shopSlug: string, order: OrderRequest): Promise<OrderResult> {
  let response: Response;
  try {
    response = await fetch(`${API_URL}/shops/${encodeURIComponent(shopSlug)}/orders`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(order),
      cache: "no-store",
    });
  } catch (error) {
    console.error("Biponon API: placing an order failed", error);
    return { ok: false, reason: "failed" };
  }

  if (response.status === 201) {
    const body = (await response.json()) as { data: { number: string; total: string; currency: string } };
    return { ok: true, order: { number: body.data.number, total: body.data.total, currency: body.data.currency } };
  }

  if (response.status === 422) {
    const body = (await response.json()) as { message?: string; errors?: Record<string, string[]> };
    return { ok: false, reason: "invalid", message: body.message ?? "", errors: body.errors ?? {} };
  }

  if (response.status === 429) return { ok: false, reason: "too-many" };
  if (response.status === 404) return { ok: false, reason: "not-found" };

  console.error(`Biponon API: placing an order answered ${response.status}`);
  return { ok: false, reason: "failed" };
}

/** One product page: photos, sizes, colors and orderable variants. Null when the product or shop is not found. */
export async function getProduct(shopSlug: string, productSlug: string): Promise<ProductDetail | null> {
  "use cache";
  cacheLife("minutes");

  const body = await getJson<{ data: ProductDetail }>(
    `/shops/${encodeURIComponent(shopSlug)}/products/${encodeURIComponent(productSlug)}`,
  );

  return body?.data ?? null;
}
