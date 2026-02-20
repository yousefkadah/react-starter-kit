import React, { useRef, useEffect } from 'react';
import QRCode from 'qrcode';

interface QRCodeDisplayProps {
    url: string;
    width?: number;
    height?: number;
    errorLevel?: 'L' | 'M' | 'Q' | 'H';
    downloadable?: boolean;
    colors?: {
        dark?: string;
        light?: string;
    };
}

export default function QRCodeDisplay({
    url,
    width = 200,
    height = 200,
    errorLevel = 'M',
    downloadable = false,
    colors = { dark: '#000000', light: '#FFFFFF' },
}: QRCodeDisplayProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        if (canvasRef.current) {
            QRCode.toCanvas(
                canvasRef.current,
                url,
                {
                    errorCorrectionLevel: errorLevel,
                    type: 'image/png',
                    quality: 0.95,
                    margin: 1,
                    color: {
                        dark: colors.dark,
                        light: colors.light,
                    },
                    width,
                },
                (error) => {
                    if (error)
                        console.error('QR Code generation error:', error);
                },
            );
        }
    }, [url, width, errorLevel, colors]);

    const handleDownload = () => {
        if (canvasRef.current) {
            const link = document.createElement('a');
            link.href = canvasRef.current.toDataURL('image/png');
            link.download = `pass-qr-code-${Date.now()}.png`;
            link.click();
        }
    };

    return (
        <div className="flex flex-col items-center space-y-4">
            <canvas ref={canvasRef} width={width} height={height} />
            {downloadable && (
                <button
                    onClick={handleDownload}
                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Download QR Code
                </button>
            )}
        </div>
    );
}
