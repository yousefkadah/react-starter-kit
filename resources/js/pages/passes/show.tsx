import { Head, Link, router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Apple,
    ArrowLeft,
    Chrome,
    Download,
    Edit,
    ExternalLink,
    QrCode,
    Share2,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { PassPreview } from '@/components/pass-preview';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import BulkUpdatePanel from '@/pages/passes/components/BulkUpdatePanel';
import DeliveryStatusBadge from '@/pages/passes/components/DeliveryStatusBadge';
import PassUpdatePanel from '@/pages/passes/components/PassUpdatePanel';
import passes from '@/routes/passes';
import type { Pass, PassStatus } from '@/types/pass';

interface PassUpdateHistoryItem {
    id: number;
    fields_changed: Record<string, { old: string | null; new: string | null }>;
    apple_delivery_status: 'pending' | 'sent' | 'delivered' | 'failed' | 'skipped' | null;
    google_delivery_status: 'pending' | 'sent' | 'delivered' | 'failed' | 'skipped' | null;
    created_at: string;
}

interface ScanEventItem {
    id: number;
    scanner_link_id: number | null;
    action: string;
    result: string;
    ip_address: string | null;
    created_at: string;
    scanner_link?: {
        id: number;
        name: string;
    } | null;
}

interface PassesShowProps {
    pass: Pass;
    recentPassUpdates: PassUpdateHistoryItem[];
    recentScanEvents: ScanEventItem[];
}

export default function PassesShow({ pass, recentPassUpdates, recentScanEvents }: PassesShowProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [googleLink, setGoogleLink] = useState(pass.google_save_url);
    const [passUpdates, setPassUpdates] = useState<PassUpdateHistoryItem[]>(recentPassUpdates);
    const { post: postDownload, processing: downloading } = useForm();
    const { post: postGenerate, processing: generating } = useForm();
    const { delete: deletePass, processing: deleting } = useForm();

    const refreshPassUpdates = async () => {
        const response = await fetch(`/passes/${pass.id}/updates`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        setPassUpdates(payload.data ?? []);
    };

    const handleDownloadApple = () => {
        postDownload(passes.download.apple({ pass: pass.id }).url, {
            onSuccess: () => {
                // Download handled by response
            },
        });
    };

    const handleGenerateGoogleLink = () => {
        postGenerate(passes.generate.google({ pass: pass.id }).url, {
            onSuccess: (page) => {
                if (page.props.googleSaveUrl) {
                    setGoogleLink(page.props.googleSaveUrl as string);
                }
            },
        });
    };

    const handleDelete = () => {
        deletePass(passes.destroy({ pass: pass.id }).url, {
            onSuccess: () => {
                router.visit(passes.index().url);
            },
        });
    };

    const getStatusBadge = (status: PassStatus) => {
        const variants: Record<
            PassStatus,
            'default' | 'secondary' | 'destructive'
        > = {
            active: 'default',
            voided: 'destructive',
            expired: 'secondary',
        };

        return (
            <Badge variant={variants[status]}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    return (
        <AppLayout
            title="Pass Details"
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={passes.index().url}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h2 className="text-xl font-semibold">
                                Pass Details
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {pass.pass_data.description || 'Untitled Pass'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={passes.edit({ pass: pass.id }).url}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={`/passes/${pass.id}/distribution-links`}
                            >
                                <Share2 className="mr-2 h-4 w-4" />
                                Share
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => setDeleteDialogOpen(true)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={`Pass: ${pass.serial_number}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Left Column: Preview */}
                <div className="lg:col-span-1">
                    <Card className="sticky top-6">
                        <CardHeader>
                            <CardTitle>Preview</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PassPreview
                                passData={pass.pass_data}
                                barcodeData={pass.barcode_data}
                                platform={pass.platforms[0] || 'apple'}
                            />
                        </CardContent>
                    </Card>
                </div>

                {/* Right Column: Details & Actions */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Pass Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Pass Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label className="text-muted-foreground">
                                        Platforms
                                    </Label>
                                    <div className="mt-1 flex items-center gap-2">
                                        {pass.platforms.includes('apple') && (
                                            <div className="flex items-center gap-1">
                                                <Apple className="h-4 w-4" />
                                                <span>Apple Wallet</span>
                                            </div>
                                        )}
                                        {pass.platforms.length === 2 && (
                                            <span className="text-muted-foreground">
                                                +
                                            </span>
                                        )}
                                        {pass.platforms.includes('google') && (
                                            <div className="flex items-center gap-1">
                                                <Chrome className="h-4 w-4" />
                                                <span>Google Wallet</span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <Label className="text-muted-foreground">
                                        Status
                                    </Label>
                                    <div className="mt-1">
                                        {getStatusBadge(pass.status)}
                                    </div>
                                </div>

                                <div>
                                    <Label className="text-muted-foreground">
                                        Pass Type
                                    </Label>
                                    <p className="mt-1 capitalize">
                                        {pass.pass_type
                                            .replace(/([A-Z])/g, ' $1')
                                            .trim()}
                                    </p>
                                </div>

                                <div>
                                    <Label className="text-muted-foreground">
                                        Serial Number
                                    </Label>
                                    <code className="mt-1 block text-sm">
                                        {pass.serial_number}
                                    </code>
                                </div>

                                <div>
                                    <Label className="text-muted-foreground">
                                        Created
                                    </Label>
                                    <p className="mt-1 text-sm">
                                        {format(
                                            new Date(pass.created_at),
                                            'PPp',
                                        )}
                                    </p>
                                </div>

                                <div>
                                    <Label className="text-muted-foreground">
                                        Last Updated
                                    </Label>
                                    <p className="mt-1 text-sm">
                                        {format(
                                            new Date(pass.updated_at),
                                            'PPp',
                                        )}
                                    </p>
                                </div>

                                {pass.last_generated_at && (
                                    <div className="md:col-span-2">
                                        <Label className="text-muted-foreground">
                                            Last Generated
                                        </Label>
                                        <p className="mt-1 text-sm">
                                            {format(
                                                new Date(
                                                    pass.last_generated_at,
                                                ),
                                                'PPp',
                                            )}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Download/Distribution */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Download & Distribution</CardTitle>
                            <CardDescription>
                                Download the pass or generate distribution links
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {pass.platforms.includes('apple') && (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h4 className="font-medium">
                                                Apple Wallet Pass
                                            </h4>
                                            <p className="text-sm text-muted-foreground">
                                                Download .pkpass file to add to
                                                Apple Wallet
                                            </p>
                                        </div>
                                        <Button
                                            onClick={handleDownloadApple}
                                            disabled={downloading}
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            {downloading
                                                ? 'Downloading...'
                                                : 'Download'}
                                        </Button>
                                    </div>
                                    {pass.pkpass_path && (
                                        <p className="text-xs text-muted-foreground">
                                            File: {pass.pkpass_path}
                                        </p>
                                    )}
                                </div>
                            )}

                            {pass.platforms.includes('google') && (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h4 className="font-medium">
                                                Google Wallet Link
                                            </h4>
                                            <p className="text-sm text-muted-foreground">
                                                Generate a save link for Google
                                                Wallet
                                            </p>
                                        </div>
                                        <Button
                                            onClick={handleGenerateGoogleLink}
                                            disabled={generating}
                                        >
                                            {generating
                                                ? 'Generating...'
                                                : googleLink
                                                  ? 'Regenerate'
                                                  : 'Generate Link'}
                                        </Button>
                                    </div>
                                    {googleLink && (
                                        <div className="space-y-2">
                                            <Button
                                                asChild
                                                className="w-full"
                                                variant="outline"
                                            >
                                                <a
                                                    href={googleLink}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <ExternalLink className="mr-2 h-4 w-4" />
                                                    Add to Google Wallet
                                                </a>
                                            </Button>
                                            <p className="text-xs break-all text-muted-foreground">
                                                {googleLink}
                                            </p>
                                        </div>
                                    )}
                                    {pass.google_class_id && (
                                        <div className="space-y-1 text-xs text-muted-foreground">
                                            <p>
                                                Class ID: {pass.google_class_id}
                                            </p>
                                            <p>
                                                Object ID:{' '}
                                                {pass.google_object_id}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Public Sharing - Removed: Use the Share button above to manage distribution links */}

                    <PassUpdatePanel
                        passId={pass.id}
                        passData={pass.pass_data}
                        isVoided={pass.status === 'voided'}
                        onUpdated={() => {
                            void refreshPassUpdates();
                        }}
                    />

                    <BulkUpdatePanel
                        passTemplateId={pass.pass_template_id}
                        passData={pass.pass_data}
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Delivery Status</CardTitle>
                            <CardDescription>Recent update delivery results for this pass.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {passUpdates.length === 0 && (
                                    <p className="text-sm text-muted-foreground">No update history yet.</p>
                                )}

                                {passUpdates.map((update) => (
                                    <div key={update.id} className="rounded-md border p-3">
                                        <div className="mb-2 flex items-center justify-between text-sm">
                                            <span className="font-medium">Update #{update.id}</span>
                                            <span className="text-muted-foreground">
                                                {format(new Date(update.created_at), 'PPp')}
                                            </span>
                                        </div>
                                        <div className="mb-2 flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground">Apple</span>
                                            <DeliveryStatusBadge status={update.apple_delivery_status} />
                                            <span className="text-xs text-muted-foreground">Google</span>
                                            <DeliveryStatusBadge status={update.google_delivery_status} />
                                        </div>
                                        <pre className="overflow-auto rounded bg-muted p-2 text-xs">
                                            {JSON.stringify(update.fields_changed, null, 2)}
                                        </pre>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Scan Event History */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <QrCode className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <CardTitle>Scan History</CardTitle>
                                    <CardDescription>Recent scan and redemption events for this pass.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {recentScanEvents.length === 0 && (
                                    <p className="text-sm text-muted-foreground">No scan events yet.</p>
                                )}

                                {recentScanEvents.map((event) => (
                                    <div key={event.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <Badge variant={event.result === 'success' ? 'default' : 'secondary'}>
                                                    {event.action}
                                                </Badge>
                                                <Badge variant={event.result === 'success' ? 'default' : 'destructive'}>
                                                    {event.result}
                                                </Badge>
                                            </div>
                                            {event.scanner_link && (
                                                <p className="text-xs text-muted-foreground">
                                                    Scanner: {event.scanner_link.name}
                                                </p>
                                            )}
                                        </div>
                                        <div className="text-right text-sm text-muted-foreground">
                                            <p>{format(new Date(event.created_at), 'PPp')}</p>
                                            {event.ip_address && (
                                                <p className="text-xs">{event.ip_address}</p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Pass Data */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Technical Details</CardTitle>
                            <CardDescription>
                                Raw pass configuration
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <pre className="max-h-96 overflow-auto rounded-lg bg-muted p-4 text-xs">
                                {JSON.stringify(
                                    {
                                        pass_data: pass.pass_data,
                                        barcode_data: pass.barcode_data,
                                        images: Object.keys(pass.images || {}),
                                    },
                                    null,
                                    2,
                                )}
                            </pre>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Delete Confirmation Dialog */}
            <AlertDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete the pass "
                            {pass.pass_data.description || pass.serial_number}".
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            disabled={deleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleting ? 'Deleting...' : 'Delete Pass'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
