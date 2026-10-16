import { Ban, CalendarDays, ClipboardList, CalendarRange, Clock, ListChecks, LogOut, Menu, MessageCircle, MessagesSquare, Palette, Shapes, Shield, Trophy, UserRound, Users, X } from 'lucide-react';
import { useState, type ComponentType } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router';
import { toast } from 'sonner';
import type { Me } from '@/api/types';
import { ClubLogo } from '@/components/ClubLogo';
import { Button } from '@/components/ui/button';
import { useLogout } from '@/hooks/useAuth';
import { t } from '@/i18n/ca';
import { cn } from '@/lib/utils';

interface NavItem {
    to: string;
    label: string;
    icon: ComponentType<{ className?: string }>;
    visible: (me: Me) => boolean;
    /** Primer element d'un grup: es mostra el títol del grup a sobre. */
    group?: string;
}

const isAdmin = (me: Me) => me.permissions.manage_settings;

const navItems: NavItem[] = [
    { to: '/', label: t('nav.schedule'), icon: CalendarDays, visible: () => true },
    { to: '/partits', label: t('nav.matches'), icon: Trophy, visible: () => true },
    { to: '/equips', label: t('nav.teams'), icon: Shield, visible: () => true },
    { to: '/temporades', label: t('nav.seasons'), icon: CalendarRange, visible: () => true },
    { to: '/avisos', label: t('nav.notifications'), icon: MessageCircle, visible: () => true },
    { to: '/configuracio/categories', label: t('nav.categories'), icon: Shapes, visible: isAdmin, group: t('nav.configuration') },
    { to: '/configuracio/franges', label: t('nav.slots'), icon: Clock, visible: isAdmin },
    { to: '/configuracio/bloquejos', label: t('nav.blocks'), icon: Ban, visible: isAdmin },
    { to: '/configuracio/regles', label: t('nav.rules'), icon: ListChecks, visible: isAdmin },
    { to: '/configuracio/whatsapp', label: t('nav.whatsapp'), icon: MessagesSquare, visible: isAdmin },
    { to: '/configuracio/aparenca', label: t('nav.appearance'), icon: Palette, visible: isAdmin },
    { to: '/usuaris', label: t('nav.users'), icon: Users, visible: (me) => me.permissions.manage_users },
    { to: '/auditoria', label: t('nav.audit'), icon: ClipboardList, visible: (me) => me.permissions.view_audit },
];

export function AppLayout({ me }: { me: Me }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const logout = useLogout();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout.mutate(undefined, {
            onSettled: () => {
                toast.success(t('auth.loggedOut'));
                void navigate('/inicia-sessio', { replace: true });
            },
        });
    };

    const navigation = (
        <nav aria-label={t('nav.mainNavigation')} className="flex flex-1 flex-col gap-1 overflow-y-auto">
            {navItems
                .filter((item) => item.visible(me))
                .map(({ to, label, icon: Icon, group }) => (
                    <div key={to} className="contents">
                        {group !== undefined && <p className="mt-4 px-3 pb-1 text-xs font-semibold tracking-wide text-sidebar-muted uppercase">{group}</p>}
                        <NavLink
                            to={to}
                            end={to === '/'}
                            onClick={() => setMenuOpen(false)}
                            className={({ isActive }) =>
                                cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    isActive ? 'bg-white text-primary shadow-sm' : 'text-sidebar-muted hover:bg-white/10 hover:text-white',
                                )
                            }
                        >
                            <Icon className="size-4" aria-hidden />
                            {label}
                        </NavLink>
                    </div>
                ))}
        </nav>
    );

    const userBox = (
        <div className="border-t border-white/15 pt-4">
            <NavLink
                to="/perfil"
                onClick={() => setMenuOpen(false)}
                className={({ isActive }) =>
                    cn('flex items-center gap-3 rounded-lg px-3 py-2 text-sm', isActive ? 'bg-white text-primary' : 'text-white hover:bg-white/10')
                }
            >
                <UserRound className="size-4" aria-hidden />
                <span className="min-w-0">
                    <span className="block truncate font-medium">{me.name}</span>
                    <span className="block truncate text-xs opacity-75">{me.role_label}</span>
                </span>
            </NavLink>
            <button
                type="button"
                onClick={handleLogout}
                disabled={logout.isPending}
                className="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-sidebar-muted hover:bg-white/10 hover:text-white"
            >
                <LogOut className="size-4" aria-hidden />
                {t('nav.logout')}
            </button>
        </div>
    );

    return (
        <div className="min-h-svh lg:grid lg:grid-cols-[15rem_1fr]">
            <a href="#contingut" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">
                {t('app.skipToContent')}
            </a>

            {/* Barra lateral (ordinador) */}
            <aside className="hidden bg-sidebar p-4 text-sidebar-foreground lg:sticky lg:top-0 lg:flex lg:h-svh lg:flex-col lg:gap-6">
                <Brand />
                {navigation}
                {userBox}
            </aside>

            {/* Capçalera (tauleta i mòbil) */}
            <header className="sticky top-0 z-30 flex items-center justify-between bg-sidebar px-4 py-3 text-white lg:hidden">
                <Brand />
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-white hover:bg-white/10 hover:text-white"
                    onClick={() => setMenuOpen((open) => !open)}
                    aria-expanded={menuOpen}
                    aria-controls="menu-mobil"
                    aria-label={menuOpen ? t('app.closeMenu') : t('app.openMenu')}
                >
                    {menuOpen ? <X className="size-5" aria-hidden /> : <Menu className="size-5" aria-hidden />}
                </Button>
            </header>
            {menuOpen && (
                <div id="menu-mobil" className="fixed inset-x-0 top-16 z-20 flex flex-col gap-4 bg-sidebar p-4 shadow-lg lg:hidden">
                    {navigation}
                    {userBox}
                </div>
            )}

            <main id="contingut" className="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
                <Outlet />
            </main>
        </div>
    );
}

function Brand() {
    return (
        <div className="flex items-center gap-2">
            <ClubLogo className="size-9" />
            <div className="leading-tight">
                <p className="font-bold">{t('app.name')}</p>
                <p className="text-xs text-sidebar-muted">{t('app.club')}</p>
            </div>
        </div>
    );
}
