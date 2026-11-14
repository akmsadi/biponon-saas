import { Head, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import { useMoneyFormat } from '@/lib/money';
import { exportMethod, index } from '@/routes/reports/purchases';

type Status = 'all' | 'open' | 'received' | 'closed';

type Totals = {
    orders: number;
    open_orders: number;
    units_ordered: number;
    units_received: number;
    units_outstanding: number;
    /** Only for managers and admins. */
    amount_ordered?: string;
    amount_received?: string;
};

type Row = Totals & { supplier: { id: number; name: string } };

type Filters = {
    search: string;
    status: Status;
    date_from: string | null;
    date_to: string | null;
};

type Props = {
    rows: Paginated<Row>;
    summary: Totals;
    filters: Filters;
    canSeeCosts: boolean;
};

const STATUS_LABELS: Record<Status, string> = {
    all: 'All placed orders',
    open: 'Open (still receiving)',
    received: 'Fully received',
    closed: 'Closed short',
};

/**
 * The query string for a set of filters. Dates are always sent (empty means open-ended), because without them the
 * server falls back to the last 30 days.
 */
function queryFor(filters: Filters) {
    return {
        ...(filters.search && { search: filters.search }),
        ...(filters.status !== 'all' && { status: filters.status }),
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

export default function PurchasesReport({
    rows,
    summary,
    filters,
    canSeeCosts,
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

    return (
        <>
            <Head title="Purchases report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Purchases by supplier"
                        description="Placed purchase orders by the date they were placed. Still to come counts open orders only."
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
                            ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4'
                            : 'grid gap-4 sm:grid-cols-3'
                    }
                >
                    <SummaryCard
                        label="Orders placed"
                        value={summary.orders.toLocaleString()}
                        hint={`${summary.open_orders.toLocaleString()} still open`}
                    />
                    <SummaryCard
                        label="Units received"
                        value={summary.units_received.toLocaleString()}
                        hint={`of ${summary.units_ordered.toLocaleString()} ordered`}
                    />
                    <SummaryCard
                        label="Still to come"
                        value={summary.units_outstanding.toLocaleString()}
                    />
                    {canSeeCosts && (
                        <SummaryCard
                            label="Amount received"
                            value={formatMoney(summary.amount_received ?? null)}
                            hint={`of ${formatMoney(summary.amount_ordered ?? null)} ordered · costed lines only`}
                        />
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-4">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search supplier"
                            aria-label="Search supplier"
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={filters.status}
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                status: value as Status,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-56"
                            aria-label="Filter by status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(Object.keys(STATUS_LABELS) as Status[]).map(
                                (status) => (
                                    <SelectItem key={status} value={status}>
                                        {STATUS_LABELS[status]}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>

                    <div className="grid gap-1">
                        <Label
                            htmlFor="date_from"
                            className="text-xs text-muted-foreground"
                        >
                            Placed from
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
                                <th className="px-4 py-3 font-medium">
                                    Supplier
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Orders
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Open
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Units ordered
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Received
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Still to come
                                </th>
                                {canSeeCosts && (
                                    <>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Amount ordered
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Amount received
                                        </th>
                                    </>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canSeeCosts ? 8 : 6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No placed purchase orders match these
                                        filters.
                                    </td>
                                </tr>
                            )}
                            {rows.data.map((row) => (
                                <tr
                                    key={row.supplier.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {row.supplier.name}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.orders.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {row.open_orders.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.units_ordered.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.units_received.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                        {row.units_outstanding.toLocaleString()}
                                    </td>
                                    {canSeeCosts && (
                                        <>
                                            <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                                {formatMoney(
                                                    row.amount_ordered ?? null,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatMoney(
                                                    row.amount_received ?? null,
                                                )}
                                            </td>
                                        </>
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

PurchasesReport.layout = {
    breadcrumbs: [
        {
            title: 'Purchases report',
            href: index(),
        },
    ],
};
