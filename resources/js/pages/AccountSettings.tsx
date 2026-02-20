import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import TierBadge from '@/components/tier-badge';
import TierRoadmap from '@/components/tier-roadmap';
import TierProgressionCard from '@/components/tier-progression-card';
import PreLaunchChecklist from '@/components/pre-launch-checklist';
import AppleCertificateList from '@/components/apple-certificate-list';
import GoogleCredentialList from '@/components/google-credential-list';
import OnboardingWizard from '@/components/onboarding-wizard';
import { AlertCircle, Loader, CheckCircle } from 'lucide-react';
import { getIndustryLabel, INDUSTRY_OPTIONS } from '@/lib/industry-options';

interface User {
    id: number;
    name: string;
    email: string;
    region: 'EU' | 'US';
    industry?: string;
    tier: 'Email_Verified' | 'Verified_And_Configured' | 'Production' | 'Live';
    approval_status: 'pending' | 'approved' | 'rejected';
    approved_at?: string;
    created_at: string;
}

interface ApplesData {
    appleCertificates: Array<{
        id: number;
        fingerprint: string;
        valid_from: string;
        expiry_date: string;
    }>;
}

interface GooglesData {
    googleCredentials: Array<{
        id: number;
        issuer_id: string;
        project_id: string;
        last_rotated_at: string | null;
    }>;
}

interface OnboardingData {
    onboardingSteps: Array<{
        step_key: string;
        completed_at: string | null;
    }>;
}

interface PageProps {
    auth: {
        user: User;
    };
    passCount?: number;
    appleCertificates?: Array<{
        id: number;
        fingerprint: string;
        valid_from: string;
        expiry_date: string;
    }>;
    googleCredentials?: Array<{
        id: number;
        issuer_id: string;
        project_id: string;
        last_rotated_at: string | null;
    }>;
    onboardingSteps?: Array<{
        step_key: string;
        completed_at: string | null;
    }>;
}

const getDaysElapsed = (date: string) => {
    const days = Math.floor(
        (Date.now() - new Date(date).getTime()) / (1000 * 60 * 60 * 24),
    );
    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    return `${days} days ago`;
};

