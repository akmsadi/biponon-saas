import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SupplierController from '@/actions/App/Http/Controllers/Purchasing/SupplierController';
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
import { index } from '@/routes/purchasing/suppliers';

type Supplier = {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
};

const textareaClassName =
    'flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

export default function Suppliers({ suppliers }: { suppliers: Supplier[] }) {
    // undefined = closed, null = creating, Supplier = editing
    const [editing, setEditing] = useState<Supplier | null | undefined>();
    const [deleting, setDeleting] = useState<Supplier | null>(null);
    const canManage = usePage().props.auth.can.managePurchasing;

    return (
        <>
            <Head title="Suppliers" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Suppliers"
                        description="Businesses you buy stock from"
                    />
                    {canManage && (
                        <Button onClick={() => setEditing(null)}>
                            <Plus /> Add supplier
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Contact
                                </th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Phone</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {suppliers.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No suppliers yet.
                                        {canManage &&
                                            ' Add one to start creating purchase orders.'}
                                    </td>
                                </tr>
                            )}
                            {suppliers.map((supplier) => (
                                <tr
                                    key={supplier.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {supplier.name}
                                        </div>
                                        {supplier.address && (
                                            <div className="text-xs text-muted-foreground">
                                                {supplier.address}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {supplier.contact_name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {supplier.email ? (
                                            <a
                                                href={`mailto:${supplier.email}`}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {supplier.email}
                                            </a>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {supplier.phone ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                supplier.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {supplier.is_active
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
                                                        setEditing(supplier)
                                                    }
                                                >
                                                    <Pencil />
                                                    <span className="sr-only">
                                                        Edit {supplier.name}
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setDeleting(supplier)
                                                    }
                                                >
                                                    <Trash2 />
                                                    <span className="sr-only">
                                                        Delete {supplier.name}
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

            <SupplierFormDialog
                supplier={editing}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Delete supplier “{deleting?.name}”?
                    </DialogTitle>
                    <DialogDescription>
                        Suppliers with purchase orders can't be deleted.
                        Deactivate them instead.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...SupplierController.destroy.form(deleting)}
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
        </>
    );
}

function SupplierFormDialog({
    supplier,
    onClose,
}: {
    supplier: Supplier | null | undefined;
    onClose: () => void;
}) {
    const isEditing = supplier != null;
    const action = isEditing
        ? SupplierController.update.form(supplier)
        : SupplierController.store.form();

    return (
        <Dialog
            open={supplier !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogTitle>
                    {isEditing ? 'Edit supplier' : 'Add supplier'}
                </DialogTitle>
                <DialogDescription>
                    Inactive suppliers can't be picked for new purchase orders.
                </DialogDescription>

                <Form
                    key={supplier?.id ?? 'new'}
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
                                    maxLength={255}
                                    defaultValue={supplier?.name}
                                    placeholder="e.g. Rahman Textiles"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contact_name">
                                    Contact person
                                </Label>
                                <Input
                                    id="contact_name"
                                    name="contact_name"
                                    maxLength={255}
                                    defaultValue={supplier?.contact_name ?? ''}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.contact_name} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        maxLength={255}
                                        defaultValue={supplier?.email ?? ''}
                                        placeholder="Optional"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        maxLength={50}
                                        defaultValue={supplier?.phone ?? ''}
                                        placeholder="Optional"
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">Address</Label>
                                <Input
                                    id="address"
                                    name="address"
                                    maxLength={500}
                                    defaultValue={supplier?.address ?? ''}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    maxLength={2000}
                                    defaultValue={supplier?.notes ?? ''}
                                    placeholder="Optional, e.g. payment terms or lead time"
                                    className={textareaClassName}
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={supplier?.is_active ?? true}
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
                                    {isEditing ? 'Save' : 'Add supplier'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Suppliers.layout = {
    breadcrumbs: [
        {
            title: 'Suppliers',
            href: index(),
        },
    ],
};
