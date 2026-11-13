import { Form, Head, usePage } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Organization/SettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/organization/settings/general';

type Props = {
    settings: {
        name: string;
        slug: string;
        contact_email: string | null;
        phone: string | null;
        address: string | null;
        timezone: string;
    };
    timezones: string[];
};

export default function GeneralSettings({ settings, timezones }: Props) {
    const canEdit = usePage().props.auth.can.manageSettings;

    return (
        <>
            <Head title="Organization settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="General"
                    description="Your organization's name, contact details and timezone"
                />

                <Form
                    {...SettingsController.updateGeneral.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <fieldset disabled={!canEdit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Organization name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxLength={255}
                                    defaultValue={settings.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    value={settings.slug}
                                    readOnly
                                    disabled
                                    className="font-mono"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Set by the platform administrator.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contact_email">
                                    Contact email
                                </Label>
                                <Input
                                    id="contact_email"
                                    name="contact_email"
                                    type="email"
                                    maxLength={255}
                                    defaultValue={settings.contact_email ?? ''}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.contact_email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    maxLength={50}
                                    defaultValue={settings.phone ?? ''}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">Address</Label>
                                <textarea
                                    id="address"
                                    name="address"
                                    rows={3}
                                    maxLength={500}
                                    defaultValue={settings.address ?? ''}
                                    placeholder="Optional"
                                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <Input
                                    id="timezone"
                                    name="timezone"
                                    required
                                    list="timezone-options"
                                    defaultValue={settings.timezone}
                                    placeholder="e.g. Asia/Dhaka"
                                    autoComplete="off"
                                />
                                <datalist id="timezone-options">
                                    {timezones.map((timezone) => (
                                        <option
                                            key={timezone}
                                            value={timezone}
                                        />
                                    ))}
                                </datalist>
                                <p className="text-xs text-muted-foreground">
                                    Everyone in this organization sees and
                                    enters dates in this timezone. Start typing
                                    a city or region, e.g. Dhaka.
                                </p>
                                <InputError message={errors.timezone} />
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

GeneralSettings.layout = {
    breadcrumbs: [{ title: 'Organization settings', href: edit() }],
};
