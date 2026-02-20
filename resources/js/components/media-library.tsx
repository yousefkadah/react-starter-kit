import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { SamplePicker } from '@/components/sample-picker';
import type { PassImageSlot } from '@/types/pass';
import type { MediaLibraryAsset } from '@/types/sample';
import {
    deleteMediaAsset,
    listMediaAssets,
    uploadMediaAsset,
} from '@/lib/samples';

interface MediaLibraryProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    slot?: PassImageSlot;
    onSelect: (asset: MediaLibraryAsset) => void;
}

export function MediaLibrary({
    open,
    onOpenChange,
    slot,
    onSelect,
}: MediaLibraryProps) {
    const [assets, setAssets] = useState<MediaLibraryAsset[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const refreshAssets = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await listMediaAssets({
                source: 'all',
                slot,
            });
            const items = Array.isArray(response) ? response : response.data;
            setAssets(items ?? []);
        } catch (err) {
            console.error(err);
            setError('Unable to load assets.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open) {
            refreshAssets();
        }
    }, [open, slot]);

    const handleUpload = async (file: File) => {
        try {
            setUploading(true);
            await uploadMediaAsset(file, slot);
            await refreshAssets();
        } catch (err) {
            console.error(err);
            setError('Upload failed.');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = async (asset: MediaLibraryAsset) => {
        try {
            await deleteMediaAsset(asset.id);
            await refreshAssets();
        } catch (err) {
            console.error(err);
            setError('Unable to delete asset.');
        }
    };

    return (
        <SamplePicker
            open={open}
            onOpenChange={onOpenChange}
            title="Media Library"
            description="Choose or upload an image for this slot."
            footer={
                <label className="inline-flex">
                    <input
                        type="file"
                        accept="image/png"
                        className="hidden"
                        disabled={uploading}
                        onChange={(event) => {
                            const file = event.target.files?.[0];
                            if (file) {
                                handleUpload(file);
                            }
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={uploading}
                    >
                        {uploading ? 'Uploading…' : 'Upload image'}
                    </Button>
                </label>
            }
        >
            <div className="space-y-3">
                {error && <p className="text-sm text-destructive">{error}</p>}
                {loading ? (
                    <p className="text-sm text-muted-foreground">
                        Loading assets…
                    </p>
                ) : assets.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No assets yet. Upload one to get started.
                    </p>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-3">
                        {assets.map((asset) => (
                            <div
                                key={asset.id}
                                className="rounded-lg border p-2"
                            >
                                <button
                                    type="button"
                                    className="w-full"
                                    onClick={() => onSelect(asset)}
                                >
                                    <img
                                        src={asset.url}
                                        alt="Library asset"
                                        className="h-24 w-full rounded-md object-cover"
                                    />
                                </button>
                                {asset.source === 'user' && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="mt-2 w-full"
                                        onClick={() => handleDelete(asset)}
                                    >
                                        Delete
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </SamplePicker>
    );
}
