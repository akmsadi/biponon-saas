import { Form, Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, UserPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import OrganizationController from '@/actions/App/Http/Controllers/Admin/OrganizationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import InvitationLinkDialog from '@/components/invitation-link-dialog';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
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
import { index } from '@/routes/admin/organizations';

type Organization = {
    id: number;
    name: string;
    slug: string;
    status: string;
    members_count: number;
    created_at: string | null;
    admins: { name: string; email: string }[];
    pending_admin_invitation: {
        email: string;
        expires_at: string;
        is_expired: boolean;
    } | null;
};

type Props = {
    organizations: Paginated<Organization>;
    filters: { search: string };
};

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';
}

/**
 * Mirrors Str::slug closely enough for a suggestion; the server derives its own when left blank.
 */
function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function Organizations({ organizations, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Organization | null>(null);
    const [invitingAdmin, setInvitingAdmin] = useState<Organization | null>(
        null,
    );
    const [link, setLink] = useState<string | null>(null);
    const isFirstRender = useRef(true);

    // Debounce the search box.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    index.url(),
                    search.trim() ? { search: search.trim() } : {},
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search]);

    const showLink = (flash: Record<string, unknown>) => {
        if (typeof flash.invitationLink === 'string') {
            setLink(flash.invitationLink);
        }
    };

    return (
        <>
            <Head title="Organizations" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Organizations"
                        description="Every organization on the platform and who administers it"
                    />
                    <Button onClick={() => setCreating(true)}>
                        <Plus /> Create organization
                    </Button>
                </div>

                <div className="relative w-full max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search name or slug"
                        aria-label="Search name or slug"
                        maxLength={150}
                        className="pl-9"
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Organization
                                </th>
                                <th className="px-4 py-3 font-medium">Admin</th>
                                <th className="px-4 py-3 font-medium">
                                    Members
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Created
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {organizations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {filters.search
                                            ? 'No organizations match your search.'
                                            : 'No organizations yet. Create one and invite its first admin.'}
                                    </td>
                                </tr>
                            )}
                            {organizations.data.map((organization) => (
                                <tr
                                    key={organization.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {organization.name}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {organization.slug}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <AdminCell
                                            organization={organization}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        {organization.members_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                organization.status === 'active'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {organization.status === 'active'
                                                ? 'Active'
                                                : 'Suspended'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDate(organization.created_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            {organization.admins.length ===
                                                0 && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setInvitingAdmin(
                                                            organization,
                                                        )
                                                    }
                                                >
                                                    <UserPlus />
                                                    <span className="sr-only">
                                                        Invite an admin to{' '}
                                                        {organization.name}
                                                    </span>
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditing(organization)
                                                }
                                            >
                                                <Pencil />
                                                <span className="sr-only">
                                                    Edit {organization.name}
                                                </span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={organizations} />
            </div>

            <CreateOrganizationDialog
                open={creating}
                onClose={() => setCreating(false)}
                onCreated={showLink}
            />

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogTitle>Edit organization</DialogTitle>
                    <DialogDescription>
                        Suspending hides the organization from all its members
                        until you reactivate it. Nothing is deleted.
                    </DialogDescription>
                    {editing && (
                        <Form
                            key={editing.id}
                            {...OrganizationController.update.form(editing.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-name">Name</Label>
                                        <Input
                                            id="edit-name"
                                            name="name"
                                            required
                                            maxLength={255}
                                            defaultValue={editing.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-slug">Slug</Label>
                                        <Input
                                            id="edit-slug"
                                            name="slug"
                                            required
                                            maxLength={100}
                                            defaultValue={editing.slug}
                                            className="font-mono"
                                        />
                                        <InputError message={errors.slug} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-status">
                                            Status
                                        </Label>
                                        <Select
                                            name="status"
                                            defaultValue={editing.status}
                                        >
                                            <SelectTrigger
                                                id="edit-status"
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
                open={invitingAdmin !== null}
                onOpenChange={(open) => !open && setInvitingAdmin(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Invite an admin to {invitingAdmin?.name}
                    </DialogTitle>
                    <DialogDescription>
                        This organization has no active admin. We email a link
                        that works for 7 days. Using the address of the pending
                        invitation sends it again with a new link.
                    </DialogDescription>
                    {invitingAdmin && (
                        <Form
                            key={invitingAdmin.id}
                            {...OrganizationController.inviteAdmin.form(
                                invitingAdmin.id,
                            )}
                            options={{ preserveScroll: true }}
                            onSuccess={(page) => {
                                setInvitingAdmin(null);
                                showLink(page.flash);
                            }}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-email">
                                            Email
                                        </Label>
                                        <Input
                                            id="invite-email"
                                            name="email"
                                            type="email"
                                            required
                                            maxLength={255}
                                            defaultValue={
                                                invitingAdmin
                                                    .pending_admin_invitation
                                                    ?.email ?? ''
                                            }
                                            placeholder="admin@example.com"
                                        />
                                        <InputError message={errors.email} />
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
                                            Send invitation
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <InvitationLinkDialog link={link} onClose={() => setLink(null)} />
        </>
    );
}

function AdminCell({ organization }: { organization: Organization }) {
    if (organization.admins.length > 0) {
        return (
            <div className="space-y-0.5">
                {organization.admins.map((admin) => (
                    <div key={admin.email}>
                        <div>{admin.name}</div>
                        <div className="text-xs text-muted-foreground">
                            {admin.email}
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    const invitation = organization.pending_admin_invitation;

    if (invitation) {
        return (
            <div>
                <div>{invitation.email}</div>
                <div className="text-xs text-muted-foreground">
                    {invitation.is_expired
                        ? 'Invitation expired'
                        : `Invited · expires ${formatDate(invitation.expires_at)}`}
                </div>
            </div>
        );
    }

    return <span className="text-muted-foreground">No admin</span>;
}

function CreateOrganizationDialog({
    open,
    onClose,
    onCreated,
}: {
    open: boolean;
    onClose: () => void;
    onCreated: (flash: Record<string, unknown>) => void;
}) {
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    // The slug follows the name until it is edited by hand.
    const [slugEdited, setSlugEdited] = useState(false);

    const reset = () => {
        setName('');
        setSlug('');
        setSlugEdited(false);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    reset();
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogTitle>Create organization</DialogTitle>
                <DialogDescription>
                    The first admin gets an emailed invitation. Once they join,
                    they invite everyone else from Members.
                </DialogDescription>

                <Form
                    {...OrganizationController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={(page) => {
                        reset();
                        onClose();
                        onCreated(page.flash);
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Name</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    required
                                    maxLength={255}
                                    value={name}
                                    onChange={(event) => {
                                        setName(event.target.value);

                                        if (!slugEdited) {
                                            setSlug(
                                                slugify(event.target.value),
                                            );
                                        }
                                    }}
                                    placeholder="e.g. Acme Fashion"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-slug">Slug</Label>
                                <Input
                                    id="create-slug"
                                    name="slug"
                                    maxLength={100}
                                    value={slug}
                                    onChange={(event) => {
                                        setSlug(event.target.value);
                                        setSlugEdited(true);
                                    }}
                                    placeholder="acme-fashion"
                                    className="font-mono"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Lower-case letters, numbers and hyphens.
                                    Must be unique.
                                </p>
                                <InputError message={errors.slug} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-admin-email">
                                    First admin's email
                                </Label>
                                <Input
                                    id="create-admin-email"
                                    name="admin_email"
                                    type="email"
                                    required
                                    maxLength={255}
                                    placeholder="owner@example.com"
                                />
                                <InputError message={errors.admin_email} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    Create and invite
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Organizations.layout = {
    breadcrumbs: [
        {
            title: 'Organizations',
            href: index(),
        },
    ],
};
