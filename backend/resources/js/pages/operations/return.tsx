import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import ReturnStockController from '@/actions/App/Http/Controllers/Operations/ReturnStockController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import StockLine, {
    MAX_STOCK_LINES,
    emptyStockLine,
    localNow,
    toLinePayload,
} from '@/components/stock-line';
import type { LineProduct, StockLineValues } from '@/components/stock-line';
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
import { create } from '@/routes/operations/return';

type Warehouse = { id: number; name: string; code: string };

type Props = {
    warehouses: Warehouse[];
    products: LineProduct[];
};

type ReturnForm = {
    warehouse_id: string;
    returned_at: string;
    notes: string;
    lines: StockLineValues[];
};

export default function ReturnStock({ warehouses, products }: Props) {
    const { data, setData, transform, post, processing, errors } =
        useForm<ReturnForm>({
            warehouse_id: '',
            returned_at: localNow(),
            notes: '',
            lines: [emptyStockLine()],
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );

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
            returned_at: new Date(form.returned_at).toISOString(),
            notes: form.notes,
            lines: form.lines.map(toLinePayload),
        }));

        post(ReturnStockController.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () =>
                setData((current) => ({
                    ...current,
                    returned_at: localNow(),
                    notes: '',
                    lines: [emptyStockLine()],
                })),
        });
    }

    const hasSetup = warehouses.length > 0 && products.length > 0;

    return (
        <>
            <Head title="Return stock" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Return stock"
                    description="Take returned units back into a warehouse as sellable stock. Serial numbers are optional."
                />

                {!hasSetup ? (
                    <p className="text-sm text-muted-foreground">
                        You need at least one active warehouse and one product
                        with variants before you can record a return.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-3xl">
                            <CardHeader>
                                <CardTitle>Return</CardTitle>
                                <CardDescription>
                                    Where the stock comes back into and when.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="warehouse_id">
                                        Warehouse
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
                                    <Label htmlFor="returned_at">
                                        Returned at
                                    </Label>
                                    <Input
                                        id="returned_at"
                                        type="datetime-local"
                                        required
                                        max={localNow()}
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
                                        placeholder="Optional, e.g. order number or reason"
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
                                    One line per variant. Serial numbers must be
                                    units that were dispatched.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {data.lines.map((line, index) => (
                                    <StockLine
                                        key={line.key}
                                        line={line}
                                        index={index}
                                        products={products}
                                        canRemove={data.lines.length > 1}
                                        errorFor={errorFor}
                                        onChange={(changes) =>
                                            updateLine(index, changes)
                                        }
                                        onRemove={() =>
                                            setData(
                                                'lines',
                                                data.lines.filter(
                                                    (_, lineIndex) =>
                                                        lineIndex !== index,
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
                                        data.lines.length >= MAX_STOCK_LINES
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
                            </CardContent>
                        </Card>

                        <div className="flex max-w-3xl items-center justify-end gap-4">
                            <span className="text-sm text-muted-foreground">
                                {totalUnits} unit(s) on {data.lines.length}{' '}
                                line(s)
                            </span>
                            <Button disabled={processing}>Return stock</Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

ReturnStock.layout = {
    breadcrumbs: [
        {
            title: 'Return stock',
            href: create(),
        },
    ],
};
