import React, { ReactNode } from 'react';
import { AlertCircle, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ErrorBoundaryProps {
    children: ReactNode;
    fallback?: (error: Error, reset: () => void) => ReactNode;
}

interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends React.Component<
    ErrorBoundaryProps,
    ErrorBoundaryState
> {
    constructor(props: ErrorBoundaryProps) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        console.error('Error caught by boundary:', error, errorInfo);
    }

    resetError = () => {
        this.setState({ hasError: false, error: null });
    };

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback(this.state.error!, this.resetError);
            }

            return (
                <div className="flex min-h-screen items-center justify-center rounded-lg border border-red-200 bg-red-50 p-4">
                    <div className="max-w-md space-y-4 text-center">
                        <AlertCircle className="mx-auto h-12 w-12 text-red-600" />
                        <div>
                            <h2 className="text-lg font-semibold text-red-900">
                                Something went wrong
                            </h2>
                            <p className="mt-1 text-sm text-red-700">
                                {this.state.error?.message ||
                                    'An unexpected error occurred'}
                            </p>
                        </div>
                        <Button
                            onClick={this.resetError}
                            className="mx-auto"
                            variant="outline"
                        >
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Try again
                        </Button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

/**
 * Higher-order component to wrap a component with ErrorBoundary
 */
export function withErrorBoundary<P extends object>(
    Component: React.ComponentType<P>,
    fallback?: (error: Error, reset: () => void) => ReactNode,
) {
    const WrappedComponent = (props: P) => (
        <ErrorBoundary fallback={fallback}>
            <Component {...props} />
        </ErrorBoundary>
    );

    WrappedComponent.displayName = `withErrorBoundary(${Component.displayName || Component.name})`;

    return WrappedComponent;
}
