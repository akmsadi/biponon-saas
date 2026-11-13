import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import PurchaseOrderStatusBadge, {
    PURCHASE_ORDER_STATUSES,
} from '@/components/purchase-order-status';
import type { PurchaseOrderStatus } from '@/components/purchase-order-status';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/datetime';
import { useMoneyFormat } from '@/lib/money';
import { create, index, show } from '@/routes/purchasing/orders';

type OrderRow = {
    id: number;
    number: string;
    status: PurchaseOrderStatus;
    supplier: string;
    warehouse: { name: string; code: string };
    expected_at: string | null;
    lines_count: number;
    units_ordered: number;
    units_received: number;
    total_cost?: string | null;
    has_uncosted_lines?: boolean;
};

type Filters = {
    search: string;
    status: PurchaseOrderStatus | null;
    supplier: number | null;
    warehouse: number | null;
};

type Props = {
    orders: Paginated<OrderRow>;
    suppliers: { id: number; name: string }[];
    warehouses: { id: number; name: string; code: string }[];
    filters: Filters;
    canSeeCosts: boolean;
};

const ALL = 'all';

/**
 * Visit the page with new filters, leaving out the defaults. Changing a filter resets to page 1.
 */
function applyFilters(filters: Filters) {
    router.get(
        index.url(),
        {
            ...(filters.search && { search: filters.search }),
            ...(filters.status && { status: filters.status }),
            ...(filters.supplier && { supplier: filters.supplier }),
            ...(filters.warehouse && { warehouse: filters.warehouse }),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function PurchaseOrders({
    orders,
    suppliers,
    warehouses,
    filters,
    canSeeCosts,
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

    const current: Filters = { ...filters, search };
    const isFiltered =
        filters.search !== '' ||
        filters.status !== null ||
        filters.supplier !== null ||
        filters.warehouse !== null;
    const columnCount = canSeeCosts ? 7 : 6;

    return (
        <>
            <Head title="Purchase orders" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Purchase orders"
                        description="Stock ordered from suppliers, newest first"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> New purchase order
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-end gap-4">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search PO number or supplier"
                            aria-label="Search PO number or supplier"
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                status:
                                    value === ALL
                                        ? null
                                        : (value as PurchaseOrderStatus),
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
                            {PURCHASE_ORDER_STATUSES.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.supplier ? String(filters.supplier) : ALL
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                supplier: value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-52"
                            aria-label="Filter by supplier"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All suppliers</SelectItem>
                            {suppliers.map((supplier) => (
                                <SelectItem
                                    key={supplier.id}
                                    value={String(supplier.id)}
                                >
                                    {supplier.name}
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

                    {isFiltered && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                applyFilters({
                                    search: '',
                                    status: null,
                                    supplier: null,
                                    warehouse: null,
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
                                    Number
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Supplier
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Deliver to
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Received / ordered
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Expected
                                </th>
                                {canSeeCosts && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columnCount}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No purchase orders match these filters.'
                                            : 'No purchase orders yet.'}
                                    </td>
                                </tr>
                            )}
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(order.id)}
                                            className="font-mono font-medium underline-offset-4 hover:underline"
                                        >
                                            {order.number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {order.supplier}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {order.warehouse.name} (
                                        {order.warehouse.code})
                                    </td>
                                    <td className="px-4 py-3">
                                        <PurchaseOrderStatusBadge
                                            status={order.status}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {order.units_received} /{' '}
                                        {order.units_ordered}
                                        <div className="text-xs text-muted-foreground">
                                            {order.lines_count} line(s)
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {/* A calendar date, not an instant: show it as stored. */}
                                        {formatDate(order.expected_at, 'UTC')}
                                    </td>
                                    {canSeeCosts && (
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatMoney(
                                                order.total_cost ?? null,
                                            )}
                                            {order.has_uncosted_lines &&
                                                order.total_cost !== null && (
                                                    <div className="text-xs text-muted-foreground">
                                                        some lines without cost
                                                    </div>
                                                )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={orders} />
            </div>
        </>
    );
}

PurchaseOrders.layout = {
    breadcrumbs: [
        {
            title: 'Purchase orders',
            href: index(),
        },
    ],
};
