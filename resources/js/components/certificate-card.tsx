import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle, Clock, Trash2 } from 'lucide-react';
import React from 'react';

interface CertificateCardProps {
    type: 'apple' | 'google';
    name?: string;
    uploadDate: string; // ISO timestamp
    expiryDate: string; // ISO timestamp
    issuerId?: string; // For Google
    projectId?: string; // For Google
    fingerprint?: string; // For Apple
    onRenew?: () => void;
    onDelete?: () => void;
}

export default function CertificateCard({
    type,
    name,
    uploadDate,
    expiryDate,
    issuerId,
    projectId,
    fingerprint,
    onRenew,
    onDelete,
}: CertificateCardProps) {
    const now = new Date();
    const expiry = new Date(expiryDate);
    const daysUntilExpiry = Math.ceil(
        (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
    );

    let statusColor = 'bg-green-50 border-green-200';
    let statusLabel = 'Valid';
    let statusIcon = <CheckCircle className="h-5 w-5 text-green-600" />;

    if (daysUntilExpiry < 0) {
        statusColor = 'bg-red-50 border-red-200';
        statusLabel = 'Expired';
        statusIcon = <AlertCircle className="h-5 w-5 text-red-600" />;
    } else if (daysUntilExpiry < 7) {
        statusColor = 'bg-red-50 border-red-200';
        statusLabel = `Expires in ${daysUntilExpiry} days`;
        statusIcon = <AlertCircle className="h-5 w-5 text-red-600" />;
    } else if (daysUntilExpiry < 30) {
        statusColor = 'bg-yellow-50 border-yellow-200';
        statusLabel = `Expires in ${daysUntilExpiry} days`;
        statusIcon = <Clock className="h-5 w-5 text-yellow-600" />;
    }

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <div className={`rounded-lg border p-4 ${statusColor}`}>
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-3">
                    <div className="mt-1 flex-shrink-0">{statusIcon}</div>
                    <div>
                        <h3 className="font-semibold text-foreground capitalize">
                            {type === 'apple'
                                ? 'Apple Wallet'
                                : 'Google Wallet'}{' '}
                            Certificate
                        </h3>
                        {type === 'apple' && fingerprint && (
                            <p className="font-mono text-sm text-muted-foreground">
                                {fingerprint.slice(0, 16)}...
                            </p>
                        )}
                        {type === 'google' && issuerId && (
                            <p className="text-sm text-muted-foreground">
                                Issuer ID: {issuerId}
                            </p>
                        )}
                        {type === 'google' && projectId && (
                            <p className="text-sm text-muted-foreground">
                                Project: {projectId}
                            </p>
                        )}
                    </div>
                </div>
                <div className="flex flex-shrink-0 gap-2">
                    {(daysUntilExpiry < 30 || daysUntilExpiry < 0) && (
                        <Button
                            onClick={onRenew}
                            size="sm"
                            variant="outline"
                            className="border-yellow-200 text-yellow-700 hover:bg-yellow-100"
                        >
                            {daysUntilExpiry < 0 ? 'Replace' : 'Renew'}
                        </Button>
                    )}
                    <Button
                        onClick={onDelete}
                        size="sm"
                        variant="ghost"
                        className="text-destructive hover:bg-destructive/10"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div>
                    <p className="text-muted-foreground">Uploaded</p>
                    <p className="font-semibold text-foreground">
                        {formatDate(uploadDate)}
                    </p>
                </div>
                <div>
                    <p className="text-muted-foreground">Expires</p>
                    <p className="font-semibold text-foreground">
                        {formatDate(expiryDate)}
                    </p>
                </div>
            </div>
        </div>
    );
}
