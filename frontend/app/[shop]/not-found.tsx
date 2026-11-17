import Link from "next/link";

/** Shown when notFound() is called for an unknown shop, e.g. /no-such-shop. */
export default function ShopNotFound() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-24 text-center sm:px-6 lg:px-8">
      <h1 className="text-2xl font-medium text-foreground">Shop not found</h1>
      <p className="mt-2 text-muted-foreground">Please check the address you were given.</p>
      <Link href="/" className="mt-6 inline-block text-sm font-medium underline-offset-4 hover:underline">
        Go to Biponon
      </Link>
    </div>
  );
}
