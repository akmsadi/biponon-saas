import { Form, Head } from '@inertiajs/react';
import { Pencil, RotateCw, Trash2, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import InvitationController from '@/actions/App/Http/Controllers/Organization/InvitationController';
import MemberController from '@/actions/App/Http/Controllers/Organization/MemberController';
import Heading from '@/components/heading';
import InvitationLinkDialog from '@/components/invitation-link-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/organization/members';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    joined_at: string | null;
    is_self: boolean;
};

type Invitation = {
    id: number;
    email: string;
    role: string;
    invited_by: string | null;
    expires_at: string;
    is_expired: boolean;
};

type Props = {
    members: Member[];
    invitations: Invitation[];
    roles: string[];
};

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';
}

export default function Members({ members, invitations, roles }: Props) {
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState<Member | null>(null);
    const [removing, setRemoving] = useState<Member | null>(null);
    const [revoking, setRevoking] = useState<Invitation | null>(null);
    const [link, setLink] = useState<string | null>(null);

    const showLink = (flash: Record<string, unknown>) => {
        if (typeof flash.invitationLink === 'string') {
            setLink(flash.invitationLink);
        }
    };

    return (
        <>
            <Head title="Members" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Members"
                        description="People who can use this organization and what they can do"
                    />
                    <Button onClick={() => setInviting(true)}>
                        <UserPlus /> Invite member
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Joined
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((member) => (
                                <tr
                                    key={member.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {member.name}
                                            {member.is_self && (
                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                    (you)
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {member.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {capitalize(member.role)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                member.status === 'active'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {capitalize(member.status)}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDate(member.joined_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {!member.is_self && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setEditing(member)
                                                    }
                                                >
                                                    <Pencil />
                                                    <span className="sr-only">
                                                        Edit {member.name}
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setRemoving(member)
                                                    }
                                                >
                                                    <Trash2 />
                                                    <span className="sr-only">
                                                        Remove {member.name}
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

                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title="Pending invitations"
                        description="Invitations that have not been accepted yet"
                    />
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Invited by
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Expires
                                    </th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {invitations.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No pending invitations.
                                        </td>
                                    </tr>
                                )}
                                {invitations.map((invitation) => (
                                    <tr
                                        key={invitation.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {invitation.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            {capitalize(invitation.role)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {invitation.invited_by ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {invitation.is_expired ? (
                                                <Badge variant="outline">
                                                    Expired
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {formatDate(
                                                        invitation.expires_at,
                                                    )}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Form
                                                    {...InvitationController.resend.form(
                                                        invitation.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    onSuccess={(page) =>
                                                        showLink(page.flash)
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <RotateCw />
                                                            <span className="sr-only">
                                                                Resend to{' '}
                                                                {
                                                                    invitation.email
                                                                }
                                                            </span>
                                                        </Button>
                                                    )}
                                                </Form>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setRevoking(invitation)
                                                    }
                                                >
                                                    <X />
                                                    <span className="sr-only">
                                                        Revoke invitation for{' '}
                                                        {invitation.email}
                                                    </span>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog open={inviting} onOpenChange={setInviting}>
                <DialogContent>
                    <DialogTitle>Invite member</DialogTitle>
                    <DialogDescription>
                        We email them a link to join. It works for 7 days.
                    </DialogDescription>
                    <Form
                        {...InvitationController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={(page) => {
                            setInviting(false);
                            showLink(page.flash);
                        }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        maxLength={255}
                                        placeholder="name@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <RoleSelect
                                    roles={roles}
                                    defaultValue="member"
                                    error={errors.role}
                                />
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button disabled={processing}>
                                        Send invitation
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogTitle>Edit {editing?.name}</DialogTitle>
                    <DialogDescription>
                        Suspended members keep their history but can't open this
                        organization.
                    </DialogDescription>
                    {editing && (
                        <Form
                            key={editing.id}
                            {...MemberController.update.form(editing.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <RoleSelect
                                        roles={roles}
                                        defaultValue={editing.role}
                                        error={errors.role}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            name="status"
                                            defaultValue={editing.status}
                                        >
                                            <SelectTrigger
                                                id="status"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">
                                                    Active
                                                </SelectItem>
                                                <SelectItem value="suspended">
                                                    Suspended
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button disabled={processing}>
                                            Save
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Remove {removing?.name} from the organization?
                    </DialogTitle>
                    <DialogDescription>
                        They lose access straight away. Stock movements they
                        recorded stay in the history. You can invite them again
                        later.
                    </DialogDescription>
                    {removing && (
                        <Form
                            {...MemberController.destroy.form(removing.id)}
                            options={{ preserveScroll: true }}
                            onFinish={() => setRemoving(null)}
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
                                        Remove
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={revoking !== null}
                onOpenChange={(open) => !open && setRevoking(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Revoke the invitation for {revoking?.email}?
                    </DialogTitle>
                    <DialogDescription>
                        The link they received stops working.
                    </DialogDescription>
                    {revoking && (
                        <Form
                            {...InvitationController.destroy.form(revoking.id)}
                            options={{ preserveScroll: true }}
                            onFinish={() => setRevoking(null)}
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
                                        Revoke
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <InvitationLinkDialog link={link} onClose={() => setLink(null)} />
        </>
    );
}

function RoleSelect({
    roles,
    defaultValue,
    error,
}: {
    roles: string[];
    defaultValue: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="role">Role</Label>
            <Select name="role" defaultValue={defaultValue}>
                <SelectTrigger id="role" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role} value={role}>
                            {capitalize(role)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
                Only admins can manage members.
            </p>
            <InputError message={error} />
        </div>
    );
}

Members.layout = {
    breadcrumbs: [
        {
            title: 'Members',
            href: index(),
        },
    ],
};
