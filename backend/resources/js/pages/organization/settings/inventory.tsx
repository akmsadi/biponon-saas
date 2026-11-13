import { Form, Head, usePage } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Organization/SettingsController';
import Heading from '@/components/heading';
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
import { edit } from '@/routes/organization/settings/inventory';

type Props = {
    settings: {
        default_warehouse_id: number | null;
        default_reorder_level: number | null;
    };
    warehouses: { id: number; name: string; code: string }[];
};

const NONE = 'none';

export default function InventorySettings({ settings, warehouses }: Props) {
    const canEdit = usePage().props.auth.can.manageSettings;

    return (
        <>
            <Head title="Inventory defaults" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Inventory defaults"
                    description="Values filled in for you on new stock operations and variants"
                />

                <Form
                    {...SettingsController.updateInventory.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <fieldset disabled={!canEdit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="default_warehouse_id">
                                    Default warehouse
                                </Label>
                                <Select
                                    name="default_warehouse_id"
                                    defaultValue={
                                        settings.default_warehouse_id
                                            ? String(
                                                  settings.default_warehouse_id,
                                              )
                                            : NONE
                                    }
                                    disabled={!canEdit}
                                >
                                    <SelectTrigger
                                        id="default_warehouse_id"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>
                                            No default
                                        </SelectItem>
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
                                <p className="text-xs text-muted-foreground">
                                    Preselected on Receive, Transfer (as the
                                    source), Dispatch and Return. Ignored if the
                                    warehouse is deactivated.
                                </p>
                                <InputError
                                    message={errors.default_warehouse_id}
                                />
                            </div>

                            <div className="grid max-w-48 gap-2">
                                <Label htmlFor="default_reorder_level">
                                    Default reorder level
                                </Label>
                                <Input
                                    id="default_reorder_level"
                                    name="default_reorder_level"
                                    type="number"
                                    min={0}
                                    max={1000000}
                                    step={1}
                                    defaultValue={
                                        settings.default_reorder_level ?? ''
                                    }
                                    placeholder="Optional"
                                />
                                <InputError
                                    message={errors.default_reorder_level}
                                />
                            </div>
                            <p className="-mt-4 text-xs text-muted-foreground">
                                Filled in when adding variants to a product.
                                Existing variants keep their own level.
                            </p>

                            {canEdit && (
                                <Button disabled={processing}>Save</Button>
                            )}
                        </fieldset>
                    )}
                </Form>
            </div>
        </>
    );
}

InventorySettings.layout = {
    breadcrumbs: [{ title: 'Organization settings', href: edit() }],
};
