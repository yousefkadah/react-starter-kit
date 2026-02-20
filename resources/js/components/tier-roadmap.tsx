import { Button } from '@/components/ui/button';
import { Check, Lock, AlertCircle, ArrowRight } from 'lucide-react';
import React from 'react';

interface TierRoadmapProps {
    currentTier: 'Email_Verified' | 'Verified_And_Configured' | 'Production' | 'Live';
    approvalStatus: 'pending' | 'approved' | 'rejected';
    onRequestProduction?: () => void;
    onGoLive?: () => void;
}

const TIER_ROADMAP = [
    {
        tier: 'Email_Verified',
        title: 'Email Verified',
        status: 'Completed',
        requirements: ['Email validated'],
        unlocked: true,
        isCompleted: true,
    },
    {
        tier: 'Verified_And_Configured',
        title: 'Verified & Configured',
        status: 'In Progress',
        requirements: ['Set up Apple Wallet (optional)', 'Set up Google Wallet (optional)'],
        unlocked: true,
        isCompleted: false,
        action: {
            label: 'Set Up Wallets',
            href: '/settings/wallets',
        },
    },
    {
        tier: 'Production',
        title: 'Production',
        status: 'Locked',
        requirements: ['Admin approval required', 'Certificates configured'],
        unlocked: false,
        isCompleted: false,
        info: 'Request production access after configuring certificates',
    },
    {
        tier: 'Live',
        title: 'Live',
        status: 'Locked',
        requirements: ['Create your first production pass', 'Confirmation required'],
        unlocked: false,
        isCompleted: false,
        info: 'Launch to production after creating your first pass',
    },
];

export default function TierRoadmap({
    currentTier,
    approvalStatus,
    onRequestProduction,
    onGoLive,
}: TierRoadmapProps) {
    const TIER_ORDER = [
        'Email_Verified',
        'Verified_And_Configured',
        'Production',
        'Live',
    ];
    const currentIndex = TIER_ORDER.indexOf(currentTier);

    return (
        <div className="space-y-4">
            <div className="space-y-3">
                {TIER_ROADMAP.map((tier, index) => {
                    const isCompleted = index < currentIndex;
                    const isCurrent = index === currentIndex;
                    const isLocked = index > currentIndex;

                    return (
                        <div key={tier.tier}>
                            <div
                                className={`rounded-lg border p-4 transition-colors ${
                                    isCompleted
                                        ? 'border-green-200 bg-green-50'
                                        : isCurrent
                                          ? 'border-blue-200 bg-blue-50'
                                          : 'border-gray-200 bg-gray-50'
                                }`}
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start gap-3">
                                        <div
                                            className={`mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                                                isCompleted
                                                    ? 'bg-green-500 text-white'
                                                    : isCurrent
                                                      ? 'bg-blue-500 text-white'
                                                      : 'bg-gray-300 text-gray-600'
                                            }`}
                                        >
                                            {isCompleted ? (
                                                <Check className="h-4 w-4" />
                                            ) : isLocked ? (
                                                <Lock className="h-3 w-3" />
                                            ) : (
                                                index + 1
                                            )}
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-foreground">
                                                {tier.title}
                                            </h3>
                                            <p
                                                className={`text-sm ${
                                                    isCompleted
                                                        ? 'text-green-700'
                                                        : isCurrent
                                                          ? 'text-blue-700'
                                                          : 'text-gray-600'
                                                }`}
                                            >
                                                {tier.status}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Requirements */}
                                <div className="mt-3 ml-9 space-y-1">
                                    {tier.requirements.map((req, i) => (
                                        <div
                                            key={i}
                                            className="flex items-start gap-2 text-sm text-muted-foreground"
                                        >
                                            <span className="mt-0.5 block h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400" />
                                            {req}
                                        </div>
                                    ))}
                                </div>

                                {/* Info Note */}
                                {tier.info && (
                                    <div className="mt-3 ml-9 flex items-start gap-2 rounded bg-blue-100 px-3 py-2 text-sm text-blue-800">
                                        <AlertCircle className="h-4 w-4 flex-shrink-0 mt-0.5" />
                                        {tier.info}
                                    </div>
                                )}

                                {/* Action Buttons */}
                                {isCurrent && tier.action && (
                                    <div className="mt-3 ml-9">
                                        <Button
                                            onClick={() => window.location.href = tier.action!.href}
                                            variant="outline"
                                            size="sm"
                                        >
                                            {tier.action.label}
                                        </Button>
                                    </div>
                                )}

                                {/* Production Request */}
                                {tier.tier === 'Production' && approvalStatus === 'approved' && (
                                    <div className="mt-3 ml-9">
                                        <Button
                                            onClick={onRequestProduction}
                                            size="sm"
                                            variant="default"
                                        >
                                            Request Production Access
                                        </Button>
                                    </div>
                                )}

                                {/* Go Live Button */}
                                {tier.tier === 'Live' && isCurrent && (
                                    <div className="mt-3 ml-9">
                                        <Button
                                            onClick={onGoLive}
                                            size="sm"
                                            variant="default"
                                        >
                                            Go Live to Production
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
