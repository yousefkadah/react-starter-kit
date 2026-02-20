import React from 'react';

interface LoadingSkeletonProps {
    lines?: number;
    className?: string;
}

export function LoadingSkeleton({
    lines = 3,
    className = '',
}: LoadingSkeletonProps) {
    return (
        <div className={`space-y-3 ${className}`}>
            {Array.from({ length: lines }).map((_, i) => (
                <div
                    key={i}
                    className="h-4 w-full animate-pulse rounded bg-gray-200"
                />
            ))}
        </div>
    );
}

interface LoadingPageProps {
    title?: string;
    subtitle?: string;
}

export function LoadingPage({ title, subtitle }: LoadingPageProps) {
    return (
        <div className="flex min-h-screen items-center justify-center">
            <div className="max-w-md space-y-6 text-center">
                {/* Animated loader */}
                <div className="flex justify-center">
                    <div className="relative h-12 w-12">
                        <div className="absolute inset-0 animate-spin rounded-full border-4 border-gray-200 border-t-blue-500" />
                    </div>
                </div>

                {title && (
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">
                            {title}
                        </h2>
                        {subtitle && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {subtitle}
                            </p>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

interface LoadingOverlayProps {
    isLoading: boolean;
    message?: string;
}

export function LoadingOverlay({
    isLoading,
    message = 'Loading...',
}: LoadingOverlayProps) {
    if (!isLoading) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="rounded-lg bg-white p-6 text-center shadow-lg">
                <div className="mb-4 flex justify-center">
                    <div className="relative h-8 w-8">
                        <div className="absolute inset-0 animate-spin rounded-full border-2 border-gray-200 border-t-blue-500" />
                    </div>
                </div>
                <p className="text-sm font-medium text-foreground">{message}</p>
            </div>
        </div>
    );
}
