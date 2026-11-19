/**
 * The shopping cart's data and rules. Plain functions with no React and no "server-only",
 * so both the browser (lib/use-cart.ts) and, later, the checkout can use them.
 *
 * A cart line is one size × color (a variant). It keeps a copy of the name, size, color,
 * price and photo so the cart page needs no API call. Those copies can go out of date;
 * the checkout asks Laravel, which checks stock and works out the real total.
 */

export type CartLine = {
  variantId: number;
  quantity: number;
  productSlug: string;
  name: string;
  size: string;
  color: string;
  /** Price of one, a string like "450.00", as the API sends it. */
  price: string;
  image: string | null;
};

/** The order API accepts at most 20 lines per order. */
export const MAX_LINES = 20;

/** Most of one variant per order. Shoppers only see "in stock", so the checkout has the final say. */
export const MAX_QUANTITY = 10;

export type AddResult = "added" | "max-quantity" | "cart-full";

/** Add one more of a variant: a new line, or quantity + 1 on the existing line. */
export function addToLines(lines: CartLine[], line: Omit<CartLine, "quantity">): { lines: CartLine[]; result: AddResult } {
  const existing = lines.find((item) => item.variantId === line.variantId);

  if (existing) {
    if (existing.quantity >= MAX_QUANTITY) return { lines, result: "max-quantity" };

    return {
      lines: lines.map((item) =>
        item.variantId === line.variantId ? { ...line, quantity: item.quantity + 1 } : item,
      ),
      result: "added",
    };
  }

  if (lines.length >= MAX_LINES) return { lines, result: "cart-full" };

  return { lines: [...lines, { ...line, quantity: 1 }], result: "added" };
}

/** Set a line's quantity, kept between 1 and MAX_QUANTITY (remove a line with removeFromLines). */
export function setLineQuantity(lines: CartLine[], variantId: number, quantity: number): CartLine[] {
  const clamped = Math.min(MAX_QUANTITY, Math.max(1, Math.round(quantity)));

  return lines.map((item) => (item.variantId === variantId ? { ...item, quantity: clamped } : item));
}

export function removeFromLines(lines: CartLine[], variantId: number): CartLine[] {
  return lines.filter((item) => item.variantId !== variantId);
}

/** Number of items, e.g. 2 shirts + 1 hoodie = 3. */
export function countItems(lines: CartLine[]): number {
  return lines.reduce((sum, item) => sum + item.quantity, 0);
}

/**
 * Price × quantity, added up in whole poisha (cents) so 0.1 + 0.2 style float errors can't
 * creep in. Returns a string like "1350.00" for formatMoney().
 */
export function lineTotal(line: Pick<CartLine, "price" | "quantity">): string {
  return toAmount(toCents(line.price) * line.quantity);
}

export function subtotal(lines: CartLine[]): string {
  return toAmount(lines.reduce((sum, item) => sum + toCents(item.price) * item.quantity, 0));
}

function toCents(price: string): number {
  return Math.round(Number(price) * 100);
}

function toAmount(cents: number): string {
  return (cents / 100).toFixed(2);
}

/**
 * Read a cart saved as JSON. Anything broken or edited by hand is dropped instead of
 * crashing the page: storage is the browser's, so treat it like user input.
 */
export function parseLines(json: string | null): CartLine[] {
  if (!json) return [];

  let data: unknown;
  try {
    data = JSON.parse(json);
  } catch {
    return [];
  }

  if (!Array.isArray(data)) return [];

  return data
    .filter(
      (item): item is CartLine =>
        typeof item === "object" &&
        item !== null &&
        Number.isInteger(item.variantId) &&
        Number.isInteger(item.quantity) &&
        item.quantity >= 1 &&
        typeof item.productSlug === "string" &&
        typeof item.name === "string" &&
        typeof item.size === "string" &&
        typeof item.color === "string" &&
        typeof item.price === "string" &&
        (typeof item.image === "string" || item.image === null),
    )
    .slice(0, MAX_LINES)
    .map((item) => ({ ...item, quantity: Math.min(item.quantity, MAX_QUANTITY) }));
}
