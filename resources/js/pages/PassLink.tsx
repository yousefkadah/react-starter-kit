import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import QRCodeDisplay from '@/components/QRCodeDisplay';

interface PassLinkProps {
    pass: {
        id: number;
        serial_number: string;
        pass_data?: Record<string, any>;
        images?: Record<string, string>;
        [key: string]: any;
    };
    device: 'ios' | 'android' | 'unknown';
    link_status: 'active' | 'expired';
    publicUrl: string;
    add_to_wallet_url?: string | null;
    qr_code_data: {
        text: string;
        width: number;
        height: number;
    };
}

export default function PassLink({
    pass,
    device,
    link_status,
    publicUrl,
    add_to_wallet_url,
    qr_code_data,
}: PassLinkProps) {
    const [copied, setCopied] = useState(false);

    const handleCopyUrl = () => {
        navigator.clipboard.writeText(window.location.href);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const getDeviceButtonLabel = () => {
        switch (device) {
            case 'ios':
                return 'Add to Apple Wallet';
            case 'android':
                return 'Add to Google Pay';
            default:
                return 'Add to Mobile Wallet';
        }
    };

    const getDeviceButtonColor = () => {
        switch (device) {
            case 'ios':
                return 'black';
            case 'android':
                return 'black';
            default:
                return 'gray';
        }
    };

    return (
        <>
            <Head title="View Pass" />

            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-md space-y-6 rounded-lg bg-white p-6 shadow-lg">
                    {/* Alert for expired pass */}
                    {link_status === 'expired' && (
                        <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                            <p className="text-sm font-medium text-yellow-800">
                                ⚠️ This pass has expired and is no longer valid
                                for enrollment.
                            </p>
                        </div>
                    )}

                    {/* Pass Info */}
                    <div className="space-y-2">
                        <h1 className="text-2xl font-bold text-gray-900">
                            {pass?.pass_data?.description || 'Your Pass'}
                        </h1>
                        <p className="text-sm text-gray-500">
                            Serial: {pass?.serial_number}
                        </p>
                    </div>

                    {/* QR Code Display */}
                    {qr_code_data && (
                        <div className="flex flex-col items-center space-y-4 rounded-lg bg-gray-50 p-6">
                            <QRCodeDisplay
                                url={qr_code_data.text}
                                width={qr_code_data.width}
                                height={qr_code_data.height}
                                downloadable={true}
                            />
                            <button
                                onClick={handleCopyUrl}
                                className="text-sm font-medium text-blue-600 hover:text-blue-700"
                            >
                                {copied ? '✓ Copied!' : 'Copy Link'}
                            </button>
                        </div>
                    )}

                    {/* Share URL */}
                    {publicUrl && (
                        <div className="rounded-lg bg-gray-50 p-4">
                            <p className="mb-2 text-xs font-semibold text-gray-600">
                                Share Link:
                            </p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 overflow-x-auto rounded border border-gray-200 bg-white px-3 py-2 text-xs">
                                    {publicUrl}
                                </code>
                                <button
                                    onClick={handleCopyUrl}
                                    className="font-medium text-blue-600 hover:text-blue-700"
                                    title="Copy to clipboard"
                                >
                                    📋
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Add to Wallet Button */}
                    {link_status !== 'expired' && (
                        <div className="space-y-3">
                            {/* Primary button for detected device */}
                            {add_to_wallet_url && (
                                <a
                                    href={add_to_wallet_url}
                                    className={`block w-full rounded-lg px-4 py-3 text-center font-semibold text-white transition-colors ${
                                        getDeviceButtonColor() === 'black'
                                            ? 'bg-black hover:bg-gray-900'
                                            : 'bg-gray-600 hover:bg-gray-700'
                                    }`}
                                >
                                    {getDeviceButtonLabel()}
                                </a>
                            )}

                            {/* Fallback or secondary options for unknown device */}
                            {!add_to_wallet_url && device === 'unknown' && (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-gray-600">
                                        Add to your wallet:
                                    </p>
                                    <div className="grid grid-cols-2 gap-2">
                                        <button className="rounded-lg bg-black px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">
                                            Apple Wallet
                                        </button>
                                        <button className="rounded-lg bg-black px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">
                                            Google Pay
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Alternative sharing methods */}
                    <div className="space-y-3 border-t pt-4">
                        <p className="text-center text-xs text-gray-500">
                            Can't auto-detect your device? Download the pass
                            file manually or share the QR code above.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
