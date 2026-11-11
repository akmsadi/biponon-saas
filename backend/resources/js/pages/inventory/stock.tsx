import { Head, Link, router } from '@inertiajs/react';
import { PackagePlus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showProduct } from '@/routes/catalog/products';
import { index } from '@/routes/inventory/stock';
import { create as receiveStock } from '@/routes/operations/receive';

type Balance = {
    id: number;
    quantity: number;
    reorder_level: number | null;
    is_low: boolean;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    warehouse: { name: string; code: string };
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search: string;
    warehouse: number | null;
    zero: boolean;
    low: boolean;
};

type Summary = {
    total_units: number;
    variants_in_stock: number;
    warehouses_in_stock: number;
    low_stock: number;
};

type Props = {
    balances: Paginated<Balance>;
    summary: Summary;
    warehouses: { id: number; name: string; code: string }[];
    filters: Filters;
};

const ALL_WAREHOUSES = 'all';

/**
 * Visit the page with new filters, dropping defaults so the URL stays clean. Changing a filter resets to page 1.
 */
function applyFilters(filters: Filters) {
    router.get(
        index.url(),
        {
            ...(filters.search && { search: filters.search }),
            ...(filters.warehouse && { warehouse: filters.warehouse }),
            ...(filters.zero && { zero: 1 }),
            ...(filters.low && { low: 1 }),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function StockOverview({
    balances,
    summary,
    warehouses,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const isFirstRender = useRef(true);

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

    const isFiltered =
        filters.search !== '' ||
        filters.warehouse !== null ||
        filters.zero ||
        filters.low;

    return (
        <>
            <Head title="Stock overview" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Stock overview"
                        description="On-hand quantity of every variant in every warehouse"
                    />
                    <Button asChild>
                        <Link href={receiveStock()}>
                            <PackagePlus /> Receive stock
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        label="Units on hand"
                        value={summary.total_units}
                    />
                    <SummaryCard
                        label="Variants in stock"
                        value={summary.variants_in_stock}
                    />
                    <SummaryCard
                        label="Warehouses holding stock"
                        value={summary.warehouses_in_stock}
                    />
                    <button
                        type="button"
                        className="rounded-xl text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        onClick={() =>
                            applyFilters({
                                ...filters,
                                search,
                                low: !filters.low,
                            })
                        }
                        aria-pressed={filters.low}
                    >
                        <SummaryCard
                            label="Low stock rows"
                            value={summary.low_stock}
                            highlight={summary.low_stock > 0}
                            hint={
                                filters.low
                                    ? 'Showing low stock only'
                                    : 'Click to show only these'
                            }
                        />
                    </button>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search product or SKU"
                            aria-label="Search product or SKU"
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={
                            filters.warehouse
                                ? String(filters.warehouse)
                                : ALL_WAREHOUSES
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...filters,
                                search,
                                warehouse:
                                    value === ALL_WAREHOUSES
                                        ? null
                                        : Number(value),
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-56"
                            aria-label="Filter by warehouse"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_WAREHOUSES}>
                                All warehouses
                            </SelectItem>
                            {warehouses.map((warehouse) => (
                                <SelectItem
                                    key={warehouse.id}
                                    value={String(warehouse.id)}
                                >
                                    {warehouse.name} ({warehouse.code})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="low"
                            checked={filters.low}
                            onCheckedChange={(checked) =>
                                applyFilters({
                                    ...filters,
                                    search,
                                    low: checked === true,
                                })
                            }
                        />
                        <Label htmlFor="low">Low stock only</Label>
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="zero"
                            checked={filters.zero || filters.low}
                            disabled={filters.low}
                            onCheckedChange={(checked) =>
                                applyFilters({
                                    ...filters,
                                    search,
                                    zero: checked === true,
                                })
                            }
                        />
                        <Label htmlFor="zero">Include zero stock</Label>
                    </div>

                    {isFiltered && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                applyFilters({
                                    search: '',
                                    warehouse: null,
                                    zero: false,
                                    low: false,
                                });
                            }}
                        >
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Product
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Variant
                                </th>
                                <th className="px-4 py-3 font-medium">SKU</th>
                                <th className="px-4 py-3 font-medium">
                                    Warehouse
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Reorder level
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Qty
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {balances.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No stock matches these filters.'
                                            : 'No stock yet. Receive stock to see it here.'}
                                    </td>
                                </tr>
                            )}
                            {balances.data.map((balance) => (
                                <tr
                                    key={balance.id}
                                    className={
                                        balance.is_low
                                            ? 'border-b bg-amber-50 last:border-0 dark:bg-amber-950/30'
                                            : 'border-b last:border-0'
                                    }
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={showProduct(
                                                balance.product.id,
                                            )}
                                            className="hover:underline"
                                        >
                                            {balance.product.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ColorSwatch
                                                hex={balance.hex_code}
                                            />
                                            {balance.size} / {balance.color}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {balance.sku}
                                    </td>
                                    <td className="px-4 py-3">
                                        {balance.warehouse.name}{' '}
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {balance.warehouse.code}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {balance.reorder_level ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {balance.is_low && (
                                                <Badge
                                                    variant="outline"
                                                    className="border-amber-500 text-amber-700 dark:text-amber-400"
                                                >
                                                    {balance.quantity === 0
                                                        ? 'Out'
                                                        : 'Low'}
                                                </Badge>
                                            )}
                                            <span className="font-medium tabular-nums">
                                                {balance.quantity}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {balances.total > 0 && (
                    <div className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
                        <span>
                            Showing {balances.from}–{balances.to} of{' '}
                            {balances.total}
                        </span>
                        {balances.last_page > 1 && (
                            <div className="flex gap-2">
                                <PageLink
                                    href={balances.prev_page_url}
                                    label="Previous"
                                />
                                <PageLink
                                    href={balances.next_page_url}
                                    label="Next"
                                />
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function SummaryCard({
    label,
    value,
    highlight = false,
    hint,
}: {
    label: string;
    value: number;
    highlight?: boolean;
    hint?: string;
}) {
    return (
        <Card className="h-full gap-1 py-4">
            <CardHeader className="px-4">
                <CardDescription>{label}</CardDescription>
                <CardTitle
                    className={
                        highlight
                            ? 'text-2xl text-amber-700 tabular-nums dark:text-amber-400'
                            : 'text-2xl tabular-nums'
                    }
                >
                    {value.toLocaleString()}
                </CardTitle>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </CardHeader>
        </Card>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveState preserveScroll={false}>
                {label}
            </Link>
        </Button>
    );
}

StockOverview.layout = {
    breadcrumbs: [
        {
            title: 'Stock overview',
            href: index(),
        },
    ],
};
