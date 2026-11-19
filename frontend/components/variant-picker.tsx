"use client";

import { type ReactNode, useState } from "react";

import { Button } from "@/components/ui/button";
// Types only: a normal import of lib/api.ts would fail the build ("server-only").
import type { ProductColor, ProductSize, ProductVariant } from "@/lib/api";
import { formatMoney, formatPriceRange } from "@/lib/money";
import { cn } from "cn";

/**
 * Size and color buttons, the price and stock of the chosen variant, and the
 * "Add to cart" button (switched on in Step 3c).
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
  sizes,
  colors,
  variants,
  price,
  currency,
}: {
  sizes: ProductSize[];
  colors: ProductColor[];
  variants: ProductVariant[];
  price: { min: string; max: string };
  currency: string;
}) {
  const [sizeId, setSizeId] = useState<number | null>(sizes.length === 1 ? sizes[0].id : null);
  const [colorId, setColorId] = useState<number | null>(colors.length === 1 ? colors[0].id : null);

  /** Is any variant in stock with this size and color? null means "any". */
  const inStock = (size: number | null, color: number | null) =>
    variants.some(
      (variant) =>
        variant.in_stock && (size === null || variant.size_id === size) && (color === null || variant.color_id === color),
    );

  const chosen = variants.find((variant) => variant.size_id === sizeId && variant.color_id === colorId);

  function chooseSize(id: number) {
    setSizeId(id);
    if (colorId !== null && !inStock(id, colorId)) setColorId(null);
  }

  function chooseColor(id: number) {
    setColorId(id);
    if (sizeId !== null && !inStock(sizeId, id)) setSizeId(null);
  }

  const chosenSize = sizes.find((size) => size.id === sizeId);
  const chosenColor = colors.find((color) => color.id === colorId);

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

      {/* Step 3c puts the chosen variant in the cart. */}
      <Button size="lg" className="mt-4 w-full" disabled title="Coming soon">
        Add to cart
      </Button>
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
