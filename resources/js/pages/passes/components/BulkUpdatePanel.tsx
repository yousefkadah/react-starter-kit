import { useEffect, useMemo, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import type { PassData } from '@/types/pass';

interface BulkUpdateStatus {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    total_count: number;
    processed_count: number;
    failed_count: number;
}

interface BulkUpdatePanelProps {
    passTemplateId: number | null;
    passData: PassData;
}

export default function BulkUpdatePanel({ passTemplateId, passData }: BulkUpdatePanelProps) {
    const [fieldKey, setFieldKey] = useState('');
    const [fieldValue, setFieldValue] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [bulkUpdateId, setBulkUpdateId] = useState<number | null>(null);
    const [status, setStatus] = useState<BulkUpdateStatus | null>(null);

    const fieldKeys = useMemo(() => {
        const keys = new Set<string>();

        [
            ...(passData.headerFields ?? []),
            ...(passData.primaryFields ?? []),
            ...(passData.secondaryFields ?? []),
            ...(passData.auxiliaryFields ?? []),
            ...(passData.backFields ?? []),
        ].forEach((field) => {
            if (field.key) {
                keys.add(field.key);
            }
        });

        return Array.from(keys);
    }, [passData]);

    useEffect(() => {
        if (bulkUpdateId === null) {
            return;
        }

        const interval = setInterval(async () => {
            const response = await fetch(`/passes/bulk-update/${bulkUpdateId}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            setStatus(data.data as BulkUpdateStatus);

            if (['completed', 'failed', 'cancelled'].includes(data.data.status)) {
                clearInterval(interval);
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [bulkUpdateId]);

    const submit = async () => {
        setError(null);

        if (passTemplateId === null) {
            setError('Pass template is required for bulk updates.');
            return;
        }

        if (!fieldKey || fieldValue === '') {
            setError('Field key and value are required.');
            return;
        }

        setProcessing(true);

        try {
            const response = await fetch('/passes/bulk-update', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                body: JSON.stringify({
                    pass_template_id: passTemplateId,
                    field_key: fieldKey,
                    field_value: fieldValue,
                }),
            });

            const data = await response.json();

            if (response.status === 409) {
                setError(data.message ?? 'A bulk update is already in progress.');
                return;
            }

            if (!response.ok) {
                setError(data.message ?? 'Failed to start bulk update.');
                return;
            }

            setBulkUpdateId(data.data.id as number);
            setStatus(data.data as BulkUpdateStatus);
            setFieldValue('');
        } catch {
            setError('Failed to start bulk update.');
        } finally {
            setProcessing(false);
        }
    };

    const progress = status && status.total_count > 0
        ? Math.round(((status.processed_count + status.failed_count) / status.total_count) * 100)
        : 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Bulk Update</CardTitle>
                <CardDescription>Update the same field across passes for this template.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>Bulk update failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="bulk-field-key">Field key</Label>
                        <select
                            id="bulk-field-key"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            value={fieldKey}
                            onChange={(event) => setFieldKey(event.target.value)}
                            disabled={processing}
                        >
                            <option value="">Select field</option>
                            {fieldKeys.map((key) => (
                                <option key={key} value={key}>
                                    {key}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="bulk-field-value">New value</Label>
                        <Input
                            id="bulk-field-value"
                            value={fieldValue}
                            onChange={(event) => setFieldValue(event.target.value)}
                            disabled={processing}
                        />
                    </div>
                </div>

                <Button onClick={submit} disabled={processing || !fieldKey || passTemplateId === null}>
                    {processing ? 'Starting...' : 'Start Bulk Update'}
                </Button>

                {status && (
                    <div className="space-y-2 rounded-md border p-3">
                        <div className="flex items-center justify-between text-sm">
                            <span>Status: {status.status}</span>
                            <span>
                                {status.processed_count + status.failed_count}/{status.total_count}
                            </span>
                        </div>
                        <Progress value={progress} />
                        <p className="text-xs text-muted-foreground">
                            Processed: {status.processed_count} • Failed: {status.failed_count}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
