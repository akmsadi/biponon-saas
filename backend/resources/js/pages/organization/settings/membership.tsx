import { Form, Head } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Organization/SettingsController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { edit } from '@/routes/organization/settings/membership';

type Props = {
    organizationName: string;
    isLastAdmin: boolean;
};

export default function MembershipSettings({
    organizationName,
    isLastAdmin,
}: Props) {
    return (
        <>
            <Head title="Leave organization" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Leave organization"
                    description="Stop being a member of this organization"
                />

                <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <div className="space-y-0.5 text-red-600 dark:text-red-100">
                        <p className="font-medium">Warning</p>
                        <p className="text-sm">
                            {isLastAdmin
                                ? 'You are the only admin. Make someone else an admin in Members before you can leave.'
                                : 'You lose access straight away. An admin has to invite you again to come back. Stock history you recorded stays.'}
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                disabled={isLastAdmin}
                            >
                                Leave {organizationName}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Leave {organizationName}?</DialogTitle>
                            <DialogDescription>
                                You will no longer see this organization. An
                                admin has to invite you again to come back.
                            </DialogDescription>
                            <Form {...SettingsController.leave.form()}>
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
                                            Leave organization
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

MembershipSettings.layout = {
    breadcrumbs: [{ title: 'Organization settings', href: edit() }],
};
