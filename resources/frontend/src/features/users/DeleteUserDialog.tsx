import { toast } from 'sonner';
import { ApiError } from '@/api/client';
import type { User } from '@/api/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/i18n/ca';
import { useDeleteUser } from './useUsers';

interface DeleteUserDialogProps {
    user: User | null;
    onOpenChange: (open: boolean) => void;
}

export function DeleteUserDialog({ user, onOpenChange }: DeleteUserDialogProps) {
    const deleteUser = useDeleteUser();

    const confirm = () => {
        if (user === null) return;

        deleteUser.mutate(user.id, {
            onSuccess: () => {
                toast.success(t('users.deleted'));
                onOpenChange(false);
            },
            onError: (error) => {
                const message = error instanceof ApiError ? (Object.values(error.errors)[0]?.[0] ?? error.message) : '';
                toast.error(message !== '' ? message : t('common.error'));
            },
        });
    };

    return (
        <Dialog open={user !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('users.deleteConfirmTitle', { name: user?.name ?? '' })}</DialogTitle>
                    <DialogDescription>{t('users.deleteConfirmHelp')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common.cancel')}
                    </Button>
                    <Button variant="destructive" onClick={confirm} disabled={deleteUser.isPending}>
                        {t('users.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
