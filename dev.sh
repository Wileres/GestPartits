#!/usr/bin/env bash
# Arrenca GestPartits en local: servidor web, frontend (Vite) i cua de treballs.
#
#   ./dev.sh            servidor + Vite amb recàrrega en calent + cua
#   ./dev.sh --build    compila el frontend una vegada (sense Vite) i arrenca servidor + cua
#   ./dev.sh --fresh    a més, buida la base de dades i la torna a omplir (demana confirmació)
#   ./dev.sh --test     executa les proves (backend i frontend) i surt
#
# Surt amb Ctrl+C: atura tots els processos que ha arrencat. Executeu-lo amb el vostre usuari, no amb root.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

PORT="${PORT:-8000}"
MODE="dev"
FRESH=0
TEST=0

for arg in "$@"; do
    case "$arg" in
        --build) MODE="build" ;;
        --fresh) FRESH=1 ;;
        --test) TEST=1 ;;
        -h|--help) sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opció desconeguda: $arg (vegeu ./dev.sh --help)" >&2; exit 2 ;;
    esac
done

bold() { printf '\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '\033[32m✔\033[0m %s\n' "$*"; }
warn() { printf '\033[33m!\033[0m %s\n' "$*"; }
fail() { printf '\033[31m✘\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -ne 0 ] || fail "No l'executeu com a root: deixaria fitxers de storage/ que el vostre usuari no podria escriure."

# --- Programari necessari -------------------------------------------------------------------------

command -v php >/dev/null      || fail "Falta PHP 8.5 (sudo apt install php8.5-cli ...)."
command -v composer >/dev/null || fail "Falta Composer (https://getcomposer.org/download/)."

# Node.js instal·lat amb nvm: si no és al PATH, es busca la versió més nova.
if ! command -v node >/dev/null; then
    for dir in "$HOME"/.nvm/versions/node/*/bin; do
        [ -x "$dir/node" ] && NODE_BIN="$dir"
    done
    [ -n "${NODE_BIN:-}" ] || fail "Falta Node.js 24 (instal·leu-lo amb nvm o des de nodejs.org)."
    export PATH="$NODE_BIN:$PATH"
fi

php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);' || fail "Cal PHP 8.5 o més nou (teniu $(php -r 'echo PHP_VERSION;'))."
for ext in pdo_mysql mbstring intl gd xml curl zip sodium; do
    php -m | grep -qix "$ext" || fail "Falta l'extensió PHP «$ext» (sudo apt install php8.5-${ext/pdo_mysql/mysql})."
done

[ -f .env ] || fail "Falta el fitxer .env. Creeu-lo amb: cp .env.example .env && php artisan key:generate (vegeu el README)."

# --- Dependències ---------------------------------------------------------------------------------

if [ ! -f vendor/.dev-installed ] || [ composer.lock -nt vendor/.dev-installed ]; then
    bold "Instal·lant les dependències de PHP..."
    composer install --no-interaction
    touch vendor/.dev-installed
fi
if [ ! -d node_modules ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
    bold "Instal·lant les dependències de JavaScript..."
    npm install --no-audit --no-fund
fi
grep -qE '^APP_KEY=.+' .env || { php artisan key:generate --ansi; }

# --- Proves ---------------------------------------------------------------------------------------

if [ "$TEST" -eq 1 ]; then
    bold "Proves del backend (cal la base de dades gestpartits_test)"
    php artisan test
    bold "Proves del frontend"
    npm test
    npm run typecheck
    npm run lint
    exit 0
fi

# --- Base de dades --------------------------------------------------------------------------------

bold "Comprovant la base de dades..."
if ! php artisan migrate:status >/dev/null 2>&1; then
    if command -v systemctl >/dev/null && ! systemctl is-active --quiet mariadb 2>/dev/null && ! systemctl is-active --quiet mysql 2>/dev/null; then
        fail "MariaDB no està en marxa. Arrenqueu-la amb: sudo systemctl start mariadb"
    fi
    fail "No es pot connectar a la base de dades. Reviseu DB_* al .env i que existeixi la base «gestpartits» (vegeu el README)."
fi

if [ "$FRESH" -eq 1 ]; then
    warn "--fresh ESBORRA TOTES les dades de la base de dades local (partits, usuaris, equips...)."
    read -r -p "Escriviu «si» per continuar: " answer
    [ "$answer" = "si" ] || fail "Cancel·lat."
    read -r -s -p "Contrasenya inicial de l'usuari Gestor: " INITIAL_USER_PASSWORD
    echo
    [ -n "$INITIAL_USER_PASSWORD" ] || fail "La contrasenya no pot ser buida."
    export INITIAL_USER_PASSWORD
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force
fi
ok "Base de dades al dia."

mkdir -p storage/app/chromium

# --- Frontend -------------------------------------------------------------------------------------

if [ "$MODE" = "build" ]; then
    bold "Compilant el frontend..."
    rm -f public/hot
    npm run build
    ok "Frontend compilat a public/build."
fi

# --- Port ocupat? ---------------------------------------------------------------------------------

if (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null; then
    fail "El port $PORT ja està ocupat (potser ja teniu «php artisan serve» en marxa). Atureu-lo o feu servir un altre port: PORT=8001 ./dev.sh"
fi
if [ "$PORT" != "8000" ]; then
    warn "Esteu fent servir el port $PORT: afegiu-lo a APP_URL i SANCTUM_STATEFUL_DOMAINS del .env (p. ex. localhost:$PORT) o no podreu iniciar sessió."
fi

# --- Arrencada ------------------------------------------------------------------------------------

PIDS=()

# Cada procés va en el seu grup per poder-lo aturar sencer (php artisan serve en crea un de fill).
start() {
    local label="$1"; shift
    setsid "$@" > >(sed -u "s/^/[$label] /") 2>&1 &
    PIDS+=("$!")
}

cleanup() {
    trap - EXIT INT TERM
    echo
    bold "Aturant..."
    for pid in "${PIDS[@]}"; do
        kill -TERM -- "-$pid" 2>/dev/null || kill -TERM "$pid" 2>/dev/null || true
    done
    # Espera fins a 5 s que acabin; si algun no ho fa, se'l mata.
    for _ in $(seq 1 25); do
        alive=0
        for pid in "${PIDS[@]}"; do
            pgrep -g "$pid" >/dev/null 2>&1 && alive=1
        done
        [ "$alive" -eq 0 ] && break
        sleep 0.2
    done
    for pid in "${PIDS[@]}"; do
        kill -KILL -- "-$pid" 2>/dev/null || true
    done
    rm -f public/hot
    ok "Fet."
}
trap cleanup EXIT INT TERM

bold "Arrencant GestPartits..."
start servidor php artisan serve --host=127.0.0.1 --port="$PORT"
start cua      php artisan queue:work --sleep=2 --tries=1 --timeout=180
if [ "$MODE" = "dev" ]; then
    start vite npm run dev -- --host 127.0.0.1
fi

# Espera que el servidor respongui.
for _ in $(seq 1 40); do
    curl -fs -o /dev/null "http://127.0.0.1:$PORT/up" && break
    sleep 0.5
done
curl -fs -o /dev/null "http://127.0.0.1:$PORT/up" || fail "El servidor no ha arrencat. Reviseu els missatges de més amunt."

echo
ok "Tot en marxa:  http://localhost:$PORT"
echo "   Usuari:    Gestor   (la contrasenya que vau triar en el primer accés)"
echo "   Aturar:    Ctrl+C"
echo "   Registres: storage/logs/laravel.log"
echo

wait
