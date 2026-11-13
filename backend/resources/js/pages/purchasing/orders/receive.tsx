import { Head, Link, useForm } from '@inertiajs/react';
import { ListPlus, Split, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import PurchaseOrderReceiptController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderReceiptController';
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
import { index, show } from '@/routes/purchasing/orders';

type Warehouse = { id: number; name: string; code: string };

type OrderLine = {
    id: number;
    product: string;
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    quantity_ordered: number;
    quantity_received: number;
    quantity_remaining: number;
};

type Props = {
    order: {
        id: number;
        number: string;
        supplier: string;
        warehouse: Warehouse & { is_active: boolean };
        lines: OrderLine[];
    };
    warehouses: Warehouse[];
    maxSplits: number;
};

/**
 * Part of a line going to one warehouse. A blank or 0 quantity is skipped.
 */
type Allocation = {
    key: number;
    warehouse_id: string;
    quantity: string;
    serials: string;
    showSerials: boolean;
};

type ReceiptLine = {
    purchase_order_line_id: number;
    allocations: Allocation[];
};

type ReceiveForm = {
    received_at: string;
    notes: string;
    lines: ReceiptLine[];
};

let nextAllocationKey = 0;

function allocation(warehouseId: string, quantity: string): Allocation {
    return {
        key: nextAllocationKey++,
        warehouse_id: warehouseId,
        quantity,
        serials: '',
        showSerials: false,
    };
}

function units(value: string): number {
    return Number.parseInt(value, 10) || 0;
}

function countSerials(text: string): number {
    return text.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '').length;
}

