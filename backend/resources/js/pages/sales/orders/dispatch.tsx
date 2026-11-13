import { Head, Link, useForm } from '@inertiajs/react';
import { ListPlus, Split, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import SalesOrderDispatchController from '@/actions/App/Http/Controllers/Sales/SalesOrderDispatchController';
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

type Warehouse = { id: number; name: string; code: string; is_active: boolean };

type OrderLine = {
    id: number;
    product: string;
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
    quantity_ordered: number;
    quantity_dispatched: number;
    quantity_remaining: number;
    /** Units on hand per warehouse id; warehouses without stock are left out. */
    stock: Record<string, number>;
};

type Props = {
    order: {
        id: number;
        number: string;
        customer: string;
        warehouse: { id: number; name: string; code: string };
        lines: OrderLine[];
    };
    warehouses: Warehouse[];
    maxSplits: number;
};

/**
 * Part of a line leaving one warehouse. A blank or 0 quantity is skipped.
 */
type Allocation = {
    key: number;
    warehouse_id: string;
    quantity: string;
    serials: string;
    showSerials: boolean;
};

type DispatchLine = {
    sales_order_line_id: number;
    allocations: Allocation[];
};

type DispatchForm = {
    dispatched_at: string;
    notes: string;
    lines: DispatchLine[];
};

let nextAllocationKey = 0;

function allocation(warehouseId: string, quantity: number): Allocation {
    return {
        key: nextAllocationKey++,
        warehouse_id: warehouseId,
        quantity: quantity > 0 ? String(quantity) : '',
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

function stockIn(line: OrderLine, warehouseId: string): number {
    return line.stock[warehouseId] ?? 0;
}

/**
 * Start each line from the order's ship-from warehouse with as much as it can cover.
 */
function defaultAllocation(line: OrderLine, shipFromId: string): Allocation {
    return allocation(
        shipFromId,
        Math.min(line.quantity_remaining, stockIn(line, shipFromId)),
    );
}

export default function DispatchSalesOrder({
    order,
    warehouses,
    maxSplits,
}: Props) {
    const timeZone = useTimeZone();
    const shipFromId = String(order.warehouse.id);
    const { data, setData, transform, post, processing, errors } =
        useForm<DispatchForm>({
            dispatched_at: nowForInput(timeZone),
            notes: '',
            lines: order.lines.map((line) => ({
                sales_order_line_id: line.id,
                allocations: [defaultAllocation(line, shipFromId)],
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

    /**
     * Add a split from the unused warehouse with the most stock, covering what is still left if it can.
     */
    function addSplit(lineIndex: number) {
        const orderLine = order.lines[lineIndex];

        updateAllocations(lineIndex, (allocations) => {
            const used = new Set(
                allocations.map((split) => split.warehouse_id),
            );
            const best = Object.entries(orderLine.stock)
                .filter(([warehouseId]) => !used.has(warehouseId))
                .sort(([, a], [, b]) => b - a)[0];
            const left =
                orderLine.quantity_remaining -
                allocations.reduce(
                    (sum, split) => sum + units(split.quantity),
                    0,
                );

            return [
                ...allocations,
                best
                    ? allocation(best[0], Math.min(Math.max(left, 0), best[1]))
                    : allocation('', 0),
            ];
        });
    }

    function setAll(fill: boolean) {
        setData(
            'lines',
            order.lines.map((line) => ({
                sales_order_line_id: line.id,
                allocations: [
                    fill
                        ? defaultAllocation(line, shipFromId)
                        : allocation(shipFromId, 0),
                ],
            })),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((form) => ({
            // Send an absolute timestamp so the server never guesses the browser's time zone.
            dispatched_at: inputToIso(form.dispatched_at, timeZone),
            notes: form.notes,
            lines: form.lines.map((line) => ({
                sales_order_line_id: line.sales_order_line_id,
                allocations: line.allocations.map((split) => ({
                    warehouse_id: split.warehouse_id,
                    quantity: split.quantity,
                    serials: split.serials,
                })),
            })),
        }));

        post(SalesOrderDispatchController.store.url(order.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Dispatch ${order.number}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Dispatch ${order.number}`}
                    description={`Stock for ${order.customer}. Each line starts with what ${order.warehouse.name} can cover; split a line to send the rest from another warehouse.`}
                />

                <form onSubmit={submit} className="space-y-6">
                    <Card className="max-w-3xl">
                        <CardHeader>
                            <CardTitle>Dispatch</CardTitle>
                            <CardDescription>
                                When the stock left.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="dispatched_at">
                                    Dispatched at
                                </Label>
                                <Input
                                    id="dispatched_at"
                                    type="datetime-local"
                                    required
                                    max={nowForInput(timeZone)}
                                    value={data.dispatched_at}
                                    onChange={(event) =>
                                        setData(
                                            'dispatched_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.dispatched_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Input
                                    id="notes"
                                    maxLength={1000}
                                    placeholder="Optional, e.g. courier or tracking number"
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
                                    (sum, split) => sum + units(split.quantity),
                                    0,
                                );
                                const over =
                                    allocated > orderLine.quantity_remaining;
                                const stockedWarehouses = warehouses.filter(
                                    (warehouse) =>
                                        stockIn(
                                            orderLine,
                                            String(warehouse.id),
                                        ) > 0,
                                );

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
                                                        hex={orderLine.hex_code}
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
                                                {orderLine.quantity_ordered} ·
                                                dispatched{' '}
                                                {orderLine.quantity_dispatched}
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
                                                    still to send
                                                </div>
                                            </div>
                                        </div>

                                        {stockedWarehouses.length === 0 && (
                                            <p className="text-sm text-amber-700 dark:text-amber-400">
                                                No stock of this variant in any
                                                warehouse yet. It stays on
                                                backorder.
                                            </p>
                                        )}

                                        {line.allocations.map(
                                            (split, splitIndex) => (
                                                <AllocationRow
                                                    key={split.key}
                                                    split={split}
                                                    label={`${orderLine.sku}, split ${splitIndex + 1}`}
                                                    errorKey={`lines.${lineIndex}.allocations.${splitIndex}`}
                                                    errorFor={errorFor}
                                                    options={warehouses
                                                        .filter(
                                                            (warehouse) =>
                                                                String(
                                                                    warehouse.id,
                                                                ) ===
                                                                    split.warehouse_id ||
                                                                (stockIn(
                                                                    orderLine,
                                                                    String(
                                                                        warehouse.id,
                                                                    ),
                                                                ) > 0 &&
                                                                    !line.allocations.some(
                                                                        (
                                                                            other,
                                                                        ) =>
                                                                            other.warehouse_id ===
                                                                            String(
                                                                                warehouse.id,
                                                                            ),
                                                                    )),
                                                        )
                                                        .map((warehouse) => ({
                                                            ...warehouse,
                                                            stock: stockIn(
                                                                orderLine,
                                                                String(
                                                                    warehouse.id,
                                                                ),
                                                            ),
                                                        }))}
                                                    max={Math.min(
                                                        orderLine.quantity_remaining,
                                                        stockIn(
                                                            orderLine,
                                                            split.warehouse_id,
                                                        ),
                                                    )}
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
                                                `lines.${lineIndex}.sales_order_line_id`,
                                            )}
                                        />

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                line.allocations.length >=
                                                    maxSplits ||
                                                stockedWarehouses.every(
                                                    (warehouse) =>
                                                        line.allocations.some(
                                                            (split) =>
                                                                split.warehouse_id ===
                                                                String(
                                                                    warehouse.id,
                                                                ),
                                                        ),
                                                )
                                            }
                                            onClick={() => addSplit(lineIndex)}
                                        >
                                            <Split /> Send some from another
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
                            Dispatch stock
                        </Button>
                    </div>
                </form>
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
    options,
    max,
    canRemove,
    onChange,
    onRemove,
}: {
    split: Allocation;
    label: string;
    errorKey: string;
    errorFor: (key: string) => string | undefined;
    options: (Warehouse & { stock: number })[];
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
                        From warehouse
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
                            {options.map((warehouse) => (
                                <SelectItem
                                    key={warehouse.id}
                                    value={String(warehouse.id)}
                                >
                                    {warehouse.name} ({warehouse.code})
                                    <span className="text-xs text-muted-foreground">
                                        {warehouse.stock} in stock
                                        {!warehouse.is_active && ' · inactive'}
                                    </span>
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
                        Quantity (max {max})
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
                        Serial numbers of the units leaving, one per line (
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

DispatchSalesOrder.layout = {
    breadcrumbs: [
        {
            title: 'Sales orders',
            href: index(),
        },
    ],
};
