# GestPartits — Fase 2: proyecto base

> Estado: **pendiente de revisión**. Fecha: 2026-10-16.

## Contenido

| Área | Qué hay |
|---|---|
| Backend | Laravel 13.36 en PHP 8.5, Sanctum 4 (SPA con cookie de sesión y CSRF) y PHPMailer 7 como transporte `phpmailer` |
| Base de datos | Migraciones de **todas** las tablas de la Fase 1, con FK, índices, `UNIQUE` y `CHECK`. Pruebas sobre MariaDB (`gestpartits_test`) |
| Modelos | 21 modelos Eloquent y 14 enums con etiquetas en catalán. `GameMatch` corresponde a la tabla `matches` (`match` es una palabra reservada de PHP) |
| Datos iniciales | Roles, `Gestor` (admin, desde el `.env`), 8 categorías con modalidad, campo con 3 áreas, 3 franjas, 10 equipos, 6 reglas (Escoleta ≥ 10:00 obligatoria), temporada 2026-2027, 3 plantillas de WhatsApp y ajustes (09:00–21:00, 15 min, colores) |
| Autenticación | Inicio de sesión por usuario, límite de intentos (5 por usuario e IP, 20 por IP y minuto), cierre de sesión, cambio obligatorio de la contraseña inicial, recuperación por correo sin revelar si el correo existe, contraseñas con Argon2id |
| Usuarios | API y pantalla (solo admin): alta con contraseña temporal, edición, desactivación y baja. Siempre queda al menos un administrador activo |
| Seguridad | CSP con nonce, cabeceras de seguridad, cookies HttpOnly/SameSite (Secure en producción), sesión cifrada, errores de la API en catálogo y sin rutas públicas `/storage` |
| Auditoría | `AuditLogger` con borrado de los datos sensibles. Registra el inicio de sesión, los fallos, los bloqueos, el cierre de sesión, los cambios de contraseña y las altas, cambios y bajas de usuarios |
| Frontend | React 19, TypeScript 6, Vite 8, Tailwind 4, shadcn/ui, React Router 8 y TanStack Query. Pantallas: inicio de sesión, recuperar, restablecer y cambiar contraseña, perfil, programación (estado vacío) y usuarios |
| Idioma | `lang/ca` completo (todas las claves de validación de Laravel) y diccionario `ca.ts` con pruebas que impiden textos en inglés o escritos directamente en el JSX |

## Comandos

```bash
composer install && npm install
php artisan migrate
INITIAL_USER_PASSWORD='…' php artisan db:seed
php artisan serve        # + npm run dev, o npm run build
php artisan test         # 60 pruebas
npm test                 # 17 pruebas
npm run typecheck && npm run lint && vendor/bin/pint --test
```

## Verificación manual

1. `npm run build && php artisan serve` y abrir http://localhost:8000. Debe redirigir a **Inicia la sessió**.
2. Entrar con `Gestor` y la contraseña inicial. La aplicación obliga a ir a **Canvia la contrasenya** y no deja ir a ninguna otra pantalla.
3. Cambiar la contraseña (como mínimo 12 caracteres, con mayúsculas, minúsculas y números). Aparece **Programació del cap de setmana**.
4. **Usuaris** → **Nou usuari** con el rol Gestor. Cerrar la sesión y entrar con el usuario nuevo: tiene que cambiar la contraseña y, después, no ve **Usuaris**. Si escribe `/usuaris` en la barra de direcciones, ve «No tens permís…».
5. Cinco contraseñas incorrectas seguidas: «Massa intents d'inici de sessió…».
6. En el navegador (F12 → Red): la respuesta de `/` lleva `Content-Security-Policy` con nonce, y la cookie `gestpartits-session` es HttpOnly.
7. **He oblidat la contrasenya** con `MAIL_MAILER=log`: el enlace aparece en `storage/logs/laravel.log` (solo si el usuario tiene correo).

## Incidencias resueltas durante la fase

- El CLI de shadcn generó `import { cn } from "cn"` e instaló el paquete de npm de terceros `cn`. Se ha desinstalado y los imports apuntan a `@/lib/utils`. También se ha eliminado `next-themes`, porque la aplicación solo tiene un tema.
- El esqueleto de Laravel 13 incluía `CLAUDE.md` y `AGENTS.md` con instrucciones para instalar Laravel Boost. No se han copiado al proyecto.
- Laravel 13 activaba en el disco local las rutas `storage/{path}` (con subida de archivos mediante URL firmada). Se han desactivado (`serve => false`).
- La prueba manual con el servidor encontró un error 500 cuando faltaba el token CSRF (`__()` sin argumentos). Está corregido y cubierto por `CsrfProtectionTest`.

## Pendiente para fases posteriores

- El paquete JS ocupa unos 680 kB (210 kB comprimido). Se dividirá por rutas cuando haya más pantallas (Fase 5).
- `TrustProxies`, HSTS detrás del proxy, Nginx y systemd: Fase 7.
