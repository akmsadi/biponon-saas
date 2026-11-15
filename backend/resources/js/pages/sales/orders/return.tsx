import { Head, Link, useForm } from '@inertiajs/react';
import { ListPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import SalesOrderReturnController from '@/actions/App/Http/Controllers/Sales/SalesOrderReturnController';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
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
import { inputToIso, nowForInput, useTimeZone } from '@/lib/datetime';
import { index, show } from '@/routes/sales/orders';

type Warehouse = { id: number; name: string; code: string };

type OrderLine = {
    id: number;
    product: string;
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    quantity_dispatched: number;
    quantity_returned: number;
    quantity_returnable: number;
};

type Props = {
    order: {
        id: number;
        number: string;
        customer: string;
        warehouse: Warehouse & { is_active: boolean };
        lines: OrderLine[];
    };
    warehouses: Warehouse[];
};

type ReturnLine = {
    sales_order_line_id: number;
    quantity: string;
    serials: string;
    showSerials: boolean;
};

type ReturnForm = {
    warehouse_id: string;
    returned_at: string;
    notes: string;
    lines: ReturnLine[];
};

function countSerials(text: string): number {
    return text.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '').length;
}

export default function ReturnSalesOrder({ order, warehouses }: Props) {
    const timeZone = useTimeZone();
    const { data, setData, transform, post, processing, errors } =
        useForm<ReturnForm>({
            warehouse_id: order.warehouse.is_active
                ? String(order.warehouse.id)
                : '',
            returned_at: nowForInput(timeZone),
            notes: '',
            lines: order.lines.map((line) => ({
                sales_order_line_id: line.id,
                quantity: '',
                serials: '',
                showSerials: false,
            })),
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );

    function updateLine(lineIndex: number, changes: Partial<ReturnLine>) {
        setData(
            'lines',
            data.lines.map((line, index) =>
                index === lineIndex ? { ...line, ...changes } : line,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((form) => ({
            warehouse_id: form.warehouse_id,
            // Send an absolute timestamp so the server never guesses the browser's time zone.
            returned_at: inputToIso(form.returned_at, timeZone),
            notes: form.notes,
            lines: form.lines.map((line) => ({
                sales_order_line_id: line.sales_order_line_id,
                quantity: line.quantity,
                serials: line.serials,
            })),
        }));

        post(SalesOrderReturnController.store.url(order.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Return against ${order.number}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Return against ${order.number}`}
                    description={`Stock coming back from ${order.customer}. Returned units go back into stock as sellable.`}
                />

                <form onSubmit={submit} className="space-y-6">
                    <Card className="max-w-3xl">
                        <CardHeader>
                            <CardTitle>Return</CardTitle>
                            <CardDescription>
                                Where and when the stock came back.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">
                                    Return into
                                </Label>
                                <Select
                                    value={data.warehouse_id}
                                    onValueChange={(value) =>
                                        setData('warehouse_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="warehouse_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choose a warehouse" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((warehouse) => (
                                            <SelectItem
                                                key={warehouse.id}
                                                value={String(warehouse.id)}
                                            >
                                                {warehouse.name} (
                                                {warehouse.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.warehouse_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="returned_at">Returned at</Label>
                                <Input
                                    id="returned_at"
                                    type="datetime-local"
                                    required
                                    max={nowForInput(timeZone)}
                                    value={data.returned_at}
                                    onChange={(event) =>
                                        setData(
                                            'returned_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.returned_at} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Input
                                    id="notes"
                                    maxLength={1000}
                                    placeholder="Optional, e.g. reason for the return"
                                    value={data.notes}
                                    onChange={(event) =>
                                        setData('notes', event.target.value)
                                    }
                                />
                                <InputError message={errors.notes} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="max-w-3xl">
                        <CardHeader>
                            <CardTitle>Items</CardTitle>
                            <CardDescription>
                                Enter how many of each came back; leave the rest
                                empty. Serial numbers are optional and must have
                                been dispatched on this order.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {order.lines.map((orderLine, lineIndex) => {
                                const line = data.lines[lineIndex];

                                return (
                                    <div
                                        key={orderLine.id}
                                        className="space-y-2 rounded-lg border p-3"
                                    >
                                        <div className="grid gap-3 sm:grid-cols-[1fr_7rem_auto] sm:items-end">
                                            <div>
                                                <div className="font-medium">
                                                    {orderLine.product}
                                                </div>
                                                <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <ColorSwatch
                                                        hex={orderLine.hex_code}
                                                        className="size-3"
                                                    />
                                                    {orderLine.size} ·{' '}
                                                    {orderLine.color}
                                                    <span className="font-mono">
                                                        {orderLine.sku}
                                                    </span>
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground tabular-nums">
                                                    Dispatched{' '}
                                                    {
                                                        orderLine.quantity_dispatched
                                                    }{' '}
                                                    · returned{' '}
                                                    {
                                                        orderLine.quantity_returned
                                                    }
                                                </div>
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor={`quantity-${orderLine.id}`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Qty (max{' '}
                                                    {
                                                        orderLine.quantity_returnable
                                                    }
                                                    )
                                                </Label>
                                                <Input
                                                    id={`quantity-${orderLine.id}`}
                                                    type="number"
                                                    inputMode="numeric"
                                                    min={0}
                                                    max={
                                                        orderLine.quantity_returnable
                                                    }
                                                    step={1}
                                                    placeholder="0"
                                                    value={line.quantity}
                                                    onChange={(event) =>
                                                        updateLine(lineIndex, {
                                                            quantity:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="button"
                                                variant={
                                                    line.showSerials
                                                        ? 'secondary'
                                                        : 'ghost'
                                                }
                                                size="icon"
                                                onClick={() =>
                                                    updateLine(lineIndex, {
                                                        showSerials:
                                                            !line.showSerials,
                                                    })
                                                }
                                            >
                                                <ListPlus />
                                                <span className="sr-only">
                                                    Serial numbers for{' '}
                                                    {orderLine.sku}
                                                </span>
                                            </Button>
                                        </div>

                                        <InputError
                                            message={errorFor(
                                                `lines.${lineIndex}.quantity`,
                                            )}
                                        />
                                        <InputError
                                            message={errorFor(
                                                `lines.${lineIndex}.sales_order_line_id`,
                                            )}
                                        />

                                        {line.showSerials && (
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor={`serials-${orderLine.id}`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Serial numbers of the units
                                                    coming back, one per line (
                                                    {countSerials(line.serials)}{' '}
                                                    of{' '}
                                                    {Number.parseInt(
                                                        line.quantity,
                                                        10,
                                                    ) || 0}
                                                    ). Leave empty to skip.
                                                </Label>
                                                <textarea
                                                    id={`serials-${orderLine.id}`}
                                                    rows={3}
                                                    value={line.serials}
                                                    onChange={(event) =>
                                                        updateLine(lineIndex, {
                                                            serials:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                                />
                                            </div>
                                        )}
                                        <InputError
                                            message={errorFor(
                                                `lines.${lineIndex}.serials`,
                                            )}
                                        />
                                    </div>
                                );
                            })}

                            <InputError message={errors.lines} />
                        </CardContent>
                    </Card>

                    <div className="flex max-w-3xl items-center justify-end gap-4">
                        <span className="text-sm text-muted-foreground">
                            {totalUnits} unit(s)
                        </span>
                        <Button variant="ghost" asChild>
                            <Link href={show(order.id)}>Cancel</Link>
                        </Button>
                        <Button disabled={processing || totalUnits === 0}>
                            Take back stock
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ReturnSalesOrder.layout = {
    breadcrumbs: [
        {
            title: 'Sales orders',
            href: index(),
        },
    ],
};
