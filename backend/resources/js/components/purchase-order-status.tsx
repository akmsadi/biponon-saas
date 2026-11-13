import { Badge } from '@/components/ui/badge';

export type PurchaseOrderStatus =
    | 'draft'
    | 'ordered'
    | 'partially_received'
    | 'received'
    | 'cancelled';

export const PURCHASE_ORDER_STATUSES: {
    value: PurchaseOrderStatus;
    label: string;
}[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'ordered', label: 'Ordered' },
    { value: 'partially_received', label: 'Partly received' },
    { value: 'received', label: 'Received' },
    { value: 'cancelled', label: 'Cancelled' },
];

const VARIANTS: Record<
    PurchaseOrderStatus,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    draft: 'outline',
    ordered: 'default',
    partially_received: 'default',
    received: 'secondary',
    cancelled: 'outline',
};

export default function PurchaseOrderStatusBadge({
    status,
}: {
    status: PurchaseOrderStatus;
}) {
    const label =
        PURCHASE_ORDER_STATUSES.find((candidate) => candidate.value === status)
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
