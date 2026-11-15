import { Badge } from '@/components/ui/badge';

export type SalesOrderStatus =
    | 'draft'
    | 'confirmed'
    | 'partially_dispatched'
    | 'dispatched'
    | 'closed'
    | 'cancelled';

export const SALES_ORDER_STATUSES: {
    value: SalesOrderStatus;
    label: string;
}[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'partially_dispatched', label: 'Partly dispatched' },
    { value: 'dispatched', label: 'Dispatched' },
    { value: 'closed', label: 'Closed short' },
    { value: 'cancelled', label: 'Cancelled' },
];

const VARIANTS: Record<
    SalesOrderStatus,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    draft: 'outline',
    confirmed: 'default',
    partially_dispatched: 'default',
    dispatched: 'secondary',
    closed: 'secondary',
    cancelled: 'outline',
};

export default function SalesOrderStatusBadge({
    status,
}: {
    status: SalesOrderStatus;
}) {
    const label =
        SALES_ORDER_STATUSES.find((candidate) => candidate.value === status)
            ?.label ?? status;

    return (
        <Badge
            variant={VARIANTS[status]}
            className={
                status === 'cancelled'
                    ? 'text-muted-foreground line-through'
                    : undefined
            }
        >
            {label}
        </Badge>
    );
}
