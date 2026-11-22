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

## Step 3b: product page ✅

Goal: `/demo-fashion/products/classic-cotton-t-shirt` shows the photos, name, description
and price, and lets the shopper pick a size and colour. The product cards already link
here. The "Add to cart" button stays disabled until Step 3c.

**Built and ready to read.** The tasks below explain the code in the order it was written:
`lib/api.ts` (`getProduct`), `app/[shop]/products/[product]/page.tsx` and `not-found.tsx`,
`components/product-gallery.tsx` and `components/variant-picker.tsx`. Read each task, then
the matching file, then do the exercises.

Mistakes worth remembering from the first try:
- `import notFound from "./not-found"` imports the not-found *component*, and calling it just
  draws it inside the page. The real one is `import { notFound } from "next/navigation"`:
  it stops the page, shows the nearest `not-found.tsx` and adds `noindex`.
- Import `Metadata` from `"next"`, never from `next/dist/...` (internal paths change).
- `next/image` needs `fill` (inside a `relative` box) or `width` + `height`, and `sizes`
  should describe *this* layout (the product photo is half the screen, not a third).

Course chapters first: 13 (error handling, `notFound()`). Docs to read:
`01-app/01-getting-started/05-server-and-client-components.md` (the most important one
for this step) and `01-app/03-api-reference/04-functions/generate-metadata.md`.

### What the API gives you

`GET {BIPONON_API_URL}/shops/demo-fashion/products/classic-cotton-t-shirt` answers
`{ "data": { ... } }` (open it in the browser to see real data). `data` has
everything a card has, plus:

```jsonc
{
  "slug": "classic-cotton-t-shirt", "name": "...", "image": "...", "price": { "min": "450.00", "max": "450.00" }, "in_stock": true,
  "description": "100% combed cotton crew neck tee.",   // can be null
  "images": ["http://.../storage/products/4/23/a.png"], // main photo first, can be []
  "sizes":  [{ "id": 1, "name": "S" }],
  "colors": [{ "id": 3, "name": "Navy", "hex": "#1F2A44" }], // hex can be null
  "variants": [{ "id": 101, "sku": "TSH-S-NVY", "size_id": 1, "color_id": 3, "price": "450.00", "in_stock": true }]
}
```

Every variant has a size and a colour. Not every size × colour exists, and some exist but
are out of stock. A closed shop or a hidden product answers 404.

### Your tasks

**1. `getProduct()` in `lib/api.ts`.**
Add a `ProductVariant` type and a `ProductDetail` type (hint: `ProductCard & { ... }`),
then `getProduct(shopSlug, productSlug)`. Copy the shape of `getShop()`: `"use cache"`,
`cacheLife("minutes")`, `getJson`, return `body?.data ?? null`. Remember
`encodeURIComponent` on both slugs.

**2. The page: `app/[shop]/products/[product]/page.tsx`.**
- The folder path *is* the URL. Its props type is `PageProps<"/[shop]/products/[product]">`
  and `params` holds both `shop` and `product`.
- Same pattern as `app/[shop]/page.tsx`: the default export renders a frame with
  `<Suspense fallback={...}>`, and an inner `async` component awaits `params`, calls
  `getShop()` (for the currency) and `getProduct()`.
- `getProduct()` returned null → call `notFound()` (from `next/navigation`).
- Layout idea: two columns on large screens (`grid lg:grid-cols-2 gap-10`): photos on the
  left; name, price, description and the picker on the right.
- Photos: the first one big (`aspect-square`, `next/image` with `fill`, `sizes`,
  `preload`), the others as small thumbnails below. No photos → the same `ImageIcon`
  placeholder as `components/product-card.tsx`.
- Description: `whitespace-pre-line` keeps line breaks typed in Biponon.

**3. `generateMetadata` in the same file.**
Title = product name. Look at `app/[shop]/layout.tsx`: it does the same for the shop name.
Unknown product → `"Product not found"`.

**4. `app/[shop]/products/[product]/not-found.tsx`.**
"This product isn't available", with a link back to the shop. Next.js uses the
**nearest** `not-found.tsx`, so this one wins over `app/[shop]/not-found.tsx`.
(Hint: `not-found.tsx` gets no `params`, so it doesn't know the shop slug. Read it with
`useParams()`, which needs `"use client"` at the top of the file.)

