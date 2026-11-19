"use client";

import Image from "next/image";
import { ImageIcon } from "lucide-react";
import { useState } from "react";

import { cn } from "cn";

/**
 * The product page's photos: one big photo and, when there are more, clickable thumbnails
 * below that swap the big one. A Client Component because it remembers which photo is shown.
 */
export function ProductGallery({ images, name }: { images: string[]; name: string }) {
  const [shown, setShown] = useState(0);
  const current = images[shown];

  return (
    <div>
      <div className="relative aspect-square overflow-hidden rounded-2xl bg-secondary">
        {current ? (
          <Image
            src={current}
            alt={name}
            fill
            sizes="(max-width: 1024px) 100vw, 50vw"
            className="object-cover"
            preload
          />
        ) : (
          <div className="flex h-full items-center justify-center text-muted-foreground">
            <ImageIcon className="size-12" aria-hidden />
          </div>
        )}
      </div>

      {images.length > 1 && (
        <div className="mt-4 grid grid-cols-5 gap-3 sm:grid-cols-6">
          {images.map((src, index) => (
            <button
              key={src}
              type="button"
              onClick={() => setShown(index)}
              aria-label={`Show photo ${index + 1} of ${images.length}`}
              aria-current={index === shown}
              className={cn(
                "relative aspect-square overflow-hidden rounded-lg bg-secondary outline-none focus-visible:ring-2 focus-visible:ring-ring",
                index === shown ? "ring-2 ring-foreground" : "opacity-70 hover:opacity-100",
              )}
            >
              <Image src={src} alt="" fill sizes="96px" className="object-cover" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
