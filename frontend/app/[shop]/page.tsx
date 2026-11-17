import { Suspense } from "react";

import { ListingPagination } from "@/components/listing-pagination";
import { ProductCard } from "@/components/product-card";
import { ProductSearch } from "@/components/product-search";
import { Skeleton } from "@/components/ui/skeleton";
import { getProducts, getShop } from "@/lib/api";

/**
 * The shop's home page: the product grid, e.g. /demo-fashion?search=shirt&page=2.
 * Grid layout adapted from Your Next Store's product grid (MIT, see THIRD_PARTY_NOTICES.md).
 */
export default function ShopPage(props: PageProps<"/[shop]">) {
  return (
    <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      {/* The URL (params, ?search, ?page) is only known per request, so the grid streams in
          inside <Suspense> while the skeleton shows. */}
      <Suspense fallback={<GridSkeleton />}>
        <ProductListing {...props} />
      </Suspense>
    </section>
  );
}

async function ProductListing({ params, searchParams }: PageProps<"/[shop]">) {
  const { shop: slug } = await params;
  const query = await searchParams;
  const search = typeof query.search === "string" ? query.search.trim() : "";
  const page = Math.max(1, Number.parseInt(String(query.page ?? "1"), 10) || 1);

  // An unknown shop is handled by the layout (not-found page).
  const shop = await getShop(slug);
  if (!shop) return null;

  const products = shop.open ? await getProducts(slug, { search, page }) : null;

  if (!products) {
    return <ShopClosed name={shop.name} />;
  }

  return (
    <>
      <div className="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-medium text-foreground sm:text-3xl">
            {search ? `Results for “${search}”` : "All products"}
          </h1>
          <p className="mt-2 text-muted-foreground">
            {products.meta.total} {products.meta.total === 1 ? "product" : "products"}
          </p>
        </div>
        <ProductSearch key={search} shopSlug={slug} defaultValue={search} />
      </div>

      {products.data.length === 0 ? (
        <p className="py-16 text-center text-muted-foreground">
          {search ? "No products match your search." : "No products yet. Please check back soon."}
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
          {products.data.map((product, index) => (
            <ProductCard
              key={product.slug}
              shopSlug={slug}
              product={product}
              currency={shop.currency}
              priority={index === 0}
            />
          ))}
        </div>
      )}

      <ListingPagination
        basePath={`/${slug}`}
        currentPage={products.meta.current_page}
        totalPages={products.meta.last_page}
        search={search}
      />
    </>
  );
}

function ShopClosed({ name }: { name: string }) {
  return (
    <div className="py-24 text-center">
      <h1 className="text-2xl font-medium text-foreground">{name} is closed right now</h1>
      <p className="mt-2 text-muted-foreground">The online shop is not taking orders. Please check back later.</p>
    </div>
  );
}

function GridSkeleton() {
  return (
    <>
      <Skeleton className="mb-10 h-9 w-56" />
      <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 6 }, (_, i) => (
          <div key={i}>
            <Skeleton className="mb-4 aspect-square rounded-2xl" />
            <Skeleton className="mb-2 h-5 w-3/4" />
            <Skeleton className="h-5 w-1/3" />
          </div>
        ))}
      </div>
    </>
  );
}
