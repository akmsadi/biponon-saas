import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import DispatchStockController from '@/actions/App/Http/Controllers/Operations/DispatchStockController';
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
import { create } from '@/routes/operations/dispatch';

type Warehouse = { id: number; name: string; code: string; is_active: boolean };

type Variant = LineVariant & {
    /** On-hand quantity keyed by warehouse id; warehouses without stock are absent. */
    stock: Record<string, number>;
};

type Props = {
    warehouses: Warehouse[];
    products: LineProduct<Variant>[];
};

type DispatchForm = {
    warehouse_id: string;
    dispatched_at: string;
    notes: string;
    lines: StockLineValues[];
};

export default function DispatchStock({ warehouses, products }: Props) {
    const { data, setData, transform, post, processing, errors } =
        useForm<DispatchForm>({
            warehouse_id: '',
            dispatched_at: localNow(),
            notes: '',
            lines: [emptyStockLine()],
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const source = data.warehouse_id;

    const availableAtSource = (variant: Variant): number =>
        variant.stock[source] ?? 0;

    // Only what the warehouse actually holds can be dispatched.
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

    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );

    function changeSource(value: string) {
        // Lines picked for the previous warehouse may not be available here, so start over.
        setData((current) => ({
            ...current,
            warehouse_id: value,
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
            warehouse_id: form.warehouse_id,
            // Send an absolute timestamp so the server never guesses the browser's time zone.
            dispatched_at: new Date(form.dispatched_at).toISOString(),
            notes: form.notes,
            lines: form.lines.map(toLinePayload),
        }));

        post(DispatchStockController.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () =>
                setData((current) => ({
                    ...current,
                    dispatched_at: localNow(),
                    notes: '',
                    lines: [emptyStockLine()],
                })),
        });
    }

    return (
        <>
            <Head title="Dispatch stock" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Dispatch stock"
                    description="Record stock leaving a warehouse, e.g. shipped to a customer. Serial numbers are optional."
                />

                {products.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        There is no stock in any warehouse to dispatch.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-3xl">
                            <CardHeader>
                                <CardTitle>Dispatch</CardTitle>
                                <CardDescription>
                                    Where the stock leaves from and when.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="warehouse_id">From</Label>
                                    <Select
                                        value={data.warehouse_id}
                                        onValueChange={changeSource}
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
                                                    {warehouse.is_active
                                                        ? ''
                                                        : ' · inactive'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.warehouse_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="dispatched_at">
                                        Dispatched at
                                    </Label>
                                    <Input
                                        id="dispatched_at"
                                        type="datetime-local"
                                        required
                                        max={localNow()}
                                        value={data.dispatched_at}
                                        onChange={(event) =>
                                            setData(
                                                'dispatched_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.dispatched_at}
                                    />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        maxLength={1000}
                                        placeholder="Optional, e.g. order number or customer"
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
                                    only if you want to record which units left.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!source ? (
                                    <p className="text-sm text-muted-foreground">
                                        Choose the warehouse to see what it
                                        holds.
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
                                Dispatch stock
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

DispatchStock.layout = {
    breadcrumbs: [
        {
            title: 'Dispatch stock',
            href: create(),
        },
    ],
};
