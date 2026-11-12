import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ColorController from '@/actions/App/Http/Controllers/Catalog/ColorController';
import ColorSwatch from '@/components/color-swatch';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index } from '@/routes/catalog/colors';

type Color = {
    id: number;
    name: string;
    code: string | null;
    hex_code: string | null;
    sort_order: number;
    is_active: boolean;
    product_variants_count: number;
};

const HEX_PATTERN = /^#[0-9A-Fa-f]{6}$/;

export default function Colors({ colors }: { colors: Color[] }) {
    // undefined = closed, null = creating, Color = editing
    const [editing, setEditing] = useState<Color | null | undefined>();
    const [deleting, setDeleting] = useState<Color | null>(null);
    const canManage = usePage().props.auth.can.manageCatalog;

    return (
        <>
            <Head title="Colors" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Colors"
                        description="Colors your product variants come in"
                    />
                    {canManage && (
                        <Button onClick={() => setEditing(null)}>
                            <Plus /> Add color
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">Hex</th>
                                <th className="px-4 py-3 font-medium">
                                    Sort order
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Variants
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
                            {colors.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No colors yet. Add your first color to
                                        start building products.
                                    </td>
                                </tr>
                            )}
                            {colors.map((color) => {
                                const isUsed = color.product_variants_count > 0;

                                return (
                                    <tr
                                        key={color.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <div className="flex items-center gap-2">
                                                <ColorSwatch
                                                    hex={color.hex_code}
                                                />
                                                {color.name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {color.code ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {color.hex_code ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {color.sort_order}
                                        </td>
                                        <td className="px-4 py-3">
                                            {color.product_variants_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    color.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {color.is_active
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
                                                            setEditing(color)
                                                        }
                                                    >
                                                        <Pencil />
                                                        <span className="sr-only">
                                                            Edit {color.name}
                                                        </span>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        disabled={isUsed}
                                                        title={
                                                            isUsed
                                                                ? 'Used by product variants'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            setDeleting(color)
                                                        }
                                                    >
                                                        <Trash2 />
                                                        <span className="sr-only">
                                                            Delete {color.name}
                                                        </span>
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <ColorFormDialog
                color={editing}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete color “{deleting?.name}”?</DialogTitle>
                    <DialogDescription>
                        This cannot be undone.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...ColorController.destroy.form(deleting)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setDeleting(null)}
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
        </>
    );
}

function ColorFormDialog({
    color,
    onClose,
}: {
    color: Color | null | undefined;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={color !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {color !== undefined && (
                    <ColorForm
                        key={color?.id ?? 'new'}
                        color={color}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ColorForm({
    color,
    onClose,
}: {
    color: Color | null;
    onClose: () => void;
}) {
    const isEditing = color !== null;
    const action = isEditing
        ? ColorController.update.form(color)
        : ColorController.store.form();

    // The text field is the submitted value; the picker just edits it.
    const [hex, setHex] = useState(color?.hex_code ?? '');
    const isValidHex = HEX_PATTERN.test(hex);

    return (
        <>
            <DialogTitle>{isEditing ? 'Edit color' : 'Add color'}</DialogTitle>
            <DialogDescription>
                Inactive colors stay on existing variants but can't be picked
                for new ones.
            </DialogDescription>

            <Form
                {...action}
                options={{ preserveScroll: true }}
                onSuccess={onClose}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                maxLength={60}
                                defaultValue={color?.name}
                                placeholder="e.g. Navy"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="code">Code</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    maxLength={30}
                                    defaultValue={color?.code ?? ''}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">Sort order</Label>
                                <Input
                                    id="sort_order"
                                    name="sort_order"
                                    type="number"
                                    min={0}
                                    defaultValue={color?.sort_order ?? 0}
                                />
                                <InputError message={errors.sort_order} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="hex_code">Hex color</Label>
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    aria-label="Pick a color"
                                    value={isValidHex ? hex : '#000000'}
                                    onChange={(event) =>
                                        setHex(event.target.value.toUpperCase())
                                    }
                                    className="h-9 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                />
                                <Input
                                    id="hex_code"
                                    name="hex_code"
                                    maxLength={7}
                                    value={hex}
                                    onChange={(event) =>
                                        setHex(event.target.value)
                                    }
                                    placeholder="#1F2A44 (optional)"
                                    className="font-mono"
                                />
                                {hex !== '' && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setHex('')}
                                    >
                                        Clear
                                    </Button>
                                )}
                            </div>
                            <InputError message={errors.hex_code} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="is_active"
                                name="is_active"
                                value="1"
                                defaultChecked={color?.is_active ?? true}
                            />
                            <Label htmlFor="is_active">Active</Label>
                        </div>
                        <InputError message={errors.is_active} />

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button disabled={processing}>
                                {isEditing ? 'Save' : 'Add color'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </>
    );
}

Colors.layout = {
    breadcrumbs: [
        {
            title: 'Colors',
            href: index(),
        },
    ],
};
