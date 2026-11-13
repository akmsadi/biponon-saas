import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import SalesOrderController from '@/actions/App/Http/Controllers/Sales/SalesOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import StockLine, {
    MAX_STOCK_LINES,
    emptyStockLine,
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
import { useMoneyFormat } from '@/lib/money';
import { index as customersIndex } from '@/routes/sales/customers';
import { index, show } from '@/routes/sales/orders';

type Option = { id: number; name: string };
type Warehouse = Option & { code: string };

/**
 * A variant with its list price and the units on hand per warehouse id.
 */
type PricedVariant = LineVariant & {
    price: string | null;
    stock: Record<string, number>;
};

type ExistingOrder = {
    id: number;
    number: string;
    customer_id: number;
    warehouse_id: number;
    deliver_by: string | null;
    notes: string | null;
    lines: {
        product_id: number;
        product_variant_id: number;
        quantity: number;
        unit_price: string;
    }[];
};

type Props = {
    order: ExistingOrder | null;
    customers: Option[];
    warehouses: Warehouse[];
    products: LineProduct<PricedVariant>[];
    defaultWarehouseId: number | null;
};

type OrderLine = StockLineValues & { unit_price: string };

type OrderForm = {
    customer_id: string;
    warehouse_id: string;
    deliver_by: string;
    notes: string;
    lines: OrderLine[];
};

function emptyOrderLine(): OrderLine {
    return { ...emptyStockLine(), unit_price: '' };
}

/**
 * Whole cents of quantity × price, or null while either is missing.
 */
function lineCents(line: OrderLine): number | null {
    const quantity = Number.parseInt(line.quantity, 10);
    const price = Number.parseFloat(line.unit_price);

    return Number.isNaN(quantity) || Number.isNaN(price)
        ? null
        : Math.round(price * 100) * quantity;
}

