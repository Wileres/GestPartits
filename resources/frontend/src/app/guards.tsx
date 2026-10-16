import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router';
import type { Me } from '@/api/types';
import { FullPageSpinner } from '@/components/FullPageSpinner';
import { ForbiddenPage } from '@/features/errors/ForbiddenPage';
import { useMe } from '@/hooks/useAuth';

export const CHANGE_PASSWORD_PATH = '/canvia-contrasenya';

/**
 * Exigeix sessió. Mentre l'usuari tingui una contrasenya temporal, només pot anar a canviar-la.
 */
export function RequireAuth({ children }: { children: (me: Me) => ReactNode }) {
    const { data: me, isPending } = useMe();
    const location = useLocation();

    if (isPending) {
        return <FullPageSpinner />;
    }

    if (me == null) {
        return <Navigate to="/inicia-sessio" replace state={{ from: location.pathname }} />;
    }

    if (me.must_change_password && location.pathname !== CHANGE_PASSWORD_PATH) {
        return <Navigate to={CHANGE_PASSWORD_PATH} replace />;
    }

    return <>{children(me)}</>;
}

/**
 * Pàgines només per a usuaris sense sessió.
 */
export function GuestOnly({ children }: { children: ReactNode }) {
    const { data: me, isPending } = useMe();

    if (isPending) {
        return <FullPageSpinner />;
    }

    if (me != null) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}

/**
 * La SPA amaga el que l'usuari no pot fer; el servidor ho torna a comprovar sempre.
 */
export function RequirePermission({ allowed, children }: { allowed: boolean; children: ReactNode }) {
    return allowed ? <>{children}</> : <ForbiddenPage />;
}
