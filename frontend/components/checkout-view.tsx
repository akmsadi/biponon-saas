"use client";

import Link from "next/link";
import { CheckIcon, LoaderCircleIcon } from "lucide-react";
import { type ReactNode, useActionState, useEffect } from "react";

import { submitCheckout } from "@/app/[shop]/checkout/actions";
import { CartSkeleton } from "@/components/cart-view";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { lineTotal, subtotal } from "@/lib/cart";
import { type CheckoutField, INITIAL_CHECKOUT_STATE } from "@/lib/checkout";
import { formatMoney } from "@/lib/money";
import { emptyCart, useCart } from "@/lib/use-cart";

/**
 * The checkout form and order summary, then the "order placed" message.
 *
 * useActionState runs the Server Action (app/[shop]/checkout/actions.ts) when the form is
 * submitted, gives back its answer as `state`, and `pending` while it runs. `.bind(null, shopSlug)`
 * fixes the action's first argument, so React only adds (previous state, form data).
 */
export function CheckoutView({
  shopSlug,
  shopName,
  currency,
  open,
}: {
  shopSlug: string;
  shopName: string;
  currency: string;
  open: boolean;
}) {
  const { lines, ready } = useCart();

  const [state, formAction, pending] = useActionState(submitCheckout.bind(null, shopSlug), INITIAL_CHECKOUT_STATE);

  // Empty the cart once the "order placed" message is on screen. Doing it inside the action
  // would re-render with an empty cart first and flash "Your cart is empty".
  useEffect(() => {
    if (state.status === "placed") emptyCart(shopSlug);
  }, [state.status, shopSlug]);

  if (state.status === "placed") {
    return <OrderPlaced shopSlug={shopSlug} shopName={shopName} order={state.order} />;
  }

  if (!ready) return <CartSkeleton />;

  if (!open) {
    return (
      <Message title={`${shopName} is closed right now`} text="Orders can't be placed at the moment. Please try again later.">
        <Button asChild variant="outline">
          <Link href={`/${shopSlug}/cart`}>Back to cart</Link>
        </Button>
      </Message>
    );
  }

  if (lines.length === 0) {
    return (
      <Message title="Your cart is empty" text="Add something to your cart first.">
        <Button asChild>
          <Link href={`/${shopSlug}`}>Browse products</Link>
        </Button>
      </Message>
    );
  }

  const failed = state.status === "error" ? state : null;
  const fieldProps = (field: CheckoutField) => ({
    id: field,
    name: field,
    // React empties a form after its action runs; show what was typed again after an error.
    defaultValue: failed?.values[field] ?? "",
    "aria-invalid": failed?.fieldErrors[field] ? true : undefined,
    "aria-describedby": failed?.fieldErrors[field] ? `${field}-error` : undefined,
  });

  return (
    <form action={formAction} noValidate className="grid gap-10 lg:grid-cols-[1fr_20rem]">
      <div>
        <h1 className="mb-8 text-2xl font-medium text-foreground sm:text-3xl">Checkout</h1>

        <div className="space-y-5">
          <Field label="Full name" field="name" error={failed?.fieldErrors.name}>
            <Input {...fieldProps("name")} autoComplete="name" required />
          </Field>
          <Field label="Phone" field="phone" hint="The shop calls this number to confirm your order." error={failed?.fieldErrors.phone}>
            <Input {...fieldProps("phone")} type="tel" autoComplete="tel" inputMode="tel" required />
          </Field>
          <Field label="Delivery address" field="address" error={failed?.fieldErrors.address}>
            <Textarea {...fieldProps("address")} autoComplete="street-address" rows={3} required />
          </Field>
          <Field label="Email (optional)" field="email" error={failed?.fieldErrors.email}>
            <Input {...fieldProps("email")} type="email" autoComplete="email" />
          </Field>
          <Field label="Note for the shop (optional)" field="note" error={failed?.fieldErrors.note}>
            <Textarea {...fieldProps("note")} rows={2} placeholder="e.g. call after 5 pm" />
          </Field>
        </div>

        {/* Honeypot: hidden from people (and screen readers); bots that fill it are rejected. */}
        <div className="absolute -left-[9999px]" aria-hidden>
          <label htmlFor="website">Website</label>
          <input id="website" name="website" type="text" tabIndex={-1} autoComplete="off" />
        </div>

        {/* The cart travels with the form: just variant ids and quantities. */}
        <input
          type="hidden"
          name="lines"
          value={JSON.stringify(lines.map((line) => ({ variant_id: line.variantId, quantity: line.quantity })))}
        />
      </div>

      <aside className="h-fit rounded-2xl bg-secondary p-6">
        <div className="mb-4 flex items-baseline justify-between">
          <h2 className="font-semibold text-foreground">Your order</h2>
          <Link
            href={`/${shopSlug}/cart`}
            className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
          >
            Edit cart
          </Link>
        </div>

        <ul className="space-y-3 border-b border-border pb-4">
          {lines.map((line) => (
            <li key={line.variantId} className="text-sm">
              <div className="flex justify-between gap-3">
                <span className="text-foreground">
                  {line.name} <span className="text-muted-foreground">× {line.quantity}</span>
                </span>
                <span className="shrink-0 tabular-nums text-foreground">{formatMoney(lineTotal(line), currency)}</span>
              </div>
              <p className="text-muted-foreground">
                {line.size} · {line.color}
              </p>
              {failed?.lineErrors[line.variantId] && (
                <p className="font-medium text-destructive">{failed.lineErrors[line.variantId]}</p>
              )}
            </li>
          ))}
        </ul>

        <div className="mt-4 flex justify-between text-base font-semibold text-foreground">
          <span>Subtotal</span>
          <span className="tabular-nums">{formatMoney(subtotal(lines), currency)}</span>
        </div>
        <p className="mt-2 text-sm text-muted-foreground">Cash on delivery: pay when your order arrives.</p>

        {failed?.message && (
          <p role="alert" className="mt-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
            {failed.message}
          </p>
        )}

        <Button type="submit" size="lg" className="mt-6 w-full" disabled={pending}>
          {pending ? (
            <>
              <LoaderCircleIcon className="animate-spin" aria-hidden /> Placing order…
            </>
          ) : (
            "Place order"
          )}
        </Button>
      </aside>
    </form>
  );
}

