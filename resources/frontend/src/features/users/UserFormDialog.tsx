import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { RoleCode, User } from '@/api/types';
import { FormField } from '@/components/FormField';
import { PasswordInput } from '@/components/PasswordInput';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { applyServerErrors } from '@/lib/forms';
import { useCreateUser, useUpdateUser } from './useUsers';

const roles: RoleCode[] = ['manager', 'admin'];

function buildSchema(isNew: boolean) {
    return z.object({
        username: z.string().trim().min(3, t('common.minLength', { min: 3 })),
        name: z.string().trim().min(1, t('common.required')),
        email: z.union([z.literal(''), z.email(t('common.invalidEmail'))]),
        role: z.enum(['admin', 'manager']),
        is_active: z.boolean(),
        password: isNew ? z.string().min(12, t('common.minLength', { min: 12 })) : z.union([z.literal(''), z.string().min(12, t('common.minLength', { min: 12 }))]),
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

const fields = ['username', 'name', 'email', 'role', 'is_active', 'password'] as const;

interface UserFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** null = usuari nou. */
    user: User | null;
    currentUserId: number;
}

export function UserFormDialog({ open, onOpenChange, user, currentUserId }: UserFormDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{user === null ? t('users.create') : t('users.edit')}</DialogTitle>
                    {user !== null && <DialogDescription>{user.username}</DialogDescription>}
                </DialogHeader>
                {/* El formulari es munta cada cop que s'obre el diàleg: sempre comença amb les dades actuals. */}
                {open && <UserForm user={user} currentUserId={currentUserId} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

interface UserFormProps {
    user: User | null;
    currentUserId: number;
    onDone: () => void;
}

function UserForm({ user, currentUserId, onDone }: UserFormProps) {
    const isNew = user === null;
    const createUser = useCreateUser();
    const updateUser = useUpdateUser();
    const [formError, setFormError] = useState<string | null>(null);

    const {
        register,
        control,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(buildSchema(isNew)),
        defaultValues: {
            username: user?.username ?? '',
            name: user?.name ?? '',
            email: user?.email ?? '',
            role: user?.role ?? 'manager',
            is_active: user?.is_active ?? true,
            password: '',
        },
    });

    const pending = createUser.isPending || updateUser.isPending;

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        const onError = (error: unknown) => setFormError(applyServerErrors(error, setError, fields));
        const email = values.email === '' ? null : values.email;

        if (isNew) {
            createUser.mutate(
                { username: values.username, name: values.name, email, role: values.role, password: values.password },
                {
                    onSuccess: () => {
                        toast.success(t('users.created'));
                        onDone();
                    },
                    onError,
                },
            );

            return;
        }

        updateUser.mutate(
            {
                id: user.id,
                input: {
                    username: values.username,
                    name: values.name,
                    email,
                    role: values.role,
                    is_active: values.is_active,
                    ...(values.password !== '' ? { password: values.password } : {}),
                },
            },
            {
                onSuccess: () => {
                    toast.success(t('users.updated'));
                    onDone();
                },
                onError,
            },
        );
    });

    const passwordHelp = isNew ? t('users.temporaryPasswordHelp') : t('users.newTemporaryPasswordHelp');
    const isSelf = user?.id === currentUserId;

    return (
        <form onSubmit={onSubmit} noValidate className="grid gap-4">
            {formError !== null && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{formError}</AlertDescription>
                </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="user-username" label={t('users.username')} error={errors.username?.message}>
                    <Input
                        id="user-username"
                        autoComplete="off"
                        aria-invalid={errors.username !== undefined}
                        aria-describedby={describedBy('user-username', errors.username?.message)}
                        {...register('username')}
                    />
                </FormField>
                <FormField id="user-name" label={t('users.name')} error={errors.name?.message}>
                    <Input
                        id="user-name"
                        aria-invalid={errors.name !== undefined}
                        aria-describedby={describedBy('user-name', errors.name?.message)}
                        {...register('name')}
                    />
                </FormField>
            </div>
            <FormField id="user-email" label={t('users.email')} error={errors.email?.message} help={t('users.emailHelp')}>
                <Input
                    id="user-email"
                    type="email"
                    aria-invalid={errors.email !== undefined}
                    aria-describedby={describedBy('user-email', errors.email?.message, t('users.emailHelp'))}
                    {...register('email')}
                />
            </FormField>
            <FormField id="user-role" label={t('users.role')} error={errors.role?.message}>
                <Controller
                    control={control}
                    name="role"
                    render={({ field }) => (
                        <Select value={field.value} onValueChange={field.onChange}>
                            <SelectTrigger id="user-role" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {t(`users.roles.${role}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                />
            </FormField>
            {!isNew && !isSelf && (
                <div className="flex items-center justify-between rounded-lg border p-3">
                    <Label htmlFor="user-active">{t('users.active')}</Label>
                    <Controller
                        control={control}
                        name="is_active"
                        render={({ field }) => <Switch id="user-active" checked={field.value} onCheckedChange={field.onChange} />}
                    />
                </div>
            )}
            <FormField id="user-password" label={isNew ? t('users.temporaryPassword') : t('users.newTemporaryPassword')} error={errors.password?.message} help={passwordHelp}>
                <PasswordInput
                    id="user-password"
                    autoComplete="new-password"
                    aria-invalid={errors.password !== undefined}
                    aria-describedby={describedBy('user-password', errors.password?.message, passwordHelp)}
                    {...register('password')}
                />
            </FormField>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={pending}>
                    {pending ? t('common.saving') : t('common.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