**5. The picker: `components/variant-picker.tsx` — your first Client Component.**
The page is a Server Component: it runs once on the server and can't react to clicks.
Anything with `useState` or `onClick` needs `"use client"` as the file's first line.

- Props: `sizes`, `colors`, `variants`, `currency`, `price` (the min/max range). The page
  passes them in. Props sent from server to client must be plain data (strings, numbers,
  arrays, objects — no functions, no `Date`).
- Import types only: `import type { ProductVariant } from "@/lib/api"`. A normal import
  of `lib/api.ts` would break the build because of `"server-only"` — try it once to see
  the error.
- State: `const [sizeId, setSizeId] = useState<number | null>(...)` and the same for colour.
  If there is only one size (or colour), start with it selected.
- The chosen variant: `variants.find(v => v.size_id === sizeId && v.color_id === colorId)`.
- Size buttons: `<Button variant={selected ? "default" : "outline"}>` from
  `components/ui/button`. Disable a size when no variant with that size (and the chosen
  colour, if any) is in stock. Same rule the other way round for colours. Show a small
  round swatch with `style={{ backgroundColor: color.hex }}` when `hex` is set.
- Price: the chosen variant's price with `formatMoney`, otherwise `formatPriceRange`.
  (`lib/money.ts` has no `"server-only"`, so the client can use it.)
- Below: "In stock" / "Out of stock" for the chosen variant, or "Choose a size and colour".
- "Add to cart" `<Button disabled>` for now.

### Checklist before you ask me to review

- [ ] `npm run lint` and `npx tsc --noEmit` pass; `npm run build` shows the new route.
- [ ] A product with several sizes/colours, one with a single variant, and one with no photo all look right.
- [ ] Out-of-stock combinations can't be chosen.
- [ ] `/demo-fashion/products/nope` shows your not-found page.
- [ ] With the shop switched off in Biponon (wait a minute for the cache), the product page is not found.
- [ ] The browser tab shows the product name.
- [ ] Phone width (DevTools device mode): one column, nothing sticks out sideways.

### Exercises

1. In the picker, `console.log` the chosen variant. Where does the log appear: the
   terminal or the browser console? Now `console.log` in the page component. Why the difference?
2. `components/product-gallery.tsx` is a Client Component because the thumbnails swap the
   big photo. Remove `"use client"` and read the error. Then explain why the description,
   right next to it, can stay on the server.
3. In `app/[shop]/layout.tsx`, change the metadata to `title: { template: "%s · " + shop.name, default: shop.name }`.
   What does the tab show on the product page now?
4. Explain out loud: why is the page a Server Component and only the picker a Client
   Component, instead of putting `"use client"` on the whole page?
5. In `variant-picker.tsx`, choose a size, then a colour that is crossed out. What happens to
   the size, and which function does that?

---

## Step 3c: the cart ✅

"Add to cart" on a product page, a cart icon with a count in the header, and the cart page
`/demo-fashion/cart` (change quantities, remove, subtotal). Checkout comes in Step 3d.

Read in this order: `lib/cart.ts` → `lib/use-cart.ts` → `components/cart-link.tsx` and
`app/[shop]/layout.tsx` → `components/variant-picker.tsx` (`addToCart`) →
`app/[shop]/cart/page.tsx` → `components/cart-view.tsx`.

Docs: `01-app/01-getting-started/05-server-and-client-components.md`. React docs:
https://react.dev/reference/react/useSyncExternalStore.

### 1. Where the cart lives: the browser

The cart is saved in `localStorage`, one cart per shop (key `biponon-cart:demo-fashion`).
No login, nothing in Laravel until the order is placed, and every shop page stays cached.
The other common choice, a cart ID in a cookie with the cart on the server, makes every
page that shows the cart icon read the cookie, so those pages can't be cached.

`localStorage` belongs to the visitor, so `parseLines()` treats it like user input: anything
broken or edited by hand is dropped instead of crashing the page.

### 2. Plain logic in plain files

`lib/cart.ts` has no React and no `"server-only"`: just data in, data out (`addToLines`,
`setLineQuantity`, `subtotal`...). Easy to read, easy to test, and the checkout will reuse it.

Money is added in whole poisha (`toCents`), because `0.1 + 0.2` is `0.30000000000000004`
in JavaScript.

### 3. Sharing state: one store, many components

Every Client Component that calls `useCart()` (`lib/use-cart.ts`) reads the same store, so
the header icon, the picker and the cart page update together. When the cart changes,
`writeLines()` saves it and tells every subscribed component to re-render.

