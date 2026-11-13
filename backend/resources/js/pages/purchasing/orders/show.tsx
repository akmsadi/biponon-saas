import { Form, Head, Link } from '@inertiajs/react';
import {
    Ban,
    CircleStop,
    PackagePlus,
    Pencil,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import PurchaseOrderStatusBadge from '@/components/purchase-order-status';
import type { PurchaseOrderStatus } from '@/components/purchase-order-status';
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
import { edit, index } from '@/routes/purchasing/orders';
import { create as receive } from '@/routes/purchasing/orders/receive';

type Line = {
    id: number;
    product: { id: number; name: string };
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    quantity_ordered: number;
    quantity_received: number;
    unit_cost?: string | null;
    total_cost?: string | null;
};

type Order = {
    id: number;
    number: string;
    status: PurchaseOrderStatus;
    supplier: {
        id: number;
        name: string;
        contact_name: string | null;
        email: string | null;
        phone: string | null;
    };
    warehouse: { id: number; name: string; code: string };
    expected_at: string | null;
    notes: string | null;
    created_by: string | null;
    created_at: string;
    ordered_at: string | null;
    cancelled_at: string | null;
    closed_at: string | null;
    units_ordered: number;
    units_received: number;
    total_cost?: string | null;
    has_uncosted_lines?: boolean;
    lines: Line[];
};

type Receipt = {
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
    receipts: Receipt[];
    can: {
        edit: boolean;
        delete: boolean;
        place: boolean;
        cancel: boolean;
        receive: boolean;
        close: boolean;
    };
    canSeeCosts: boolean;
};

type Confirming = 'place' | 'cancel' | 'close' | 'delete' | null;

export default function PurchaseOrderShow({
    order,
    receipts,
    can,
    canSeeCosts,
}: Props) {
    const { formatDateTime } = useDateFormat();
    const { formatMoney } = useMoneyFormat();
    const [confirming, setConfirming] = useState<Confirming>(null);

    return (
        <>
            <Head title={order.number} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Heading
                            title={order.number}
                            description={`From ${order.supplier.name} to ${order.warehouse.name}`}
                        />
                        <div className="pt-1.5">
                            <PurchaseOrderStatusBadge status={order.status} />
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
                        {can.place && (
                            <Button onClick={() => setConfirming('place')}>
                                <Send /> Mark as ordered
                            </Button>
                        )}
                        {can.receive && (
                            <Button asChild>
                                <Link href={receive(order.id)}>
                                    <PackagePlus /> Receive
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {order.status === 'draft' && !can.place && (
                    <p className="max-w-4xl rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                        This is a draft. A manager or admin marks it as ordered
                        once it's been sent to the supplier
                        {canSeeCosts ? '.' : ', and can add unit costs first.'}
                    </p>
                )}

                <div className="grid max-w-4xl gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Supplier
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div className="font-medium">
                                {order.supplier.name}
                            </div>
                            {order.supplier.contact_name && (
                                <div>{order.supplier.contact_name}</div>
                            )}
                            {order.supplier.email && (
                                <a
                                    href={`mailto:${order.supplier.email}`}
                                    className="block text-muted-foreground underline-offset-4 hover:underline"
                                >
                                    {order.supplier.email}
                                </a>
                            )}
                            {order.supplier.phone && (
                                <div className="text-muted-foreground">
                                    {order.supplier.phone}
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
                                    Deliver to
                                </dt>
                                <dd>
                                    {order.warehouse.name} (
                                    {order.warehouse.code})
                                </dd>
                                <dt className="text-muted-foreground">
                                    Expected
                                </dt>
                                <dd>{formatDate(order.expected_at, 'UTC')}</dd>
                                <dt className="text-muted-foreground">
                                    Created
                                </dt>
                                <dd>
                                    {formatDateTime(order.created_at)}
                                    {order.created_by &&
                                        ` by ${order.created_by}`}
                                </dd>
                                {order.ordered_at && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Ordered
                                        </dt>
                                        <dd>
                                            {formatDateTime(order.ordered_at)}
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
                                    Received
                                </th>
                                {canSeeCosts && (
                                    <>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Unit cost
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Total
                                        </th>
                                    </>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {order.lines.map((line) => (
                                <tr
                                    key={line.id}
                                    className="border-b last:border-0"
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
                                        {line.quantity_received}
                                    </td>
                                    {canSeeCosts && (
                                        <>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatMoney(
                                                    line.unit_cost ?? null,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatMoney(
                                                    line.total_cost ?? null,
                                                )}
                                            </td>
                                        </>
                                    )}
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
                                    {order.units_received}
                                </td>
                                {canSeeCosts && (
                                    <>
                                        <td />
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatMoney(
                                                order.total_cost ?? null,
                                            )}
                                        </td>
                                    </>
                                )}
                            </tr>
                        </tfoot>
                    </table>
                </div>
                {canSeeCosts && order.has_uncosted_lines && (
                    <p className="-mt-3 max-w-4xl text-xs text-muted-foreground">
                        Some lines have no unit cost, so the total covers only
                        the lines that do.
                    </p>
                )}

                {receipts.length > 0 && (
                    <section className="max-w-4xl space-y-3">
                        <h2 className="text-base font-semibold">Receipts</h2>
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
                                            Into
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
                                    {receipts.map((receipt) => (
                                        <tr
                                            key={receipt.id}
                                            className="border-b align-top last:border-0"
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    receipt.occurred_at,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {receipt.product}
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {receipt.sku}
                                                </div>
                                                {receipt.notes && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {receipt.notes}
                                                    </div>
                                                )}
                                            </td>
                                            <td
                                                className="px-4 py-3 font-mono text-xs"
                                                title={receipt.warehouse.name}
                                            >
                                                {receipt.warehouse.code}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {receipt.quantity}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                                {receipt.serial_count || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {receipt.performed_by ?? '—'}
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
                    {confirming === 'place' && (
                        <ConfirmAction
                            title={`Mark ${order.number} as ordered?`}
                            description={`Do this once the order has been sent to ${order.supplier.name}. Its lines can't be changed afterwards.${
                                order.has_uncosted_lines
                                    ? ' Some lines still have no unit cost.'
                                    : ''
                            }`}
                            form={PurchaseOrderController.place.form(order.id)}
                            label="Mark as ordered"
                            onDone={() => setConfirming(null)}
                        />
                    )}
                    {confirming === 'cancel' && (
                        <ConfirmAction
                            title={`Cancel ${order.number}?`}
                            description="The order stays in the list as cancelled and can't be received or reopened."
                            form={PurchaseOrderController.cancel.form(order.id)}
                            label="Cancel order"
                            destructive
                            onDone={() => setConfirming(null)}
                        />
                    )}
                    {confirming === 'close' && (
                        <ConfirmAction
                            title={`Close ${order.number}?`}
                            description={`Do this when ${order.supplier.name} won't send the rest. The ${order.units_received} of ${order.units_ordered} units that arrived stay in stock, and nothing more can be received against this order.`}
                            form={PurchaseOrderController.close.form(order.id)}
                            label="Close order"
                            onDone={() => setConfirming(null)}
                        />
                    )}
                    {confirming === 'delete' && (
                        <ConfirmAction
                            title={`Delete draft ${order.number}?`}
                            description="The draft and its lines are removed for good."
                            form={PurchaseOrderController.destroy.form(
                                order.id,
                            )}
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

PurchaseOrderShow.layout = {
    breadcrumbs: [
        {
            title: 'Purchase orders',
            href: index(),
        },
    ],
};
