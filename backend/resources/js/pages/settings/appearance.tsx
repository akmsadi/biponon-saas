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
                        description="For showing Biponon to clients. Demo adds a panel explaining the technology to the landing and login pages, and offers the demo accounts on the login page. Inside the app the panel is shown to demo accounts only, and they can log in only while the site is in demo mode."
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