**What went wrong first (worth remembering):** the first version used a React Context
provider wrapped around the whole shop layout. It called `useParams()` for the shop slug,
and the build stopped with `blocking-prerender-client-hook`. Next.js prerenders the static
frame of `/[shop]` *before* any shop slug is known, so anything that reads the URL (`params`,
`useParams()`, `searchParams`) must sit inside `<Suspense>`. A provider around everything
can't. So the hook reads the slug itself, and only components already inside `<Suspense>`
use it. The header's cart icon got its own `<Suspense fallback={<CartLinkPlaceholder />}>`.

Context is still the right tool for state that doesn't depend on the URL (a theme, a
signed-in user). For a store outside React like `localStorage`, `useSyncExternalStore` alone
is enough.

### 4. Reading browser storage without a hydration error

The server has no `localStorage`, so its HTML always shows an empty cart. If the browser's
first render showed 3 items, React would see different HTML and report a **hydration
mismatch**.

`useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot)` solves it: React uses the
server snapshot (empty cart, `ready: false`) while hydrating, then switches to the real one.
That is why the cart page shows a skeleton until `ready`, not a wrong "Your cart is empty".

`getSnapshot` must return the **same array** while nothing changed, or React re-renders
forever. `readLines()` remembers the parsed cart by its JSON text for that reason.

The `"storage"` event fires when **another tab** changes `localStorage`, so two open tabs
stay in step.

### 5. A page that is mostly client-side

`app/[shop]/cart/page.tsx` is still a Server Component: it fetches the shop (currency, open
or closed). The list itself is `<CartView>`, a Client Component. Use server for data you can
fetch, client for what lives in the browser or reacts to clicks.

`export const metadata = { title: "Your cart" }` is the static form of `generateMetadata`.

### Exercises

1. Add something to the cart, open DevTools → Application → Local Storage and find the
   cart. Change a quantity to `99` by hand and reload. What does the page show, and which
   function decided that?
2. Open the shop in two tabs. Add to the cart in one: does the other tab's icon change?
   Which line of `lib/use-cart.ts` makes that happen?
3. In `cart-view.tsx`, remove `if (!ready) return <CartSkeleton />;` and reload a cart with
   items. What flashes first, and why?
4. Change `MAX_QUANTITY` to 3. Which buttons and messages change, without any other edit?
5. Explain out loud: why is the cart not stored in Laravel until checkout?

---

## Step 3d: checkout (cash on delivery) ✅

`/demo-fashion/checkout`: name, phone, address, optional email and note, the order summary,
and "Place order". The order lands in Biponon as a **draft sales order** for the manager to
confirm; the shopper sees the order number and the cart is emptied.

Course chapters: 12 (mutating data), 14 (improving accessibility: form validation).
Docs: `01-app/01-getting-started/07-mutating-data.md`, `01-app/02-guides/forms.md`.

Read in this order: `lib/checkout.ts` → `lib/api.ts` (`placeOrder`) →
`app/[shop]/checkout/actions.ts` → `app/[shop]/checkout/page.tsx` → `components/checkout-view.tsx`.

### 1. Server Actions

`actions.ts` starts with `"use server"`. Its exported functions run **only on the Next.js
server**, but a Client Component can import one and use it as a form's `action`. Next.js
turns that into a POST request behind the scenes. That is how the form reaches Laravel
without the browser ever learning `BIPONON_API_URL`.

A Server Action is a **public endpoint**: anyone can call it with any data. So it checks
everything again (`checkValues`, `parseOrderLines`) instead of trusting the form, and
Laravel checks a third time (stock, open shop, phone).

### 2. `useActionState`: form state from the server

```ts
const [state, formAction, pending] = useActionState(submitCheckout.bind(null, shopSlug), INITIAL_CHECKOUT_STATE);
```

- `formAction` goes on `<form action={formAction}>`.
- When the form is sent, React calls the action with `(previous state, form data)` and
  shows its answer as `state`: `idle`, `error` (messages per field and per cart line) or
  `placed` (order number).
- `pending` is true while it runs: the button shows "Placing order…" and is disabled, so a
  double-click can't place two orders.
- `.bind(null, shopSlug)` fixes the first argument (the shop), so React only adds the rest.

### 3. Showing errors

- Each field has `aria-invalid` and `aria-describedby` pointing to its message, so screen
  readers read the error with the field.
