import React, { useEffect, useState } from 'react';
import { X, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface OnboardingStep {
    step_key: string;
    completed_at: string | null;
}

interface OnboardingWizardProps {
    steps: OnboardingStep[];
    onDismiss?: () => void;
}

const STEP_DEFINITIONS = {
    email_verified: {
        label: 'Email Verified',
        description: 'Your email has been verified',
        icon: '✓',
    },
    apple_setup: {
        label: 'Apple Wallet Setup',
        description: 'Configure Apple Wallet certificate',
        link: '/settings/certificates/apple',
    },
    google_setup: {
        label: 'Google Wallet Setup',
        description: 'Configure Google Wallet credentials',
        link: '/settings/certificates/google',
    },
    user_profile: {
        label: 'Complete Profile',
        description: 'Add company details and contact info',
        link: '/settings/profile',
    },
    first_pass: {
        label: 'Create First Pass',
        description: 'Create your first digital pass',
        link: '/passes/new',
    },
} as const;

const STEP_ORDER = [
    'email_verified',
    'apple_setup',
    'google_setup',
    'user_profile',
    'first_pass',
] as const;

export default function OnboardingWizard({
    steps,
    onDismiss,
}: OnboardingWizardProps) {
    const [isVisible, setIsVisible] = useState(true);
    const [isMinimized, setIsMinimized] = useState(false);
    const [isCompleting, setIsCompleting] = useState(false);

    useEffect(() => {
        const dismissed = localStorage.getItem('onboarding-wizard-dismissed');
        if (dismissed === 'true') {
            setIsVisible(false);
        }
    }, []);

    const completedCount = steps.filter((s) => s.completed_at).length;
    const totalSteps = STEP_ORDER.length;
    const allComplete = completedCount === totalSteps;

    const handleDismiss = () => {
        localStorage.setItem('onboarding-wizard-dismissed', 'true');
        setIsVisible(false);
        onDismiss?.();
    };

    useEffect(() => {
        if (!allComplete) return;

        setIsCompleting(true);

        const timer = window.setTimeout(() => {
            localStorage.setItem('onboarding-wizard-dismissed', 'true');
            setIsVisible(false);
            onDismiss?.();
        }, 3000);

        return () => window.clearTimeout(timer);
    }, [allComplete, onDismiss]);

    if (!isVisible) return null;

    if (isMinimized) {
        return (
            <div className="fixed right-4 bottom-4 z-40">
                <Button
                    onClick={() => setIsMinimized(false)}
                    variant="outline"
                    className="rounded-full"
                >
                    <span className="text-sm font-semibold">
                        Onboarding {completedCount}/{totalSteps}
                    </span>
                </Button>
            </div>
        );
    }

    return (
        <div className="fixed right-4 bottom-4 z-40 w-96 rounded-lg border border-gray-200 bg-white shadow-lg">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <div className="flex items-center gap-2">
                    <div className="text-lg font-semibold text-foreground">
                        🚀 Get Started
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        onClick={() => setIsMinimized(true)}
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0"
                    >
                        −
                    </Button>
                    <Button
                        onClick={handleDismiss}
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Content */}
            <div className="space-y-3 p-4">
                {/* Progress Bar */}
                <div className="space-y-1">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Progress</span>
                        <span className="font-semibold text-foreground">
                            {completedCount}/{totalSteps}
                        </span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                        <div
                            className="h-full bg-blue-500 transition-all duration-300"
                            style={{
                                width: `${(completedCount / totalSteps) * 100}%`,
                            }}
                        />
                    </div>
                </div>

                {/* Steps */}
                <div className="space-y-2 pt-2">
                    {STEP_ORDER.map((stepKey) => {
                        const step = steps.find((s) => s.step_key === stepKey);
                        const isCompleted = !!step?.completed_at;
                        const definition = STEP_DEFINITIONS[stepKey];

                        return (
                            <div
                                key={stepKey}
                                className={`flex items-start gap-3 rounded px-2 py-2 text-sm transition-colors ${
                                    isCompleted
                                        ? 'bg-green-50 text-green-900'
                                        : 'hover:bg-gray-50'
                                }`}
                            >
                                <div
                                    className={`mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                        isCompleted
                                            ? 'bg-green-500 text-white'
                                            : 'bg-gray-300 text-gray-600'
                                    }`}
                                >
                                    {isCompleted ? (
                                        <Check className="h-3 w-3" />
                                    ) : (
                                        '·'
                                    )}
                                </div>
                                <div className="flex-1">
                                    <p
                                        className={`font-medium ${
                                            isCompleted
                                                ? 'text-green-900'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        {definition.label}
                                    </p>
                                    <p
                                        className={`text-xs ${
                                            isCompleted
                                                ? 'text-green-700'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {definition.description}
                                    </p>
                                </div>
                                {!isCompleted && definition.link && (
                                    <Button
                                        onClick={() => {
                                            window.location.href =
                                                definition.link;
                                        }}
                                        variant="ghost"
                                        size="sm"
                                        className="h-auto p-0 text-xs text-blue-600 hover:text-blue-700"
                                    >
                                        Start
                                    </Button>
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Complete Message */}
                {allComplete && (
                    <div className="rounded-lg bg-green-50 p-3 text-center text-sm text-green-900">
                        <p className="font-semibold">🎉 Setup Complete!</p>
                        <p className="mt-1 text-xs text-green-700">
                            You're ready to start creating passes.
                        </p>
                        {isCompleting && (
                            <p className="mt-1 text-xs text-green-700">
                                Closing in a moment...
                            </p>
                        )}
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="border-t border-gray-200 px-4 py-3">
                <Button
                    onClick={handleDismiss}
                    variant="outline"
                    size="sm"
                    className="w-full"
                >
                    {allComplete ? 'Done' : 'Skip Tour'}
                </Button>
            </div>
        </div>
    );
}
