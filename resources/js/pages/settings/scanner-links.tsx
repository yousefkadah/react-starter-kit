import { Head, useForm, usePage, router } from '@inertiajs/react';
import { Copy, Plus, QrCode, ToggleLeft, ToggleRight, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { formatDistance } from 'date-fns';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

type ScannerLink = {
    id: number;
    name: string;
    token: string;
    is_active: boolean;
    last_used_at: string | null;
    created_at: string;
    scan_count: number;
};

type Props = {
    scannerLinks: ScannerLink[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Scanner Links',
        href: '/settings/scanner-links',
    },
];

export default function ScannerLinks({ scannerLinks }: Props) {
    const { flash } = usePage<{
        flash?: { success?: string };
    }>().props;
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [copiedId, setCopiedId] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/settings/scanner-links', {
            onSuccess: () => {
                setShowCreateDialog(false);
                createForm.reset();
            },
        });
    };

    const handleToggle = (link: ScannerLink) => {
        router.patch(`/settings/scanner-links/${link.id}`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (linkId: number) => {
        if (
            confirm(
                'Are you sure you want to delete this scanner link? This action cannot be undone.',
            )
        ) {
            router.delete(`/settings/scanner-links/${linkId}`, {
                preserveScroll: true,
            });
        }
    };

    const copyUrl = (token: string, linkId: number) => {
        const baseUrl = typeof window !== 'undefined' ? window.location.origin : '';
        navigator.clipboard.writeText(`${baseUrl}/scanner/${token}`);
        setCopiedId(linkId);
        setTimeout(() => setCopiedId(null), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scanner Links" />

            <h1 className="sr-only">Scanner Links</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Scanner Links"
                            description="Generate unique URLs for scanning and validating passes at different locations"
                        />
                        <Button onClick={() => setShowCreateDialog(true)}>
                            <Plus className="h-4 w-4" />
                            Create Link
                        </Button>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Scanner Links</CardTitle>
                            <CardDescription>
                                Each link provides a unique scanner URL that can be used at a point of sale or event entrance
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {scannerLinks.length === 0 ? (
                                <div className="py-8 text-center text-muted-foreground">
                                    <QrCode className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                    <p>No scanner links yet. Create one to start scanning passes.</p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Scans</TableHead>
                                            <TableHead>Last Used</TableHead>
                                            <TableHead>Created</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {scannerLinks.map((link) => (
                                            <TableRow key={link.id}>
                                                <TableCell className="font-medium">
                                                    {link.name}
                                                </TableCell>
                                                <TableCell>
                                                    {link.is_active ? (
                                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            Active
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {link.scan_count}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {link.last_used_at
                                                        ? formatDistance(
                                                              new Date(link.last_used_at),
                                                              new Date(),
                                                              { addSuffix: true },
                                                          )
                                                        : 'Never'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {formatDistance(
                                                        new Date(link.created_at),
                                                        new Date(),
                                                        { addSuffix: true },
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => copyUrl(link.token, link.id)}
                                                            title="Copy scanner URL"
                                                        >
                                                            {copiedId === link.id ? (
                                                                <span className="text-xs">Copied!</span>
                                                            ) : (
                                                                <Copy className="h-4 w-4" />
                                                            )}
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleToggle(link)}
                                                            title={link.is_active ? 'Deactivate' : 'Activate'}
                                                        >
                                                            {link.is_active ? (
                                                                <ToggleRight className="h-4 w-4 text-green-600" />
                                                            ) : (
                                                                <ToggleLeft className="h-4 w-4 text-muted-foreground" />
                                                            )}
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(link.id)}
                                                            title="Delete scanner link"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>How Scanner Links Work</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Scanner links provide unique URLs that open a web-based pass scanner. Share these with your
                                staff at different locations.
                            </p>
                            <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                                <li>Create a scanner link for each location or device.</li>
                                <li>Copy the URL and open it on the scanning device's browser.</li>
                                <li>Staff can scan customer passes using the device camera or enter codes manually.</li>
                                <li>Single-use coupons are automatically redeemed. Loyalty passes log visits.</li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>

            <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                <DialogContent>
                    <form onSubmit={handleCreate}>
                        <DialogHeader>
                            <DialogTitle>Create Scanner Link</DialogTitle>
                            <DialogDescription>
                                Enter a name to identify where this scanner will be used
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Scanner Name</Label>
                                <Input
                                    id="name"
                                    placeholder="Main Entrance, Store #42, Event Booth"
                                    value={createForm.data.name}
                                    onChange={(e) =>
                                        createForm.setData('name', e.target.value)
                                    }
                                    autoFocus
                                />
                                {createForm.errors.name && (
                                    <p className="text-sm text-destructive">
                                        {createForm.errors.name}
                                    </p>
                                )}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowCreateDialog(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                Create Link
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