- **React empties a form after its action runs.** That's why the action sends back `values`
  and each input uses `defaultValue={failed?.values[field]}`: after an error, what the
  shopper typed is still there.
- Laravel's errors come back as keys like `lines.2.quantity`. `fromLaravelErrors()` turns
  the `2` back into a variant id, so "Not enough in stock." appears under the right item.
- `noValidate` on the form turns off the browser's own popups so all messages look the same.

### 4. The cart goes with the form

The cart lives in the browser, so the form carries it in a hidden field (`name="lines"`,
just variant ids and quantities as JSON). Prices are *not* sent: Laravel works out the
total from its own prices.

The cart is emptied in a `useEffect` **after** the "order placed" message is on screen.
Emptying it inside the action re-rendered with an empty cart first, which flashed
"Your cart is empty" for a moment.

### 5. Spam protection

- **Honeypot:** a `website` field moved far off-screen. People never see it; simple bots
  fill every field, and the action rejects the order.
- **Rate limit:** Laravel allows 30 orders a minute per shop and 5 an hour per phone
  number. They're counted per shop and phone, not per IP, because every order comes from
  the same Next.js server.

### Exercises

1. Submit the form empty. Which messages appear, and did the request reach Laravel?
   (Hint: look at `checkValues` and Laravel's log.)
2. Put 10 of an item in the cart, then lower its stock in Biponon below 10 and place the
   order. Where does "Not enough in stock." appear?
3. In DevTools → Elements, find the honeypot field, type something into it and submit.
   What happens?
4. Place a real order and find it in Biponon (Sales orders, draft). Which customer did
   it create or match, and what does the note say?
5. Explain out loud: why does the browser post to a Server Action instead of calling the
   Laravel API directly?

---

## Step 4: deploy to Vercel

**Goal:** the shop live at https://shop.mypersonalwork.com, rebuilt on every push.
Setup steps are in [README.md](README.md#deploy-vercel); this section explains what is going on.

### What Vercel does with the app

`next build` sorts every route into two kinds. Parts that don't depend on the request are
**prerendered** once at build time into static HTML (the shell: header, layout, the
loading skeletons). Parts inside `<Suspense>` that read data are **streamed** at request
time by Vercel Functions (small servers that start on demand). With Cache Components, the
`"use cache"` results in `lib/api.ts` are kept between requests for about a minute, so
most visits don't call Laravel at all.

### Build time vs run time

`BIPONON_API_URL` is used in two places:
- `lib/api.ts` reads it at **run time**, on the server, for every API call.
- `next.config.ts` reads it at **build time**, to allow photos from that host in
  `next/image`. A config file is evaluated when the app is built, not per request.

So changing the variable in Vercel only takes full effect after a **redeploy**. Because it
has no `NEXT_PUBLIC_` prefix, it is never put into the JavaScript sent to browsers.

### Which pushes deploy

`vercel.json` holds two rules:
- `ignoreCommand`: Vercel runs `git diff HEAD^ HEAD --quiet -- .` inside `frontend/`. Exit
  code 0 means "nothing changed here", and Vercel skips the build. A Laravel-only commit
  costs no build minutes.
- `git.deploymentEnabled.production: false`: the `production` branch holds the Laravel
  build for cPanel; Vercel never builds it.

Any other branch you push gets a **preview deployment** with its own URL: a full copy of
the shop to test before merging into `main`.

### Domains and DNS

The domain stays at the current host. A **CNAME** record says "`shop.mypersonalwork.com` is
another name for Vercel's address", so browsers asking for the shop are sent to Vercel,
while `saas-backend.mypersonalwork.com` and email keep pointing at cPanel. Vercel sees the
domain point at it and issues the HTTPS certificate itself.

### Exercises

1. In the Vercel dashboard, open the latest deployment → **Build Logs**. Find the route
   list at the end. Which routes are fully static (○) and which partly prerendered (◐)?
   What part of a ◐ page is in the static HTML, and what is streamed in later?
2. Push a commit that only changes `backend/`. What does Vercel show for it, and why?
3. Make a branch, change the shop's home page text, push it, and open the preview URL.
   Is the live site changed?
4. In **Runtime Logs**, open a product page and look at the requests. Reload within a
   minute: does Laravel get called again? Why or why not?
5. Explain out loud: why can't this app run on the cPanel server next to Laravel?
