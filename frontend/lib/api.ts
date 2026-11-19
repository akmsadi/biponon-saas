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

/** One product page: photos, sizes, colors and orderable variants. Null when the product or shop is not found. */
export async function getProduct(shopSlug: string, productSlug: string): Promise<ProductDetail | null> {
  "use cache";
  cacheLife("minutes");

  const body = await getJson<{ data: ProductDetail }>(
    `/shops/${encodeURIComponent(shopSlug)}/products/${encodeURIComponent(productSlug)}`,
  );

  return body?.data ?? null;
}
