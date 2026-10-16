import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/i18n/ca';

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    description?: string;
    confirmLabel?: string;
    pending?: boolean;
    destructive?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

/**
 * Confirmació explícita abans d'una acció irreversible.
 */
export function ConfirmDialog({ open, title, description, confirmLabel, pending = false, destructive = true, onConfirm, onCancel }: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={(value) => !value && onCancel()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description !== undefined && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                    <Button variant={destructive ? 'destructive' : 'default'} onClick={onConfirm} disabled={pending}>
                        {confirmLabel ?? t('common.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
