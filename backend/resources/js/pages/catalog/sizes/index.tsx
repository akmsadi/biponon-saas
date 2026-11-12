import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SizeController from '@/actions/App/Http/Controllers/Catalog/SizeController';
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
import { index } from '@/routes/catalog/sizes';

type Size = {
    id: number;
    name: string;
    code: string | null;
    sort_order: number;
    is_active: boolean;
    product_variants_count: number;
};

export default function Sizes({ sizes }: { sizes: Size[] }) {
    // undefined = closed, null = creating, Size = editing
    const [editing, setEditing] = useState<Size | null | undefined>();
    const [deleting, setDeleting] = useState<Size | null>(null);
    const canManage = usePage().props.auth.can.manageCatalog;

    return (
        <>
            <Head title="Sizes" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Sizes"
                        description="Sizes your product variants come in"
                    />
                    {canManage && (
                        <Button onClick={() => setEditing(null)}>
                            <Plus /> Add size
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Code</th>
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
                            {sizes.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No sizes yet. Add your first size to
                                        start building products.
                                    </td>
                                </tr>
                            )}
                            {sizes.map((size) => {
                                const isUsed = size.product_variants_count > 0;

                                return (
                                    <tr
                                        key={size.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {size.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {size.code ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {size.sort_order}
                                        </td>
                                        <td className="px-4 py-3">
                                            {size.product_variants_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    size.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {size.is_active
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
                                                            setEditing(size)
                                                        }
                                                    >
                                                        <Pencil />
                                                        <span className="sr-only">
                                                            Edit {size.name}
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
                                                            setDeleting(size)
                                                        }
                                                    >
                                                        <Trash2 />
                                                        <span className="sr-only">
                                                            Delete {size.name}
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

            <SizeFormDialog
                size={editing}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete size “{deleting?.name}”?</DialogTitle>
                    <DialogDescription>
                        This cannot be undone.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...SizeController.destroy.form(deleting)}
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

function SizeFormDialog({
    size,
    onClose,
}: {
    size: Size | null | undefined;
    onClose: () => void;
}) {
    const isEditing = size != null;
    const action = isEditing
        ? SizeController.update.form(size)
        : SizeController.store.form();

    return (
        <Dialog
            open={size !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogTitle>
                    {isEditing ? 'Edit size' : 'Add size'}
                </DialogTitle>
                <DialogDescription>
                    Inactive sizes stay on existing variants but can't be picked
                    for new ones.
                </DialogDescription>

                <Form
                    key={size?.id ?? 'new'}
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
                                    maxLength={50}
                                    defaultValue={size?.name}
                                    placeholder="e.g. XL"
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
                                        defaultValue={size?.code ?? ''}
                                        placeholder="Optional"
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sort_order">
                                        Sort order
                                    </Label>
                                    <Input
                                        id="sort_order"
                                        name="sort_order"
                                        type="number"
                                        min={0}
                                        defaultValue={size?.sort_order ?? 0}
                                    />
                                    <InputError message={errors.sort_order} />
                                </div>
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={size?.is_active ?? true}
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
                                    {isEditing ? 'Save' : 'Add size'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Sizes.layout = {
    breadcrumbs: [
        {
            title: 'Sizes',
            href: index(),
        },
    ],
};
