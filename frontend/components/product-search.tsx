import Form from "next/form";
import { SearchIcon } from "lucide-react";

import { Input } from "@/components/ui/input";

/**
 * Name search above the grid. next/form's <Form> with a string action behaves like a
 * normal GET form (works without JavaScript) but navigates client-side when it can:
 * submitting "shirt" goes to /{shop}?search=shirt.
 */
export function ProductSearch({ shopSlug, defaultValue }: { shopSlug: string; defaultValue: string }) {
  return (
    <Form action={`/${shopSlug}`} className="relative w-full sm:max-w-xs" role="search">
      <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        type="search"
        name="search"
        defaultValue={defaultValue}
        placeholder="Search products"
        aria-label="Search products"
        className="pl-9"
      />
    </Form>
  );
}
