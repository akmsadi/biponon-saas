import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

/**
 * The shape of a Laravel length-aware paginator as serialized by Inertia.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/**
 * "Showing x–y of z" with Previous / Next links. Renders nothing when the list is empty.
 */
export default function Pagination({
    paginator,
}: {
    paginator: Paginated<unknown>;
}) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
            <span>
                Showing {paginator.from}–{paginator.to} of {paginator.total}
            </span>
            {paginator.last_page > 1 && (
                <div className="flex gap-2">
                    <PageLink href={paginator.prev_page_url} label="Previous" />
                    <PageLink href={paginator.next_page_url} label="Next" />
                </div>
            )}
        </div>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveState>
                {label}
            </Link>
        </Button>
    );
}
