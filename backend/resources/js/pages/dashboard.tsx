import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownToLine,
    ArrowLeftRight,
    ArrowUpFromLine,
    PackageMinus,
    PackagePlus,
    Undo2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import StockFlowChart from '@/components/stock-flow-chart';
import type { HourlyFlow } from '@/components/stock-flow-chart';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useDateFormat } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { show as showProduct } from '@/routes/catalog/products';
import { index as movementsIndex } from '@/routes/inventory/movements';
import { index as stockIndex } from '@/routes/inventory/stock';
import { create as dispatchStock } from '@/routes/operations/dispatch';
import { create as receiveStock } from '@/routes/operations/receive';
import { create as returnStock } from '@/routes/operations/return';
import { create as transferStock } from '@/routes/operations/transfer';

type Summary = {
    total_units: number;
    variants_in_stock: number;
    warehouses_in_stock: number;
    low_stock: number;
};

type LowStockRow = {
    id: number;
    quantity: number;
    reorder_level: number;
    sku: string;
    product: { id: number; name: string };
    size: string;
    color: string;
    hex_code: string | null;
    warehouse: string;
};

type Movement = {
    id: number;
    type: string;
    quantity: number;
    occurred_at: string;
    sku: string;
    product: string;
    from: string | null;
    to: string | null;
    performed_by: string | null;
};

type WarehouseUnits = { id: number; name: string; code: string; units: number };

type Props = {
    summary: Summary;
    lastSevenDays: { units_in: number; units_out: number };
    hourlyFlow: HourlyFlow[];
    lowStock: LowStockRow[];
    recentMovements: Movement[];
    warehouses: WarehouseUnits[];
};

