import Image from "next/image";
import Link from "next/link";
import { ImageIcon } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import type { ProductCard as ProductCardData } from "@/lib/api";
import { formatPriceRange } from "@/lib/money";

/**
 * One product tile in the grid: square photo, name, price range, out-of-stock badge.
 * Layout and styling adapted from Your Next Store's product card (MIT, see THIRD_PARTY_NOTICES.md).
 */
export function ProductCard({
  shopSlug,
  product,
  currency,
  priority = false,
}: {
  shopSlug: string;
  product: ProductCardData;
  currency: string;
  /** Load this photo first: true for the first card, which is usually visible straight away. */
  priority?: boolean;
}) {
  return (
    <Link href={`/${shopSlug}/products/${product.slug}`} className="group">
      <div className="relative mb-4 aspect-square overflow-hidden rounded-2xl bg-secondary">
        {product.image ? (
          <Image
            src={product.image}
            alt={product.name}
            fill
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            className="object-cover transition-transform duration-500 group-hover:scale-105"
            preload={priority}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-muted-foreground">
            <ImageIcon className="size-10" aria-hidden />
          </div>
        )}
        {!product.in_stock && (
          <Badge variant="secondary" className="absolute top-3 left-3">
            Out of stock
          </Badge>
        )}
      </div>
      <div className="space-y-1">
        <h2 className="text-base font-medium text-foreground">{product.name}</h2>
        <p className="text-base font-semibold text-foreground">
          {formatPriceRange(product.price, currency)}
        </p>
      </div>
    </Link>
  );
}
