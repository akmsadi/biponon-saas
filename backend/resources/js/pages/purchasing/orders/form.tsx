import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import StockLine, {
    MAX_STOCK_LINES,
    emptyStockLine,
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
import { useMoneyFormat } from '@/lib/money';
import { index, show } from '@/routes/purchasing/orders';
import { index as suppliersIndex } from '@/routes/purchasing/suppliers';

type Option = { id: number; name: string };
type Warehouse = Option & { code: string };

type ExistingOrder = {
    id: number;
    number: string;
    supplier_id: number;
    warehouse_id: number;
    expected_at: string | null;
    notes: string | null;
    lines: {
        product_id: number;
        product_variant_id: number;
        quantity: number;
        unit_cost?: string | null;
    }[];
};

type Props = {
    order: ExistingOrder | null;
    suppliers: Option[];
    warehouses: Warehouse[];
    products: LineProduct[];
    defaultWarehouseId: number | null;
    canSeeCosts: boolean;
};

type OrderLine = StockLineValues & { unit_cost: string };

type OrderForm = {
    supplier_id: string;
    warehouse_id: string;
    expected_at: string;
    notes: string;
    lines: OrderLine[];
};

function emptyOrderLine(): OrderLine {
    return { ...emptyStockLine(), unit_cost: '' };
}

/**
 * Whole cents of quantity × cost, or null while either is missing.
 */
function lineCents(line: OrderLine): number | null {
    const quantity = Number.parseInt(line.quantity, 10);
    const cost = Number.parseFloat(line.unit_cost);

    return Number.isNaN(quantity) || Number.isNaN(cost)
        ? null
        : Math.round(cost * 100) * quantity;
}

export default function PurchaseOrderForm({
    order,
    suppliers,
    warehouses,
    products,
    defaultWarehouseId,
    canSeeCosts,
}: Props) {
    const { formatMoney } = useMoneyFormat();
    const { data, setData, transform, post, put, processing, errors } =
        useForm<OrderForm>({
            supplier_id: order ? String(order.supplier_id) : '',
            warehouse_id: order
                ? String(order.warehouse_id)
                : defaultWarehouseId
                  ? String(defaultWarehouseId)
                  : '',
            expected_at: order?.expected_at ?? '',
            notes: order?.notes ?? '',
            lines: order
                ? order.lines.map((line) => ({
                      ...emptyOrderLine(),
                      product_id: String(line.product_id),
                      product_variant_id: String(line.product_variant_id),
                      quantity: String(line.quantity),
                      unit_cost: line.unit_cost ?? '',
                  }))
                : [emptyOrderLine()],
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );
    const costedCents = data.lines
        .map(lineCents)
        .filter((cents): cents is number => cents !== null);
    const totalCents = costedCents.reduce((sum, cents) => sum + cents, 0);

    function updateLine(lineIndex: number, changes: Partial<OrderLine>) {
        setData(
            'lines',
            data.lines.map((line, candidateIndex) =>
                candidateIndex === lineIndex ? { ...line, ...changes } : line,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((form) => ({
            supplier_id: form.supplier_id,
            warehouse_id: form.warehouse_id,
            expected_at: form.expected_at || null,
            notes: form.notes,
            lines: form.lines.map((line) => ({
                product_variant_id: line.product_variant_id,
                quantity: line.quantity,
                ...(canSeeCosts && { unit_cost: line.unit_cost }),
            })),
        }));

        if (order) {
            put(PurchaseOrderController.update.url(order.id), {
                preserveScroll: true,
            });
        } else {
            post(PurchaseOrderController.store.url(), {
                preserveScroll: true,
            });
        }
    }

    const title = order ? `Edit ${order.number}` : 'New purchase order';
    const hasSetup =
        suppliers.length > 0 && warehouses.length > 0 && products.length > 0;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={title}
                    description="Saved as a draft. A manager marks it as ordered once it's sent to the supplier."
                />

                {!hasSetup ? (
                    <p className="text-sm text-muted-foreground">
                        You need at least one active{' '}
                        <Link
                            href={suppliersIndex()}
                            className="underline underline-offset-4"
                        >
                            supplier
                        </Link>
                        , one active warehouse and one active product with
                        active variants before you can create a purchase order.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-4xl">
                            <CardHeader>
                                <CardTitle>Order</CardTitle>
                                <CardDescription>
                                    Who you're buying from and where the stock
                                    will arrive.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="supplier_id">
                                        Supplier
                                    </Label>
                                    <Select
                                        value={data.supplier_id}
                                        onValueChange={(value) =>
                                            setData('supplier_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="supplier_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choose a supplier" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {suppliers.map((supplier) => (
                                                <SelectItem
                                                    key={supplier.id}
                                                    value={String(supplier.id)}
                                                >
                                                    {supplier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.supplier_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="warehouse_id">
                                        Deliver to
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
                                    <Label htmlFor="expected_at">
                                        Expected delivery
                                    </Label>
                                    <Input
                                        id="expected_at"
                                        type="date"
                                        value={data.expected_at}
                                        onChange={(event) =>
                                            setData(
                                                'expected_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.expected_at} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        rows={2}
                                        maxLength={2000}
                                        placeholder="Optional, e.g. payment terms or the supplier's quote number"
                                        value={data.notes}
                                        onChange={(event) =>
                                            setData('notes', event.target.value)
                                        }
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="max-w-4xl">
                            <CardHeader>
                                <CardTitle>Items</CardTitle>
                                <CardDescription>
                                    One line per variant.
                                    {canSeeCosts &&
                                        ' Unit cost is optional and only visible to managers and admins.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {data.lines.map((line, lineIndex) => (
                                    <StockLine
                                        key={line.key}
                                        line={line}
                                        index={lineIndex}
                                        products={products}
                                        withSerials={false}
                                        canRemove={data.lines.length > 1}
                                        errorFor={errorFor}
                                        onChange={(changes) =>
                                            updateLine(lineIndex, changes)
                                        }
                                        onRemove={() =>
                                            setData(
                                                'lines',
                                                data.lines.filter(
                                                    (_, candidateIndex) =>
                                                        candidateIndex !==
                                                        lineIndex,
                                                ),
                                            )
                                        }
                                        extraErrorKey={
                                            canSeeCosts
                                                ? 'unit_cost'
                                                : undefined
                                        }
                                        extraField={
                                            canSeeCosts && (
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor={`unit-cost-${line.key}`}
                                                        className="text-xs text-muted-foreground"
                                                    >
                                                        Unit cost
                                                    </Label>
                                                    <Input
                                                        id={`unit-cost-${line.key}`}
                                                        type="number"
                                                        inputMode="decimal"
                                                        min={0}
                                                        max={99999999.99}
                                                        step="0.01"
                                                        placeholder="Optional"
                                                        value={line.unit_cost}
                                                        onChange={(event) =>
                                                            updateLine(
                                                                lineIndex,
                                                                {
                                                                    unit_cost:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </div>
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
                                            emptyOrderLine(),
                                        ])
                                    }
                                >
                                    <Plus /> Add line
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="flex max-w-4xl flex-wrap items-center justify-end gap-4">
                            <span className="text-sm text-muted-foreground">
                                {totalUnits} unit(s) on {data.lines.length}{' '}
                                line(s)
                                {canSeeCosts &&
                                    costedCents.length > 0 &&
                                    ` · ${formatMoney(totalCents / 100)}${
                                        costedCents.length < data.lines.length
                                            ? ' (some lines have no cost)'
                                            : ''
                                    }`}
                            </span>
                            <Button variant="outline" asChild>
                                <Link href={order ? show(order.id) : index()}>
                                    Back
                                </Link>
                            </Button>
                            <Button disabled={processing}>
                                {order ? 'Save draft' : 'Create draft'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

PurchaseOrderForm.layout = {
    breadcrumbs: [
        {
            title: 'Purchase orders',
            href: index(),
        },
    ],
};
