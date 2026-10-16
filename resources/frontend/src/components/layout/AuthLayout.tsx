import type { ReactNode } from 'react';
import { ClubLogo } from '@/components/ClubLogo';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/i18n/ca';

interface AuthLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

/**
 * Pantalles sense sessió (inici de sessió, recuperació de contrasenya).
 */
export function AuthLayout({ title, description, children }: AuthLayoutProps) {
    return (
        <main className="flex min-h-svh items-center justify-center bg-gradient-to-b from-primary to-[#082f66] px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-6 flex items-center justify-center gap-3 text-white">
                    <ClubLogo className="size-12" />
                    <div>
                        <p className="text-lg leading-tight font-bold">{t('app.name')}</p>
                        <p className="text-sm text-white/80">{t('app.club')}</p>
                    </div>
                </div>
                <Card className="shadow-xl">
                    <CardHeader>
                        <CardTitle className="text-xl">
                            <h1>{title}</h1>
                        </CardTitle>
                        {description !== undefined && <CardDescription>{description}</CardDescription>}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>
            </div>
        </main>
    );
}
