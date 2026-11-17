# Learning Next.js with the Biponon shop

The shop is built in small steps. Each step below lists the Next.js ideas it uses,
where to see them in our code, what to read, and a few exercises. The exercises are
small changes you make yourself, then undo or keep.

**How to use it**

1. Do the matching chapters of the free course at https://nextjs.org/learn
   (the "App Router" course, which builds a dashboard).
2. Read our code for the step, with the notes below open.
3. Do the exercises with `npm run dev` running and watch the page change.
4. Write one or two sentences in your own words for each idea. That's your answer
   when a client asks "why did you do it this way?"

The official docs for the exact version we use are also inside the project:
`node_modules/next/dist/docs/` (plain Markdown files, searchable in VS Code).

---

## Step 3a: the product grid ✅

Course chapters: 1–5 (setup, CSS, fonts and images, layouts and pages, navigation),
7 (fetching data), 9 (streaming), 10 (partial prerendering), 11 (search and pagination).

### 1. Routing is folders

`app/page.tsx` is `/`. `app/[shop]/page.tsx` is `/demo-fashion`, `/any-shop`... The square
brackets make `shop` a **dynamic segment**: its value arrives in `params`.

- `app/[shop]/layout.tsx` wraps every page under `/[shop]` (header and footer), and later
  `/[shop]/products/...` too. Layouts stay on screen when you move between pages.
- `app/[shop]/not-found.tsx` is what `notFound()` shows.

Docs: `01-app/01-getting-started/03-layouts-and-pages.md`,
`01-app/03-api-reference/03-file-conventions/dynamic-routes.md`.

### 2. Server Components fetch data directly

Our pages are `async` functions that run **on the server only**. They `await` the API and
send finished HTML to the browser. No `useEffect`, no loading state by hand, and the
browser never sees the API address.

- `lib/api.ts` is the only file that calls Laravel. Its first line, `import "server-only"`,
  makes the build fail if a browser component ever imports it.
- `BIPONON_API_URL` has no `NEXT_PUBLIC_` prefix, so it stays on the server.
  (`NEXT_PUBLIC_*` variables are copied into the browser's JavaScript.)

Docs: `01-app/01-getting-started/06-fetching-data.md`.

### 3. Caching with `"use cache"`

Next 16 apps use **Cache Components** (`cacheComponents: true` in `next.config.ts`).
Nothing is cached unless you ask for it.

- `getShop()` and `getProducts()` start with `"use cache"` and `cacheLife("minutes")`.
  The result is reused for about a minute. The arguments are the cache key, so
  `getProducts("demo-fashion", { page: 2 })` is cached separately from page 1.
- This matches the Laravel API's `Cache-Control: max-age=60`. A product switched on in
  Biponon appears in the shop within about a minute.

Docs: `01-app/01-getting-started/08-caching.md`, `01-app/03-api-reference/04-functions/cacheLife.md`.

### 4. Streaming with `<Suspense>`

`params` and `searchParams` are **Promises** in Next 16: the URL is only known when a
request arrives. A component that awaits them goes inside `<Suspense fallback={...}>`.
Next.js sends the page frame and the fallback (our grey skeletons) **immediately**, then
streams in the real content.

- See `ShopLayout` → `<Suspense><ShopName params={params} /></Suspense>` and
  `ShopPage` → `<Suspense fallback={<GridSkeleton />}><ProductListing /></Suspense>`.
- The build output shows `◐ /[shop]` = **Partial Prerender**: static frame + streamed parts.
- A side effect worth knowing: once streaming has started, the HTTP status is already
  `200`. So `/no-such-shop` shows our not-found page with status 200, and Next.js adds
  `<meta name="robots" content="noindex">` so Google doesn't index it.

Docs: `01-app/02-guides/streaming.md` (section "Status codes").

### 5. Search and pagination live in the URL

`/demo-fashion?search=shirt&page=2`. The URL holds the state, so links can be shared and
the back button works.

- `components/product-search.tsx` uses `next/form`'s `<Form action="/demo-fashion">`: a
  normal GET form that works without JavaScript but navigates client-side when JS is on.
- `components/listing-pagination.tsx` builds links that keep `search`.
- `components/ui/pagination.tsx` was changed from `<a>` to `next/link`, so changing page
  doesn't reload the whole document.

Docs: `01-app/03-api-reference/02-components/form.md`, `.../link.md`.

### 6. Images

`next/image` resizes photos, serves WebP/AVIF, and lazy-loads them. Outside images must
be allowed in `next.config.ts` → `images.remotePatterns` (we allow only Laravel's
`/storage/**`). `fill` + `sizes` lets the image fill its square box at the right size per
screen width. `preload` loads the first card's photo early.

Docs: `01-app/03-api-reference/02-components/image.md`.

### Exercises

1. In `app/[shop]/page.tsx`, change the grid to 4 columns on large screens (`lg:grid-cols-4`).
2. In `lib/api.ts`, change `cacheLife("minutes")` to `cacheLife("seconds")`, switch a product's
   "Show in online shop" off in Biponon, and see how fast it disappears. Change it back.
3. Add `app/[shop]/loading.tsx` that returns `<p>Loading…</p>`. When does it show compared
   with the skeleton? (Read about `loading.js` in the fetching-data doc.) Then delete it.
4. Make the footer show "Free delivery in Dhaka" only for one shop slug. Where does the slug come from?
5. Explain out loud: why does the browser never see `BIPONON_API_URL`?

---

## Step 3b: product page (you write it, I review)

`app/[shop]/products/[product]/page.tsx`, using `getProduct()` which we add to
`lib/api.ts` together. You'll practise nested dynamic segments, `generateMetadata`, and
a first **Client Component** (`"use client"`) for picking size and colour.
Course chapters to do first: 12 (mutating data, Server Actions), 13 (error handling).

## Step 3c: cart · Step 3d: checkout (cash on delivery) · Step 4: deploy to Vercel

Notes are added here as we build each one.
