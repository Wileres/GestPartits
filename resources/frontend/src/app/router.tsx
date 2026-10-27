import { lazy, Suspense, type ComponentType, type ReactNode } from 'react';
import { createBrowserRouter, type RouteObject } from 'react-router';
import type { Me } from '@/api/types';
import { AppLayout } from '@/components/layout/AppLayout';
import { LoadingState } from '@/components/QueryState';
import { ForcedPasswordChangePage } from '@/features/auth/ForcedPasswordChangePage';
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage';
import { LoginPage } from '@/features/auth/LoginPage';
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage';
import { NotFoundPage } from '@/features/errors/NotFoundPage';
import { CHANGE_PASSWORD_PATH, GuestOnly, RequireAuth, RequirePermission } from './guards';

/** Pàgines carregades sota demanda (el paquet inicial només conté l'inici de sessió i el marc). */
function page<P extends object>(loader: () => Promise<Record<string, ComponentType<P>>>, name: string) {
    return lazy(async () => ({ default: (await loader())[name] as ComponentType<P> }));
}

const SchedulePage = page(() => import('@/features/schedule/SchedulePage'), 'SchedulePage');
const MatchesPage = page(() => import('@/features/matches/MatchesPage'), 'MatchesPage');
const TeamsPage = page(() => import('@/features/teams/TeamsPage'), 'TeamsPage');
const SeasonsPage = page(() => import('@/features/seasons/SeasonsPage'), 'SeasonsPage');
const CategoriesPage = page(() => import('@/features/categories/CategoriesPage'), 'CategoriesPage');
const SlotsPage = page(() => import('@/features/slots/SlotsPage'), 'SlotsPage');
const BlocksPage = page(() => import('@/features/blocks/BlocksPage'), 'BlocksPage');
const RulesPage = page(() => import('@/features/rules/RulesPage'), 'RulesPage');
const UsersPage = page(() => import('@/features/users/UsersPage'), 'UsersPage');
const NotificationsPage = page(() => import('@/features/notifications/NotificationsPage'), 'NotificationsPage');
const WhatsAppSettingsPage = page(() => import('@/features/whatsapp/WhatsAppSettingsPage'), 'WhatsAppSettingsPage');
const AppearancePage = page(() => import('@/features/appearance/AppearancePage'), 'AppearancePage');
const MailSettingsPage = page(() => import('@/features/mail/MailSettingsPage'), 'MailSettingsPage');
const ImagesHistoryPage = page(() => import('@/features/images/ImagesHistoryPage'), 'ImagesHistoryPage');
const SocialImagePage = page(() => import('@/features/social/SocialImagePage'), 'SocialImagePage');
const QuickGuidePage = page(() => import('@/features/help/HelpGuides'), 'QuickGuidePage');
const FullGuidePage = page(() => import('@/features/help/HelpGuides'), 'FullGuidePage');
const AuditPage = page(() => import('@/features/audit/AuditPage'), 'AuditPage');
const ProfilePage = page(() => import('@/features/auth/ProfilePage'), 'ProfilePage');

function protectedPage(render: (me: Me) => ReactNode, permission?: (me: Me) => boolean) {
    return (
        <RequireAuth>
            {(me) => (
                <Suspense fallback={<LoadingState />}>
                    {permission === undefined ? render(me) : <RequirePermission allowed={permission(me)}>{render(me)}</RequirePermission>}
                </Suspense>
            )}
        </RequireAuth>
    );
}

const adminOnly = (me: Me) => me.permissions.manage_settings;

/**
 * Rutes en català. Les pàgines protegides reben l'usuari autenticat.
 */
export const routes: RouteObject[] = [
    { path: '/inicia-sessio', element: <GuestOnly><LoginPage /></GuestOnly> },
    { path: '/recupera-contrasenya', element: <GuestOnly><ForgotPasswordPage /></GuestOnly> },
    { path: '/restableix-contrasenya', element: <GuestOnly><ResetPasswordPage /></GuestOnly> },
    { path: CHANGE_PASSWORD_PATH, element: <RequireAuth>{() => <ForcedPasswordChangePage />}</RequireAuth> },
    {
        path: '/',
        element: <RequireAuth>{(me) => <AppLayout me={me} />}</RequireAuth>,
        children: [
            { index: true, element: protectedPage(() => <SchedulePage />) },
            { path: 'partits', element: protectedPage(() => <MatchesPage />) },
            { path: 'equips', element: protectedPage(() => <TeamsPage />) },
            { path: 'temporades', element: protectedPage(() => <SeasonsPage />) },
            { path: 'avisos', element: protectedPage(() => <NotificationsPage />) },
            { path: 'xarxes-socials', element: protectedPage(() => <SocialImagePage />) },
            { path: 'imatges', element: protectedPage(() => <ImagesHistoryPage />) },
            { path: 'ajuda/guia-rapida', element: protectedPage(() => <QuickGuidePage />) },
            { path: 'ajuda/guia-completa', element: protectedPage(() => <FullGuidePage />) },
            { path: 'configuracio/categories', element: protectedPage(() => <CategoriesPage />, adminOnly) },
            { path: 'configuracio/franges', element: protectedPage(() => <SlotsPage />, adminOnly) },
            { path: 'configuracio/bloquejos', element: protectedPage(() => <BlocksPage />, adminOnly) },
            { path: 'configuracio/regles', element: protectedPage(() => <RulesPage />, adminOnly) },
            { path: 'configuracio/whatsapp', element: protectedPage(() => <WhatsAppSettingsPage />, adminOnly) },
            { path: 'configuracio/correu', element: protectedPage(() => <MailSettingsPage />, adminOnly) },
            { path: 'configuracio/aparenca', element: protectedPage(() => <AppearancePage />, adminOnly) },
            { path: 'usuaris', element: protectedPage((me) => <UsersPage me={me} />, (me) => me.permissions.manage_users) },
            { path: 'auditoria', element: protectedPage(() => <AuditPage />, (me) => me.permissions.view_audit) },
            { path: 'perfil', element: protectedPage((me) => <ProfilePage me={me} />) },
            { path: '*', element: <NotFoundPage /> },
        ],
    },
];

export function createAppRouter() {
    return createBrowserRouter(routes);
}
