import { Head, usePage } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import SiteModeTabs from '@/components/site-mode-tabs';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="Update the appearance settings for your account"
                />
                <AppearanceTabs />
            </div>

            {auth.can.administerPlatform && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Site mode"
                        description="For the whole site, not just your account. Demo adds a panel to the bottom of every page that explains the technology behind it, for showing Biponon to clients."
                    />
                    <SiteModeTabs />
                </div>
            )}
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
