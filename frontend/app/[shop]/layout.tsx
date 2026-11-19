import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import { CartLink, CartLinkPlaceholder } from "@/components/cart-link";
import { Skeleton } from "@/components/ui/skeleton";
import { getShop } from "@/lib/api";

/**
 * The frame around every page of one shop: /{shop}, /{shop}/products/{product}, /{shop}/cart...
 * The [shop] folder name makes "shop" a URL parameter, e.g. "demo-fashion".
 */

export async function generateMetadata({ params }: LayoutProps<"/[shop]">): Promise<Metadata> {
  const shop = await getShop((await params).shop);

  return { title: shop?.name ?? "Shop not found" };
}

export default function ShopLayout({ children, params }: LayoutProps<"/[shop]">) {
  // params is a Promise. It is awaited inside the <Suspense> parts below, not here, so
  // Next.js can send the static frame straight away and stream in the shop's details.
  return (
    <>
      <header className="border-b border-border">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          <Suspense fallback={<Skeleton className="h-6 w-40" />}>
            <ShopName params={params} />
          </Suspense>
          {/* The cart icon needs the shop slug from the URL, which is only known per request. */}
          <Suspense fallback={<CartLinkPlaceholder />}>
            <CartLink />
          </Suspense>
        </div>
      </header>

      <main className="flex-1">{children}</main>

      <footer className="border-t border-border">
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <Suspense fallback={null}>
            <ShopContact params={params} />
          </Suspense>
          <p className="mt-6 text-sm text-muted-foreground">
            Cash on delivery · Powered by{" "}
            <Link href="/" className="underline-offset-4 hover:underline">
              Biponon
            </Link>
          </p>
        </div>
      </footer>
    </>
  );
}

async function ShopName({ params }: Pick<LayoutProps<"/[shop]">, "params">) {
  const shop = await getShop((await params).shop);

  // Unknown shop: show app/[shop]/not-found.tsx instead.
  if (!shop) notFound();

  return (
    <Link href={`/${shop.slug}`} className="text-xl font-bold text-foreground">
      {shop.name}
    </Link>
  );
}

async function ShopContact({ params }: Pick<LayoutProps<"/[shop]">, "params">) {
  const shop = await getShop((await params).shop);
  if (!shop) return null;

  const { phone, email, address } = shop.contact;

  return (
    <div className="space-y-1 text-sm text-muted-foreground">
      <p className="font-semibold text-foreground">{shop.name}</p>
      {address && <p className="whitespace-pre-line">{address}</p>}
      {phone && <p>Phone: {phone}</p>}
      {email && <p>Email: {email}</p>}
    </div>
  );
}
