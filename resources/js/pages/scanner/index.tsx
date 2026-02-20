import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Camera, Keyboard, CheckCircle, XCircle, AlertTriangle, WifiOff, RotateCcw, Loader2 } from 'lucide-react';

interface ScannerProps {
    scannerToken: string;
    scannerName: string;
}

interface PassResult {
    id: number;
    type: 'single_use' | 'multi_use';
    status: 'active' | 'redeemed' | 'voided' | 'expired';
    custom_redemption_message: string | null;
    redeemed_at: string | null;
    pass_type: string;
    description: string | null;
}

interface ValidateResponse {
    valid: boolean;
    pass?: PassResult;
    error?: string;
}

interface RedeemResponse {
    success: boolean;
    message: string;
    pass?: {
        id: number;
        status: string;
    };
    error?: string;
}

type ScanMode = 'camera' | 'manual';
type ScanState = 'idle' | 'scanning' | 'validating' | 'result' | 'error';

export default function ScannerIndex({ scannerToken, scannerName }: ScannerProps) {
    const [scanMode, setScanMode] = useState<ScanMode>('camera');
    const [scanState, setScanState] = useState<ScanState>('idle');
    const [manualCode, setManualCode] = useState('');
    const [passResult, setPassResult] = useState<PassResult | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [isRedeeming, setIsRedeeming] = useState(false);
    const [redeemMessage, setRedeemMessage] = useState<string | null>(null);
    const scannerRef = useRef<HTMLDivElement>(null);
    const html5QrCodeRef = useRef<any>(null);

    // Online/offline detection
    useEffect(() => {
        const handleOnline = () => setIsOnline(true);
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    // Cleanup QR scanner on unmount
    useEffect(() => {
        return () => {
            if (html5QrCodeRef.current) {
                html5QrCodeRef.current.stop().catch(() => {});
            }
        };
    }, []);

    const startCameraScanning = useCallback(async () => {
        if (!scannerRef.current) return;

        try {
            const { Html5Qrcode } = await import('html5-qrcode');

            if (html5QrCodeRef.current) {
                await html5QrCodeRef.current.stop().catch(() => {});
            }

            const scanner = new Html5Qrcode('qr-reader');
            html5QrCodeRef.current = scanner;
            setScanState('scanning');

            await scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                },
                (decodedText: string) => {
                    scanner.stop().catch(() => {});
                    handlePayload(decodedText);
                },
                () => {
                    // Ignore scan errors (no QR found in frame)
                }
            );
        } catch {
            setScanMode('manual');
            setErrorMessage('Camera access denied or not available. Please use manual entry.');
            setScanState('error');
        }
    }, [scannerToken]);

    const handlePayload = async (payload: string) => {
        if (!isOnline) {
            setErrorMessage('Offline — Cannot Validate. Please check your internet connection.');
            setScanState('error');
            return;
        }

        setScanState('validating');
        setPassResult(null);
        setErrorMessage(null);
        setRedeemMessage(null);

        try {
            const response = await fetch('/api/scanner/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Scanner-Token': scannerToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ payload }),
            });

            const data: ValidateResponse = await response.json();

            if (response.ok && data.pass) {
                setPassResult(data.pass);
                setScanState('result');
            } else {
                setErrorMessage(data.error || 'Invalid pass.');
                setScanState('error');
            }
        } catch {
            setErrorMessage('Network error. Please check your connection and try again.');
            setScanState('error');
        }
    };

    const handleRedeem = async () => {
        if (!passResult || !isOnline) return;

        setIsRedeeming(true);
        setRedeemMessage(null);

        try {
            const payload = manualCode || '';

            const response = await fetch('/api/scanner/redeem', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Scanner-Token': scannerToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ pass_id: passResult.id }),
            });

            const data: RedeemResponse = await response.json();

            if (response.ok && data.success) {
                setRedeemMessage(data.message);
                setPassResult(prev => prev ? { ...prev, status: data.pass?.status as PassResult['status'] || 'redeemed' } : null);
            } else {
                setRedeemMessage(data.error || data.message || 'Redemption failed.');
            }
        } catch {
            setRedeemMessage('Network error. Please try again.');
        } finally {
            setIsRedeeming(false);
        }
    };

    const handleManualSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (manualCode.trim()) {
            handlePayload(manualCode.trim());
        }
    };

    const resetScanner = () => {
        setScanState('idle');
        setPassResult(null);
        setErrorMessage(null);
        setRedeemMessage(null);
        setManualCode('');
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</Badge>;
            case 'redeemed':
                return <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Redeemed</Badge>;
            case 'voided':
                return <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Voided</Badge>;
            case 'expired':
                return <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Expired</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <>
            <Head title={`Scanner — ${scannerName}`} />
            <div className="flex min-h-screen flex-col items-center bg-gray-50 p-4 dark:bg-gray-950">
                <div className="w-full max-w-md space-y-4">
                    {/* Header */}
                    <div className="text-center">
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Pass Scanner</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{scannerName}</p>
                    </div>

                    {/* Offline Banner */}
                    {!isOnline && (
                        <Alert variant="destructive">
                            <WifiOff className="h-4 w-4" />
                            <AlertTitle>Offline</AlertTitle>
                            <AlertDescription>
                                Cannot validate passes without internet connection.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Scan Mode Toggle */}
                    {scanState !== 'result' && (
                        <div className="flex gap-2">
                            <Button
                                variant={scanMode === 'camera' ? 'default' : 'outline'}
                                className="flex-1"
                                onClick={() => {
                                    setScanMode('camera');
                                    resetScanner();
                                }}
                            >
                                <Camera className="mr-2 h-4 w-4" />
                                Camera
                            </Button>
                            <Button
                                variant={scanMode === 'manual' ? 'default' : 'outline'}
                                className="flex-1"
                                onClick={() => {
                                    setScanMode('manual');
                                    if (html5QrCodeRef.current) {
                                        html5QrCodeRef.current.stop().catch(() => {});
                                    }
                                    setScanState('idle');
                                }}
                            >
                                <Keyboard className="mr-2 h-4 w-4" />
                                Manual Entry
                            </Button>
                        </div>
                    )}

                    {/* Camera Scanner */}
                    {scanMode === 'camera' && scanState !== 'result' && (
                        <Card>
                            <CardContent className="pt-6">
                                <div
                                    id="qr-reader"
                                    ref={scannerRef}
                                    className="overflow-hidden rounded-lg"
                                />
                                {scanState === 'idle' && (
                                    <Button
                                        className="mt-4 w-full"
                                        onClick={startCameraScanning}
                                        disabled={!isOnline}
                                    >
                                        <Camera className="mr-2 h-4 w-4" />
                                        Start Scanning
                                    </Button>
                                )}
                                {scanState === 'scanning' && (
                                    <p className="mt-4 text-center text-sm text-gray-500">
                                        Point camera at QR code...
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Manual Entry */}
                    {scanMode === 'manual' && scanState !== 'result' && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Manual Entry</CardTitle>
                                <CardDescription>Enter the pass code manually</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleManualSubmit} className="space-y-4">
                                    <Input
                                        value={manualCode}
                                        onChange={(e) => setManualCode(e.target.value)}
                                        placeholder="Paste or type pass code..."
                                        disabled={!isOnline}
                                    />
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={!manualCode.trim() || !isOnline}
                                    >
                                        Validate Pass
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    {/* Validating State */}
                    {scanState === 'validating' && (
                        <Card>
                            <CardContent className="flex items-center justify-center py-12">
                                <Loader2 className="mr-2 h-6 w-6 animate-spin" />
                                <span className="text-lg">Validating...</span>
                            </CardContent>
                        </Card>
                    )}

                    {/* Result State */}
                    {scanState === 'result' && passResult && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Pass Details</CardTitle>
                                    {getStatusBadge(passResult.status)}
                                </div>
                                {passResult.description && (
                                    <CardDescription>{passResult.description}</CardDescription>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Status Display */}
                                {passResult.status === 'active' && (
                                    <Alert>
                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                        <AlertTitle>Valid Pass</AlertTitle>
                                        <AlertDescription>
                                            This {passResult.type === 'multi_use' ? 'loyalty' : 'coupon'} pass is active and ready to use.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {passResult.status === 'redeemed' && (
                                    <Alert variant="destructive">
                                        <XCircle className="h-4 w-4" />
                                        <AlertTitle>Already Redeemed</AlertTitle>
                                        <AlertDescription>
                                            This pass was redeemed{passResult.redeemed_at ? ` on ${new Date(passResult.redeemed_at).toLocaleString()}` : ''}.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {passResult.status === 'voided' && (
                                    <Alert variant="destructive">
                                        <XCircle className="h-4 w-4" />
                                        <AlertTitle>Voided</AlertTitle>
                                        <AlertDescription>This pass has been voided and cannot be used.</AlertDescription>
                                    </Alert>
                                )}

                                {passResult.status === 'expired' && (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>Expired</AlertTitle>
                                        <AlertDescription>This pass has expired and is no longer valid.</AlertDescription>
                                    </Alert>
                                )}

                                {/* Custom Redemption Message */}
                                {passResult.custom_redemption_message && (
                                    <Alert>
                                        <AlertTitle>Instructions</AlertTitle>
                                        <AlertDescription className="font-medium">
                                            {passResult.custom_redemption_message}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* Redeem Message */}
                                {redeemMessage && (
                                    <Alert>
                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                        <AlertTitle>Redemption</AlertTitle>
                                        <AlertDescription>{redeemMessage}</AlertDescription>
                                    </Alert>
                                )}

                                {/* Action Buttons */}
                                <div className="space-y-2">
                                    {passResult.status === 'active' && passResult.type === 'single_use' && (
                                        <Button
                                            className="w-full"
                                            onClick={handleRedeem}
                                            disabled={isRedeeming || !isOnline}
                                        >
                                            {isRedeeming ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Redeeming...
                                                </>
                                            ) : (
                                                'Redeem Coupon'
                                            )}
                                        </Button>
                                    )}

                                    {passResult.status === 'active' && passResult.type === 'multi_use' && (
                                        <Button
                                            className="w-full"
                                            onClick={handleRedeem}
                                            disabled={isRedeeming || !isOnline}
                                        >
                                            {isRedeeming ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Logging...
                                                </>
                                            ) : (
                                                'Log Visit'
                                            )}
                                        </Button>
                                    )}

                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={resetScanner}
                                    >
                                        <RotateCcw className="mr-2 h-4 w-4" />
                                        Scan Another Pass
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Error State */}
                    {scanState === 'error' && errorMessage && (
                        <Card>
                            <CardContent className="space-y-4 pt-6">
                                <Alert variant="destructive">
                                    <XCircle className="h-4 w-4" />
                                    <AlertTitle>Error</AlertTitle>
                                    <AlertDescription>{errorMessage}</AlertDescription>
                                </Alert>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={resetScanner}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Try Again
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
