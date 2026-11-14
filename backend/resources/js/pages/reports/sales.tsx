import { Head, Link, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useMoneyFormat } from '@/lib/money';
import { show as showProduct } from '@/routes/catalog/products';
import { exportMethod, index } from '@/routes/reports/sales';

type Group = 'product' | 'variant' | 'customer';

type Totals = {
    orders: number;
    units: number;
    dispatched: number;
    returned: number;
    net_units: number;
    net_revenue: string;
};

type Row = Totals & {
    key: string;
    product?: { id: number; name: string };
    sku?: string;
    size?: string;
    color?: string;
    hex_code?: string | null;
    customer?: { id: number; name: string };
};

type Filters = {
    group: Group;
    search: string;
    customer: number | null;
    date_from: string | null;
    date_to: string | null;
};

type Summary = {
    net_revenue: string;
    net_units: number;
    orders: number;
    average_order_value: string | null;
};

type Props = {
    rows: Paginated<Row>;
    summary: Summary;
    customers: { id: number; name: string }[];
    filters: Filters;
};

const ALL_CUSTOMERS = 'all';

const GROUP_LABELS: Record<Group, string> = {
    product: 'Product',
    variant: 'Variant',
    customer: 'Customer',
};

/**
 * The query string for a set of filters. Dates are always sent (empty means open-ended), because without them the
 * server falls back to the last 30 days.
 */
function queryFor(filters: Filters) {
    return {
        ...(filters.group !== 'product' && { group: filters.group }),
        ...(filters.search && { search: filters.search }),
        ...(filters.customer && { customer: filters.customer }),
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    };
}

/**
 * Visit the page with new filters. Changing a filter resets to page 1.
 */
function applyFilters(filters: Filters) {
    router.get(index.url(), queryFor(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

export default function SalesReport({
    rows,
    summary,
    customers,
    filters,
}: Props) {
    const { formatMoney } = useMoneyFormat();
    const [search, setSearch] = useState(filters.search);
    const isFirstRender = useRef(true);
    const current = { ...filters, search };

    // Debounce the search box; the other filters apply immediately.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(
            () => applyFilters({ ...filters, search: search.trim() }),
            300,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const labelColumns = filters.group === 'variant' ? 3 : 1;

    return (
        <>
            <Head title="Sales report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Sales report"
                        description="Confirmed sales orders by confirmed date, less returns. Orders closed short count only what was dispatched."
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={exportMethod.url({
                                query: queryFor(filters),
                            })}
                        >
                            <Download /> Export CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        label="Net revenue"
                        value={formatMoney(summary.net_revenue)}
                    />
                    <SummaryCard
                        label="Net units"
                        value={summary.net_units.toLocaleString()}
                    />
                    <SummaryCard
                        label="Orders"
                        value={summary.orders.toLocaleString()}
                    />
                    <SummaryCard
                        label="Average order value"
                        value={formatMoney(summary.average_order_value)}
                    />
                </div>

                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            Group by
                        </span>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={filters.group}
                            onValueChange={(value) =>
                                value &&
                                applyFilters({
                                    ...current,
                                    group: value as Group,
                                })
                            }
                            aria-label="Group by"
                        >
                            {(Object.keys(GROUP_LABELS) as Group[]).map(
                                (group) => (
                                    <ToggleGroupItem
                                        key={group}
                                        value={group}
                                        className="px-3"
                                    >
                                        {GROUP_LABELS[group]}
                                    </ToggleGroupItem>
                                ),
                            )}
                        </ToggleGroup>
                    </div>

                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search product, SKU or customer"
                            aria-label="Search product, SKU or customer"
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={
                            filters.customer
                                ? String(filters.customer)
                                : ALL_CUSTOMERS
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                customer:
                                    value === ALL_CUSTOMERS
                                        ? null
                                        : Number(value),
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-56"
                            aria-label="Filter by customer"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_CUSTOMERS}>
                                All customers
                            </SelectItem>
                            {customers.map((customer) => (
                                <SelectItem
                                    key={customer.id}
                                    value={String(customer.id)}
                                >
                                    {customer.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="grid gap-1">
                        <Label
                            htmlFor="date_from"
                            className="text-xs text-muted-foreground"
                        >
                            Confirmed from
                        </Label>
                        <Input
                            id="date_from"
                            type="date"
                            value={filters.date_from ?? ''}
                            max={filters.date_to ?? undefined}
                            onChange={(event) =>
                                applyFilters({
                                    ...current,
                                    date_from: event.target.value || null,
                                })
                            }
                            className="w-40"
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label
                            htmlFor="date_to"
                            className="text-xs text-muted-foreground"
                        >
                            To
                        </Label>
                        <Input
                            id="date_to"
                            type="date"
                            value={filters.date_to ?? ''}
                            min={filters.date_from ?? undefined}
                            onChange={(event) =>
                                applyFilters({
                                    ...current,
                                    date_to: event.target.value || null,
                                })
                            }
                            className="w-40"
                        />
                    </div>

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearch('');
                            router.get(
                                index.url(),
                                {},
                                { preserveScroll: true, replace: true },
                            );
                        }}
                    >
                        Reset to last 30 days
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                {filters.group === 'customer' && (
                                    <th className="px-4 py-3 font-medium">
                                        Customer
                                    </th>
                                )}
                                {filters.group !== 'customer' && (
                                    <th className="px-4 py-3 font-medium">
                                        Product
                                    </th>
                                )}
                                {filters.group === 'variant' && (
                                    <>
                                        <th className="px-4 py-3 font-medium">
                                            Variant
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            SKU
                                        </th>
                                    </>
                                )}
                                <th className="px-4 py-3 text-right font-medium">
                                    Orders
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Units
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Dispatched
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Returned
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Net units
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Net revenue
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={labelColumns + 6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No confirmed sales match these filters.
                                    </td>
                                </tr>
                            )}
                            {rows.data.map((row) => (
                                <tr
                                    key={row.key}
                                    className="border-b last:border-0"
                                >
                                    {row.customer && (
                                        <td className="px-4 py-3 font-medium">
                                            {row.customer.name}
                                        </td>
                                    )}
                                    {row.product && (
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={showProduct(
                                                    row.product.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {row.product.name}
                                            </Link>
                                        </td>
                                    )}
                                    {filters.group === 'variant' && (
                                        <>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <ColorSwatch
                                                        hex={
                                                            row.hex_code ?? null
                                                        }
                                                    />
                                                    {row.size} / {row.color}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {row.sku}
                                            </td>
                                        </>
                                    )}
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.orders.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.units.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {row.dispatched.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {row.returned.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.net_units.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                        {formatMoney(row.net_revenue)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={rows} />
            </div>
        </>
    );
}

function SummaryCard({ label, value }: { label: string; value: string }) {
    return (
        <Card className="h-full gap-1 py-4">
            <CardHeader className="px-4">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

SalesReport.layout = {
    breadcrumbs: [
        {
            title: 'Sales report',
            href: index(),
        },
    ],
};
