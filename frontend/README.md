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

## Where things are

| Path | What |
| --- | --- |
| `lib/api.ts` | The only code that calls the Laravel API (typed, cached about 1 minute) |
| `lib/money.ts` | Price formatting |
| `app/[shop]/layout.tsx` | Shop header and footer |
| `app/[shop]/page.tsx` | Product grid with search and pages |
| `app/[shop]/not-found.tsx` | Unknown shop |
| `components/` | Product card, search box, pagination; `components/ui/` = shadcn |

Learning notes for each step: [LEARNING.md](LEARNING.md). UI credits: [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
