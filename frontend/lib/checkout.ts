import { MAX_LINES, MAX_QUANTITY } from "@/lib/cart";

/**
 * The checkout form's data and first checks, shared by the form (components/checkout-view.tsx)
 * and the Server Action (app/[shop]/checkout/actions.ts). No React and no "server-only".
 *
 * These checks only catch the obvious (empty fields, a broken cart) before calling Laravel.
 * Laravel checks again and has the final word: stock, the phone number, an open shop, totals.
 * Limits match backend/app/Http/Requests/Shop/PlaceShopOrderRequest.php.
 */

export const CHECKOUT_FIELDS = ["name", "phone", "email", "address", "note"] as const;

export type CheckoutField = (typeof CHECKOUT_FIELDS)[number];

export type CheckoutValues = Record<CheckoutField, string>;

/** One line as the order API wants it. */
export type OrderLine = { variant_id: number; quantity: number };

export type PlacedOrder = { number: string; total: string; currency: string };

/** What the Server Action answers, and what the form shows. */
export type CheckoutState =
  | { status: "idle" }
  | {
      status: "error";
      /** Shown above the button: closed shop, too many orders, server trouble... */
      message: string | null;
      fieldErrors: Partial<Record<CheckoutField, string>>;
      /** Problems with cart lines, by variant id: "Not enough in stock." */
      lineErrors: Record<number, string>;
      /** What was typed, so the form can show it again (React empties a form after its action). */
      values: CheckoutValues;
    }
  | { status: "placed"; order: PlacedOrder };

export const INITIAL_CHECKOUT_STATE: CheckoutState = { status: "idle" };

const MAX_LENGTH: Record<CheckoutField, number> = { name: 255, phone: 50, email: 255, address: 1000, note: 1000 };

/** Read the typed values from the submitted form, trimmed. */
export function readValues(formData: FormData): CheckoutValues {
  const values = {} as CheckoutValues;
  for (const field of CHECKOUT_FIELDS) {
    const value = formData.get(field);
    values[field] = typeof value === "string" ? value.trim() : "";
  }
  return values;
}

/** First checks on the typed values; an empty object means "send it to Laravel". */
export function checkValues(values: CheckoutValues): Partial<Record<CheckoutField, string>> {
  const errors: Partial<Record<CheckoutField, string>> = {};

  if (!values.name) errors.name = "Enter your name.";
  if (!values.phone) errors.phone = "Enter a phone number we can call.";
  else if (!/^[0-9+()\-\s]+$/.test(values.phone)) errors.phone = "Use digits, spaces, + ( ) or - only.";
  if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) errors.email = "Enter a valid email address, or leave it empty.";
  if (!values.address) errors.address = "Enter the delivery address.";

  for (const field of CHECKOUT_FIELDS) {
    if (!errors[field] && values[field].length > MAX_LENGTH[field]) {
      errors[field] = `Please keep this under ${MAX_LENGTH[field]} characters.`;
    }
  }

  return errors;
}

/**
 * The cart lines arrive as JSON in a hidden form field, so they are user input: null when
 * they are not 1–20 lines of whole-number variant ids and quantities.
 */
export function parseOrderLines(json: FormDataEntryValue | null): OrderLine[] | null {
  if (typeof json !== "string") return null;

  let data: unknown;
  try {
    data = JSON.parse(json);
  } catch {
    return null;
  }

  if (!Array.isArray(data) || data.length === 0 || data.length > MAX_LINES) return null;

  const lines: OrderLine[] = [];
  for (const item of data) {
    if (
      typeof item !== "object" ||
      item === null ||
      !Number.isInteger(item.variant_id) ||
      !Number.isInteger(item.quantity) ||
      item.quantity < 1 ||
      item.quantity > MAX_QUANTITY
    ) {
      return null;
    }
    lines.push({ variant_id: item.variant_id, quantity: item.quantity });
  }

  return lines;
}
