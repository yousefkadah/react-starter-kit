import { Button } from '@/components/ui/button';
import CertificateCard from './certificate-card';
import { Plus } from 'lucide-react';
import React from 'react';

interface GoogleCredentialListProps {
    credentials: Array<{
        id: number;
        issuer_id: string;
        project_id: string;
        last_rotated_at: string | null;
    }>;
    onAddCredential?: () => void;
    onRotate?: (credentialId: number) => void;
    onDelete?: (credentialId: number) => void;
}

export default function GoogleCredentialList({
    credentials,
    onAddCredential,
    onRotate,
    onDelete,
}: GoogleCredentialListProps) {
    const formatLastRotated = (date: string | null) => {
        if (!date) return 'Never';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-foreground">
                    Google Wallet Credentials
                </h3>
                <Button onClick={onAddCredential} size="sm" variant="default">
                    <Plus className="mr-2 h-4 w-4" />
                    Add Credential
                </Button>
            </div>

            {credentials && credentials.length > 0 ? (
                <div className="space-y-3">
                    {credentials.map((cred) => (
                        <div key={cred.id} className="space-y-2">
                            <CertificateCard
                                type="google"
                                issuerId={cred.issuer_id}
                                projectId={cred.project_id}
                                uploadDate={new Date().toISOString()} // Fallback - real date from DB
                                expiryDate={new Date(
                                    Date.now() + 365 * 24 * 60 * 60 * 1000,
                                ).toISOString()} // Fallback - Google credentials don't expire like Apple certs
                                onRenew={() => onRotate?.(cred.id)}
                                onDelete={() => onDelete?.(cred.id)}
                            />
                            <p className="ml-12 text-xs text-muted-foreground">
                                Last rotated:{' '}
                                {formatLastRotated(cred.last_rotated_at)}
                            </p>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                        No Google credentials uploaded yet
                    </p>
                    <Button
                        onClick={onAddCredential}
                        variant="link"
                        className="mt-2"
                    >
                        Upload your first credential
                    </Button>
                </div>
            )}

            <div className="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                <p className="font-semibold">
                    Need help setting up Google Wallet?
                </p>
                <p className="mt-1">
                    Follow our{' '}
                    <Button variant="link" className="h-auto p-0 text-blue-700">
                        step-by-step guide
                    </Button>{' '}
                    to create a Google Cloud project and upload your service
                    account credentials.
                </p>
            </div>
        </div>
    );
}
