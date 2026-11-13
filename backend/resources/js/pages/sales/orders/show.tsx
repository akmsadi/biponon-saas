import { Form, Head, Link } from '@inertiajs/react';
import {
    Ban,
    CircleCheck,
    CircleStop,
    PackageMinus,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import SalesOrderController from '@/actions/App/Http/Controllers/Sales/SalesOrderController';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import SalesOrderStatusBadge from '@/components/sales-order-status';
import type { SalesOrderStatus } from '@/components/sales-order-status';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate, useDateFormat } from '@/lib/datetime';
import { useMoneyFormat } from '@/lib/money';
import { show as showProduct } from '@/routes/catalog/products';
import { edit, index } from '@/routes/sales/orders';
import { create as dispatch } from '@/routes/sales/orders/dispatch';

type Line = {
    id: number;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    quantity_ordered: number;
    quantity_dispatched: number;
    unit_price: string;
    total_price: string;
    in_stock_here?: number;
    in_stock_total?: number;
};

type Order = {
    id: number;
    number: string;
    status: SalesOrderStatus;
    customer: {
        id: number;
        name: string;
        contact_name: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
    warehouse: { id: number; name: string; code: string };
    deliver_by: string | null;
    notes: string | null;
    created_by: string | null;
    created_at: string;
    confirmed_at: string | null;
    cancelled_at: string | null;
    closed_at: string | null;
    units_ordered: number;
    units_dispatched: number;
    total_price: string;
    lines: Line[];
};

type Dispatch = {
    id: number;
    occurred_at: string;
    product: string;
    sku: string;
    warehouse: { name: string; code: string };
    quantity: number;
    serial_count: number;
    performed_by: string | null;
    notes: string | null;
};

type Props = {
    order: Order;
    dispatches: Dispatch[];
    can: {
        dispatch: boolean;
        edit: boolean;
        delete: boolean;
        confirm: boolean;
        cancel: boolean;
        close: boolean;
    };
};

type Confirming = 'confirm' | 'cancel' | 'close' | 'delete' | null;

/**
 * Units still to send that the ship-from warehouse can't cover right now, or 0.
 */
function shortBy(line: Line): number {
    if (line.in_stock_here === undefined) {
        return 0;
    }

    return Math.max(
        0,
        line.quantity_ordered - line.quantity_dispatched - line.in_stock_here,
    );
}

export default function SalesOrderShow({ order, dispatches, can }: Props) {
    const { formatDateTime } = useDateFormat();
    const { formatMoney } = useMoneyFormat();
    const [confirming, setConfirming] = useState<Confirming>(null);
    const showsStock = order.lines.some(
        (line) => line.in_stock_here !== undefined,
    );
    const shortLines = order.lines.filter((line) => shortBy(line) > 0);

    return (
        <>
            <Head title={order.number} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Heading
                            title={order.number}
                            description={`For ${order.customer.name}, shipping from ${order.warehouse.name}`}
                        />
                        <div className="pt-1.5">
                            <SalesOrderStatusBadge status={order.status} />
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {can.edit && (
                            <Button variant="outline" asChild>
                                <Link href={edit(order.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirming('delete')}
                            >
                                <Trash2 /> Delete draft
                            </Button>
                        )}
                        {can.cancel && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirming('cancel')}
                            >
                                <Ban /> Cancel order
                            </Button>
                        )}
                        {can.close && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirming('close')}
                            >
                                <CircleStop /> Close order
                            </Button>
                        )}
                        {can.confirm && (
                            <Button onClick={() => setConfirming('confirm')}>
                                <CircleCheck /> Confirm order
                            </Button>
                        )}
                        {can.dispatch && (
                            <Button asChild>
                                <Link href={dispatch(order.id)}>
                                    <PackageMinus /> Dispatch
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {order.status === 'draft' && !can.confirm && (
                    <p className="max-w-4xl rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                        This is a draft. A manager or admin confirms it once the
                        customer has agreed to it.
                    </p>
                )}

                {shortLines.length > 0 && (
                    <p className="max-w-4xl rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                        {order.warehouse.code} doesn't have enough stock for{' '}
                        {shortLines.length} line(s) right now. The order can
                        still be confirmed and sent later, or from another
                        warehouse.
                    </p>
                )}

                <div className="grid max-w-4xl gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div className="font-medium">
                                {order.customer.name}
                            </div>
                            {order.customer.contact_name && (
                                <div>{order.customer.contact_name}</div>
                            )}
                            {order.customer.email && (
                                <a
                                    href={`mailto:${order.customer.email}`}
                                    className="block text-muted-foreground underline-offset-4 hover:underline"
                                >
                                    {order.customer.email}
                                </a>
                            )}
                            {order.customer.phone && (
                                <div className="text-muted-foreground">
                                    {order.customer.phone}
                                </div>
                            )}
                            {order.customer.address && (
                                <div className="text-muted-foreground">
                                    {order.customer.address}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                <dt className="text-muted-foreground">
                                    Ship from
                                </dt>
                                <dd>
                                    {order.warehouse.name} (
                                    {order.warehouse.code})
                                </dd>
                                <dt className="text-muted-foreground">
                                    Deliver by
                                </dt>
                                <dd>{formatDate(order.deliver_by, 'UTC')}</dd>
                                <dt className="text-muted-foreground">
                                    Created
                                </dt>
                                <dd>
                                    {formatDateTime(order.created_at)}
                                    {order.created_by &&
                                        ` by ${order.created_by}`}
                                </dd>
                                {order.confirmed_at && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Confirmed
                                        </dt>
                                        <dd>
                                            {formatDateTime(order.confirmed_at)}
                                        </dd>
                                    </>
                                )}
                                {order.closed_at && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Closed
                                        </dt>
                                        <dd>
                                            {formatDateTime(order.closed_at)}
                                        </dd>
                                    </>
                                )}
                                {order.cancelled_at && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Cancelled
                                        </dt>
                                        <dd>
                                            {formatDateTime(order.cancelled_at)}
                                        </dd>
                                    </>
                                )}
                            </dl>
                            {order.notes && (
                                <p className="mt-3 text-sm whitespace-pre-line text-muted-foreground">
                                    {order.notes}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="max-w-4xl overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Product
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Variant
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Ordered
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Dispatched
                                </th>
                                {showsStock && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        In stock
                                    </th>
                                )}
                                <th className="px-4 py-3 text-right font-medium">
                                    Unit price
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.lines.map((line) => (
                                <tr
                                    key={line.id}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={showProduct(line.product.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {line.product.name}
                                        </Link>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {line.sku}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="inline-flex items-center gap-2">
                                            <ColorSwatch
                                                hex={line.hex_code}
                                                className="size-3.5"
                                            />
                                            {line.size} · {line.color}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {line.quantity_ordered}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {line.quantity_dispatched}
                                    </td>
                                    {showsStock && (
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            <span
                                                className={
                                                    shortBy(line) > 0
                                                        ? 'font-medium text-amber-700 dark:text-amber-400'
                                                        : undefined
                                                }
                                            >
                                                {line.in_stock_here} at{' '}
                                                {order.warehouse.code}
                                            </span>
                                            <div className="text-xs text-muted-foreground">
                                                {line.in_stock_total} in all
                                            </div>
                                        </td>
                                    )}
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatMoney(line.unit_price)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatMoney(line.total_price)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-muted/30 font-medium">
                            <tr>
                                <td className="px-4 py-3" colSpan={2}>
                                    Total
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {order.units_ordered}
                                </td>
                                <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                    {order.units_dispatched}
                                </td>
                                {showsStock && <td />}
                                <td />
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {formatMoney(order.total_price)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {dispatches.length > 0 && (
                    <section className="max-w-4xl space-y-3">
                        <h2 className="text-base font-semibold">Dispatches</h2>
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Date
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Product
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            From
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Qty
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Serials
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            By
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {dispatches.map((sent) => (
                                        <tr
                                            key={sent.id}
                                            className="border-b align-top last:border-0"
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    sent.occurred_at,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {sent.product}
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {sent.sku}
                                                </div>
                                                {sent.notes && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {sent.notes}
                                                    </div>
                                                )}
                                            </td>
                                            <td
                                                className="px-4 py-3 font-mono text-xs"
                                                title={sent.warehouse.name}
                                            >
                                                {sent.warehouse.code}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {sent.quantity}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                                {sent.serial_count || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {sent.performed_by ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>

            <Dialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
            >
                <DialogContent>
                    {confirming === 'confirm' && (
                        <ConfirmAction
                            title={`Confirm ${order.number}?`}
                            description={`Do this once ${order.customer.name} has agreed to the order. Its lines can't be changed afterwards.${
                                shortLines.length > 0
                                    ? ` ${shortLines.length} line(s) are short at ${order.warehouse.code}; they'll wait as a backorder or can be sent from another warehouse.`
                                    : ''
                            }`}
                            form={SalesOrderController.confirm.form(order.id)}
                            label="Confirm order"
                            onDone={() => setConfirming(null)}
                        />
                    )}
                    {confirming === 'cancel' && (
                        <ConfirmAction
                            title={`Cancel ${order.number}?`}
                            description="The order stays in the list as cancelled and can't be dispatched or reopened."
                            form={SalesOrderController.cancel.form(order.id)}
                            label="Cancel order"
                            destructive
                            onDone={() => setConfirming(null)}
                        />
                    )}
                    {confirming === 'close' && (
                        <ConfirmAction
                            title={`Close ${order.number}?`}
                            description={`Do this when the rest won't be sent. ${order.units_dispatched} of ${order.units_ordered} units were dispatched, and nothing more can be dispatched against this order.`}
                            form={SalesOrderController.close.form(order.id)}
                            label="Close order"
                            onDone={() => setConfirming(null)}
                        />
                    )}
                    {confirming === 'delete' && (
                        <ConfirmAction
                            title={`Delete draft ${order.number}?`}
                            description="The draft and its lines are removed for good."
                            form={SalesOrderController.destroy.form(order.id)}
                            label="Delete draft"
                            destructive
                            onDone={() => setConfirming(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function ConfirmAction({
    title,
    description,
    form,
    label,
    destructive = false,
    onDone,
}: {
    title: string;
    description: string;
    form: { action: string; method: 'get' | 'post' };
    label: string;
    destructive?: boolean;
    onDone: () => void;
}) {
    return (
        <>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{description}</DialogDescription>
            <Form
                {...form}
                options={{ preserveScroll: true }}
                onFinish={onDone}
            >
                {({ processing }) => (
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Keep it
                            </Button>
                        </DialogClose>
                        <Button
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={processing}
                        >
                            {label}
                        </Button>
                    </DialogFooter>
                )}
            </Form>
        </>
    );
}

SalesOrderShow.layout = {
    breadcrumbs: [
        {
            title: 'Sales orders',
            href: index(),
        },
    ],
};
