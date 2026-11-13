import { Form, Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { History, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import ProductVariantController from '@/actions/App/Http/Controllers/Catalog/ProductVariantController';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ProductFields from '@/components/product-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/catalog/products';
import { index as movementsIndex } from '@/routes/inventory/movements';

type Product = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
};

type Size = { id: number; name: string; code: string | null };

type Color = {
    id: number;
    name: string;
    code: string | null;
    hex_code: string | null;
};

type Variant = {
    id: number;
    size_id: number;
    color_id: number;
    sku: string;
    barcode: string | null;
    price: string | null;
    reorder_level: number | null;
    is_active: boolean;
    in_stock_count: number;
    size: Size & { is_active: boolean };
    color: Color & { is_active: boolean };
};

type Props = {
    product: Product;
    variants: Variant[];
    sizes: Size[];
    colors: Color[];
    defaultReorderLevel: number | null;
};

const PRICE_PATTERN = '\\d+(\\.\\d{1,2})?';

function combinationKey(sizeId: number, colorId: number): string {
    return `${sizeId}-${colorId}`;
}

function skuPart(value: string): string {
    return value
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * Suggest a SKU such as TSHIRT-M-RED from the product name and the size and color codes (or names).
 */
function suggestSku(product: Product, size: Size, color: Color): string {
    return [product.name, size.code || size.name, color.code || color.name]
        .map(skuPart)
        .filter(Boolean)
        .join('-')
        .slice(0, 100);
}

export default function ShowProduct({
    product,
    variants,
    sizes,
    colors,
    defaultReorderLevel,
}: Props) {
    const [addingVariants, setAddingVariants] = useState(false);
    // undefined = closed, Variant = editing
    const [editing, setEditing] = useState<Variant | undefined>();
    const [deleting, setDeleting] = useState<Variant | null>(null);
    const [deletingProduct, setDeletingProduct] = useState(false);
    const canManage = usePage().props.auth.can.manageCatalog;

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Products', href: index() },
                { title: product.name, href: show(product) },
            ],
        });
    }, [product]);

    return (
        <>
            <Head title={product.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Heading
                            title={product.name}
                            description="Product details and its size × color variants"
                        />
                        {!product.is_active && (
                            <Badge variant="outline" className="mt-1">
                                Inactive
                            </Badge>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={movementsIndex({
                                    query: { product: product.id },
                                })}
                            >
                                <History /> View movements
                            </Link>
                        </Button>
                        {canManage && (
                            <Button
                                variant="outline"
                                onClick={() => setDeletingProduct(true)}
                            >
                                <Trash2 /> Delete product
                            </Button>
                        )}
                    </div>
                </div>

                {!canManage && product.description && (
                    <p className="max-w-2xl text-sm whitespace-pre-line text-muted-foreground">
                        {product.description}
                    </p>
                )}

                {canManage && (
                    <Card className="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ProductController.update.form(product)}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <ProductFields
                                            product={product}
                                            errors={errors}
                                        />
                                        <Button disabled={processing}>
                                            Save
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Variants"
                        description="One per size and color. Stock is received against a variant."
                    />
                    {canManage && (
                        <Button onClick={() => setAddingVariants(true)}>
                            <Plus /> Add variants
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Size</th>
                                <th className="px-4 py-3 font-medium">Color</th>
                                <th className="px-4 py-3 font-medium">SKU</th>
                                <th className="px-4 py-3 font-medium">
                                    Barcode
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Price
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    In stock
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Reorder at
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {variants.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No variants yet. Add the sizes and
                                        colors this product comes in.
                                    </td>
                                </tr>
                            )}
                            {variants.map((variant) => (
                                <tr
                                    key={variant.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {variant.size.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ColorSwatch
                                                hex={variant.color.hex_code}
                                            />
                                            {variant.color.name}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {variant.sku}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {variant.barcode ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {variant.price ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {variant.in_stock_count}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {variant.reorder_level ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                variant.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {variant.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {canManage && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setEditing(variant)
                                                    }
                                                >
                                                    <Pencil />
                                                    <span className="sr-only">
                                                        Edit {variant.sku}
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setDeleting(variant)
                                                    }
                                                >
                                                    <Trash2 />
                                                    <span className="sr-only">
                                                        Delete {variant.sku}
                                                    </span>
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <AddVariantsDialog
                open={addingVariants}
                onClose={() => setAddingVariants(false)}
                product={product}
                variants={variants}
                sizes={sizes}
                colors={colors}
                defaultReorderLevel={defaultReorderLevel}
            />

            <EditVariantDialog
                variant={editing}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete variant “{deleting?.sku}”?</DialogTitle>
                    <DialogDescription>
                        Only variants that have never held stock can be deleted.
                        Otherwise, deactivate it instead.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...ProductVariantController.destroy.form(deleting)}
                            options={{ preserveScroll: true }}
                            onFinish={() => setDeleting(null)}
                        >
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Delete
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={deletingProduct} onOpenChange={setDeletingProduct}>
                <DialogContent>
                    <DialogTitle>Delete product “{product.name}”?</DialogTitle>
                    <DialogDescription>
                        Its {variants.length} variant(s) are deleted too. This
                        is only possible if none of them has ever held stock.
                        Otherwise, deactivate the product instead.
                    </DialogDescription>
                    <Form
                        {...ProductController.destroy.form(product)}
                        onFinish={() => setDeletingProduct(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function AddVariantsDialog({
    open,
    onClose,
    product,
    variants,
    sizes,
    colors,
    defaultReorderLevel,
}: {
    open: boolean;
    onClose: () => void;
    product: Product;
    variants: Variant[];
    sizes: Size[];
    colors: Color[];
    defaultReorderLevel: number | null;
}) {
    const existing = useMemo(
        () =>
            new Set(
                variants.map((variant) =>
                    combinationKey(variant.size_id, variant.color_id),
                ),
            ),
        [variants],
    );
    const [selected, setSelected] = useState<Set<string>>(new Set());

    // New combinations in grid order (sizes first, then colors), which is also the submitted order.
    const rows = sizes.flatMap((size) =>
        colors
            .filter((color) => selected.has(combinationKey(size.id, color.id)))
            .map((color) => ({
                key: combinationKey(size.id, color.id),
                size,
                color,
            })),
    );

    const availableKeys = sizes.flatMap((size) =>
        colors
            .map((color) => combinationKey(size.id, color.id))
            .filter((key) => !existing.has(key)),
    );
    const allSelected =
        availableKeys.length > 0 && selected.size === availableKeys.length;

    function toggle(key: string, checked: boolean) {
        setSelected((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(key);
            } else {
                next.delete(key);
            }

            return next;
        });
    }

    function close() {
        setSelected(new Set());
        onClose();
    }

    const hasCatalog = sizes.length > 0 && colors.length > 0;

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogTitle>Add variants</DialogTitle>
                <DialogDescription>
                    Tick the size and color combinations to add. Existing ones
                    are already ticked. Only active sizes and colors are shown.
                </DialogDescription>

                {!hasCatalog ? (
                    <p className="text-sm text-muted-foreground">
                        You need at least one active size and one active color
                        first. Add them under Catalog → Sizes and Colors.
                    </p>
                ) : (
                    <Form
                        {...ProductVariantController.store.form(product)}
                        options={{ preserveScroll: true }}
                        onSuccess={close}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="text-sm">
                                        <thead className="border-b bg-muted/50">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                                                    Size \ Color
                                                </th>
                                                {colors.map((color) => (
                                                    <th
                                                        key={color.id}
                                                        className="px-3 py-2 font-medium"
                                                    >
                                                        <div className="flex flex-col items-center gap-1">
                                                            <ColorSwatch
                                                                hex={
                                                                    color.hex_code
                                                                }
                                                                className="size-4"
                                                            />
                                                            <span className="whitespace-nowrap">
                                                                {color.name}
                                                            </span>
                                                        </div>
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sizes.map((size) => (
                                                <tr
                                                    key={size.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <th className="px-3 py-2 text-left font-medium whitespace-nowrap">
                                                        {size.name}
                                                    </th>
                                                    {colors.map((color) => {
                                                        const key =
                                                            combinationKey(
                                                                size.id,
                                                                color.id,
                                                            );
                                                        const exists =
                                                            existing.has(key);

                                                        return (
                                                            <td
                                                                key={color.id}
                                                                className="px-3 py-2 text-center"
                                                            >
                                                                <Checkbox
                                                                    aria-label={`${size.name} / ${color.name}`}
                                                                    checked={
                                                                        exists ||
                                                                        selected.has(
                                                                            key,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        exists
                                                                    }
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) =>
                                                                        toggle(
                                                                            key,
                                                                            checked ===
                                                                                true,
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {availableKeys.length > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setSelected(
                                                allSelected
                                                    ? new Set()
                                                    : new Set(availableKeys),
                                            )
                                        }
                                    >
                                        {allSelected
                                            ? 'Clear selection'
                                            : 'Select all new'}
                                    </Button>
                                )}

                                {rows.length > 0 && (
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-[minmax(0,7rem)_1fr_1fr] gap-x-3 text-xs font-medium text-muted-foreground">
                                            <span>Variant</span>
                                            <span>SKU</span>
                                            <span>Barcode (optional)</span>
                                        </div>
                                        {rows.map((row, rowIndex) => {
                                            const rowErrors = [
                                                errors[
                                                    `variants.${rowIndex}.size_id`
                                                ],
                                                errors[
                                                    `variants.${rowIndex}.color_id`
                                                ],
                                                errors[
                                                    `variants.${rowIndex}.sku`
                                                ],
                                                errors[
                                                    `variants.${rowIndex}.barcode`
                                                ],
                                            ].filter(Boolean);

                                            return (
                                                <div
                                                    key={row.key}
                                                    className="space-y-1"
                                                >
                                                    <div className="grid grid-cols-[minmax(0,7rem)_1fr_1fr] items-center gap-x-3">
                                                        <div className="flex items-center gap-2 text-sm">
                                                            <ColorSwatch
                                                                hex={
                                                                    row.color
                                                                        .hex_code
                                                                }
                                                                className="size-4"
                                                            />
                                                            <span className="truncate">
                                                                {row.size.name}{' '}
                                                                /{' '}
                                                                {row.color.name}
                                                            </span>
                                                        </div>
                                                        <input
                                                            type="hidden"
                                                            name={`variants[${rowIndex}][size_id]`}
                                                            value={row.size.id}
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name={`variants[${rowIndex}][color_id]`}
                                                            value={row.color.id}
                                                        />
                                                        <Input
                                                            name={`variants[${rowIndex}][sku]`}
                                                            required
                                                            maxLength={100}
                                                            defaultValue={suggestSku(
                                                                product,
                                                                row.size,
                                                                row.color,
                                                            )}
                                                            aria-label={`SKU for ${row.size.name} / ${row.color.name}`}
                                                            className="font-mono uppercase"
                                                        />
                                                        <Input
                                                            name={`variants[${rowIndex}][barcode]`}
                                                            maxLength={100}
                                                            aria-label={`Barcode for ${row.size.name} / ${row.color.name}`}
                                                            className="font-mono"
                                                        />
                                                    </div>
                                                    {rowErrors.map(
                                                        (message) => (
                                                            <InputError
                                                                key={message}
                                                                message={
                                                                    message
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-4">
                                    <div className="grid max-w-48 gap-2">
                                        <Label htmlFor="price">
                                            Price for all (optional)
                                        </Label>
                                        <Input
                                            id="price"
                                            name="price"
                                            inputMode="decimal"
                                            pattern={PRICE_PATTERN}
                                            placeholder="0.00"
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                    <div className="grid max-w-48 gap-2">
                                        <Label htmlFor="new_reorder_level">
                                            Reorder level for all
                                        </Label>
                                        <Input
                                            id="new_reorder_level"
                                            name="reorder_level"
                                            type="number"
                                            min={0}
                                            max={1000000}
                                            step={1}
                                            defaultValue={
                                                defaultReorderLevel ?? ''
                                            }
                                            placeholder="Optional"
                                        />
                                        <InputError
                                            message={errors.reorder_level}
                                        />
                                    </div>
                                </div>
                                <InputError message={errors.variants} />

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        disabled={
                                            processing || rows.length === 0
                                        }
                                    >
                                        {rows.length === 1
                                            ? 'Add 1 variant'
                                            : `Add ${rows.length} variants`}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function EditVariantDialog({
    variant,
    onClose,
}: {
    variant: Variant | undefined;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={variant !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogTitle>
                    Edit variant {variant?.size.name} / {variant?.color.name}
                </DialogTitle>
                <DialogDescription>
                    Size and color can't change. To fix them, delete this
                    variant and add a new one.
                </DialogDescription>

                {variant && (
                    <Form
                        key={variant.id}
                        {...ProductVariantController.update.form(variant)}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="sku">SKU</Label>
                                    <Input
                                        id="sku"
                                        name="sku"
                                        required
                                        maxLength={100}
                                        defaultValue={variant.sku}
                                        className="font-mono uppercase"
                                    />
                                    <InputError message={errors.sku} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="barcode">Barcode</Label>
                                        <Input
                                            id="barcode"
                                            name="barcode"
                                            maxLength={100}
                                            defaultValue={variant.barcode ?? ''}
                                            placeholder="Optional"
                                            className="font-mono"
                                        />
                                        <InputError message={errors.barcode} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="price">Price</Label>
                                        <Input
                                            id="price"
                                            name="price"
                                            inputMode="decimal"
                                            pattern={PRICE_PATTERN}
                                            defaultValue={variant.price ?? ''}
                                            placeholder="Optional"
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                </div>

                                <div className="grid max-w-48 gap-2">
                                    <Label htmlFor="reorder_level">
                                        Reorder level
                                    </Label>
                                    <Input
                                        id="reorder_level"
                                        name="reorder_level"
                                        type="number"
                                        min={0}
                                        max={1000000}
                                        step={1}
                                        defaultValue={
                                            variant.reorder_level ?? ''
                                        }
                                        placeholder="Optional"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Flagged as low stock in a warehouse at
                                        or below this quantity.
                                    </p>
                                    <InputError
                                        message={errors.reorder_level}
                                    />
                                </div>

                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="variant_is_active"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={variant.is_active}
                                    />
                                    <Label htmlFor="variant_is_active">
                                        Active
                                    </Label>
                                </div>
                                {variant.is_active &&
                                    variant.in_stock_count > 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            {variant.in_stock_count} units are
                                            in stock. Deactivating keeps them.
                                        </p>
                                    )}
                                <InputError message={errors.is_active} />

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button disabled={processing}>Save</Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

ShowProduct.layout = {
    breadcrumbs: [
        {
            title: 'Products',
            href: index(),
        },
    ],
};
