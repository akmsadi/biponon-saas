import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import ReceiveStockController from '@/actions/App/Http/Controllers/Operations/ReceiveStockController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import StockLine, {
    MAX_STOCK_LINES,
    emptyStockLine,
    toLinePayload,
} from '@/components/stock-line';
import { inputToIso, nowForInput, useTimeZone } from '@/lib/datetime';
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
import { create } from '@/routes/operations/receive';

type Warehouse = { id: number; name: string; code: string };

type Props = {
    warehouses: Warehouse[];
    defaultWarehouseId: number | null;
    products: LineProduct[];
};

type ReceiveForm = {
    warehouse_id: string;
    received_at: string;
    notes: string;
    lines: StockLineValues[];
};

export default function ReceiveStock({
    warehouses,
    products,
    defaultWarehouseId,
}: Props) {
    const timeZone = useTimeZone();
    const { data, setData, transform, post, processing, errors } =
        useForm<ReceiveForm>({
            warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
            received_at: nowForInput(timeZone),
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
            received_at: inputToIso(form.received_at, timeZone),
            notes: form.notes,
            lines: form.lines.map(toLinePayload),
        }));

        post(ReceiveStockController.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () =>
                setData((current) => ({
                    ...current,
                    received_at: nowForInput(timeZone),
                    notes: '',
                    lines: [emptyStockLine()],
                })),
        });
    }

    const hasSetup = warehouses.length > 0 && products.length > 0;

    return (
        <>
            <Head title="Receive stock" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Receive stock"
                    description="Record incoming stock into a warehouse. Serial numbers are optional."
                />

                {!hasSetup ? (
                    <p className="text-sm text-muted-foreground">
                        You need at least one active warehouse and one active
                        product with active variants before you can receive
                        stock.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-3xl">
                            <CardHeader>
                                <CardTitle>Receipt</CardTitle>
                                <CardDescription>
                                    Where and when the stock arrived.
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

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        maxLength={1000}
                                        placeholder="Optional, e.g. supplier or invoice number"
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
                                    One line per variant. Add serial numbers
                                    only if you track individual units.
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
                            <Button disabled={processing}>Receive stock</Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

ReceiveStock.layout = {
    breadcrumbs: [
        {
            title: 'Receive stock',
            href: create(),
        },
    ],
};
