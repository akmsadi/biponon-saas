"use client";

import Link from "next/link";
import { useParams } from "next/navigation";

/**
 * Shown when the product page calls notFound(): an unknown or hidden product, or a closed
 * shop, e.g. /demo-fashion/products/nope. Next.js uses the nearest not-found.tsx, so this
 * one wins over app/[shop]/not-found.tsx.
 *
 * not-found.tsx gets no props, so the shop slug comes from useParams(), which needs
 * "use client".
 */
export default function ProductNotFound() {
  const { shop } = useParams<{ shop: string }>();

  return (
    <div className="mx-auto max-w-7xl px-4 py-24 text-center sm:px-6 lg:px-8">
      <h1 className="text-2xl font-medium text-foreground">This product isn&apos;t available</h1>
      <p className="mt-2 text-muted-foreground">It may have been removed, or the shop is closed right now.</p>
      <Link
        href={`/${shop}`}
        className="mt-6 inline-block text-sm font-medium underline-offset-4 hover:underline"
      >
        Back to the shop
      </Link>
    </div>
  );
}
