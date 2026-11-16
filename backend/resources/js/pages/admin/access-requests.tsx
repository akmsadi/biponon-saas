import { Head, Link, router } from '@inertiajs/react';
import { Building2, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import AccessRequestController from '@/actions/App/Http/Controllers/Admin/AccessRequestController';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/access-requests';
import { index as organizationsIndex } from '@/routes/admin/organizations';

type Status = 'new' | 'contacted' | 'closed';

type AccessRequest = {
    id: number;
    name: string;
    company: string;
    email: string;
    phone: string | null;
    message: string | null;
    language: string;
    status: Status;
    created_at: string | null;
};

type Props = {
    accessRequests: Paginated<AccessRequest>;
    filters: { search: string; status: Status | null };
    statusCounts: Partial<Record<Status, number>>;
};

const STATUSES: { value: Status; label: string }[] = [
    { value: 'new', label: 'New' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'closed', label: 'Closed' },
];

function formatDateTime(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '—';
}

function visitList(search: string, status: Status | null) {
    router.get(
        index.url(),
        {
            ...(search.trim() ? { search: search.trim() } : {}),
            ...(status ? { status } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function AccessRequests({
    accessRequests,
    filters,
    statusCounts,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [deleting, setDeleting] = useState<AccessRequest | null>(null);

    const { status, search: currentSearch } = filters;

    // Debounce the search box; nothing to do while it matches the list already shown.
    useEffect(() => {
        if (search.trim() === currentSearch) {
            return;
        }

        const timeout = setTimeout(() => visitList(search, status), 300);

        return () => clearTimeout(timeout);
    }, [search, status, currentSearch]);

    const total = Object.values(statusCounts).reduce(
        (sum, count) => sum + (count ?? 0),
        0,
    );

    const changeStatus = (accessRequest: AccessRequest, status: string) =>
        router.put(
            AccessRequestController.update.url(accessRequest.id),
            { status },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Access requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Access requests"
                    description="Sent from the landing page. Create an organization from a request to invite its first admin."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <div
                        role="group"
                        aria-label="Filter by status"
                        className="inline-flex gap-1 rounded-lg bg-muted p-1"
                    >
                        {[
                            { value: null, label: 'All', count: total },
                            ...STATUSES.map((status) => ({
                                ...status,
                                count: statusCounts[status.value] ?? 0,
                            })),
                        ].map((tab) => (
                            <button
                                key={tab.label}
                                type="button"
                                aria-pressed={filters.status === tab.value}
                                onClick={() => visitList(search, tab.value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm transition-colors',
                                    filters.status === tab.value
                                        ? 'bg-background font-medium shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}{' '}
                                <span className="text-muted-foreground tabular-nums">
                                    {tab.count}
                                </span>
                            </button>
                        ))}
                    </div>

                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name, company or email"
                            aria-label="Search name, company or email"
                            maxLength={150}
                            className="pl-9"
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Request
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Message
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Received
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {accessRequests.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        {filters.search || filters.status
                                            ? 'No requests match these filters.'
                                            : 'No requests yet. They appear here when someone fills in the form on the landing page.'}
                                    </td>
                                </tr>
                            )}
                            {accessRequests.data.map((accessRequest) => (
                                <tr
                                    key={accessRequest.id}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {accessRequest.company}
                                        </div>
                                        <div>{accessRequest.name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            <a
                                                href={`mailto:${accessRequest.email}`}
                                                className="hover:underline"
                                            >
                                                {accessRequest.email}
                                            </a>
                                            {accessRequest.phone && (
                                                <>
                                                    {' · '}
                                                    <a
                                                        href={`tel:${accessRequest.phone}`}
                                                        className="hover:underline"
                                                    >
                                                        {accessRequest.phone}
                                                    </a>
                                                </>
                                            )}
                                        </div>
                                        {accessRequest.language === 'bn' && (
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Wrote in Bangla
                                            </div>
                                        )}
                                    </td>
                                    <td className="max-w-md px-4 py-3 whitespace-pre-line text-muted-foreground">
                                        {accessRequest.message || '—'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(
                                            accessRequest.created_at,
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Select
                                            value={accessRequest.status}
                                            onValueChange={(status) =>
                                                changeStatus(
                                                    accessRequest,
                                                    status,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-32"
                                                aria-label={`Status of the request from ${accessRequest.company}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {STATUSES.map((status) => (
                                                    <SelectItem
                                                        key={status.value}
                                                        value={status.value}
                                                    >
                                                        {status.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            {accessRequest.status !==
                                                'closed' && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={organizationsIndex(
                                                            {
                                                                query: {
                                                                    from_request:
                                                                        accessRequest.id,
                                                                },
                                                            },
                                                        )}
                                                    >
                                                        <Building2 />
                                                        Create organization
                                                    </Link>
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setDeleting(accessRequest)
                                                }
                                            >
                                                <Trash2 />
                                                <span className="sr-only">
                                                    Delete the request from{' '}
                                                    {accessRequest.company}
                                                </span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={accessRequests} />
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Delete the request from {deleting?.company}?
                    </DialogTitle>
                    <DialogDescription>
                        This can't be undone. To keep it but take it off your
                        list, set its status to Closed instead.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleting &&
                                router.delete(
                                    AccessRequestController.destroy.url(
                                        deleting.id,
                                    ),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setDeleting(null),
                                    },
                                )
                            }
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AccessRequests.layout = {
    breadcrumbs: [
        {
            title: 'Access requests',
            href: index(),
        },
    ],
};
