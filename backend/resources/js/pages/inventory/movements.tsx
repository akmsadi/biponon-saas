import { Head, Link, router, useHttp } from '@inertiajs/react';
import { Download, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useDateFormat } from '@/lib/datetime';
import { useMoneyFormat } from '@/lib/money';
import { show as showProduct } from '@/routes/catalog/products';
import { exportMethod, index, serials } from '@/routes/inventory/movements';
import { show as showPurchaseOrder } from '@/routes/purchasing/orders';
import { show as showSalesOrder } from '@/routes/sales/orders';

type Warehouse = { name: string; code: string };

type Movement = {
    id: number;
    movement_type: string;
    quantity: number;
    direction: 'in' | 'out' | null;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    from: Warehouse | null;
    to: Warehouse | null;
    performed_by: string | null;
    notes: string | null;
    serial_count: number;
    purchase_order: { id: number; number: string } | null;
    sales_order: { id: number; number: string } | null;
    unit_cost?: string | null;
    occurred_at: string;
};

type Filters = {
    search: string;
    type: string | null;
    warehouse: number | null;
    product: number | null;
    date_from: string | null;
    date_to: string | null;
};

type Props = {
    movements: Paginated<Movement>;
    warehouses: { id: number; name: string; code: string }[];
    types: string[];
    product: { id: number; name: string } | null;
    filters: Filters;
    canSeeCosts: boolean;
};

type SerialRow = { id: number; serial_number: string; status: string };

const ALL = 'all';

/**
 * Turn a stored code such as in_stock into "In stock".
 */
