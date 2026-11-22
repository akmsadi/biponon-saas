# Biponon shop (Next.js)

The public online shop for businesses on Biponon. Each organization's shop lives under
its own path, e.g. `/demo-fashion`. All data comes from the Laravel app's public shop
API (`backend/routes/api.php`, `/api/v1/shops/{slug}`): this app has no database.

Next.js 16 (App Router, Cache Components), React 19, Tailwind CSS 4, shadcn/ui.

## Run locally

1. Laravel running at the address in `.env.local` (e.g. `http://backend.biponon.local`),
   with an organization whose online shop is open (Organization settings → Online shop)
   and some products switched to "Show in online shop".
2. `cp .env.example .env.local` and set `BIPONON_API_URL`.
3. `npm install`, then `npm run dev` and open http://localhost:3000/{organization-slug}.

`npm run build` checks types and builds for production. `npm run lint` runs ESLint.

## Deploy (Vercel)

Live at https://shop.mypersonalwork.com on Vercel's free Hobby plan (fine for a demo or
portfolio; a real business taking real orders needs Pro or another host under Vercel's terms).

- **Project:** the GitHub repo imported into Vercel with **Root Directory** `frontend`; the
  framework (Next.js) and `npm run build` are detected.
- **Environment variable:** `BIPONON_API_URL=https://saas-backend.mypersonalwork.com/api/v1`
  (Production and Preview). It is read at build time too (`next.config.ts` allows product
  photos from that host), so redeploy after changing it.
- **Domain:** `shop.mypersonalwork.com` added in Vercel → Settings → Domains, plus a CNAME
  record `shop` → the target Vercel shows, in cPanel → Zone Editor. Vercel issues HTTPS.
- **Deploys:** every push to `main` that changes `frontend/` deploys to production; a push
  that doesn't is skipped (`vercel.json` `ignoreCommand`). The `production` branch (the
  Laravel build) never deploys here (`git.deploymentEnabled`). Other branches get preview URLs.
- **Laravel side:** the server's `SHOP_URL=https://shop.mypersonalwork.com` makes the
  "View shop" link in Settings → Online shop point here.

## Where things are

| Path | What |
| --- | --- |
| `lib/api.ts` | The only code that calls the Laravel API (typed, cached about 1 minute) |
| `lib/money.ts` | Price formatting |
| `lib/cart.ts` | Cart rules (add, quantities, totals), no React |
| `lib/use-cart.ts` | `useCart()`: the cart in the browser's localStorage, for Client Components |
| `app/[shop]/layout.tsx` | Shop header (with cart icon) and footer |
| `app/[shop]/page.tsx` | Product grid with search and pages |
| `app/[shop]/products/[product]/page.tsx` | Product page: photos, size and color, add to cart |
| `app/[shop]/cart/page.tsx` | Cart: quantities, remove, subtotal |
| `app/[shop]/checkout/page.tsx` | Checkout form (cash on delivery) and order summary |
| `app/[shop]/checkout/actions.ts` | Server Action: checks the form, places the order through the API |
| `lib/checkout.ts` | Checkout fields, first checks and form state, no React |
| `app/[shop]/not-found.tsx` | Unknown shop |
| `components/` | Product card, search, pagination, gallery, variant picker, cart icon and cart view; `components/ui/` = shadcn |
| `vercel.json` | Which pushes Vercel builds |

The cart is kept in the shopper's browser (`localStorage`, one per shop) until checkout.
Placing an order creates a draft sales order in Biponon for the shop's manager to confirm.

Learning notes for each step: [LEARNING.md](LEARNING.md). UI credits: [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
