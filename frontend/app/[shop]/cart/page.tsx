import type { Metadata } from "next";
import { Suspense } from "react";

import { CartSkeleton, CartView } from "@/components/cart-view";
import { getShop } from "@/lib/api";

/**
 * The cart, e.g. /demo-fashion/cart. The server part only looks up the shop (currency,
 * open or closed); the lines themselves live in the browser, so <CartView> is a Client
 * Component that reads them from the cart provider.
 */

export const metadata: Metadata = { title: "Your cart" };

export default function CartPage(props: PageProps<"/[shop]/cart">) {
  return (
    <section className="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
      <h1 className="mb-8 text-2xl font-medium text-foreground sm:text-3xl">Your cart</h1>
      <Suspense fallback={<CartSkeleton />}>
        <Cart {...props} />
      </Suspense>
    </section>
  );
}

async function Cart({ params }: PageProps<"/[shop]/cart">) {
  const { shop: slug } = await params;

  // An unknown shop is handled by the layout (not-found page).
  const shop = await getShop(slug);
  if (!shop) return null;

  return <CartView shopSlug={shop.slug} currency={shop.currency} open={shop.open} />;
}
