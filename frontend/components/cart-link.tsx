"use client";

import Link from "next/link";
import { ShoppingBagIcon } from "lucide-react";

import { useCart } from "@/lib/use-cart";

const iconBox = "relative inline-flex size-10 items-center justify-center rounded-full text-foreground";

/** The cart icon in the shop header, with the number of items once the saved cart is read. */
export function CartLink() {
  const { shop, count, ready } = useCart();

  return (
    <Link
      href={`/${shop}/cart`}
      className={`${iconBox} hover:bg-secondary`}
      aria-label={ready && count > 0 ? `Cart, ${count} ${count === 1 ? "item" : "items"}` : "Cart"}
    >
      <ShoppingBagIcon className="size-5" aria-hidden />
      {ready && count > 0 && (
        <span className="absolute -top-0.5 -right-0.5 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground">
          {count}
        </span>
      )}
    </Link>
  );
}

/** Same size and icon, shown in the static frame until the real link streams in. */
export function CartLinkPlaceholder() {
  return (
    <span className={iconBox} aria-hidden>
      <ShoppingBagIcon className="size-5" />
    </span>
  );
}
