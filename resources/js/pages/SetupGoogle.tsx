import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { AlertCircle, CheckCircle, ExternalLink, Loader } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';

type SetupStep =
    | 'gcp-project'
    | 'wallet-api'
    | 'service-account'
    | 'download-key'
    | 'upload-json';

const STEPS = [
    {
        key: 'gcp-project',
        number: 1,
        title: 'Create GCP Project',
        duration: '~5 min',
    },
    {
        key: 'wallet-api',
        number: 2,
        title: 'Enable Wallet API',
        duration: '~5 min',
    },
    {
        key: 'service-account',
        number: 3,
        title: 'Create Service Account',
        duration: '~5 min',
    },
    {
        key: 'download-key',
        number: 4,
        title: 'Download JSON Key',
        duration: '~2 min',
    },
    {
        key: 'upload-json',
        number: 5,
        title: 'Upload JSON Key',
        duration: 'Here',
    },
] as const;

export default function SetupGoogle() {
    const [currentStep, setCurrentStep] = useState<SetupStep>('gcp-project');
    const [completedSteps, setCompletedSteps] = useState<Set<SetupStep>>(
        new Set(),
    );
    const [isLoading, setIsLoading] = useState(false);
    const [uploadError, setUploadError] = useState('');
    const [uploadSuccess, setUploadSuccess] = useState(false);
    const [uploadedCred, setUploadedCred] = useState<{
        issuer_id: string;
        project_id: string;
        last_rotated_at: string;
    } | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);

    const handleStepComplete = (step: SetupStep) => {
        const newCompleted = new Set(completedSteps);
        newCompleted.add(step);
        setCompletedSteps(newCompleted);

        // Auto-advance to next step
        const nextStepIndex = STEPS.findIndex((s) => s.key === step) + 1;
        if (nextStepIndex < STEPS.length) {
            setCurrentStep(STEPS[nextStepIndex].key as SetupStep);
        }
    };

    const handleUploadJSON = async (e: React.FormEvent) => {
        e.preventDefault();
        setUploadError('');
        setUploadSuccess(false);

        if (!selectedFile) {
            setUploadError('Please select a JSON credentials file');
            return;
        }

        setIsLoading(true);

        try {
            const formData = new FormData();
            formData.append('credentials', selectedFile);

            const response = await fetch('/api/certificates/google', {
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
                        data.message || 'Failed to upload credentials',
                    );
                }
            } else {
                setUploadSuccess(true);
                setUploadedCred(data.credential);
                setSelectedFile(null);
                handleStepComplete('upload-json');
            }
        } catch (error) {
            console.error('Upload error:', error);
            setUploadError('An unexpected error occurred. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };

    const isStepCompleted = (step: SetupStep) => completedSteps.has(step);
    const currentStepIndex = STEPS.findIndex((s) => s.key === currentStep);

    return (
        <AppLayout>
            <Head title="Google Wallet Setup" />

            <div className="py- mx-auto max-w-3xl space-y-6 px-4">
                {/* Page Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold text-foreground">
                        Google Wallet Setup
                    </h1>
                    <p className="text-muted-foreground">
                        Follow these 5 steps to enable Google Wallet for your
                        account
                    </p>
                </div>

                {/* Progress Steps */}
                <div className="space-y-3">
                    {STEPS.map((step, index) => {
                        const stepKey = step.key as SetupStep;
                        const isActive = currentStep === stepKey;
                        const isCompleted = isStepCompleted(stepKey);

                        return (
                            <div
                                key={step.key}
                                onClick={() => setCurrentStep(stepKey)}
                                className={`cursor-pointer rounded-lg border px-4 py-3 transition-colors ${
                                    isActive
                                        ? 'border-blue-200 bg-blue-50'
                                        : isCompleted
                                          ? 'border-green-200 bg-green-50'
                                          : 'border-gray-200 bg-gray-50'
                                }`}
                            >
                                <div className="flex items-start gap-3">
                                    <div
                                        className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                                            isCompleted
                                                ? 'bg-green-500 text-white'
                                                : isActive
                                                  ? 'bg-blue-500 text-white'
                                                  : 'bg-gray-300 text-gray-600'
                                        }`}
                                    >
                                        {isCompleted ? (
                                            <CheckCircle className="h-5 w-5" />
                                        ) : (
                                            step.number
                                        )}
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-medium text-foreground">
                                                {step.title}
                                            </h3>
                                            <span className="text-xs text-muted-foreground">
                                                {step.duration}
                                            </span>
                                        </div>
                                        {isActive && (
                                            <p className="mt-1 text-xs text-blue-600">
                                                Currently working on this step
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Step Content */}
                <Card className="p-6">
                    {currentStep === 'gcp-project' && (
                        <div className="space-y-4">
                            <h3 className="text-lg font-semibold text-foreground">
                                Create a Google Cloud Project
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                You'll need a Google Cloud Project to create
                                wallet credentials.
                            </p>
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertTitle>Free Tier</AlertTitle>
                                <AlertDescription>
                                    Google Cloud has a free tier that covers
                                    most use cases.
                                </AlertDescription>
                            </Alert>
                            <ol className="list-inside list-decimal space-y-2 text-sm text-muted-foreground">
                                <li>
                                    <a
                                        href="https://console.cloud.google.com/projectcreate"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-blue-600 underline hover:text-blue-700"
                                    >
                                        Go to Google Cloud Console
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </li>
                                <li>Click "Create Project"</li>
                                <li>
                                    Enter project name (e.g., "PassKit Wallet")
                                </li>
                                <li>
                                    Click "Create" and wait for the project to
                                    be created
                                </li>
                            </ol>
                            <Button
                                onClick={() =>
                                    handleStepComplete('gcp-project')
                                }
                                className="w-full"
                            >
                                I've Created the Project
                            </Button>
                        </div>
                    )}

                    {currentStep === 'wallet-api' && (
                        <div className="space-y-4">
                            <h3 className="text-lg font-semibold text-foreground">
                                Enable Google Wallet API
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Enable the Google Wallet API in your Google
                                Cloud Project.
                            </p>
                            <ol className="list-inside list-decimal space-y-2 text-sm text-muted-foreground">
                                <li>
                                    <a
                                        href="https://console.cloud.google.com/marketplace/product/google/walletobjects.googleapis.com"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-blue-600 underline hover:text-blue-700"
                                    >
                                        Go to API Marketplace
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </li>
                                <li>
                                    Make sure your project is selected at the
                                    top
                                </li>
                                <li>
                                    Click "Enable" for the Google Wallet API
                                </li>
                                <li>Wait for the API to be enabled</li>
                            </ol>
                            <Button
                                onClick={() => handleStepComplete('wallet-api')}
                                className="w-full"
                            >
                                I've Enabled the API
                            </Button>
                        </div>
                    )}

                    {currentStep === 'service-account' && (
                        <div className="space-y-4">
                            <h3 className="text-lg font-semibold text-foreground">
                                Create a Service Account
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Create a service account to authenticate with
                                Google Wallet API.
                            </p>
                            <ol className="list-inside list-decimal space-y-2 text-sm text-muted-foreground">
                                <li>
                                    <a
                                        href="https://console.cloud.google.com/iam-admin/serviceaccounts"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-blue-600 underline hover:text-blue-700"
                                    >
                                        Go to Service Accounts
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </li>
                                <li>Click "Create Service Account"</li>
                                <li>Name it "passkit" (or similar)</li>
                                <li>Click "Create and Continue"</li>
                                <li>
                                    Grant it the role: "Editor" (or more
                                    specific "Service Account Token Creator")
                                </li>
                                <li>Click "Continue" and then "Done"</li>
                            </ol>
                            <Button
                                onClick={() =>
                                    handleStepComplete('service-account')
                                }
                                className="w-full"
                            >
                                I've Created the Service Account
                            </Button>
                        </div>
                    )}

                    {currentStep === 'download-key' && (
                        <div className="space-y-4">
                            <h3 className="text-lg font-semibold text-foreground">
                                Download JSON Key
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Generate and download the JSON key for your
                                service account.
                            </p>
                            <ol className="list-inside list-decimal space-y-2 text-sm text-muted-foreground">
                                <li>
                                    <a
                                        href="https://console.cloud.google.com/iam-admin/serviceaccounts"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-blue-600 underline hover:text-blue-700"
                                    >
                                        Go back to Service Accounts
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </li>
                                <li>
                                    Click on the "passkit" service account email
                                </li>
                                <li>Go to the "Keys" tab</li>
                                <li>Click "Add Key" → "Create new key"</li>
                                <li>Choose "JSON"</li>
                                <li>
                                    Click "Create" - the JSON file will download
                                    automatically
                                </li>
                                <li>
                                    Keep this file safe - you'll upload it next
                                </li>
                            </ol>
                            <Button
                                onClick={() =>
                                    handleStepComplete('download-key')
                                }
                                className="w-full"
                            >
                                I've Downloaded the JSON Key
                            </Button>
                        </div>
                    )}

                    {currentStep === 'upload-json' && (
                        <div className="space-y-4">
                            <h3 className="text-lg font-semibold text-foreground">
                                Upload JSON Key
                            </h3>
                            {uploadSuccess && uploadedCred ? (
                                <div className="space-y-4">
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-4">
                                        <div className="flex items-start gap-3">
                                            <CheckCircle className="mt-0.5 h-6 w-6 flex-shrink-0 text-green-600" />
                                            <div>
                                                <h4 className="font-semibold text-green-900">
                                                    Credentials Uploaded
                                                    Successfully! 🎉
                                                </h4>
                                                <p className="mt-1 text-sm text-green-700">
                                                    Your Google Wallet
                                                    credentials are now active.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid gap-3">
                                        <div className="rounded border p-3">
                                            <p className="text-xs text-muted-foreground">
                                                Issuer ID
                                            </p>
                                            <p className="mt-1 font-mono text-sm">
                                                {uploadedCred.issuer_id}
                                            </p>
                                        </div>
                                        <div className="rounded border p-3">
                                            <p className="text-xs text-muted-foreground">
                                                Project ID
                                            </p>
                                            <p className="mt-1 font-mono text-sm">
                                                {uploadedCred.project_id}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            onClick={() =>
                                                window.history.back()
                                            }
                                            className="flex-1"
                                        >
                                            Done
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <form
                                    onSubmit={handleUploadJSON}
                                    className="space-y-4"
                                >
                                    <p className="text-sm text-muted-foreground">
                                        Select the JSON key file you downloaded
                                        from Google Cloud.
                                    </p>

                                    <div>
                                        <label className="mb-2 block text-sm font-medium text-foreground">
                                            Upload JSON Credentials File
                                        </label>
                                        <input
                                            type="file"
                                            accept=".json"
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
                                        <p className="text-xs text-green-600">
                                            ✓ {selectedFile.name}
                                        </p>
                                    )}

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
                                            : 'Upload & Complete Setup'}
                                    </Button>
                                </form>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
