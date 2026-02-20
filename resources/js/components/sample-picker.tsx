import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface SamplePickerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
}

export function SamplePicker({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
}: SamplePickerProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                {children}
                {footer ? <DialogFooter>{footer}</DialogFooter> : null}
            </DialogContent>
        </Dialog>
    );
}

interface SampleOverwriteDialogProps {
    open: boolean;
    message: string;
    onCancel: () => void;
    onConfirm: () => void;
}

export function SampleOverwriteDialog({
    open,
    message,
    onCancel,
    onConfirm,
}: SampleOverwriteDialogProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => (nextOpen ? null : onCancel())}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Replace current edits?</DialogTitle>
                    <DialogDescription>{message}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Keep my edits
                    </Button>
                    <Button type="button" onClick={onConfirm}>
                        Replace with sample
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
