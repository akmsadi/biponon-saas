import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import WarehouseController from '@/actions/App/Http/Controllers/WarehouseController';
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
import { index } from '@/routes/warehouses';

type Warehouse = {
    id: number;
    name: string;
    code: string;
    address_line: string | null;
    city: string | null;
    country_code: string | null;
    is_active: boolean;
    in_stock_count: number;
};

function location(warehouse: Warehouse): string {
    return (
        [warehouse.city, warehouse.country_code].filter(Boolean).join(', ') ||
        '—'
    );
}

export default function Warehouses({
    warehouses,
}: {
    warehouses: Warehouse[];
}) {
    // undefined = closed, null = creating, Warehouse = editing
    const [editing, setEditing] = useState<Warehouse | null | undefined>();
    const [deleting, setDeleting] = useState<Warehouse | null>(null);
    const canManage = usePage().props.auth.can.manageWarehouses;

    return (
        <>
            <Head title="Warehouses" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Warehouses"
                        description="Places where your stock is kept"
                    />
                    {canManage && (
                        <Button onClick={() => setEditing(null)}>
                            <Plus /> Add warehouse
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
                                    Location
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    In stock
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
                            {warehouses.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No warehouses yet. Add one to start
                                        receiving stock.
                                    </td>
                                </tr>
                            )}
                            {warehouses.map((warehouse) => (
                                <tr
                                    key={warehouse.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {warehouse.name}
                                        </div>
                                        {warehouse.address_line && (
                                            <div className="text-xs text-muted-foreground">
                                                {warehouse.address_line}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {warehouse.code}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {location(warehouse)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {warehouse.in_stock_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                warehouse.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {warehouse.is_active
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
                                                        setEditing(warehouse)
                                                    }
                                                >
                                                    <Pencil />
                                                    <span className="sr-only">
                                                        Edit {warehouse.name}
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setDeleting(warehouse)
                                                    }
                                                >
                                                    <Trash2 />
                                                    <span className="sr-only">
                                                        Delete {warehouse.name}
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

            <WarehouseFormDialog
                warehouse={editing}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Delete warehouse “{deleting?.name}”?
                    </DialogTitle>
                    <DialogDescription>
                        Only warehouses that have never held stock can be
                        deleted. Otherwise, deactivate it instead.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...WarehouseController.destroy.form(deleting)}
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

function WarehouseFormDialog({
    warehouse,
    onClose,
}: {
    warehouse: Warehouse | null | undefined;
    onClose: () => void;
}) {
    const isEditing = warehouse != null;
    const action = isEditing
        ? WarehouseController.update.form(warehouse)
        : WarehouseController.store.form();

    return (
        <Dialog
            open={warehouse !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogTitle>
                    {isEditing ? 'Edit warehouse' : 'Add warehouse'}
                </DialogTitle>
                <DialogDescription>
                    Inactive warehouses keep their stock but can't receive new
                    stock.
                </DialogDescription>

                <Form
                    key={warehouse?.id ?? 'new'}
                    {...action}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-2 grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={255}
                                        defaultValue={warehouse?.name}
                                        placeholder="e.g. Dhaka Main"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="code">Code</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        maxLength={50}
                                        defaultValue={warehouse?.code}
                                        placeholder="DHK-01"
                                        className="font-mono uppercase"
                                    />
                                    <InputError message={errors.code} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address_line">Address</Label>
                                <Input
                                    id="address_line"
                                    name="address_line"
                                    maxLength={255}
                                    defaultValue={warehouse?.address_line ?? ''}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.address_line} />
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-2 grid gap-2">
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        name="city"
                                        maxLength={255}
                                        defaultValue={warehouse?.city ?? ''}
                                        placeholder="Optional"
                                    />
                                    <InputError message={errors.city} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="country_code">
                                        Country
                                    </Label>
                                    <Input
                                        id="country_code"
                                        name="country_code"
                                        maxLength={2}
                                        defaultValue={
                                            warehouse?.country_code ?? ''
                                        }
                                        placeholder="BD"
                                        className="uppercase"
                                    />
                                    <InputError message={errors.country_code} />
                                </div>
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={
                                        warehouse?.is_active ?? true
                                    }
                                />
                                <Label htmlFor="is_active">Active</Label>
                            </div>
                            {isEditing &&
                                warehouse.is_active &&
                                warehouse.in_stock_count > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        This warehouse holds{' '}
                                        {warehouse.in_stock_count} units.
                                        Deactivating keeps them here.
                                    </p>
                                )}
                            <InputError message={errors.is_active} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {isEditing ? 'Save' : 'Add warehouse'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Warehouses.layout = {
    breadcrumbs: [
        {
            title: 'Warehouses',
            href: index(),
        },
    ],
};
