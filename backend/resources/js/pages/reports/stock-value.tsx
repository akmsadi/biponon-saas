import { Head, Link, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
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
import { useMoneyFormat } from '@/lib/money';
import { show as showProduct } from '@/routes/catalog/products';
import { exportMethod, index } from '@/routes/reports/stock-value';

type Row = {
    variant_id: number;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    on_hand: number;
    average_cost: string | null;
    value: string | null;
};

type Filters = {
    search: string;
    warehouse: number | null;
    uncosted: boolean;
};

type Summary = {
    total_value: string;
    costed_units: number;
    uncosted_units: number;
    uncosted_variants: number;
};

type Props = {
    rows: Paginated<Row>;
    summary: Summary;
    warehouses: { id: number; name: string; code: string }[];
    filters: Filters;
};

const ALL_WAREHOUSES = 'all';

/**
 * The query string for a set of filters, dropping defaults so the URL stays clean.
 */
function queryFor(filters: Filters) {
    return {
        ...(filters.search && { search: filters.search }),
        ...(filters.warehouse && { warehouse: filters.warehouse }),
        ...(filters.uncosted && { uncosted: 1 }),
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

export default function StockValue({
    rows,
    summary,
    warehouses,
    filters,
}: Props) {
    const { formatMoney } = useMoneyFormat();
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
        filters.search !== '' || filters.warehouse !== null || filters.uncosted;

    return (
        <>
            <Head title="Stock value" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Stock value"
                        description="Stock on hand valued at the weighted average cost of purchase order receipts"
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

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="Stock value"
                        value={formatMoney(summary.total_value)}
                        hint={
                            summary.uncosted_units > 0
                                ? 'Excludes units without a cost'
                                : undefined
                        }
                    />
                    <SummaryCard
                        label="Units with a cost"
                        value={summary.costed_units.toLocaleString()}
                    />
                    <button
                        type="button"
                        className="rounded-xl text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        onClick={() =>
                            applyFilters({
                                ...filters,
                                search,
                                uncosted: !filters.uncosted,
                            })
                        }
                        aria-pressed={filters.uncosted}
                    >
                        <SummaryCard
                            label="Units without a cost"
                            value={summary.uncosted_units.toLocaleString()}
                            highlight={summary.uncosted_units > 0}
                            hint={
                                summary.uncosted_variants > 0
                                    ? `${summary.uncosted_variants.toLocaleString()} ${summary.uncosted_variants === 1 ? 'variant' : 'variants'} never received with a cost · ${filters.uncosted ? 'showing these only' : 'click to show'}`
                                    : 'Every variant in stock has a cost'
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
                            id="uncosted"
                            checked={filters.uncosted}
                            onCheckedChange={(checked) =>
                                applyFilters({
                                    ...filters,
                                    search,
                                    uncosted: checked === true,
                                })
                            }
                        />
                        <Label htmlFor="uncosted">Without a cost only</Label>
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
                                    uncosted: false,
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
                                <th className="px-4 py-3 text-right font-medium">
                                    On hand
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Average cost
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Value
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No stock matches these filters.'
                                            : 'No stock on hand yet.'}
                                    </td>
                                </tr>
                            )}
                            {rows.data.map((row) => (
                                <tr
                                    key={row.variant_id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={showProduct(row.product.id)}
                                            className="hover:underline"
                                        >
                                            {row.product.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ColorSwatch hex={row.hex_code} />
                                            {row.size} / {row.color}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {row.sku}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.on_hand.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {formatMoney(row.average_cost)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                        {row.value === null ? (
                                            <Badge
                                                variant="outline"
                                                className="border-amber-500 text-amber-700 dark:text-amber-400"
                                            >
                                                No cost
                                            </Badge>
                                        ) : (
                                            formatMoney(row.value)
                                        )}
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

function SummaryCard({
    label,
    value,
    highlight = false,
    hint,
}: {
    label: string;
    value: string;
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
                    {value}
                </CardTitle>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </CardHeader>
        </Card>
    );
}

StockValue.layout = {
    breadcrumbs: [
        {
            title: 'Stock value',
            href: index(),
        },
    ],
};
