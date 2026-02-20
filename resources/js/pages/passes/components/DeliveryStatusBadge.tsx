import { Badge } from '@/components/ui/badge';

type DeliveryStatus = 'pending' | 'sent' | 'delivered' | 'failed' | 'skipped' | null;

interface DeliveryStatusBadgeProps {
    status: DeliveryStatus;
}

export default function DeliveryStatusBadge({ status }: DeliveryStatusBadgeProps) {
    if (status === null) {
        return <Badge variant="secondary">n/a</Badge>;
    }

    if (status === 'failed') {
        return <Badge variant="destructive">failed</Badge>;
    }

    if (status === 'delivered') {
        return <Badge>delivered</Badge>;
    }

    if (status === 'sent') {
        return <Badge variant="secondary">sent</Badge>;
    }

    if (status === 'pending') {
        return <Badge variant="secondary">pending</Badge>;
    }

    return <Badge variant="secondary">skipped</Badge>;
}
