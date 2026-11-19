"use client";

import Image from "next/image";
import Link from "next/link";
import { ImageIcon, MinusIcon, PlusIcon, Trash2Icon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { type CartLine, lineTotal, MAX_QUANTITY, subtotal } from "@/lib/cart";
import { formatMoney } from "@/lib/money";
import { useCart } from "@/lib/use-cart";

/**
 * The cart page's lines, quantities and subtotal. A Client Component: the cart lives in the
 * browser (lib/use-cart.ts), and the buttons change it.
 */
export function CartView({ shopSlug, currency, open }: { shopSlug: string; currency: string; open: boolean }) {
  const { lines, ready, setQuantity, remove } = useCart();

  // Until the saved cart has been read (server render and hydration), show the skeleton
  // rather than a wrong "Your cart is empty".
  if (!ready) return <CartSkeleton />;

  if (lines.length === 0) {
    return (
      <div className="py-16 text-center">
        <p className="text-muted-foreground">Your cart is empty.</p>
        <Button asChild className="mt-6">
          <Link href={`/${shopSlug}`}>Browse products</Link>
        </Button>
      </div>
    );
  }

  return (
    <div className="grid gap-10 lg:grid-cols-[1fr_18rem]">
      <ul className="divide-y divide-border border-y border-border">
        {lines.map((line) => (
          <CartLineRow
            key={line.variantId}
            line={line}
            shopSlug={shopSlug}
            currency={currency}
            onQuantity={(quantity) => setQuantity(line.variantId, quantity)}
            onRemove={() => remove(line.variantId)}
          />
        ))}
      </ul>

      <aside className="h-fit rounded-2xl bg-secondary p-6">
        <div className="flex items-center justify-between text-base font-semibold text-foreground">
          <span>Subtotal</span>
          <span>{formatMoney(subtotal(lines), currency)}</span>
        </div>
        <p className="mt-2 text-sm text-muted-foreground">
          Cash on delivery. The shop confirms your order and the final total.
        </p>

        {open ? (
          <Button asChild size="lg" className="mt-6 w-full">
            <Link href={`/${shopSlug}/checkout`}>Checkout</Link>
          </Button>
        ) : (
          <Button size="lg" className="mt-6 w-full" disabled>
            Checkout
          </Button>
        )}
        {!open && (
          <p className="mt-3 text-sm text-destructive">The shop is closed right now, so orders can&apos;t be placed.</p>
        )}

        <Link
          href={`/${shopSlug}`}
          className="mt-4 block text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
          Continue shopping
        </Link>
      </aside>
    </div>
  );
}

function CartLineRow({
  line,
  shopSlug,
  currency,
  onQuantity,
  onRemove,
}: {
  line: CartLine;
  shopSlug: string;
  currency: string;
  onQuantity: (quantity: number) => void;
  onRemove: () => void;
}) {
  const productHref = `/${shopSlug}/products/${line.productSlug}`;

  return (
    <li className="flex gap-4 py-6">
      <Link href={productHref} className="relative size-20 shrink-0 overflow-hidden rounded-lg bg-secondary">
        {line.image ? (
          <Image src={line.image} alt={line.name} fill sizes="80px" className="object-cover" />
        ) : (
          <span className="flex h-full items-center justify-center text-muted-foreground">
            <ImageIcon className="size-6" aria-hidden />
          </span>
        )}
      </Link>

      <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <Link href={productHref} className="font-medium text-foreground hover:underline">
            {line.name}
          </Link>
          <p className="text-sm text-muted-foreground">
            {line.size} · {line.color}
          </p>
          <p className="text-sm text-muted-foreground">{formatMoney(line.price, currency)} each</p>
        </div>

        <div className="flex items-center justify-between gap-4 sm:flex-col sm:items-end">
          <div className="flex items-center gap-1" role="group" aria-label={`Quantity of ${line.name}`}>
            <Button
              variant="outline"
              size="icon-sm"
              onClick={() => onQuantity(line.quantity - 1)}
              disabled={line.quantity <= 1}
              aria-label="One less"
            >
              <MinusIcon />
            </Button>
            <span className="w-8 text-center text-sm font-medium tabular-nums" aria-live="polite">
              {line.quantity}
            </span>
            <Button
              variant="outline"
              size="icon-sm"
              onClick={() => onQuantity(line.quantity + 1)}
              disabled={line.quantity >= MAX_QUANTITY}
              aria-label="One more"
            >
              <PlusIcon />
            </Button>
            <Button variant="ghost" size="icon-sm" onClick={onRemove} aria-label={`Remove ${line.name}`}>
              <Trash2Icon />
            </Button>
          </div>
          <p className="font-semibold text-foreground tabular-nums">{formatMoney(lineTotal(line), currency)}</p>
        </div>
      </div>
    </li>
  );
}

export function CartSkeleton() {
  return (
    <div className="space-y-6">
      {Array.from({ length: 2 }, (_, i) => (
        <div key={i} className="flex gap-4">
          <Skeleton className="size-20 rounded-lg" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-5 w-1/2" />
            <Skeleton className="h-4 w-1/3" />
          </div>
        </div>
      ))}
    </div>
  );
}