export default function SalesOrderForm({
    order,
    customers,
    warehouses,
    products,
    defaultWarehouseId,
}: Props) {
    const { formatMoney } = useMoneyFormat();
    const { data, setData, transform, post, put, processing, errors } =
        useForm<OrderForm>({
            customer_id: order ? String(order.customer_id) : '',
            warehouse_id: order
                ? String(order.warehouse_id)
                : defaultWarehouseId
                  ? String(defaultWarehouseId)
                  : '',
            deliver_by: order?.deliver_by ?? '',
            notes: order?.notes ?? '',
            lines: order
                ? order.lines.map((line) => ({
                      ...emptyOrderLine(),
                      product_id: String(line.product_id),
                      product_variant_id: String(line.product_variant_id),
                      quantity: String(line.quantity),
                      unit_price: line.unit_price,
                  }))
                : [emptyOrderLine()],
        });

    // Nested keys such as "lines.0.quantity" are not part of the typed error map.
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const variants = new Map(
        products.flatMap((product) =>
            product.variants.map((variant) => [String(variant.id), variant]),
        ),
    );
    const shipFrom = warehouses.find(
        (warehouse) => String(warehouse.id) === data.warehouse_id,
    );
    const totalUnits = data.lines.reduce(
        (sum, line) => sum + (Number.parseInt(line.quantity, 10) || 0),
        0,
    );
    const totalCents = data.lines
        .map(lineCents)
        .reduce<number>((sum, cents) => sum + (cents ?? 0), 0);

    function updateLine(lineIndex: number, changes: Partial<OrderLine>) {
        setData(
            'lines',
            data.lines.map((line, candidateIndex) => {
                if (candidateIndex !== lineIndex) {
                    return line;
                }

                // Picking another variant starts its price from the variant's list price.
                const variantChanged =
                    changes.product_variant_id !== undefined &&
                    changes.product_variant_id !== line.product_variant_id;
                const listPrice = variantChanged
                    ? (variants.get(changes.product_variant_id ?? '')?.price ??
                      '')
                    : undefined;

                return {
                    ...line,
                    ...changes,
                    ...(listPrice !== undefined && { unit_price: listPrice }),
                };
            }),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((form) => ({
            customer_id: form.customer_id,
            warehouse_id: form.warehouse_id,
            deliver_by: form.deliver_by || null,
            notes: form.notes,
            lines: form.lines.map((line) => ({
                product_variant_id: line.product_variant_id,
                quantity: line.quantity,
                unit_price: line.unit_price,
            })),
        }));

        if (order) {
            put(SalesOrderController.update.url(order.id), {
                preserveScroll: true,
            });
        } else {
            post(SalesOrderController.store.url(), {
                preserveScroll: true,
            });
        }
    }

    const title = order ? `Edit ${order.number}` : 'New sales order';
    const hasSetup =
        customers.length > 0 && warehouses.length > 0 && products.length > 0;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={title}
                    description="Saved as a draft. A manager confirms it with the customer; short stock doesn't block that."
                />

                {!hasSetup ? (
                    <p className="text-sm text-muted-foreground">
                        You need at least one active{' '}
                        <Link
                            href={customersIndex()}
                            className="underline underline-offset-4"
                        >
                            customer
                        </Link>
                        , one active warehouse and one active product with
                        active variants before you can create a sales order.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <Card className="max-w-4xl">
                            <CardHeader>
                                <CardTitle>Order</CardTitle>
                                <CardDescription>
                                    Who you're selling to and where the stock
                                    will usually ship from.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="customer_id">
                                        Customer
                                    </Label>
                                    <Select
                                        value={data.customer_id}
                                        onValueChange={(value) =>
                                            setData('customer_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="customer_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choose a customer" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {customers.map((customer) => (
                                                <SelectItem
                                                    key={customer.id}
                                                    value={String(customer.id)}
                                                >
                                                    {customer.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.customer_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="warehouse_id">
                                        Ship from
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
                                    <Label htmlFor="deliver_by">
                                        Deliver by
                                    </Label>
                                    <Input
                                        id="deliver_by"
                                        type="date"
                                        value={data.deliver_by}
                                        onChange={(event) =>
                                            setData(
                                                'deliver_by',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.deliver_by} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        rows={2}
                                        maxLength={2000}
                                        placeholder="Optional, e.g. delivery address or the customer's reference"
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
                                    One line per variant. The price starts from
                                    the variant's list price and can be changed.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {data.lines.map((line, lineIndex) => (
                                    <div key={line.key} className="space-y-1">
                                        <StockLine
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
                                            extraErrorKey="unit_price"
                                            extraField={
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor={`unit-price-${line.key}`}
                                                        className="text-xs text-muted-foreground"
                                                    >
                                                        Unit price
                                                    </Label>
                                                    <Input
                                                        id={`unit-price-${line.key}`}
                                                        type="number"
                                                        inputMode="decimal"
                                                        required
                                                        min={0}
                                                        max={99999999.99}
                                                        step="0.01"
                                                        value={line.unit_price}
                                                        onChange={(event) =>
                                                            updateLine(
                                                                lineIndex,
                                                                {
                                                                    unit_price:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </div>
                                            }
                                        />
                                        <StockHint
                                            variant={variants.get(
                                                line.product_variant_id,
                                            )}
                                            warehouse={shipFrom}
                                            quantity={
                                                Number.parseInt(
                                                    line.quantity,
                                                    10,
                                                ) || 0
                                            }
                                        />
                                    </div>
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
                                line(s) · {formatMoney(totalCents / 100)}
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

/**
 * How many units of the chosen variant are on hand at the ship-from warehouse and in total, flagged when the
 * line asks for more than the ship-from warehouse has.
 */
function StockHint({
    variant,
    warehouse,
    quantity,
}: {
    variant: PricedVariant | undefined;
    warehouse: Warehouse | undefined;
    quantity: number;
}) {
    if (!variant) {
        return null;
    }

    const total = Object.values(variant.stock).reduce(
        (sum, units) => sum + units,
        0,
    );
    const here = warehouse ? (variant.stock[String(warehouse.id)] ?? 0) : null;
    const isShort = here !== null && quantity > here;

    return (
        <p
            className={
                isShort
                    ? 'px-1 text-xs text-amber-700 dark:text-amber-400'
                    : 'px-1 text-xs text-muted-foreground'
            }
        >
            {here !== null && `${here} in stock at ${warehouse?.code} · `}
            {total} in all warehouses
            {isShort && ' — short; it can still be ordered'}
        </p>
    );
}

SalesOrderForm.layout = {
    breadcrumbs: [
        {
            title: 'Sales orders',
            href: index(),
        },
    ],
};
