/** "1250.00" + "BDT" → "BDT 1,250.00" (the currency code is shown as Biponon's admin app does). */
export function formatMoney(amount: string, currency: string): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
    currencyDisplay: "code",
  }).format(Number(amount));
}

/** A product's price range: one price when all variants cost the same, otherwise "from – to". */
export function formatPriceRange(price: { min: string; max: string }, currency: string): string {
  if (price.min === price.max) {
    return formatMoney(price.min, currency);
  }

  return `${formatMoney(price.min, currency)} – ${formatMoney(price.max, currency)}`;
}