function humanize(value: string): string {
    const text = value.replace(/_/g, ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

function StatTile({
    label,
    value,
    children,
}: {
    label: string;
    value: ReactNode;
    children?: ReactNode;
}) {
    return (
        <Card className="gap-2 py-4">
            <CardHeader className="px-4">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl font-semibold">
                    {value}
                </CardTitle>
            </CardHeader>
            {children && (
                <CardContent className="px-4 text-xs text-muted-foreground">
                    {children}
                </CardContent>
            )}
        </Card>
    );
}

export default function Dashboard({
    summary,
    lastSevenDays,
    hourlyFlow,
    lowStock,
    recentMovements,
    warehouses,
}: Props) {
    const { formatDateTime } = useDateFormat();
    const { can } = usePage().props.auth;
    const totalWarehouseUnits = warehouses.reduce(
        (sum, warehouse) => sum + warehouse.units,
        0,
    );

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Dashboard"
                        description="Your stock at a glance"
                    />
                    {can.runOperations && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={receiveStock()}>
                                    <PackagePlus /> Receive
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={transferStock()}>
                                    <ArrowLeftRight /> Transfer
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={dispatchStock()}>
                                    <PackageMinus /> Dispatch
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={returnStock()}>
                                    <Undo2 /> Return
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Units on hand"
                        value={summary.total_units.toLocaleString()}
                    >
                        Across {summary.warehouses_in_stock} warehouse(s)
                    </StatTile>
                    <StatTile
                        label="Variants in stock"
                        value={summary.variants_in_stock.toLocaleString()}
                    />
                    <StatTile
                        label="Low-stock rows"
                        value={
                            <span className="flex items-center gap-2">
                                {summary.low_stock > 0 && (
                                    <AlertTriangle
                                        className="size-5"
                                        style={{
                                            color: 'var(--viz-status-serious)',
                                        }}
                                        aria-hidden
                                    />
                                )}
                                {summary.low_stock.toLocaleString()}
                            </span>
                        }
                    >
                        {summary.low_stock > 0 ? (
                            <Link
                                href={stockIndex({ query: { low: 1 } })}
                                className="underline underline-offset-4"
                            >
                                At or below reorder level · view
                            </Link>
                        ) : (
                            'Nothing at or below its reorder level'
                        )}
                    </StatTile>
                    <StatTile
                        label="Last 7 days"
                        value={
                            <span className="flex items-baseline gap-4">
                                <span className="flex items-center gap-1">
                                    <ArrowDownToLine
                                        className="size-4 text-muted-foreground"
                                        aria-label="In"
                                    />
                                    {lastSevenDays.units_in.toLocaleString()}
                                </span>
                                <span className="flex items-center gap-1">
                                    <ArrowUpFromLine
                                        className="size-4 text-muted-foreground"
                                        aria-label="Out"
                                    />
                                    {lastSevenDays.units_out.toLocaleString()}
                                </span>
                            </span>
                        }
                    >
                        Units in (received + returned) and out (dispatched)
                    </StatTile>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Stock in and out</CardTitle>
                        <CardDescription>
                            Units per day over the last 30 days. Transfers
                            between warehouses are not counted.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <StockFlowChart hours={hourlyFlow} />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>Low stock</CardTitle>
                                <CardDescription>
                                    Furthest below the reorder level first
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={stockIndex({ query: { low: 1 } })}>
                                    View all
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {lowStock.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No variant is at or below its reorder level.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-b">
                                            <th className="py-2 pr-3 font-medium">
                                                Variant
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Warehouse
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                On hand / reorder at
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lowStock.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 pr-3">
                                                    <Link
                                                        href={showProduct(
                                                            row.product.id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {row.product.name}
                                                    </Link>
                                                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <ColorSwatch
                                                            hex={row.hex_code}
                                                            className="size-3"
                                                        />
                                                        {row.size} · {row.color}{' '}
                                                        ·{' '}
                                                        <span className="font-mono">
                                                            {row.sku}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-2 pr-3 font-mono text-xs">
                                                    {row.warehouse}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    <span className="font-medium">
                                                        {row.quantity}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        / {row.reorder_level}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>Recent movements</CardTitle>
                                <CardDescription>
                                    The latest changes to stock
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={movementsIndex()}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentMovements.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No stock has moved yet.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-b">
                                            <th className="py-2 pr-3 font-medium">
                                                When
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                What
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Where
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Qty
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentMovements.map((movement) => (
                                            <tr
                                                key={movement.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">
                                                    {formatDateTime(
                                                        movement.occurred_at,
                                                        {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </td>
                                                <td className="py-2 pr-3">
                                                    <div className="font-medium">
                                                        {humanize(
                                                            movement.type,
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {movement.product} ·{' '}
                                                        <span className="font-mono">
                                                            {movement.sku}
                                                        </span>
                                                        {movement.performed_by &&
                                                            ` · ${movement.performed_by}`}
                                                    </div>
                                                </td>
                                                <td className="py-2 pr-3 font-mono text-xs whitespace-nowrap">
                                                    {movement.from ?? '—'} →{' '}
                                                    {movement.to ?? '—'}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    {movement.quantity}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Stock by warehouse</CardTitle>
                        <CardDescription>
                            Units held in each warehouse and its share of the
                            total
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {warehouses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No warehouse holds stock yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {warehouses.map((warehouse) => {
                                    const share =
                                        totalWarehouseUnits > 0
                                            ? warehouse.units /
                                              totalWarehouseUnits
                                            : 0;

                                    return (
                                        <li key={warehouse.id}>
                                            <div className="mb-1 flex items-baseline justify-between gap-4 text-sm">
                                                <span>
                                                    {warehouse.name}{' '}
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {warehouse.code}
                                                    </span>
                                                </span>
                                                <span className="tabular-nums">
                                                    {warehouse.units.toLocaleString()}
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        {Math.round(
                                                            share * 100,
                                                        )}
                                                        %
                                                    </span>
                                                </span>
                                            </div>
                                            <div
                                                className="h-2 rounded-full"
                                                style={{
                                                    background:
                                                        'var(--viz-grid)',
                                                }}
                                                role="img"
                                                aria-label={`${warehouse.name}: ${warehouse.units} units, ${Math.round(share * 100)}% of stock`}
                                            >
                                                <div
                                                    className="h-2 rounded-full"
                                                    style={{
                                                        width: `${share * 100}%`,
                                                        background:
                                                            'var(--viz-in)',
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
