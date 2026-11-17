import { Form, Head, Link, usePage } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Organization/SettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as productsIndex } from '@/routes/catalog/products';
import { edit } from '@/routes/organization/settings/inventory';

type Props = {
    settings: { shop_enabled: boolean };
    shopLink: string | null;
    hasDefaultWarehouse: boolean;
    productsInShop: number;
};

export default function ShopSettings({
    settings,
    shopLink,
    hasDefaultWarehouse,
    productsInShop,
}: Props) {
    const canEdit = usePage().props.auth.can.manageSettings;

    return (
        <>
            <Head title="Online shop" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Online shop"
                    description="A public shop where your customers order with cash on delivery. Orders arrive as draft sales orders."
                />

                <dl className="grid gap-3 rounded-lg border p-4 text-sm">
                    <div className="flex flex-wrap justify-between gap-2">
                        <dt className="text-muted-foreground">Shop address</dt>
                        <dd className="font-medium break-all">
                            {shopLink && settings.shop_enabled ? (
                                <a
                                    href={shopLink}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline underline-offset-4"
                                >
                                    {shopLink}
                                </a>
                            ) : (
                                (shopLink ??
                                'Shown here once the shop site is live')
                            )}
                        </dd>
                    </div>
                    <div className="flex flex-wrap justify-between gap-2">
                        <dt className="text-muted-foreground">
                            Products in the shop
                        </dt>
                        <dd className="font-medium">
                            {productsInShop}{' '}
                            <Link
                                href={productsIndex()}
                                className="font-normal text-muted-foreground underline underline-offset-4"
                            >
                                choose on each product
                            </Link>
                        </dd>
                    </div>
                </dl>

                {!hasDefaultWarehouse && (
                    <p className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        Web orders ship from your default warehouse. Set one
                        under{' '}
                        <Link
                            href={edit()}
                            className="underline underline-offset-4"
                        >
                            Inventory defaults
                        </Link>{' '}
                        before opening the shop.
                    </p>
                )}

                <Form
                    {...SettingsController.updateShop.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <fieldset disabled={!canEdit} className="space-y-6">
                            <div className="grid gap-1">
                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="shop_enabled"
                                        name="shop_enabled"
                                        value="1"
                                        defaultChecked={settings.shop_enabled}
                                        disabled={!canEdit}
                                    />
                                    <Label htmlFor="shop_enabled">
                                        Shop is open
                                    </Label>
                                </div>
                                <p className="pl-7 text-xs text-muted-foreground">
                                    While closed, the shop address shows a “shop
                                    closed” page and takes no orders.
                                </p>
                                <InputError message={errors.shop_enabled} />
                            </div>

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

ShopSettings.layout = {
    breadcrumbs: [{ title: 'Organization settings', href: edit() }],
};
