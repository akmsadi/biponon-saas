import { usePage } from '@inertiajs/react';

/**
 * An amount such as "৳85,000.00" in the given ISO 4217 currency. Amounts arrive from the server as decimal
 * strings; null means unknown.
 */
export function formatMoney(
    amount: string | number | null,
    currency: string | null,
): string {
    if (amount === null || amount === '') {
        return '—';
    }

    const value = Number(amount);

    if (!currency) {
        return value.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    return value.toLocaleString(undefined, {
        style: 'currency',
        currency,
    });
}

/**
 * formatMoney bound to the current organization's currency.
 */
export function useMoneyFormat() {
    const currency = usePage().props.organizationCurrency;

    return {
        currency,
        formatMoney: (amount: string | number | null) =>
            formatMoney(amount, currency),
    };
}
