import type { Metadata } from "next";
import { Suspense } from "react";

import { CartSkeleton } from "@/components/cart-view";
import { CheckoutView } from "@/components/checkout-view";
import { getShop } from "@/lib/api";

/**
 * Checkout, e.g. /demo-fashion/checkout: the shopper's details and the order summary.
 * Like the cart page, the server part only looks up the shop; the form is a Client
 * Component because the cart lives in the browser. Placing the order runs on the server
 * (./actions.ts).
 */

export const metadata: Metadata = { title: "Checkout", robots: { index: false } };

export default function CheckoutPage(props: PageProps<"/[shop]/checkout">) {
  return (
    <section className="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
      <Suspense fallback={<CartSkeleton />}>
        <Checkout {...props} />
      </Suspense>
    </section>
  );
}

async function Checkout({ params }: PageProps<"/[shop]/checkout">) {
  const { shop: slug } = await params;

  // An unknown shop is handled by the layout (not-found page).
  const shop = await getShop(slug);
  if (!shop) return null;

  return <CheckoutView shopSlug={shop.slug} shopName={shop.name} currency={shop.currency} open={shop.open} />;
}
