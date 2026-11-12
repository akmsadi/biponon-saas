import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function NoOrganization() {
    return (
        <>
            <Head title="No organization" />

            <div className="grid gap-6 text-center text-sm text-muted-foreground">
                <p>
                    Your account is not an active member of any organization.
                    You may have been suspended or removed. Ask an admin to
                    invite you again or to reactivate your membership.
                </p>
                <Button variant="outline" asChild>
                    <Link href={logout()} as="button">
                        Log out
                    </Link>
                </Button>
            </div>
        </>
    );
}

NoOrganization.layout = {
    title: 'No active organization',
    description: '',
};