export default function AccountSettings({
    auth,
    passCount = 0,
    appleCertificates = [],
    googleCredentials = [],
    onboardingSteps = [],
}: PageProps) {
    const user = auth.user;
    const [isEditing, setIsEditing] = useState(false);
    const [editData, setEditData] = useState({
        name: user.name,
        industry: user.industry || '',
    });
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [saveSuccess, setSaveSuccess] = useState(false);
    const [isRequestingProduction, setIsRequestingProduction] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [isGoingLive, setIsGoingLive] = useState(false);
    const [goLiveError, setGoLiveError] = useState('');
    const [goLiveSuccess, setGoLiveSuccess] = useState(false);

    const handleEditChange = (
        e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        const { name, value } = e.target;
        setEditData((prev) => ({
            ...prev,
            [name]: value,
        }));
    };

    const handleSaveEdit = async () => {
        setIsSaving(true);
        setSaveError('');
        setSaveSuccess(false);

        try {
            const response = await fetch('/api/account', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify(editData),
            });

            if (!response.ok) {
                const error = await response.json();
                setSaveError(error.message || 'Failed to save changes');
            } else {
                setSaveSuccess(true);
                setIsEditing(false);
                // Optionally reload page or update state
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } catch (error) {
            console.error('Save error:', error);
            setSaveError('An unexpected error occurred');
        } finally {
            setIsSaving(false);
        }
    };

    const handleRequestProduction = async () => {
        setIsRequestingProduction(true);
        setRequestError('');

        try {
            const response = await fetch('/api/tier/request-production', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                setRequestError(
                    data.message || 'Failed to request production tier',
                );
            } else {
                setRequestError('');
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            }
        } catch (error) {
            console.error('Production request error:', error);
            setRequestError('An unexpected error occurred');
        } finally {
            setIsRequestingProduction(false);
        }
    };

    const handleGoLive = async (testedOnDevice: boolean) => {
        setIsGoingLive(true);
        setGoLiveError('');
        setGoLiveSuccess(false);

        try {
            const validateResponse = await fetch('/api/tier/request-live', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ tested_on_device: testedOnDevice }),
            });

            const validateData = await validateResponse.json();

            if (!validateResponse.ok) {
                setGoLiveError(
                    validateData.message ||
                        'Pre-launch checklist validation failed',
                );
                return;
            }

            const goLiveResponse = await fetch('/api/tier/go-live', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            const goLiveData = await goLiveResponse.json();

            if (!goLiveResponse.ok) {
                setGoLiveError(goLiveData.message || 'Failed to go live');
            } else {
                setGoLiveSuccess(true);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } catch (error) {
            console.error('Go live error:', error);
            setGoLiveError('An unexpected error occurred');
        } finally {
            setIsGoingLive(false);
        }
    };

    const isPending = user.approval_status === 'pending';
    const isApproved = user.approval_status === 'approved';
    const isRejected = user.approval_status === 'rejected';
    const hasAppleCert = appleCertificates.length > 0;
    const hasGoogleCred = googleCredentials.length > 0;
    const hasCreatedPass = passCount > 0;
    const userProfileComplete = Boolean(user.name) && Boolean(user.industry);

    return (
        <AppLayout>
            <Head title="Account Settings" />

            {/* Onboarding Wizard */}
            <OnboardingWizard steps={onboardingSteps} />

            <div className="mx-auto max-w-4xl space-y-8 px-4 py-10">
                {/* Page Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold text-foreground">
                        Account Settings
                    </h1>
                    <p className="text-muted-foreground">
                        Manage your account and wallet setup
                    </p>
                </div>

                {/* Status Banner */}
                {isPending && (
                    <div className="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                        <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-600" />
                        <div>
                            <h3 className="font-semibold text-yellow-900">
                                Pending Approval
                            </h3>
                            <p className="text-sm text-yellow-800">
                                Your account is pending approval. We'll email
                                you within 24 hours.
                            </p>
                        </div>
                    </div>
                )}

                {isRejected && (
                    <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
                        <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                        <div>
                            <h3 className="font-semibold text-red-900">
                                Application Declined
                            </h3>
                            <p className="text-sm text-red-800">
                                Your account application was declined. Please
                                contact support for more information.
                            </p>
                        </div>
                    </div>
                )}

                {isApproved && (
                    <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4">
                        <CheckCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600" />
                        <div>
                            <h3 className="font-semibold text-green-900">
                                Account Approved!
                            </h3>
                            <p className="text-sm text-green-800">
                                Your account has been approved. Start setting up
                                your wallet credentials.
                            </p>
                        </div>
                    </div>
                )}

                {/* Tabs */}
                <Tabs defaultValue="account" className="w-full">
                    <TabsList className="grid w-full grid-cols-5">
                        <TabsTrigger value="account">Account</TabsTrigger>
                        <TabsTrigger value="tier">Tier Progress</TabsTrigger>
                        <TabsTrigger value="apple">Apple</TabsTrigger>
                        <TabsTrigger value="google">Google</TabsTrigger>
                        <TabsTrigger value="certificates">Certs</TabsTrigger>
                    </TabsList>

                    {/* Account Info Tab */}
                    <TabsContent value="account" className="space-y-4">
                        <Card className="p-6">
                            <div className="space-y-4">
                                {!isEditing ? (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <Label className="text-sm text-muted-foreground">
                                                    Full Name
                                                </Label>
                                                <p className="text-lg font-semibold text-foreground">
                                                    {user.name}
                                                </p>
                                            </div>
                                            <div>
                                                <Label className="text-sm text-muted-foreground">
                                                    Email
                                                </Label>
                                                <p className="text-lg font-semibold text-foreground">
                                                    {user.email}
                                                </p>
                                            </div>
                                            <div>
                                                <Label className="text-sm text-muted-foreground">
                                                    Data Region
                                                </Label>
                                                <p className="text-lg font-semibold text-foreground">
                                                    {user.region === 'EU'
                                                        ? '🇪🇺 Europe'
                                                        : '🇺🇸 United States'}
                                                </p>
                                            </div>
                                            <div>
                                                <Label className="text-sm text-muted-foreground">
                                                    Industry
                                                </Label>
                                                <p className="text-lg font-semibold text-foreground">
                                                    {user.industry
                                                        ? getIndustryLabel(
                                                              user.industry,
                                                          )
                                                        : 'Not specified'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="border-t pt-4">
                                            <Button
                                                onClick={() =>
                                                    setIsEditing(true)
                                                }
                                            >
                                                Edit Account
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <div className="space-y-4">
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <Label htmlFor="name">
                                                        Full Name
                                                    </Label>
                                                    <Input
                                                        id="name"
                                                        name="name"
                                                        value={editData.name}
                                                        onChange={
                                                            handleEditChange
                                                        }
                                                        disabled={isSaving}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="industry">
                                                        Industry
                                                    </Label>
                                                    <select
                                                        id="industry"
                                                        name="industry"
                                                        value={
                                                            editData.industry
                                                        }
                                                        onChange={
                                                            handleEditChange
                                                        }
                                                        disabled={isSaving}
                                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    >
                                                        <option value="">
                                                            Select an industry
                                                        </option>
                                                        {INDUSTRY_OPTIONS.map(
                                                            (opt) => (
                                                                <option
                                                                    key={
                                                                        opt.value
                                                                    }
                                                                    value={
                                                                        opt.value
                                                                    }
                                                                >
                                                                    {opt.label}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                            </div>

                                            {saveError && (
                                                <InputError
                                                    message={saveError}
                                                />
                                            )}

                                            {saveSuccess && (
                                                <div className="rounded-md bg-green-50 p-3 text-sm text-green-800">
                                                    Changes saved successfully!
                                                </div>
                                            )}

                                            <div className="flex gap-2 border-t pt-4">
                                                <Button
                                                    onClick={handleSaveEdit}
                                                    disabled={isSaving}
                                                >
                                                    {isSaving && (
                                                        <Loader className="mr-2 h-4 w-4 animate-spin" />
                                                    )}
                                                    {isSaving
                                                        ? 'Saving...'
                                                        : 'Save'}
                                                </Button>
                                                <Button
                                                    onClick={() => {
                                                        setIsEditing(false);
                                                        setEditData({
                                                            name: user.name,
                                                            industry:
                                                                user.industry ||
                                                                '',
                                                        });
                                                    }}
                                                    variant="outline"
                                                    disabled={isSaving}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </Card>
                    </TabsContent>

                    {/* Tier Progress Tab */}
                    <TabsContent value="tier" className="space-y-4">
                        <Card className="p-6">
                            <TierBadge
                                currentTier={user.tier}
                                compact={false}
                            />
                            <div className="mt-6">
                                <TierRoadmap
                                    currentTier={user.tier}
                                    approvalStatus={user.approval_status}
                                />
                            </div>
                        </Card>

                        <TierProgressionCard
                            currentTier={user.tier}
                            hasAppleCert={hasAppleCert}
                            hasGoogleCred={hasGoogleCred}
                            onRequestProduction={handleRequestProduction}
                            isLoading={isRequestingProduction}
                            error={requestError}
                        />

                        <PreLaunchChecklist
                            userId={user.id}
                            tier={user.tier}
                            hasAppleCert={hasAppleCert}
                            hasGoogleCred={hasGoogleCred}
                            hasCreatedPass={hasCreatedPass}
                            userProfileComplete={userProfileComplete}
                            onGoLive={handleGoLive}
                            isLoading={isGoingLive}
                        />

                        {goLiveError && <InputError message={goLiveError} />}
                        {goLiveSuccess && (
                            <div className="rounded-md bg-green-50 p-3 text-sm text-green-800">
                                Your account is now live! Redirecting...
                            </div>
                        )}
                    </TabsContent>

                    {/* Apple Tab */}
                    <TabsContent value="apple" className="space-y-4">
                        <Card className="p-6">
                            <AppleCertificateList
                                certificates={appleCertificates}
                                onAddCertificate={() => {
                                    window.location.href =
                                        '/settings/certificates/apple';
                                }}
                                onRenew={(id) => {
                                    console.log('Renew certificate:', id);
                                }}
                                onDelete={(id) => {
                                    console.log('Delete certificate:', id);
                                }}
                            />
                        </Card>
                    </TabsContent>

                    {/* Google Tab */}
                    <TabsContent value="google" className="space-y-4">
                        <Card className="p-6">
                            <GoogleCredentialList
                                credentials={googleCredentials}
                                onAddCredential={() => {
                                    window.location.href =
                                        '/settings/certificates/google';
                                }}
                                onRotate={(id) => {
                                    console.log('Rotate credential:', id);
                                }}
                                onDelete={(id) => {
                                    console.log('Delete credential:', id);
                                }}
                            />
                        </Card>
                    </TabsContent>

                    {/* Certificates Tab (Summary) */}
                    <TabsContent value="certificates" className="space-y-4">
                        <Card className="p-6">
                            <h3 className="mb-4 text-lg font-semibold text-foreground">
                                Certificate Status
                            </h3>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium text-foreground">
                                            Apple Certificates
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {appleCertificates.length}{' '}
                                            certificate(s) uploaded
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            window.location.href =
                                                '/settings/certificates/apple';
                                        }}
                                    >
                                        Manage
                                    </Button>
                                </div>
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium text-foreground">
                                            Google Credentials
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {googleCredentials.length}{' '}
                                            credential(s) uploaded
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            window.location.href =
                                                '/settings/certificates/google';
                                        }}
                                    >
                                        Manage
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
