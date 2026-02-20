import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle, ArrowRight, Lock } from 'lucide-react';
import InputError from '@/components/input-error';

interface TierProgressionCardProps {
    currentTier:
        | 'Email_Verified'
        | 'Verified_And_Configured'
        | 'Production'
        | 'Live';
    hasAppleCert: boolean;
    hasGoogleCred: boolean;
    onRequestProduction(): void;
    isLoading?: boolean;
    error?: string;
}

const TierProgressionCard: React.FC<TierProgressionCardProps> = ({
    currentTier,
    hasAppleCert,
    hasGoogleCred,
    onRequestProduction,
    isLoading,
    error,
}) => {
    const tierSequence = [
        'Email_Verified',
        'Verified_And_Configured',
        'Production',
        'Live',
    ];

    const tierNames = {
        Email_Verified: 'Email Verified',
        Verified_And_Configured: 'Verified & Configured',
        Production: 'Production',
        Live: 'Live',
    };

    const currentIndex = tierSequence.indexOf(currentTier);
    const nextTier = tierSequence[currentIndex + 1];

    // Check if user can progress to next tier
    const canRequestProduction =
        currentTier === 'Verified_And_Configured' &&
        hasAppleCert &&
        hasGoogleCred;

    if (currentTier === 'Live') {
        return (
            <Card className="border border-green-200 bg-gradient-to-r from-green-50 to-emerald-50 p-6">
                <div className="flex items-center gap-4">
                    <div className="flex-1">
                        <h3 className="text-lg font-bold text-green-900">
                            🎉 Account is LIVE
                        </h3>
                        <p className="mt-1 text-sm text-green-700">
                            You're able to distribute passes at unlimited scale.
                            Monitor your activity in the analytics dashboard.
                        </p>
                    </div>
                    <div className="text-4xl">✨</div>
                </div>
            </Card>
        );
    }

    return (
        <Card className="space-y-4 p-6">
            <div>
                <h3 className="text-lg font-semibold text-foreground">
                    Account Tier Progression
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    You're currently at{' '}
                    <span className="font-semibold">
                        {tierNames[currentTier]}
                    </span>
                </p>
            </div>

            {/* Tier Status */}
            <div className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-4">
                <div>
                    <p className="text-xs text-muted-foreground">
                        Current Tier
                    </p>
                    <p className="mt-1 text-sm font-semibold text-foreground">
                        {tierNames[currentTier]}
                    </p>
                </div>
                {nextTier && (
                    <>
                        <ArrowRight className="h-4 w-4 text-gray-400" />
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Next Tier
                            </p>
                            <p className="mt-1 text-sm font-semibold text-foreground">
                                {tierNames[nextTier as keyof typeof tierNames]}
                            </p>
                        </div>
                    </>
                )}
            </div>

            {/* Requirements for next tier */}
            {currentTier === 'Email_Verified' && (
                <div className="space-y-2">
                    <p className="text-sm font-medium text-foreground">
                        Requirements for Verified & Configured:
                    </p>
                    <ul className="space-y-1 text-sm text-muted-foreground">
                        <li
                            className={`flex items-center gap-2 ${hasAppleCert ? 'text-green-600' : ''}`}
                        >
                            {hasAppleCert ? '✓' : '○'} Apple Wallet Certificate
                        </li>
                        <li
                            className={`flex items-center gap-2 ${hasGoogleCred ? 'text-green-600' : ''}`}
                        >
                            {hasGoogleCred ? '✓' : '○'} Google Wallet
                            Credentials
                        </li>
                    </ul>
                </div>
            )}

            {currentTier === 'Verified_And_Configured' && (
                <div className="space-y-3">
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Ready for Production?</AlertTitle>
                        <AlertDescription>
                            Your account is configured. You can now request
                            Production tier status for higher pass distribution
                            limits.
                        </AlertDescription>
                    </Alert>

                    <Button
                        onClick={onRequestProduction}
                        disabled={!canRequestProduction || isLoading}
                        className="w-full"
                    >
                        {isLoading
                            ? 'Submitting...'
                            : 'Request Production Tier'}
                    </Button>

                    {error && <InputError message={error} />}
                </div>
            )}

            {currentTier === 'Production' && (
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Production Active</AlertTitle>
                    <AlertDescription>
                        Your account is in Production tier. Complete the
                        pre-launch checklist to go live.
                    </AlertDescription>
                </Alert>
            )}
        </Card>
    );
};

export default TierProgressionCard;
