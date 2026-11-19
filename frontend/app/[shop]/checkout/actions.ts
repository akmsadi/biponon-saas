"use server";

import { placeOrder } from "@/lib/api";
import {
  CHECKOUT_FIELDS,
  type CheckoutField,
  type CheckoutState,
  type CheckoutValues,
  checkValues,
  parseOrderLines,
  readValues,
} from "@/lib/checkout";

/**
 * The checkout form's Server Action. It runs on the Next.js server, never in the browser:
 * the form posts here, this checks the basics and forwards the order to Laravel, so the
 * Laravel address (BIPONON_API_URL) stays private.
 *
 * Signature for useActionState: (bound shop slug, previous state, form data) → new state.
 *
 * Anyone can call a Server Action with any data (it is a public POST endpoint), so nothing
 * from the form is trusted: values are checked here and again by Laravel.
 */
export async function submitCheckout(
  shopSlug: string,
  _previous: CheckoutState,
  formData: FormData,
): Promise<CheckoutState> {
  const values = readValues(formData);
  const fail = (
    message: string | null,
    fieldErrors: Partial<Record<CheckoutField, string>> = {},
    lineErrors: Record<number, string> = {},
  ): CheckoutState => ({ status: "error", message, fieldErrors, lineErrors, values });

  // Honeypot: a field people never see. Bots fill in every field.
  if (formData.get("website")) return fail("Your order could not be placed.");

  const fieldErrors = checkValues(values);
  if (Object.keys(fieldErrors).length > 0) return fail(null, fieldErrors);

  const lines = parseOrderLines(formData.get("lines"));
  if (!lines) return fail("Your cart could not be read. Please go back to the cart and try again.");

  const result = await placeOrder(shopSlug, {
    name: values.name,
    phone: values.phone,
    email: values.email || null,
    address: values.address,
    note: values.note || null,
    lines,
  });

  if (result.ok) return { status: "placed", order: result.order };

  switch (result.reason) {
    case "invalid":
      return fromLaravelErrors(result.errors, lines, values);
    case "too-many":
      return fail("Too many orders in a short time. Please wait a while and try again, or call the shop.");
    case "not-found":
      return fail("This shop could not be found.");
    case "failed":
      return fail("Something went wrong on our side. Please try again in a minute.");
  }
}

/**
 * Turn Laravel's 422 errors into form errors. Keys look like "phone", "shop" or
 * "lines.2.quantity"; the 2 is the line's position in what we sent, which gives its variant id.
 */
function fromLaravelErrors(
  errors: Record<string, string[]>,
  lines: { variant_id: number }[],
  values: CheckoutValues,
): CheckoutState {
  const fieldErrors: Partial<Record<CheckoutField, string>> = {};
  const lineErrors: Record<number, string> = {};
  let message: string | null = null;

  for (const [key, messages] of Object.entries(errors)) {
    const text = messages[0] ?? "Please check this.";
    const line = /^lines\.(\d+)\./.exec(key);

    if (line) {
      const variantId = lines[Number(line[1])]?.variant_id;
      if (variantId !== undefined) lineErrors[variantId] = text;
    } else if (isField(key)) {
      fieldErrors[key] = text;
    } else {
      message ??= text; // "shop" (closed), "website", "lines"...
    }
  }

  if (!message && Object.keys(lineErrors).length > 0) {
    message = "Some items in your cart can't be ordered as they are. Please change them in your cart.";
  }

  return { status: "error", message, fieldErrors, lineErrors, values };
}

function isField(key: string): key is CheckoutField {
  return (CHECKOUT_FIELDS as readonly string[]).includes(key);
}
