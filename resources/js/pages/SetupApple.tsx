import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle,
    Download,
    Upload,
    ExternalLink,
    Loader,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';

export default function SetupApple() {
    const [step, setStep] = useState<'csr' | 'upload'>('csr');
    const [isLoading, setIsLoading] = useState(false);
    const [uploadError, setUploadError] = useState('');
    const [uploadSuccess, setUploadSuccess] = useState(false);
    const [uploadedCert, setUploadedCert] = useState<{
        fingerprint: string;
        valid_from: string;
        expiry_date: string;
        days_until_expiry: number;
    } | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [certPassword, setCertPassword] = useState('');

    const handleDownloadCSR = async () => {
        setIsLoading(true);
        try {
            const response = await fetch('/api/certificates/apple/csr', {
                method: 'GET',
                headers: {
                    Accept: 'application/octet-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to download CSR');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'cert.certSigningRequest';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            setStep('upload');
        } catch (error) {
            console.error('Download error:', error);
            setUploadError('Failed to download CSR. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };

    const handleUploadCertificate = async (e: React.FormEvent) => {
        e.preventDefault();
        setUploadError('');
        setUploadSuccess(false);

        if (!selectedFile) {
            setUploadError('Please select a certificate file');
            return;
        }

        setIsLoading(true);

        try {
            const formData = new FormData();
            formData.append('certificate', selectedFile);
            if (certPassword) {
                formData.append('password', certPassword);
            }

            const response = await fetch('/api/certificates/apple', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                if (Array.isArray(data.errors)) {
                    setUploadError(data.errors.join(', '));
                } else {
                    setUploadError(
                        data.message || 'Failed to upload certificate',
                    );
                }
            } else {
                setUploadSuccess(true);
                setUploadedCert(data.certificate);
                setSelectedFile(null);
                setCertPassword('');
            }
        } catch (error) {
            console.error('Upload error:', error);
            setUploadError('An unexpected error occurred. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Apple Wallet Setup" />

            <div className="mx-auto max-w-2xl space-y-6 px-4 py-10">
                {/* Page Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold text-foreground">
                        Apple Wallet Setup
                    </h1>
                    <p className="text-muted-foreground">
                        Generate a Certificate Signing Request (CSR) and upload
                        your Apple Wallet certificate
                    </p>
                </div>

                {/* Step Indicator */}
                <div className="flex gap-4">
                    <div className="flex-1">
                        <div
                            className={`flex items-center gap-3 rounded-lg px-4 py-3 ${
                                step === 'csr'
                                    ? 'border border-blue-200 bg-blue-50'
                                    : 'border border-gray-200 bg-gray-50'
                            }`}
                        >
                            <div
                                className={`flex h-6 w-6 items-center justify-center rounded-full text-sm font-semibold ${
                                    step === 'csr'
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-gray-300 text-gray-600'
                                }`}
                            >
                                1
                            </div>
                            <div>
                                <p className="font-medium text-foreground">
                                    Generate CSR
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Create certificate request
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex-1">
                        <div
                            className={`flex items-center gap-3 rounded-lg px-4 py-3 ${
                                step === 'upload'
                                    ? 'border border-blue-200 bg-blue-50'
                                    : 'border border-gray-200 bg-gray-50'
                            }`}
                        >
                            <div
                                className={`flex h-6 w-6 items-center justify-center rounded-full text-sm font-semibold ${
                                    step === 'upload'
                                        ? 'bg-blue-500 text-white'
                                        : uploadSuccess
                                          ? 'bg-green-500 text-white'
                                          : 'bg-gray-300 text-gray-600'
                                }`}
                            >
                                {uploadSuccess ? (
                                    <CheckCircle className="h-4 w-4" />
                                ) : (
                                    '2'
                                )}
                            </div>
                            <div>
                                <p className="font-medium text-foreground">
                                    Upload Certificate
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Submit Apple certificate
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Step 1: Generate CSR */}
                {step === 'csr' && (
                    <Card className="p-6">
                        <div className="space-y-4">
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertTitle>
                                    Required: Apple Developer Account
                                </AlertTitle>
                                <AlertDescription>
                                    You'll need an Apple Developer Program
                                    membership ($99/year) to complete this
                                    process.
                                </AlertDescription>
                            </Alert>

                            <div className="space-y-3">
                                <h3 className="font-semibold text-foreground">
                                    What is a CSR?
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    A Certificate Signing Request (CSR) is a
                                    file you send to Apple to prove your
                                    identity. Apple will sign it and return a
                                    certificate that you upload here.
                                </p>
                            </div>

                            <Button
                                onClick={handleDownloadCSR}
                                disabled={isLoading}
                                size="lg"
                                className="w-full"
                            >
                                {isLoading && (
                                    <Loader className="mr-2 h-4 w-4 animate-spin" />
                                )}
                                {isLoading
                                    ? 'Generating...'
                                    : 'Generate & Download CSR'}
                            </Button>

                            <div className="space-y-3 rounded-lg bg-blue-50 p-4">
                                <h3 className="font-semibold text-blue-900">
                                    Next Steps
                                </h3>
                                <ol className="list-inside list-decimal space-y-2 text-sm text-blue-800">
                                    <li>
                                        Download the CSR file (you'll get it
                                        automatically above)
                                    </li>
                                    <li>
                                        <a
                                            href="https://developer.apple.com/account"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 underline hover:text-blue-600"
                                        >
                                            Log into Apple Developer Portal
                                            <ExternalLink className="h-3 w-3" />
                                        </a>
                                    </li>
                                    <li>
                                        Go to Certificates, IDs & Profiles →
                                        Certificates
                                    </li>
                                    <li>
                                        Click "+" and select "Apple Wallet Pass
                                        Certificate"
                                    </li>
                                    <li>Upload your CSR file</li>
                                    <li>
                                        Download the signed certificate (.cer
                                        file)
                                    </li>
                                    <li>Come back here and upload it</li>
                                </ol>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Step 2: Upload Certificate */}
                {step === 'upload' && (
                    <Card className="p-6">
                        {uploadSuccess && uploadedCert ? (
                            <div className="space-y-4">
                                <div className="rounded-lg border border-green-200 bg-green-50 p-4">
                                    <div className="flex items-start gap-3">
                                        <CheckCircle className="mt-0.5 h-6 w-6 flex-shrink-0 text-green-600" />
                                        <div>
                                            <h3 className="font-semibold text-green-900">
                                                Certificate Uploaded
                                                Successfully! 🎉
                                            </h3>
                                            <p className="mt-1 text-sm text-green-700">
                                                Your Apple Wallet certificate is
                                                now active.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Fingerprint
                                        </p>
                                        <p className="mt-1 font-mono text-xs">
                                            {uploadedCert.fingerprint.slice(
                                                0,
                                                20,
                                            )}
                                            ...
                                        </p>
                                    </div>
                                    <div className="rounded border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Valid From
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {new Date(
                                                uploadedCert.valid_from,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="rounded border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Expires
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {new Date(
                                                uploadedCert.expiry_date,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="rounded border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Days Until Expiry
                                        </p>
                                        <p
                                            className={`mt-1 text-sm font-medium ${
                                                uploadedCert.days_until_expiry >
                                                30
                                                    ? 'text-green-600'
                                                    : uploadedCert.days_until_expiry >
                                                        7
                                                      ? 'text-yellow-600'
                                                      : 'text-red-600'
                                            }`}
                                        >
                                            {uploadedCert.days_until_expiry}{' '}
                                            days
                                        </p>
                                    </div>
                                </div>

                                <div className="flex gap-2 pt-4">
                                    <Button
                                        onClick={() => {
                                            setStep('upload');
                                            setUploadSuccess(false);
                                            setUploadedCert(null);
                                        }}
                                        variant="outline"
                                        className="flex-1"
                                    >
                                        Upload Another Certificate
                                    </Button>
                                    <Button
                                        onClick={() => window.history.back()}
                                        className="flex-1"
                                    >
                                        Done
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <form
                                onSubmit={handleUploadCertificate}
                                className="space-y-4"
                            >
                                {/* File Upload */}
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">
                                        Upload Certificate (.cer file)
                                    </label>
                                    <div className="relative">
                                        <input
                                            type="file"
                                            accept=".cer,.pem"
                                            onChange={(e) =>
                                                setSelectedFile(
                                                    e.target.files?.[0] || null,
                                                )
                                            }
                                            disabled={isLoading}
                                            required
                                            className="block w-full text-sm text-gray-500 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100"
                                        />
                                    </div>
                                    {selectedFile && (
                                        <p className="mt-1 text-xs text-green-600">
                                            ✓ {selectedFile.name}
                                        </p>
                                    )}
                                </div>

                                {uploadError && (
                                    <InputError message={uploadError} />
                                )}

                                <Button
                                    type="submit"
                                    disabled={isLoading || !selectedFile}
                                    className="w-full"
                                >
                                    {isLoading && (
                                        <Loader className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    {isLoading
                                        ? 'Uploading...'
                                        : 'Upload Certificate'}
                                </Button>
                            </form>
                        )}
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
