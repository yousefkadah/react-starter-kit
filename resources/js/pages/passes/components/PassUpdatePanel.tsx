import { useMemo, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PassData } from '@/types/pass';

interface PassUpdatePanelProps {
    passId: number;
    passData: PassData;
    isVoided: boolean;
    onUpdated: () => void;
}

export default function PassUpdatePanel({ passId, passData, isVoided, onUpdated }: PassUpdatePanelProps) {
    const [fieldKey, setFieldKey] = useState('');
    const [fieldValue, setFieldValue] = useState('');
    const [changeMessage, setChangeMessage] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [warning, setWarning] = useState<string | null>(null);

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

    const submit = async () => {
        setError(null);
        setWarning(null);

        if (!fieldKey || fieldValue === '') {
            setError('Field key and value are required.');
            return;
        }

        setProcessing(true);

        try {
            const body: {
                fields: Record<string, string>;
                change_messages?: Record<string, string>;
            } = {
                fields: {
                    [fieldKey]: fieldValue,
                },
            };

            if (changeMessage.trim() !== '') {
                body.change_messages = {
                    [fieldKey]: changeMessage,
                };
            }

            const response = await fetch(`/passes/${passId}/update`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();

            if (!response.ok) {
                setError(data.message ?? 'Failed to queue pass update.');
                return;
            }

            if (typeof data.warning === 'string' && data.warning !== '') {
                setWarning(data.warning);
            }

            setFieldValue('');
            setChangeMessage('');
            onUpdated();
        } catch {
            setError('Failed to queue pass update.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Pass Update</CardTitle>
                <CardDescription>Update a single field and trigger wallet delivery.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {isVoided && (
                    <Alert variant="destructive">
                        <AlertTitle>Pass is voided</AlertTitle>
                        <AlertDescription>Voided passes cannot be updated.</AlertDescription>
                    </Alert>
                )}

                {warning && (
                    <Alert>
                        <AlertTitle>No registered devices</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                )}

                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>Update failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="field-key">Field key</Label>
                        <select
                            id="field-key"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            value={fieldKey}
                            onChange={(event) => setFieldKey(event.target.value)}
                            disabled={processing || isVoided}
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
                        <Label htmlFor="field-value">New value</Label>
                        <Input
                            id="field-value"
                            value={fieldValue}
                            onChange={(event) => setFieldValue(event.target.value)}
                            disabled={processing || isVoided}
                        />
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="change-message">Change message (optional)</Label>
                    <Input
                        id="change-message"
                        value={changeMessage}
                        onChange={(event) => setChangeMessage(event.target.value)}
                        placeholder="You now have %@ points!"
                        disabled={processing || isVoided}
                    />
                </div>

                <Button onClick={submit} disabled={processing || isVoided || !fieldKey}>
                    {processing ? 'Updating...' : 'Update Pass'}
                </Button>
            </CardContent>
        </Card>
    );
}
