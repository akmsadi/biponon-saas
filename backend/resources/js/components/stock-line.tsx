import { ListPlus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import ColorSwatch from '@/components/color-swatch';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type LineVariant = {
    id: number;
    sku: string;
    size: string;
    color: string;
    hex_code: string | null;
};

export type LineProduct<V extends LineVariant = LineVariant> = {
    id: number;
    name: string;
    variants: V[];
};

/**
 * One line of a stock operation form: a variant, a quantity and optional serial numbers as typed.
 */
export type StockLineValues = {
    key: number;
    product_id: string;
    product_variant_id: string;
    quantity: string;
    serials: string;
    showSerials: boolean;
};

export const MAX_STOCK_LINES = 50;

let nextLineKey = 0;

export function emptyStockLine(): StockLineValues {
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
 * The fields the server expects for each line.
 */
export function toLinePayload(line: StockLineValues) {
    return {
        product_variant_id: line.product_variant_id,
        quantity: line.quantity,
        serials: line.serials,
    };
}

function countSerials(text: string): number {
    return text.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '').length;
}

/**
 * Product → variant → quantity row with a toggleable serials textarea. When `availableFor` is given,
 * each variant shows how many units are available and the quantity is capped at that number.
 * `extraField` adds a column after the quantity (e.g. unit cost) whose error is read from `extraErrorKey`;
 * `withSerials={false}` hides the serials toggle.
 */
export default function StockLine<V extends LineVariant>({
    line,
    index,
    products,
    availableFor,
    canRemove,
    errorFor,
    onChange,
    onRemove,
    extraField,
    extraErrorKey,
    withSerials = true,
}: {
    line: StockLineValues;
    index: number;
    products: LineProduct<V>[];
    availableFor?: (variant: V) => number;
    canRemove: boolean;
    errorFor: (key: string) => string | undefined;
    onChange: (changes: Partial<StockLineValues>) => void;
    onRemove: () => void;
    extraField?: ReactNode;
    extraErrorKey?: string;
    withSerials?: boolean;
}) {
    const product = products.find(
        (candidate) => String(candidate.id) === line.product_id,
    );
    const variant = product?.variants.find(
        (candidate) => String(candidate.id) === line.product_variant_id,
    );
    const available = variant && availableFor ? availableFor(variant) : null;
    const serialCount = countSerials(line.serials);
    const lineErrors = [
        errorFor(`lines.${index}.product_variant_id`),
        errorFor(`lines.${index}.quantity`),
        extraErrorKey && errorFor(`lines.${index}.${extraErrorKey}`),
    ].filter(Boolean) as string[];

    return (
        <div className="space-y-2 rounded-lg border p-3">
            <div
                className={
                    extraField
                        ? 'grid gap-3 sm:grid-cols-[1fr_1fr_6rem_8rem_auto] sm:items-end'
                        : 'grid gap-3 sm:grid-cols-[1fr_1fr_7rem_auto] sm:items-end'
                }
            >
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
                            {product?.variants.map((candidate) => (
                                <SelectItem
                                    key={candidate.id}
                                    value={String(candidate.id)}
                                >
                                    <ColorSwatch
                                        hex={candidate.hex_code}
                                        className="size-3.5"
                                    />
                                    {candidate.size} · {candidate.color}
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {candidate.sku}
                                        {availableFor &&
                                            ` · ${availableFor(candidate)} in stock`}
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
                        {available === null
                            ? 'Quantity'
                            : `Quantity (max ${available})`}
                    </Label>
                    <Input
                        id={`quantity-${line.key}`}
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={available ?? 10000}
                        step={1}
                        required
                        value={line.quantity}
                        onChange={(event) =>
                            onChange({ quantity: event.target.value })
                        }
                    />
                </div>

                {extraField}

                <div className="flex gap-1">
                    {withSerials && (
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
                    )}
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

            {withSerials && line.showSerials && (
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