function Field({
  label,
  field,
  hint,
  error,
  children,
}: {
  label: string;
  field: CheckoutField;
  hint?: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={field} className="text-sm font-medium text-foreground">
        {label}
      </label>
      {children}
      {error ? (
        <p id={`${field}-error`} className="text-sm text-destructive">
          {error}
        </p>
      ) : (
        hint && <p className="text-sm text-muted-foreground">{hint}</p>
      )}
    </div>
  );
}

function OrderPlaced({
  shopSlug,
  shopName,
  order,
}: {
  shopSlug: string;
  shopName: string;
  order: { number: string; total: string; currency: string };
}) {
  return (
    <Message
      icon
      title="Thank you! Your order is placed."
      text={`${shopName} will call you to confirm it. Pay ${formatMoney(order.total, order.currency)} in cash when it arrives.`}
    >
      <p className="mb-6 text-sm text-muted-foreground">
        Order number <span className="font-semibold text-foreground">{order.number}</span>
      </p>
      <Button asChild>
        <Link href={`/${shopSlug}`}>Continue shopping</Link>
      </Button>
    </Message>
  );
}

function Message({
  title,
  text,
  icon = false,
  children,
}: {
  title: string;
  text: string;
  icon?: boolean;
  children: ReactNode;
}) {
  return (
    <div className="mx-auto max-w-lg py-16 text-center" role="status">
      {icon && (
        <span className="mx-auto mb-6 flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
          <CheckIcon className="size-6" aria-hidden />
        </span>
      )}
      <h1 className="text-2xl font-medium text-foreground">{title}</h1>
      <p className="mt-2 mb-6 text-muted-foreground">{text}</p>
      {children}
    </div>
  );
}
