import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/Sales/CustomerController';
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
import { index } from '@/routes/sales/customers';

type Customer = {
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

export default function Customers({ customers }: { customers: Customer[] }) {
    // undefined = closed, null = creating, Customer = editing
    const [editing, setEditing] = useState<Customer | null | undefined>();
    const [deleting, setDeleting] = useState<Customer | null>(null);
    const canManage = usePage().props.auth.can.manageSales;

    return (
        <>
            <Head title="Customers" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Customers"
                        description="People and businesses you sell to"
                    />
                    {canManage && (
                        <Button onClick={() => setEditing(null)}>
                            <Plus /> Add customer
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
                            {customers.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No customers yet.
                                        {canManage &&
                                            ' Add one to start creating sales orders.'}
                                    </td>
                                </tr>
                            )}
                            {customers.map((customer) => (
                                <tr
                                    key={customer.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {customer.name}
                                        </div>
                                        {customer.address && (
                                            <div className="text-xs text-muted-foreground">
                                                {customer.address}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {customer.contact_name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {customer.email ? (
                                            <a
                                                href={`mailto:${customer.email}`}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {customer.email}
                                            </a>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {customer.phone ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                customer.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {customer.is_active
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
                                                        setEditing(customer)
                                                    }
                                                >
                                                    <Pencil />
                                                    <span className="sr-only">
                                                        Edit {customer.name}
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setDeleting(customer)
                                                    }
                                                >
                                                    <Trash2 />
                                                    <span className="sr-only">
                                                        Delete {customer.name}
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

            <CustomerFormDialog
                customer={editing}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Delete customer “{deleting?.name}”?
                    </DialogTitle>
                    <DialogDescription>
                        Customers with sales orders can't be deleted. Deactivate
                        them instead.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...CustomerController.destroy.form(deleting)}
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

function CustomerFormDialog({
    customer,
    onClose,
}: {
    customer: Customer | null | undefined;
    onClose: () => void;
}) {
    const isEditing = customer != null;
    const action = isEditing
        ? CustomerController.update.form(customer)
        : CustomerController.store.form();

    return (
        <Dialog
            open={customer !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogTitle>
                    {isEditing ? 'Edit customer' : 'Add customer'}
                </DialogTitle>
                <DialogDescription>
                    Inactive customers can't be picked for new sales orders.
                </DialogDescription>

                <Form
                    key={customer?.id ?? 'new'}
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
                                    defaultValue={customer?.name}
                                    placeholder="e.g. Karim Fashion House"
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
                                    defaultValue={customer?.contact_name ?? ''}
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
                                        defaultValue={customer?.email ?? ''}
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
                                        defaultValue={customer?.phone ?? ''}
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
                                    defaultValue={customer?.address ?? ''}
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
                                    defaultValue={customer?.notes ?? ''}
                                    placeholder="Optional, e.g. payment terms or delivery instructions"
                                    className={textareaClassName}
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={customer?.is_active ?? true}
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
                                    {isEditing ? 'Save' : 'Add customer'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Customers.layout = {
    breadcrumbs: [
        {
            title: 'Customers',
            href: index(),
        },
    ],
};
