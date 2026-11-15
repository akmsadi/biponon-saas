import { Head, Link, router, useHttp } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useDateFormat } from '@/lib/datetime';
import { show as showProduct } from '@/routes/catalog/products';
import { history, index } from '@/routes/inventory/serials';

type Warehouse = { name: string; code: string };

type Serial = {
    id: number;
    serial_number: string;
    status: string;
    received_at: string | null;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    warehouse: Warehouse | null;
};

type Movement = {
    id: number;
    movement_type: string;
    quantity: number;
    from: Warehouse | null;
    to: Warehouse | null;
    performed_by: string | null;
    notes: string | null;
    occurred_at: string;
};

type Filters = {
    search: string;
    warehouse: number | null;
    status: string | null;
};

type Props = {
    serials: Paginated<Serial>;
    warehouses: { id: number; name: string; code: string }[];
    statuses: string[];
    filters: Filters;
};

const ALL = 'all';

/**
 * Turn a stored code such as in_stock into "In stock".
 */
function humanize(value: string): string {
    const words = value.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

/**
 * Visit the page with new filters, dropping defaults so the URL stays clean. Changing a filter resets to page 1.
 */
function applyFilters(filters: Filters) {
    router.get(
        index.url(),
        {
            ...(filters.search && { search: filters.search }),
            ...(filters.warehouse && { warehouse: filters.warehouse }),
            ...(filters.status && { status: filters.status }),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function SerialNumbers({
    serials,
    warehouses,
    statuses,
    filters,
}: Props) {
    const { formatDateTime } = useDateFormat();
    const [search, setSearch] = useState(filters.search);
    const [viewing, setViewing] = useState<Serial | null>(null);
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
        filters.status !== null;

    return (
        <>
            <Head title="Serial numbers" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Serial numbers"
                    description="Individually tracked units, where they are now and how they got there"
                />

                <div className="flex flex-wrap items-center gap-4">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search serial, SKU or product"
                            aria-label="Search serial, SKU or product"
                            maxLength={150}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={
                            filters.warehouse ? String(filters.warehouse) : ALL
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...filters,
                                search,
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

                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            applyFilters({
                                ...filters,
                                search,
                                status: value === ALL ? null : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Filter by status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {humanize(status)}
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
                                    status: null,
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
                                    Serial
                                </th>
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
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Received
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {serials.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No serial numbers match these filters.'
                                            : 'No serial numbers yet. Add them when receiving stock.'}
                                    </td>
                                </tr>
                            )}
                            {serials.data.map((serial) => (
                                <tr
                                    key={serial.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <button
                                            type="button"
                                            className="font-mono text-xs font-medium hover:underline"
                                            onClick={() => setViewing(serial)}
                                        >
                                            {serial.serial_number}
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            href={showProduct(
                                                serial.product.id,
                                            )}
                                            className="hover:underline"
                                        >
                                            {serial.product.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ColorSwatch
                                                hex={serial.hex_code}
                                            />
                                            {serial.size} / {serial.color}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {serial.sku}
                                    </td>
                                    <td className="px-4 py-3">
                                        {serial.warehouse ? (
                                            <>
                                                {serial.warehouse.name}{' '}
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {serial.warehouse.code}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                serial.status === 'in_stock'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {humanize(serial.status)}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDateTime(serial.received_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={serials} />
            </div>

            <HistoryDialog serial={viewing} onClose={() => setViewing(null)} />
        </>
    );
}

function HistoryDialog({
    serial,
    onClose,
}: {
    serial: Serial | null;
    onClose: () => void;
}) {
    const { formatDateTime } = useDateFormat();
    const [movements, setMovements] = useState<Movement[] | null>(null);
    const [failed, setFailed] = useState(false);
    const { get, processing } = useHttp<
        Record<string, never>,
        { movements: Movement[] }
    >({});

    useEffect(() => {
        setMovements(null);
        setFailed(false);

        if (!serial) {
            return;
        }

        // Ignore a late response if the dialog moved on to another serial or closed.
        let isCurrent = true;

        get(history.url(serial.id))
            .then((response) => isCurrent && setMovements(response.movements))
            .catch(() => isCurrent && setFailed(true));

        return () => {
            isCurrent = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serial?.id]);

    return (
        <Dialog
            open={serial !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogTitle className="font-mono">
                    {serial?.serial_number}
                </DialogTitle>
                <DialogDescription>
                    {serial &&
                        `${serial.product.name} · ${serial.size} / ${serial.color} · ${serial.sku}`}
                </DialogDescription>

                {processing && movements === null && (
                    <div className="flex justify-center py-6">
                        <Spinner />
                    </div>
                )}

                {failed && (
                    <p className="text-sm text-destructive">
                        Could not load the history. Try again.
                    </p>
                )}

                {movements?.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No movements recorded for this unit.
                    </p>
                )}

                {movements && movements.length > 0 && (
                    <ol className="relative space-y-4 border-l pl-5">
                        {movements.map((movement) => (
                            <li key={movement.id} className="relative">
                                <span className="absolute top-1.5 -left-[25px] size-2.5 rounded-full border-2 border-background bg-primary" />
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <Badge variant="secondary">
                                        {humanize(movement.movement_type)}
                                    </Badge>
                                    <span>
                                        {movement.from
                                            ? movement.from.code
                                            : '—'}{' '}
                                        → {movement.to ? movement.to.code : '—'}
                                    </span>
                                    <span className="text-muted-foreground">
                                        ({movement.quantity} units in this
                                        movement)
                                    </span>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatDateTime(movement.occurred_at)}
                                    {movement.performed_by &&
                                        ` · by ${movement.performed_by}`}
                                </div>
                                {movement.notes && (
                                    <p className="mt-1 text-sm">
                                        {movement.notes}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </DialogContent>
        </Dialog>
    );
}

SerialNumbers.layout = {
    breadcrumbs: [
        {
            title: 'Serial numbers',
            href: index(),
        },
    ],
};
