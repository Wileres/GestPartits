import { Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { Me, User } from '@/api/types';
import { PageHeader } from '@/components/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, t } from '@/i18n/ca';
import { DeleteUserDialog } from './DeleteUserDialog';
import { UserFormDialog } from './UserFormDialog';
import { useUsers } from './useUsers';

export function UsersPage({ me }: { me: Me }) {
    const { data: users, isPending, isError } = useUsers();
    const [editing, setEditing] = useState<User | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<User | null>(null);

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (user: User) => {
        setEditing(user);
        setFormOpen(true);
    };

    return (
        <>
            <PageHeader
                title={t('users.title')}
                description={t('users.subtitle')}
                actions={
                    <Button onClick={openCreate}>
                        <Plus aria-hidden />
                        {t('users.create')}
                    </Button>
                }
            />

            {isError && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{t('common.error')}</AlertDescription>
                </Alert>
            )}

            {isPending ? (
                <div className="flex justify-center py-10" role="status">
                    <Loader2 className="size-6 animate-spin text-primary" aria-hidden />
                    <span className="sr-only">{t('app.loading')}</span>
                </div>
            ) : (
                <Card className="overflow-x-auto py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('users.name')}</TableHead>
                                <TableHead>{t('users.username')}</TableHead>
                                <TableHead>{t('users.role')}</TableHead>
                                <TableHead>{t('users.status')}</TableHead>
                                <TableHead className="hidden md:table-cell">{t('users.lastLogin')}</TableHead>
                                <TableHead className="text-right">{t('users.actions')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users?.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">
                                        {user.name}
                                        {user.id === me.id && (
                                            <Badge variant="secondary" className="ml-2">
                                                {t('users.you')}
                                            </Badge>
                                        )}
                                        {user.email !== null && <span className="block text-xs text-muted-foreground">{user.email}</span>}
                                    </TableCell>
                                    <TableCell>{user.username}</TableCell>
                                    <TableCell>{user.role_label}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge variant={user.is_active ? 'default' : 'outline'}>{user.is_active ? t('users.active') : t('users.inactive')}</Badge>
                                            {user.must_change_password && <Badge variant="outline">{t('users.mustChange')}</Badge>}
                                        </div>
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">{formatDateTime(user.last_login_at)}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" onClick={() => openEdit(user)} aria-label={`${t('users.edit')}: ${user.name}`}>
                                                <Pencil aria-hidden />
                                            </Button>
                                            {user.id !== me.id && (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleting(user)} aria-label={`${t('users.delete')}: ${user.name}`}>
                                                    <Trash2 className="text-destructive" aria-hidden />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <UserFormDialog open={formOpen} onOpenChange={setFormOpen} user={editing} currentUserId={me.id} />
            <DeleteUserDialog user={deleting} onOpenChange={(open) => !open && setDeleting(null)} />
        </>
    );
}
