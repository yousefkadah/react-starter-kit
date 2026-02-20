import { Button } from '@/components/ui/button';
import CertificateCard from './certificate-card';
import { Plus } from 'lucide-react';
import React from 'react';

interface AppleCertificateListProps {
    certificates: Array<{
        id: number;
        fingerprint: string;
        valid_from: string;
        expiry_date: string;
        password?: string;
    }>;
    onAddCertificate?: () => void;
    onRenew?: (certificateId: number) => void;
    onDelete?: (certificateId: number) => void;
}

export default function AppleCertificateList({
    certificates,
    onAddCertificate,
    onRenew,
    onDelete,
}: AppleCertificateListProps) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-foreground">
                    Apple Wallet Certificates
                </h3>
                <Button onClick={onAddCertificate} size="sm" variant="default">
                    <Plus className="mr-2 h-4 w-4" />
                    Add Certificate
                </Button>
            </div>

            {certificates && certificates.length > 0 ? (
                <div className="space-y-3">
                    {certificates.map((cert) => (
                        <CertificateCard
                            key={cert.id}
                            type="apple"
                            fingerprint={cert.fingerprint}
                            uploadDate={cert.valid_from}
                            expiryDate={cert.expiry_date}
                            onRenew={() => onRenew?.(cert.id)}
                            onDelete={() => onDelete?.(cert.id)}
                        />
                    ))}
                </div>
            ) : (
                <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                        No Apple certificates uploaded yet
                    </p>
                    <Button
                        onClick={onAddCertificate}
                        variant="link"
                        className="mt-2"
                    >
                        Upload your first certificate
                    </Button>
                </div>
            )}

            <div className="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                <p className="font-semibold">
                    Need help setting up Apple Wallet?
                </p>
                <p className="mt-1">
                    Follow our{' '}
                    <Button variant="link" className="h-auto p-0 text-blue-700">
                        step-by-step guide
                    </Button>{' '}
                    to generate a Certificate Signing Request (CSR) and upload
                    your certificate.
                </p>
            </div>
        </div>
    );
}
