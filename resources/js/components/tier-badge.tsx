import React from 'react';
import { Check, Lock, AlertCircle } from 'lucide-react';

interface TierStep {
    key: string;
    name: string;
    isCompleted: boolean;
    isCurrent: boolean;
}

interface TierBadgeProps {
    currentTier:
        | 'Email_Verified'
        | 'Verified_And_Configured'
        | 'Production'
        | 'Live';
    compact?: boolean;
}

const TIER_ORDER = [
    'Email_Verified',
    'Verified_And_Configured',
    'Production',
    'Live',
] as const;

const TIER_DATA = {
    Email_Verified: {
        label: 'Email Verified',
        description: 'Email validated',
        color: 'bg-blue-100 text-blue-900',
    },
    Verified_And_Configured: {
        label: 'Configured',
        description: 'Apple + Google setup',
        color: 'bg-purple-100 text-purple-900',
    },
    Production: {
        label: 'Production',
        description: 'Admin approved',
        color: 'bg-orange-100 text-orange-900',
    },
    Live: {
        label: 'Live',
        description: 'First pass created',
        color: 'bg-green-100 text-green-900',
    },
} as const;

export default function TierBadge({
    currentTier,
    compact = false,
}: TierBadgeProps) {
    const currentIndex = TIER_ORDER.indexOf(currentTier);

    if (compact) {
        const tier = TIER_DATA[currentTier];
        return (
            <div
                className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium ${tier.color}`}
            >
                <Check className="h-4 w-4" />
                {tier.label}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <h3 className="text-sm font-semibold text-foreground">
                    Account Tier Progression
                </h3>
                <div className="flex items-center gap-2">
                    {TIER_ORDER.map((tier, index) => {
                        const isCompleted = index < currentIndex;
                        const isCurrent = index === currentIndex;
                        const isLocked = index > currentIndex;
                        const data = TIER_DATA[tier];

                        return (
                            <React.Fragment key={tier}>
                                {index > 0 && (
                                    <div
                                        className={`h-1 flex-1 ${isCompleted ? 'bg-green-500' : 'bg-gray-200'}`}
                                    />
                                )}
                                <div className="flex flex-col items-center">
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ${
                                            isCompleted
                                                ? 'bg-green-500 text-white'
                                                : isCurrent
                                                  ? 'bg-blue-500 text-white'
                                                  : 'bg-gray-200 text-gray-500'
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
                                    <div className="mt-2 text-center">
                                        <p className="text-xs font-medium text-foreground">
                                            {data.label}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {data.description}
                                        </p>
                                    </div>
                                </div>
                            </React.Fragment>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
