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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDateFormat } from '@/lib/datetime';
import { useMoneyFormat } from '@/lib/money';
import { show as showProduct } from '@/routes/catalog/products';
import { exportMethod, index } from '@/routes/reports/slow-moving';

type Row = {
    variant_id: number;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    on_hand: number;
    last_dispatched_at: string | null;
    last_arrived_at: string | null;
    idle_since: string | null;
    days_idle: number | null;
    /** Only for managers and admins; null when the variant has no cost. */
    value?: string | null;
};

type Filters = {
    search: string;
    warehouse: number | null;
    days: number;
};

type Summary = {
    variants: number;
    units: number;
    /** Only for managers and admins. */
    value?: string;
    uncosted_units?: number;
};

type Props = {
    rows: Paginated<Row>;
    summary: Summary;
    warehouses: { id: number; name: string; code: string }[];
    dayOptions: number[];
    filters: Filters;
    canSeeCosts: boolean;
};

const ALL_WAREHOUSES = 'all';
const DEFAULT_DAYS = 90;

/**
 * The query string for a set of filters, dropping defaults so the URL stays clean.
 */
function queryFor(filters: Filters) {
    return {
        ...(filters.search && { search: filters.search }),
        ...(filters.warehouse && { warehouse: filters.warehouse }),
        ...(filters.days !== DEFAULT_DAYS && { days: filters.days }),
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

export default function SlowMovingStock({
    rows,
    summary,
    warehouses,
    dayOptions,
    filters,
    canSeeCosts,
}: Props) {
    const { formatMoney } = useMoneyFormat();
    const { formatDate } = useDateFormat();
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

    const isFiltered =
        filters.search !== '' ||
        filters.warehouse !== null ||
        filters.days !== DEFAULT_DAYS;

    return (
        <>
            <Head title="Slow-moving stock" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Slow-moving stock"
                        description={`Stock with no dispatch in ${filters.days} days. Never-dispatched stock counts from when it first arrived.`}
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

                <div
                    className={
                        canSeeCosts
                            ? 'grid gap-4 sm:grid-cols-3'
                            : 'grid gap-4 sm:grid-cols-2'
                    }
                >
                    <SummaryCard
                        label="Slow-moving variants"
                        value={summary.variants.toLocaleString()}
                    />
                    <SummaryCard
                        label="Units sitting idle"
                        value={summary.units.toLocaleString()}
                    />
                    {canSeeCosts && (
                        <SummaryCard
                            label="Value tied up"
                            value={formatMoney(summary.value ?? null)}
                            hint={
                                summary.uncosted_units
                                    ? `Excludes ${summary.uncosted_units.toLocaleString()} units without a cost`
                                    : undefined
                            }
                        />
                    )}
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
                        value={String(filters.days)}
                        onValueChange={(value) =>
                            applyFilters({ ...current, days: Number(value) })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Idle for at least"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {dayOptions.map((days) => (
                                <SelectItem key={days} value={String(days)}>
                                    Idle {days}+ days
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.warehouse
                                ? String(filters.warehouse)
                                : ALL_WAREHOUSES
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
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

                    {isFiltered && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                applyFilters({
                                    search: '',
                                    warehouse: null,
                                    days: DEFAULT_DAYS,
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
                                <th className="px-4 py-3 font-medium">
                                    Last dispatched
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Last arrived
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Days idle
                                </th>
                                {canSeeCosts && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        Value
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canSeeCosts ? 8 : 7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No slow-moving stock matches these filters.'
                                            : 'No slow-moving stock. Everything in stock has moved recently.'}
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
                                    <td className="px-4 py-3">
                                        {row.last_dispatched_at ? (
                                            formatDate(row.last_dispatched_at)
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Never
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDate(row.last_arrived_at)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                        {row.days_idle === null ? (
                                            <Badge variant="outline">
                                                No history
                                            </Badge>
                                        ) : (
                                            row.days_idle.toLocaleString()
                                        )}
                                    </td>
                                    {canSeeCosts && (
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {row.value === null ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-amber-500 text-amber-700 dark:text-amber-400"
                                                >
                                                    No cost
                                                </Badge>
                                            ) : (
                                                formatMoney(row.value ?? null)
                                            )}
                                        </td>
                                    )}
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
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <Card className="h-full gap-1 py-4">
            <CardHeader className="px-4">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </CardHeader>
        </Card>
    );
}

SlowMovingStock.layout = {
    breadcrumbs: [
        {
            title: 'Slow-moving stock',
            href: index(),
        },
    ],
};