function humanize(value: string): string {
    const words = value.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

/**
 * The query string for a set of filters, without defaults. The server reads dates as whole days in the
 * organization's timezone.
 */
function queryFor(filters: Filters): Record<string, string | number> {
    return {
        ...(filters.search && { search: filters.search }),
        ...(filters.type && { type: filters.type }),
        ...(filters.warehouse && { warehouse: filters.warehouse }),
        ...(filters.product && { product: filters.product }),
        ...(filters.date_from && { date_from: filters.date_from }),
        ...(filters.date_to && { date_to: filters.date_to }),
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

const NO_FILTERS: Filters = {
    search: '',
    type: null,
    warehouse: null,
    product: null,
    date_from: null,
    date_to: null,
};

export default function StockMovements({
    movements,
    warehouses,
    types,
    product,
    filters,
    canSeeCosts,
}: Props) {
    const { formatDateTime } = useDateFormat();
    const { formatMoney } = useMoneyFormat();
    const [search, setSearch] = useState(filters.search);
    const [viewingSerials, setViewingSerials] = useState<Movement | null>(null);
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

    const current: Filters = { ...filters, search };
    const isFiltered = Object.entries(filters).some(
        ([, value]) => value !== null && value !== '',
    );

    return (
        <>
            <Head title="Stock movements" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Stock movements"
                        description="Every change to stock, newest first"
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

                <div className="flex flex-wrap items-end gap-4">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search product, SKU or order number"
                            aria-label="Search product, SKU or order number"
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={filters.type ?? ALL}
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                type: value === ALL ? null : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-40"
                            aria-label="Filter by type"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All types</SelectItem>
                            {types.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {humanize(type)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.warehouse ? String(filters.warehouse) : ALL
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                warehouse: value === ALL ? null : Number(value),
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
                            <SelectItem value={ALL}>All warehouses</SelectItem>
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

                    <div className="grid gap-1">
                        <Label
                            htmlFor="date_from"
                            className="text-xs text-muted-foreground"
                        >
                            From
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

                    {isFiltered && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                applyFilters(NO_FILTERS);
                            }}
                        >
                            Clear filters
                        </Button>
                    )}
                </div>

                {product && (
                    <div>
                        <Badge variant="secondary" className="gap-1 pr-1">
                            Product: {product.name}
                            <button
                                type="button"
                                className="rounded-sm p-0.5 hover:bg-muted"
                                onClick={() =>
                                    applyFilters({ ...current, product: null })
                                }
                            >
                                <X className="size-3" />
                                <span className="sr-only">
                                    Remove product filter
                                </span>
                            </button>
                        </Badge>
                    </div>
                )}

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">
                                    Product
                                </th>
                                <th className="px-4 py-3 font-medium">SKU</th>
                                <th className="px-4 py-3 font-medium">
                                    From → To
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Qty
                                </th>
                                {canSeeCosts && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        Unit cost
                                    </th>
                                )}
                                <th className="px-4 py-3 font-medium">By</th>
                                <th className="px-4 py-3 font-medium">
                                    Serials
                                </th>
                                <th className="px-4 py-3 font-medium">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canSeeCosts ? 10 : 9}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No movements match these filters.'
                                            : 'No movements yet. Receive stock to start the ledger.'}
                                    </td>
                                </tr>
                            )}
                            {movements.data.map((movement) => (
                                <tr
                                    key={movement.id}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(movement.occurred_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {humanize(movement.movement_type)}
                                        </Badge>
                                        {movement.purchase_order && (
                                            <Link
                                                href={showPurchaseOrder(
                                                    movement.purchase_order.id,
                                                )}
                                                className="mt-1 block font-mono text-xs text-muted-foreground hover:underline"
                                            >
                                                {movement.purchase_order.number}
                                            </Link>
                                        )}
                                        {movement.sales_order && (
                                            <Link
                                                href={showSalesOrder(
                                                    movement.sales_order.id,
                                                )}
                                                className="mt-1 block font-mono text-xs text-muted-foreground hover:underline"
                                            >
                                                {movement.sales_order.number}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            href={showProduct(
                                                movement.product.id,
                                            )}
                                            className="font-medium hover:underline"
                                        >
                                            {movement.product.name}
                                        </Link>
                                        <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <ColorSwatch
                                                hex={movement.hex_code}
                                                className="size-3"
                                            />
                                            {movement.size} / {movement.color}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {movement.sku}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                        {movement.from?.code ?? '—'} →{' '}
                                        {movement.to?.code ?? '—'}
                                    </td>
                                    <td
                                        className={
                                            movement.direction === 'in'
                                                ? 'px-4 py-3 text-right font-medium text-emerald-700 tabular-nums dark:text-emerald-400'
                                                : movement.direction === 'out'
                                                  ? 'px-4 py-3 text-right font-medium text-red-700 tabular-nums dark:text-red-400'
                                                  : 'px-4 py-3 text-right font-medium tabular-nums'
                                        }
                                    >
                                        {movement.direction === 'in' && '+'}
                                        {movement.direction === 'out' && '−'}
                                        {movement.quantity}
                                    </td>
                                    {canSeeCosts && (
                                        <td className="px-4 py-3 text-right whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatMoney(
                                                movement.unit_cost ?? null,
                                            )}
                                        </td>
                                    )}
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {movement.performed_by ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {movement.serial_count > 0 ? (
                                            <button
                                                type="button"
                                                className="text-primary hover:underline"
                                                onClick={() =>
                                                    setViewingSerials(movement)
                                                }
                                            >
                                                {movement.serial_count}
                                            </button>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="max-w-64 px-4 py-3 text-muted-foreground">
                                        <span className="line-clamp-2">
                                            {movement.notes ?? '—'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={movements} />
            </div>

            <SerialsDialog
                movement={viewingSerials}
                onClose={() => setViewingSerials(null)}
            />
        </>
    );
}

function SerialsDialog({
    movement,
    onClose,
}: {
    movement: Movement | null;
    onClose: () => void;
}) {
    const { formatDateTime } = useDateFormat();
    const [rows, setRows] = useState<SerialRow[] | null>(null);
    const [failed, setFailed] = useState(false);
    const { get, processing } = useHttp<
        Record<string, never>,
        { serials: SerialRow[] }
    >({});

    useEffect(() => {
        setRows(null);
        setFailed(false);

        if (!movement) {
            return;
        }

        // Ignore a late response if the dialog moved on to another movement or closed.
        let isCurrent = true;

        get(serials.url(movement.id))
            .then((response) => isCurrent && setRows(response.serials))
            .catch(() => isCurrent && setFailed(true));

        return () => {
            isCurrent = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [movement?.id]);

    return (
        <Dialog
            open={movement !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogTitle>Serials in this movement</DialogTitle>
                <DialogDescription>
                    {movement &&
                        `${humanize(movement.movement_type)} · ${movement.product.name} · ${movement.size} / ${movement.color} · ${formatDateTime(movement.occurred_at)}`}
                </DialogDescription>

                {processing && rows === null && (
                    <div className="flex justify-center py-6">
                        <Spinner />
                    </div>
                )}

                {failed && (
                    <p className="text-sm text-destructive">
                        Could not load the serials. Try again.
                    </p>
                )}

                {rows && (
                    <ul className="divide-y rounded-lg border">
                        {rows.map((serial) => (
                            <li
                                key={serial.id}
                                className="flex items-center justify-between gap-4 px-3 py-2 text-sm"
                            >
                                <span className="font-mono text-xs">
                                    {serial.serial_number}
                                </span>
                                <Badge
                                    variant={
                                        serial.status === 'in_stock'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {humanize(serial.status)}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}

StockMovements.layout = {
    breadcrumbs: [
        {
            title: 'Stock movements',
            href: index(),
        },
    ],
};
