import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle, CheckCircle, Lock } from 'lucide-react';

interface PreLaunchChecklistProps {
    userId: number;
    tier: string;
    hasAppleCert: boolean;
    hasGoogleCred: boolean;
    hasCreatedPass: boolean;
    userProfileComplete: boolean;
    onGoLive(testedOnDevice: boolean): void;
    isLoading?: boolean;
}

const PreLaunchChecklist: React.FC<PreLaunchChecklistProps> = ({
    userId,
    tier,
    hasAppleCert,
    hasGoogleCred,
    hasCreatedPass,
    userProfileComplete,
    onGoLive,
    isLoading,
}) => {
    const [testedOnDevice, setTestedOnDevice] = useState(false);

    // Check all requirements
    const allRequirementsMet =
        hasAppleCert &&
        hasGoogleCred &&
        hasCreatedPass &&
        userProfileComplete &&
        testedOnDevice;

    const requirements = [
        {
            name: 'Apple Wallet Configured',
            met: hasAppleCert,
            required: true,
        },
        {
            name: 'Google Wallet Configured',
            met: hasGoogleCred,
            required: true,
        },
        {
            name: 'Created at Least 1 Pass',
            met: hasCreatedPass,
            required: true,
        },
        {
            name: 'User Profile Complete',
            met: userProfileComplete,
            required: true,
        },
        {
            name: 'Tested on iPhone/Android',
            met: testedOnDevice,
            required: true,
            manual: true,
        },
    ];

    if (tier !== 'Production') {
        return (
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p className="text-sm text-gray-600">
                    Pre-launch checklist is only available for Production tier
                    accounts.
                </p>
            </div>
        );
    }

    return (
        <Card className="p-6">
            <div className="space-y-6">
                <div>
                    <h3 className="text-lg font-semibold text-foreground">
                        Pre-Launch Checklist
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Complete all requirements below to go live with your
                        PassKit application.
                    </p>
                </div>

                {/* Requirements List */}
                <div className="space-y-3">
                    {requirements.map((req, idx) => (
                        <div
                            key={idx}
                            className={`flex items-center gap-3 rounded-lg border p-3 ${
                                req.met
                                    ? 'border-green-200 bg-green-50'
                                    : 'border-gray-200 bg-gray-50'
                            }`}
                        >
                            <div className="flex-shrink-0">
                                {req.met ? (
                                    <CheckCircle className="h-5 w-5 text-green-600" />
                                ) : (
                                    <div className="h-5 w-5 rounded-full border-2 border-gray-300" />
                                )}
                            </div>

                            {req.manual ? (
                                <label className="flex flex-1 cursor-pointer items-center gap-3">
                                    <div className="flex-1">
                                        <p
                                            className={`text-sm font-medium ${
                                                req.met
                                                    ? 'text-green-900'
                                                    : 'text-foreground'
                                            }`}
                                        >
                                            {req.name}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Manual verification required
                                        </p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        checked={testedOnDevice}
                                        onChange={(e) =>
                                            setTestedOnDevice(e.target.checked)
                                        }
                                        className="h-4 w-4"
                                    />
                                </label>
                            ) : (
                                <div className="flex-1">
                                    <p
                                        className={`text-sm font-medium ${
                                            req.met
                                                ? 'text-green-900'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        {req.name}
                                    </p>
                                    {!req.met && (
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Complete this step in your account
                                            settings
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                {/* Summary */}
                <div
                    className={`rounded-lg border p-4 ${
                        allRequirementsMet
                            ? 'border-green-200 bg-green-50'
                            : 'border-blue-200 bg-blue-50'
                    }`}
                >
                    {allRequirementsMet ? (
                        <div className="flex items-start gap-3">
                            <CheckCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600" />
                            <div>
                                <p className="text-sm font-medium text-green-900">
                                    Ready to go live!
                                </p>
                                <p className="mt-1 text-xs text-green-700">
                                    All requirements are met. Click the button
                                    below to take your app live.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600" />
                            <div>
                                <p className="text-sm font-medium text-blue-900">
                                    {requirements.filter((r) => !r.met).length}{' '}
                                    requirement
                                    {requirements.filter((r) => !r.met)
                                        .length !== 1
                                        ? 's'
                                        : ''}{' '}
                                    remaining
                                </p>
                                <p className="mt-1 text-xs text-blue-700">
                                    Complete all items to unlock the "Go Live"
                                    button.
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Action Button */}
                <Button
                    onClick={() => onGoLive(testedOnDevice)}
                    disabled={!allRequirementsMet || isLoading}
                    className="w-full"
                    size="lg"
                >
                    {isLoading ? (
                        <>
                            <span className="mr-2 animate-spin">⚙️</span>
                            Processing...
                        </>
                    ) : allRequirementsMet ? (
                        '🚀 Go Live Now'
                    ) : (
                        <>
                            <Lock className="mr-2 h-4 w-4" />
                            Complete Changes to Go Live
                        </>
                    )}
                </Button>

                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Going Live</AlertTitle>
                    <AlertDescription>
                        Once you go live, your passes will be distributed to all
                        users. Make sure everything has been thoroughly tested.
                    </AlertDescription>
                </Alert>
            </div>
        </Card>
    );
};

export default PreLaunchChecklist;