export default function ReceivePurchaseOrder({
    order,
    warehouses,
    maxSplits,
}: Props) {
    const timeZone = useTimeZone();
    const defaultWarehouseId = order.warehouse.is_active
        ? String(order.warehouse.id)
        : '';
    const { data, setData, transform, post, processing, errors } =
        useForm<ReceiveForm>({
            received_at: nowForInput(timeZone),
            notes: '',
            lines: order.lines.map((line) => ({
                purchase_order_line_id: line.id,
                allocations: [
                    allocation(
                        defaultWarehouseId,
                        String(line.quantity_remaining),
                    ),
                ],
            })),
        });

    // Nested keys such as "lines.0.allocations.1.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const totalUnits = data.lines.reduce(
        (sum, line) =>
            sum +
            line.allocations.reduce(
                (lineSum, split) => lineSum + units(split.quantity),
                0,
            ),
        0,
    );

    function updateAllocations(
        lineIndex: number,
        update: (allocations: Allocation[]) => Allocation[],
    ) {
        setData(
            'lines',
            data.lines.map((line, index) =>
                index === lineIndex
                    ? { ...line, allocations: update(line.allocations) }
                    : line,
            ),
        );
    }

    function updateAllocation(
        lineIndex: number,
        splitIndex: number,
        changes: Partial<Allocation>,
    ) {
        updateAllocations(lineIndex, (allocations) =>
            allocations.map((split, index) =>
                index === splitIndex ? { ...split, ...changes } : split,
            ),
        );
    }

    function addSplit(lineIndex: number, remaining: number) {
        updateAllocations(lineIndex, (allocations) => {
            const used = new Set(
                allocations.map((split) => split.warehouse_id),
            );
            const next = warehouses.find(
                (warehouse) => !used.has(String(warehouse.id)),
            );
            const left =
                remaining -
                allocations.reduce(
                    (sum, split) => sum + units(split.quantity),
                    0,
                );

            return [
                ...allocations,
                allocation(
                    next ? String(next.id) : '',
                    left > 0 ? String(left) : '',
                ),
            ];
        });
    }

    function setAll(toRemaining: boolean) {
        setData(
            'lines',
            data.lines.map((line, index) => ({
                ...line,
                allocations: [
                    allocation(
                        line.allocations[0]?.warehouse_id ?? defaultWarehouseId,
                        toRemaining
                            ? String(order.lines[index].quantity_remaining)
                            : '',
                    ),
                ],
            })),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((form) => ({
            // Send an absolute timestamp so the server never guesses the browser's time zone.
            received_at: inputToIso(form.received_at, timeZone),
            notes: form.notes,
            lines: form.lines.map((line) => ({
                purchase_order_line_id: line.purchase_order_line_id,
                allocations: line.allocations.map((split) => ({
                    warehouse_id: split.warehouse_id,
                    quantity: split.quantity,
                    serials: split.serials,
                })),
            })),
        }));

        post(PurchaseOrderReceiptController.store.url(order.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Receive ${order.number}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Receive ${order.number}`}
                    description={`Stock from ${order.supplier}. Each line starts with everything still expected going to ${order.warehouse.name}; split a line to send part of it elsewhere.`}
                />

                {!order.warehouse.is_active && (
                    <p className="max-w-3xl rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                        {order.warehouse.name} ({order.warehouse.code}) is
                        inactive, so choose an active warehouse for each line.
                    </p>
                )}

                {warehouses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        You need at least one active warehouse before you can
                        receive stock.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-3xl">
                            <CardHeader>
                                <CardTitle>Receipt</CardTitle>
                                <CardDescription>
                                    When the delivery arrived.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="received_at">
                                        Received at
                                    </Label>
                                    <Input
                                        id="received_at"
                                        type="datetime-local"
                                        required
                                        max={nowForInput(timeZone)}
                                        value={data.received_at}
                                        onChange={(event) =>
                                            setData(
                                                'received_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.received_at} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        maxLength={1000}
                                        placeholder="Optional, e.g. delivery note number"
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
                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2">
                                <div className="space-y-1.5">
                                    <CardTitle>Items</CardTitle>
                                    <CardDescription>
                                        Clear a quantity to skip it this time.
                                        Serial numbers are optional.
                                    </CardDescription>
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setAll(true)}
                                    >
                                        Fill all
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setAll(false)}
                                    >
                                        Clear all
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {order.lines.map((orderLine, lineIndex) => {
                                    const line = data.lines[lineIndex];
                                    const allocated = line.allocations.reduce(
                                        (sum, split) =>
                                            sum + units(split.quantity),
                                        0,
                                    );
                                    const over =
                                        allocated >
                                        orderLine.quantity_remaining;

                                    return (
                                        <div
                                            key={orderLine.id}
                                            className="space-y-3 rounded-lg border p-3"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <div className="font-medium">
                                                        {orderLine.product}
                                                    </div>
                                                    <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <ColorSwatch
                                                            hex={
                                                                orderLine.hex_code
                                                            }
                                                            className="size-3"
                                                        />
                                                        {orderLine.size} ·{' '}
                                                        {orderLine.color}
                                                        <span className="font-mono">
                                                            {orderLine.sku}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="text-right text-xs text-muted-foreground tabular-nums">
                                                    Ordered{' '}
                                                    {orderLine.quantity_ordered}{' '}
                                                    · received{' '}
                                                    {
                                                        orderLine.quantity_received
                                                    }
                                                    <div
                                                        className={
                                                            over
                                                                ? 'font-medium text-destructive'
                                                                : 'font-medium text-foreground'
                                                        }
                                                    >
                                                        {allocated} of{' '}
                                                        {
                                                            orderLine.quantity_remaining
                                                        }{' '}
                                                        remaining
                                                    </div>
                                                </div>
                                            </div>

                                            {line.allocations.map(
                                                (split, splitIndex) => (
                                                    <AllocationRow
                                                        key={split.key}
                                                        split={split}
                                                        label={`${orderLine.sku}, split ${splitIndex + 1}`}
                                                        errorKey={`lines.${lineIndex}.allocations.${splitIndex}`}
                                                        errorFor={errorFor}
                                                        warehouses={warehouses.filter(
                                                            (warehouse) =>
                                                                !line.allocations.some(
                                                                    (other) =>
                                                                        other.key !==
                                                                            split.key &&
                                                                        other.warehouse_id ===
                                                                            String(
                                                                                warehouse.id,
                                                                            ),
                                                                ),
                                                        )}
                                                        max={
                                                            orderLine.quantity_remaining
                                                        }
                                                        canRemove={
                                                            line.allocations
                                                                .length > 1
                                                        }
                                                        onChange={(changes) =>
                                                            updateAllocation(
                                                                lineIndex,
                                                                splitIndex,
                                                                changes,
                                                            )
                                                        }
                                                        onRemove={() =>
                                                            updateAllocations(
                                                                lineIndex,
                                                                (allocations) =>
                                                                    allocations.filter(
                                                                        (
                                                                            _,
                                                                            index,
                                                                        ) =>
                                                                            index !==
                                                                            splitIndex,
                                                                    ),
                                                            )
                                                        }
                                                    />
                                                ),
                                            )}

                                            <InputError
                                                message={errorFor(
                                                    `lines.${lineIndex}.allocations`,
                                                )}
                                            />
                                            <InputError
                                                message={errorFor(
                                                    `lines.${lineIndex}.purchase_order_line_id`,
                                                )}
                                            />

                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    line.allocations.length >=
                                                    Math.min(
                                                        maxSplits,
                                                        warehouses.length,
                                                    )
                                                }
                                                onClick={() =>
                                                    addSplit(
                                                        lineIndex,
                                                        orderLine.quantity_remaining,
                                                    )
                                                }
                                            >
                                                <Split /> Split to another
                                                warehouse
                                            </Button>
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
                                Receive stock
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

/**
 * Warehouse → quantity row for one split of a line, with a toggleable serials textarea.
 */
function AllocationRow({
    split,
    label,
    errorKey,
    errorFor,
    warehouses,
    max,
    canRemove,
    onChange,
    onRemove,
}: {
    split: Allocation;
    label: string;
    errorKey: string;
    errorFor: (key: string) => string | undefined;
    warehouses: Warehouse[];
    max: number;
    canRemove: boolean;
    onChange: (changes: Partial<Allocation>) => void;
    onRemove: () => void;
}) {
    const rowErrors = [
        errorFor(`${errorKey}.warehouse_id`),
        errorFor(`${errorKey}.quantity`),
    ].filter(Boolean) as string[];

    return (
        <div className="space-y-2">
            <div className="grid gap-3 sm:grid-cols-[1fr_7rem_auto] sm:items-end">
                <div className="grid gap-1.5">
                    <Label className="text-xs text-muted-foreground">
                        Warehouse
                    </Label>
                    <Select
                        value={split.warehouse_id}
                        onValueChange={(value) =>
                            onChange({ warehouse_id: value })
                        }
                    >
                        <SelectTrigger
                            className="w-full"
                            aria-label={`Warehouse for ${label}`}
                        >
                            <SelectValue placeholder="Choose a warehouse" />
                        </SelectTrigger>
                        <SelectContent>
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
                </div>

                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`quantity-${split.key}`}
                        className="text-xs text-muted-foreground"
                    >
                        Quantity
                    </Label>
                    <Input
                        id={`quantity-${split.key}`}
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={max}
                        step={1}
                        placeholder="0"
                        value={split.quantity}
                        onChange={(event) =>
                            onChange({ quantity: event.target.value })
                        }
                    />
                </div>

                <div className="flex gap-1">
                    <Button
                        type="button"
                        variant={split.showSerials ? 'secondary' : 'ghost'}
                        size="icon"
                        onClick={() =>
                            onChange({ showSerials: !split.showSerials })
                        }
                    >
                        <ListPlus />
                        <span className="sr-only">
                            Serial numbers for {label}
                        </span>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={!canRemove}
                        onClick={onRemove}
                    >
                        <Trash2 />
                        <span className="sr-only">Remove {label}</span>
                    </Button>
                </div>
            </div>

            {rowErrors.map((message) => (
                <InputError key={message} message={message} />
            ))}

            {split.showSerials && (
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`serials-${split.key}`}
                        className="text-xs text-muted-foreground"
                    >
                        Serial numbers, one per line (
                        {countSerials(split.serials)} of {units(split.quantity)}
                        ). Leave empty to skip.
                    </Label>
                    <textarea
                        id={`serials-${split.key}`}
                        rows={3}
                        value={split.serials}
                        onChange={(event) =>
                            onChange({ serials: event.target.value })
                        }
                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                </div>
            )}
            <InputError message={errorFor(`${errorKey}.serials`)} />
        </div>
    );
}

ReceivePurchaseOrder.layout = {
    breadcrumbs: [
        {
            title: 'Purchase orders',
            href: index(),
        },
    ],
};
