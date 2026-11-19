import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ChevronLeftIcon } from "lucide-react";
import { Suspense } from "react";

import { ProductGallery } from "@/components/product-gallery";
import { Skeleton } from "@/components/ui/skeleton";
import { VariantPicker } from "@/components/variant-picker";
import { getProduct, getShop } from "@/lib/api";

/**
 * One product, e.g. /demo-fashion/products/classic-cotton-t-shirt: photos on the left;
 * name, price, size and color picker and description on the right.
 */

export async function generateMetadata({ params }: PageProps<"/[shop]/products/[product]">): Promise<Metadata> {
  const { shop, product: productSlug } = await params;
  const product = await getProduct(shop, productSlug);

  return { title: product?.name ?? "Product not found" };
}

export default function ProductPage(props: PageProps<"/[shop]/products/[product]">) {
  return (
    <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      {/* params is only known per request, so the product streams in inside <Suspense>
          while the skeleton shows. */}
      <Suspense fallback={<ProductSkeleton />}>
        <ProductDetails {...props} />
      </Suspense>
    </section>
  );
}

async function ProductDetails({ params }: PageProps<"/[shop]/products/[product]">) {
  const { shop: shopSlug, product: productSlug } = await params;

  // An unknown shop is handled by the layout (not-found page).
  const shop = await getShop(shopSlug);
  if (!shop) return null;

  // A closed shop or a hidden product: the API answers 404, so product is null.
  const product = shop.open ? await getProduct(shopSlug, productSlug) : null;
  if (!product) notFound();

  return (
    <>
      <Link
        href={`/${shop.slug}`}
        className="mb-8 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeftIcon className="size-4" aria-hidden />
        All products
      </Link>

      <div className="grid gap-10 lg:grid-cols-2">
        <ProductGallery images={product.images} name={product.name} />

        <div>
          <h1 className="text-2xl font-medium text-foreground sm:text-3xl">{product.name}</h1>

          {/* Key: start the picker fresh when moving to another product. */}
          <VariantPicker
            key={product.slug}
            sizes={product.sizes}
            colors={product.colors}
            variants={product.variants}
            price={product.price}
            currency={shop.currency}
          />

          {product.description && (
            <div className="mt-10 border-t border-border pt-6">
              <h2 className="mb-2 text-sm font-semibold text-foreground">Description</h2>
              <p className="whitespace-pre-line text-muted-foreground">{product.description}</p>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

function ProductSkeleton() {
  return (
    <>
      <Skeleton className="mb-8 h-5 w-28" />
      <div className="grid gap-10 lg:grid-cols-2">
        <Skeleton className="aspect-square rounded-2xl" />
        <div>
          <Skeleton className="mb-4 h-9 w-3/4" />
          <Skeleton className="mb-8 h-7 w-32" />
          <Skeleton className="mb-3 h-5 w-16" />
          <div className="mb-8 flex gap-2">
            {Array.from({ length: 4 }, (_, i) => (
              <Skeleton key={i} className="h-9 w-12" />
            ))}
          </div>
          <Skeleton className="h-10 w-full" />
        </div>
      </div>
    </>
  );
}
