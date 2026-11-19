"use client";

import Link from "next/link";
import { CheckIcon } from "lucide-react";
import { type ReactNode, useState } from "react";

import { Button } from "@/components/ui/button";
// Types only: a normal import of lib/api.ts would fail the build ("server-only").
import type { ProductColor, ProductSize, ProductVariant } from "@/lib/api";
import { type AddResult, MAX_LINES, MAX_QUANTITY } from "@/lib/cart";
import { formatMoney, formatPriceRange } from "@/lib/money";
import { useCart } from "@/lib/use-cart";
import { cn } from "cn";

/**
 * Size and color buttons, the price and stock of the chosen variant, and the
 * "Add to cart" button, which puts one of the chosen variant in the cart.
 *
 * A Client Component: it remembers the shopper's choice (useState) and reacts to clicks.
 * The page (a Server Component) passes in plain data only.
 *
 * Rules:
 * - A size or color with no stock in any combination is disabled.
 * - One that is in stock, but not with the other choice, is crossed out; choosing it
 *   clears the other choice so the shopper can pick again.
 * - With only one size (or color), it starts chosen.
 */
export function VariantPicker({
  product,
  sizes,
  colors,
  variants,
  price,
  currency,
}: {
  /** What the cart line copies: the product's slug (for a link back), name and main photo. */
  product: { slug: string; name: string; image: string | null };
  sizes: ProductSize[];
  colors: ProductColor[];
  variants: ProductVariant[];
  price: { min: string; max: string };
  currency: string;
}) {
  const [sizeId, setSizeId] = useState<number | null>(sizes.length === 1 ? sizes[0].id : null);
  const [colorId, setColorId] = useState<number | null>(colors.length === 1 ? colors[0].id : null);
  /** What happened on the last "Add to cart" click; cleared when the choice changes. */
  const [added, setAdded] = useState<AddResult | null>(null);
  const cart = useCart();

  /** Is any variant in stock with this size and color? null means "any". */
  const inStock = (size: number | null, color: number | null) =>
    variants.some(
      (variant) =>
        variant.in_stock && (size === null || variant.size_id === size) && (color === null || variant.color_id === color),
    );

  const chosen = variants.find((variant) => variant.size_id === sizeId && variant.color_id === colorId);

  function chooseSize(id: number) {
    setSizeId(id);
    setAdded(null);
    if (colorId !== null && !inStock(id, colorId)) setColorId(null);
  }

  function chooseColor(id: number) {
    setColorId(id);
    setAdded(null);
    if (sizeId !== null && !inStock(sizeId, id)) setSizeId(null);
  }

  const chosenSize = sizes.find((size) => size.id === sizeId);
  const chosenColor = colors.find((color) => color.id === colorId);

  function addToCart() {
    if (!chosen || !chosen.in_stock || !chosenSize || !chosenColor) return;

    setAdded(
      cart.add({
        variantId: chosen.id,
        productSlug: product.slug,
        name: product.name,
        size: chosenSize.name,
        color: chosenColor.name,
        price: chosen.price,
        image: product.image,
      }),
    );
  }

  const inCart = chosen ? cart.lines.find((line) => line.variantId === chosen.id)?.quantity : undefined;

  if (variants.length === 0) {
    return <p className="mt-6 text-muted-foreground">Not available to order right now.</p>;
  }

  return (
    <div>
      <p className="mt-3 text-2xl font-semibold text-foreground">
        {chosen ? formatMoney(chosen.price, currency) : formatPriceRange(price, currency)}
      </p>

      <fieldset className="mt-8">
        <legend className="mb-3 text-sm font-semibold text-foreground">
          Size{chosenSize && <span className="font-normal text-muted-foreground">: {chosenSize.name}</span>}
        </legend>
        <div className="flex flex-wrap gap-2">
          {sizes.map((size) => (
            <OptionButton
              key={size.id}
              selected={size.id === sizeId}
              disabled={!inStock(size.id, null)}
              crossedOut={!inStock(size.id, colorId)}
              onClick={() => chooseSize(size.id)}
            >
              {size.name}
            </OptionButton>
          ))}
        </div>
      </fieldset>

      <fieldset className="mt-6">
        <legend className="mb-3 text-sm font-semibold text-foreground">
          Color{chosenColor && <span className="font-normal text-muted-foreground">: {chosenColor.name}</span>}
        </legend>
        <div className="flex flex-wrap gap-2">
          {colors.map((color) => (
            <OptionButton
              key={color.id}
              selected={color.id === colorId}
              disabled={!inStock(null, color.id)}
              crossedOut={!inStock(sizeId, color.id)}
              onClick={() => chooseColor(color.id)}
            >
              {color.hex && (
                <span
                  className="size-4 rounded-full border border-border"
                  style={{ backgroundColor: color.hex }}
                  aria-hidden
                />
              )}
              {color.name}
            </OptionButton>
          ))}
        </div>
      </fieldset>

      <p className="mt-8 text-sm" aria-live="polite">
        {!chosen ? (
          <span className="text-muted-foreground">
            Choose a {sizeId === null && colorId === null ? "size and color" : sizeId === null ? "size" : "color"}.
          </span>
        ) : chosen.in_stock ? (
          <span className="font-medium text-emerald-700 dark:text-emerald-400">In stock</span>
        ) : (
          <span className="font-medium text-destructive">Out of stock</span>
        )}
      </p>

      <Button size="lg" className="mt-4 w-full" disabled={!chosen?.in_stock} onClick={addToCart}>
        {added === "added" ? (
          <>
            <CheckIcon aria-hidden /> Added to cart
          </>
        ) : (
          "Add to cart"
        )}
      </Button>

      <p className="mt-3 min-h-5 text-sm text-muted-foreground" aria-live="polite">
        {added === "max-quantity" && `You can order at most ${MAX_QUANTITY} of one size and color. `}
        {added === "cart-full" && `Your cart is full (${MAX_LINES} different items). `}
        {inCart !== undefined && (
          <>
            {inCart} in your cart ·{" "}
            <Link href={`/${cart.shop}/cart`} className="font-medium text-foreground underline-offset-4 hover:underline">
              View cart
            </Link>
          </>
        )}
      </p>
    </div>
  );
}

function OptionButton({
  selected,
  disabled,
  crossedOut,
  onClick,
  children,
}: {
  selected: boolean;
  disabled: boolean;
  crossedOut: boolean;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <Button
      type="button"
      variant={selected ? "default" : "outline"}
      disabled={disabled}
      aria-pressed={selected}
      onClick={onClick}
      // "outline" has a 1px border and "default" has none; the same border on the selected
      // button keeps every button the same size.
      className={cn(
        "min-w-12",
        selected && "border border-primary",
        crossedOut && !selected && "text-muted-foreground line-through",
      )}
    >
      {children}
    </Button>
  );
}
