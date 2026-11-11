import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import TransferStockController from '@/actions/App/Http/Controllers/Operations/TransferStockController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import StockLine, {
    MAX_STOCK_LINES,
    emptyStockLine,
    localNow,
    toLinePayload,
} from '@/components/stock-line';
import type {
    LineProduct,
    LineVariant,
    StockLineValues,
} from '@/components/stock-line';
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
import { create } from '@/routes/operations/transfer';

type Warehouse = { id: number; name: string; code: string; is_active: boolean };

type Variant = LineVariant & {
    /** On-hand quantity keyed by warehouse id; warehouses without stock are absent. */
    stock: Record<string, number>;
};

type Props = {
    warehouses: Warehouse[];
    products: LineProduct<Variant>[];
};

type TransferForm = {
    from_warehouse_id: string;
    to_warehouse_id: string;
    transferred_at: string;
    notes: string;
    lines: StockLineValues[];
};

function warehouseLabel(warehouse: Warehouse): string {
    return `${warehouse.name} (${warehouse.code})${warehouse.is_active ? '' : ' · inactive'}`;
}

export default function TransferStock({ warehouses, products }: Props) {
    const { data, setData, transform, post, processing, errors } =
        useForm<TransferForm>({
            from_warehouse_id: '',
            to_warehouse_id: '',
            transferred_at: localNow(),
            notes: '',
            lines: [emptyStockLine()],
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const source = data.from_warehouse_id;

    const availableAtSource = (variant: Variant): number =>
        variant.stock[source] ?? 0;

    // Only what the source warehouse actually holds can be transferred.
    const availableProducts = source
        ? products
              .map((product) => ({
                  ...product,
                  variants: product.variants.filter(
                      (variant) => availableAtSource(variant) > 0,
                  ),
              }))
              .filter((product) => product.variants.length > 0)
        : [];

    const destinations = warehouses.filter(
        (warehouse) => warehouse.is_active && String(warehouse.id) !== source,
    );

    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );

    function changeSource(value: string) {
        // Lines picked for the previous source may not be available here, so start over.
        setData((current) => ({
            ...current,
            from_warehouse_id: value,
            to_warehouse_id:
                current.to_warehouse_id === value
                    ? ''
                    : current.to_warehouse_id,
            lines: [emptyStockLine()],
        }));
    }

    function updateLine(index: number, changes: Partial<StockLineValues>) {
        setData(
            'lines',
            data.lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...changes } : line,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((form) => ({
            from_warehouse_id: form.from_warehouse_id,
            to_warehouse_id: form.to_warehouse_id,
            // Send an absolute timestamp so the server never guesses the browser's time zone.
            transferred_at: new Date(form.transferred_at).toISOString(),
            notes: form.notes,
            lines: form.lines.map(toLinePayload),
        }));

        post(TransferStockController.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () =>
                setData((current) => ({
                    ...current,
                    transferred_at: localNow(),
                    notes: '',
                    lines: [emptyStockLine()],
                })),
        });
    }

    const hasSetup =
        products.length > 0 &&
        warehouses.filter((warehouse) => warehouse.is_active).length > 0 &&
        warehouses.length > 1;

    return (
        <>
            <Head title="Transfer stock" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Transfer stock"
                    description="Move stock from one warehouse to another. Serial numbers are optional."
                />

                {!hasSetup ? (
                    <p className="text-sm text-muted-foreground">
                        You need stock in a warehouse and at least one other
                        active warehouse before you can transfer stock.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-3xl">
                            <CardHeader>
                                <CardTitle>Transfer</CardTitle>
                                <CardDescription>
                                    Where the stock leaves from, where it goes,
                                    and when.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="from_warehouse_id">
                                        From
                                    </Label>
                                    <Select
                                        value={data.from_warehouse_id}
                                        onValueChange={changeSource}
                                    >
                                        <SelectTrigger
                                            id="from_warehouse_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Source warehouse" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {warehouses.map((warehouse) => (
                                                <SelectItem
                                                    key={warehouse.id}
                                                    value={String(warehouse.id)}
                                                >
                                                    {warehouseLabel(warehouse)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.from_warehouse_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to_warehouse_id">To</Label>
                                    <Select
                                        value={data.to_warehouse_id}
                                        onValueChange={(value) =>
                                            setData('to_warehouse_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="to_warehouse_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Destination warehouse" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {destinations.map((warehouse) => (
                                                <SelectItem
                                                    key={warehouse.id}
                                                    value={String(warehouse.id)}
                                                >
                                                    {warehouseLabel(warehouse)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.to_warehouse_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="transferred_at">
                                        Transferred at
                                    </Label>
                                    <Input
                                        id="transferred_at"
                                        type="datetime-local"
                                        required
                                        max={localNow()}
                                        value={data.transferred_at}
                                        onChange={(event) =>
                                            setData(
                                                'transferred_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.transferred_at}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        maxLength={1000}
                                        placeholder="Optional, e.g. reason or courier"
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
                                    One line per variant. Name serial numbers
                                    only if you want to record which units
                                    moved.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!source ? (
                                    <p className="text-sm text-muted-foreground">
                                        Choose the source warehouse to see what
                                        it holds.
                                    </p>
                                ) : availableProducts.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        This warehouse holds no stock.
                                    </p>
                                ) : (
                                    <>
                                        {data.lines.map((line, index) => (
                                            <StockLine
                                                key={line.key}
                                                line={line}
                                                index={index}
                                                products={availableProducts}
                                                availableFor={availableAtSource}
                                                canRemove={
                                                    data.lines.length > 1
                                                }
                                                errorFor={errorFor}
                                                onChange={(changes) =>
                                                    updateLine(index, changes)
                                                }
                                                onRemove={() =>
                                                    setData(
                                                        'lines',
                                                        data.lines.filter(
                                                            (_, lineIndex) =>
                                                                lineIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            />
                                        ))}

                                        <InputError message={errors.lines} />

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                data.lines.length >=
                                                MAX_STOCK_LINES
                                            }
                                            onClick={() =>
                                                setData('lines', [
                                                    ...data.lines,
                                                    emptyStockLine(),
                                                ])
                                            }
                                        >
                                            <Plus /> Add line
                                        </Button>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <div className="flex max-w-3xl items-center justify-end gap-4">
                            <span className="text-sm text-muted-foreground">
                                {totalUnits} unit(s) on {data.lines.length}{' '}
                                line(s)
                            </span>
                            <Button disabled={processing || !source}>
                                Transfer stock
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

TransferStock.layout = {
    breadcrumbs: [
        {
            title: 'Transfer stock',
            href: create(),
        },
    ],
};
