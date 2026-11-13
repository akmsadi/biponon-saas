import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import SalesOrderStatusBadge, {
    SALES_ORDER_STATUSES,
} from '@/components/sales-order-status';
import type { SalesOrderStatus } from '@/components/sales-order-status';
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
import { create, index, show } from '@/routes/sales/orders';

type OrderRow = {
    id: number;
    number: string;
    status: SalesOrderStatus;
    customer: string;
    warehouse: { name: string; code: string };
    deliver_by: string | null;
    lines_count: number;
    units_ordered: number;
    units_dispatched: number;
    total_price: string | null;
};

type Filters = {
    search: string;
    status: SalesOrderStatus | null;
    customer: number | null;
    warehouse: number | null;
};

type Props = {
    orders: Paginated<OrderRow>;
    customers: { id: number; name: string }[];
    warehouses: { id: number; name: string; code: string }[];
    filters: Filters;
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
            ...(filters.customer && { customer: filters.customer }),
            ...(filters.warehouse && { warehouse: filters.warehouse }),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function SalesOrders({
    orders,
    customers,
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

    const current: Filters = { ...filters, search };
    const isFiltered =
        filters.search !== '' ||
        filters.status !== null ||
        filters.customer !== null ||
        filters.warehouse !== null;

    return (
        <>
            <Head title="Sales orders" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Sales orders"
                        description="Orders from customers, newest first"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> New sales order
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
                            placeholder="Search SO number or customer"
                            aria-label="Search SO number or customer"
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
                                        : (value as SalesOrderStatus),
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
                            {SALES_ORDER_STATUSES.map((status) => (
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
                            filters.customer ? String(filters.customer) : ALL
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                ...current,
                                customer: value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-52"
                            aria-label="Filter by customer"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All customers</SelectItem>
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
                                    customer: null,
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
                                    Customer
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Ship from
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Dispatched / ordered
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Deliver by
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {isFiltered
                                            ? 'No sales orders match these filters.'
                                            : 'No sales orders yet.'}
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
                                        {order.customer}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {order.warehouse.name} (
                                        {order.warehouse.code})
                                    </td>
                                    <td className="px-4 py-3">
                                        <SalesOrderStatusBadge
                                            status={order.status}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {order.units_dispatched} /{' '}
                                        {order.units_ordered}
                                        <div className="text-xs text-muted-foreground">
                                            {order.lines_count} line(s)
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {/* A calendar date, not an instant: show it as stored. */}
                                        {formatDate(order.deliver_by, 'UTC')}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatMoney(order.total_price)}
                                    </td>
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

SalesOrders.layout = {
    breadcrumbs: [
        {
            title: 'Sales orders',
            href: index(),
        },
    ],
};
