import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Name, description and active fields, shared by the "Add product" dialog and the product page's details form.
 */
export default function ProductFields({
    product,
    errors,
}: {
    product?: {
        name: string;
        description: string | null;
        is_active: boolean;
    };
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    maxLength={255}
                    defaultValue={product?.name}
                    placeholder="e.g. Classic Tee"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    name="description"
                    rows={3}
                    maxLength={5000}
                    defaultValue={product?.description ?? ''}
                    placeholder="Optional"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                />
                <InputError message={errors.description} />
            </div>

            <div className="flex items-center space-x-3">
                <Checkbox
                    id="is_active"
                    name="is_active"
                    value="1"
                    defaultChecked={product?.is_active ?? true}
                />
                <Label htmlFor="is_active">Active</Label>
            </div>
            <InputError message={errors.is_active} />
        </>
    );
}
