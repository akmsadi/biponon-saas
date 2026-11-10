import { Head, useForm } from '@inertiajs/react';
import { ListPlus, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import ReceiveStockController from '@/actions/App/Http/Controllers/Operations/ReceiveStockController';
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
import { create } from '@/routes/operations/receive';

type Warehouse = { id: number; name: string; code: string };

type Variant = {
    id: number;
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
};

type Product = { id: number; name: string; variants: Variant[] };

type Props = {
    warehouses: Warehouse[];
    products: Product[];
};

type Line = {
    key: number;
    product_id: string;
    product_variant_id: string;
    quantity: string;
    serials: string;
    showSerials: boolean;
};

type ReceiveForm = {
    warehouse_id: string;
    received_at: string;
    notes: string;
    lines: Line[];
};

const MAX_LINES = 50;

let nextLineKey = 0;

function emptyLine(): Line {
    return {
        key: nextLineKey++,
        product_id: '',
        product_variant_id: '',
        quantity: '',
        serials: '',
        showSerials: false,
    };
}

/**
 * The current local time in the format a datetime-local input expects (YYYY-MM-DDTHH:mm).
 */
function localNow(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());

    return now.toISOString().slice(0, 16);
}

function countSerials(text: string): number {
    return text.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '').length;
}

export default function ReceiveStock({ warehouses, products }: Props) {
    const { data, setData, transform, post, processing, errors } =
        useForm<ReceiveForm>({
            warehouse_id: '',
            received_at: localNow(),
            notes: '',
            lines: [emptyLine()],
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );

    function updateLine(index: number, changes: Partial<Line>) {
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
            received_at: new Date(form.received_at).toISOString(),
            notes: form.notes,
            lines: form.lines.map((line) => ({
                product_variant_id: line.product_variant_id,
                quantity: line.quantity,
                serials: line.serials,
            })),
        }));

        post(ReceiveStockController.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () =>
                setData((current) => ({
                    ...current,
                    received_at: localNow(),
                    notes: '',
                    lines: [emptyLine()],
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
                                        max={localNow()}
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
                                    <ReceiveLine
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
                                    disabled={data.lines.length >= MAX_LINES}
                                    onClick={() =>
                                        setData('lines', [
                                            ...data.lines,
                                            emptyLine(),
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

function ReceiveLine({
    line,
    index,
    products,
    canRemove,
    errorFor,
    onChange,
    onRemove,
}: {
    line: Line;
    index: number;
    products: Product[];
    canRemove: boolean;
    errorFor: (key: string) => string | undefined;
    onChange: (changes: Partial<Line>) => void;
    onRemove: () => void;
}) {
    const product = products.find(
        (candidate) => String(candidate.id) === line.product_id,
    );
    const serialCount = countSerials(line.serials);
    const lineErrors = [
        errorFor(`lines.${index}.product_variant_id`),
        errorFor(`lines.${index}.quantity`),
    ].filter(Boolean) as string[];

    return (
        <div className="space-y-2 rounded-lg border p-3">
            <div className="grid gap-3 sm:grid-cols-[1fr_1fr_7rem_auto] sm:items-end">
                <div className="grid gap-1.5">
                    <Label className="text-xs text-muted-foreground">
                        Product
                    </Label>
                    <Select
                        value={line.product_id}
                        onValueChange={(value) =>
                            onChange({
                                product_id: value,
                                product_variant_id: '',
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full"
                            aria-label={`Product for line ${index + 1}`}
                        >
                            <SelectValue placeholder="Choose a product" />
                        </SelectTrigger>
                        <SelectContent>
                            {products.map((candidate) => (
                                <SelectItem
                                    key={candidate.id}
                                    value={String(candidate.id)}
                                >
                                    {candidate.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-1.5">
                    <Label className="text-xs text-muted-foreground">
                        Variant
                    </Label>
                    <Select
                        value={line.product_variant_id}
                        disabled={!product}
                        onValueChange={(value) =>
                            onChange({ product_variant_id: value })
                        }
                    >
                        <SelectTrigger
                            className="w-full"
                            aria-label={`Variant for line ${index + 1}`}
                        >
                            <SelectValue placeholder="Size · color" />
                        </SelectTrigger>
                        <SelectContent>
                            {product?.variants.map((variant) => (
                                <SelectItem
                                    key={variant.id}
                                    value={String(variant.id)}
                                >
                                    <ColorSwatch
                                        hex={variant.hex_code}
                                        className="size-3.5"
                                    />
                                    {variant.size} · {variant.color}
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {variant.sku}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`quantity-${line.key}`}
                        className="text-xs text-muted-foreground"
                    >
                        Quantity
                    </Label>
                    <Input
                        id={`quantity-${line.key}`}
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={10000}
                        step={1}
                        required
                        value={line.quantity}
                        onChange={(event) =>
                            onChange({ quantity: event.target.value })
                        }
                    />
                </div>

                <div className="flex gap-1">
                    <Button
                        type="button"
                        variant={line.showSerials ? 'secondary' : 'ghost'}
                        size="icon"
                        onClick={() =>
                            onChange({ showSerials: !line.showSerials })
                        }
                    >
                        <ListPlus />
                        <span className="sr-only">
                            Serial numbers for line {index + 1}
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
                        <span className="sr-only">Remove line {index + 1}</span>
                    </Button>
                </div>
            </div>

            {lineErrors.map((message) => (
                <InputError key={message} message={message} />
            ))}

            {line.showSerials && (
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`serials-${line.key}`}
                        className="text-xs text-muted-foreground"
                    >
                        Serial numbers, one per line ({serialCount} of{' '}
                        {line.quantity || 0}). Leave empty to skip.
                    </Label>
                    <textarea
                        id={`serials-${line.key}`}
                        rows={4}
                        value={line.serials}
                        onChange={(event) =>
                            onChange({ serials: event.target.value })
                        }
                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                </div>
            )}
            <InputError message={errorFor(`lines.${index}.serials`)} />
        </div>
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
