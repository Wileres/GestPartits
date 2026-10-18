# GestPartits

Programació dels partits de futbol del **Joventut Sant Pere Màrtir**: equips, partits de cada jornada, proposta automàtica d'horaris, calendari de dissabte i diumenge amb arrossegar i deixar anar, avisos de WhatsApp i imatges per a Instagram. Tota la interfície és en català.

- **Backend:** Laravel 13 (PHP 8.5), API REST, MariaDB o MySQL, Sanctum (sessió amb cookie i CSRF), cues a la base de dades.
- **Frontend:** React 19 i TypeScript, Vite, Tailwind CSS 4, shadcn/ui i dnd-kit.
- **Documentació:** `docs/fase-1-analisi-arquitectura.md` … `docs/fase-8-canvis-equips-estats-escut.md`, el registre de decisions `docs/DECISIONS.md` i la correspondència de les proves `docs/proves.md`.

## Funcionament

1. **Configuració** (administrador): categories, franges horàries, bloquejos del camp, regles horàries, plantilles i destins de WhatsApp, i aparença.
2. **Temporades:** es crea la jornada (per exemple, dissabte i diumenge).
3. **Partits:** amb el full ràpid es marca cada equip com a casa o a fora (suggeriment a partir de l'últim partit). Els partits nous es desen com a **confirmats**; els de casa carreguen l'**horari preferent** de l'equip i la convocatòria és **una hora abans**. Els partits només tenen tres estats: Proposat, Confirmat i Cancel·lat.
4. **Programació:** «Genera una proposta» col·loca els partits a casa segons les franges, la capacitat del camp (2 partits de futbol 7 alhora, o 1 de futbol 11) i les regles. Després es pot ajustar arrossegant les targetes.
5. **Confirma la programació:** no es pot confirmar mentre hi hagi conflictes crítics.
6. **Imatges i avisos:**
   - Genera les imatges: 1080 × 1920, una per dia.
   - Prepara els avisos: es revisen i s'envien només amb una confirmació explícita.

## Requisits

| Programari | Versió |
|---|---|
| PHP | 8.5, amb `pdo_mysql`, `mbstring`, `intl`, `gd`, `xml`, `curl`, `zip`, `sodium` i `fileinfo` |
| Composer | 2 |
| MariaDB / MySQL | MariaDB 10.11+ o MySQL 8+ |
| Node.js / npm | 24 / 11 (només per compilar el frontend) |
| Chromium | Per a les imatges. Alternativa: `IMAGE_RENDERER=gd` |
| Fonts | `fonts-dejavu-core` (renderitzador GD) |

## Desenvolupament local

```bash
# 1. Bases de dades (substituïu CONTRASENYA)
sudo mariadb <<'SQL'
CREATE DATABASE gestpartits CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE gestpartits_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gestpartits'@'localhost' IDENTIFIED BY 'CONTRASENYA';
GRANT ALL PRIVILEGES ON gestpartits.* TO 'gestpartits'@'localhost';
GRANT ALL PRIVILEGES ON gestpartits_test.* TO 'gestpartits'@'localhost';
SQL

# 2. Dependències i configuració
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Al `.env`, per treballar amb `http://localhost:8000`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_PASSWORD=CONTRASENYA
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost:8000,127.0.0.1:8000
MAIL_MAILER=log
WHATSAPP_LOG_ENABLED=true
```

```bash
# 3. Taules i dades inicials. La contrasenya inicial només es passa per l'entorn.
php artisan migrate
INITIAL_USER_PASSWORD='contrasenya-inicial' php artisan db:seed

# 4. Arrencada (tres terminals)
php artisan serve          # http://localhost:8000
npm run dev                # Vite amb recàrrega en calent (o bé: npm run build)
php artisan queue:work     # imatges i avisos
```

Es crea l'usuari `Gestor`, amb rol d'administrador, que haurà de canviar la contrasenya en el primer accés.

### Arrencada ràpida

Un cop feta la instal·lació anterior, `./dev.sh` arrenca el servidor, la cua i Vite d'una vegada i els atura amb Ctrl+C:

```bash
./dev.sh            # servidor + Vite (recàrrega en calent) + cua
./dev.sh --build    # compila el frontend una vegada, sense Vite
./dev.sh --fresh    # buida la base de dades i la torna a omplir (demana confirmació)
./dev.sh --test     # proves del backend i del frontend
```

Comprova els requisits, instal·la les dependències que falten, aplica les migracions i avisa si MariaDB no està en marxa o si el port 8000 és ocupat. S'ha d'executar amb el vostre usuari, no amb root.

## Instal·lació a producció (Debian)

### 1. Paquets

```bash
sudo apt install nginx mariadb-server certbot python3-certbot-nginx \
    php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-intl php8.5-gd php8.5-xml php8.5-curl php8.5-zip \
    chromium fonts-dejavu-core unzip git
# Composer: https://getcomposer.org/download/   Node.js 24 (només per compilar): https://nodejs.org
```

### 2. Codi i dependències

```bash
sudo mkdir -p /var/www/gestpartits && sudo chown $USER:www-data /var/www/gestpartits
git clone https://github.com/Wileres/GestPartits.git /var/www/gestpartits
cd /var/www/gestpartits
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # genera public/build (node_modules no cal en producció)
cp .env.example .env && php artisan key:generate
```

### 3. Configuració (`.env`)

Valors imprescindibles:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://partits.el-club.cat
DB_PASSWORD=…
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=partits.el-club.cat
MAIL_MAILER=phpmailer
MAIL_HOST=…  MAIL_PORT=587  MAIL_ENCRYPTION=tls  MAIL_USERNAME=…  MAIL_PASSWORD=…  MAIL_FROM_ADDRESS=…
IMAGE_RENDERER=chromium
```

- El fitxer `.env` no es puja mai a Git (és a `.gitignore`).
- Protegiu-lo: `chmod 640 .env && chown $USER:www-data .env`.
- Les credencials de WhatsApp també van al `.env` (vegeu l'apartat «WhatsApp»).

### 4. Base de dades i usuari inicial

```bash
php artisan migrate --force
INITIAL_USER_PASSWORD='…' php artisan db:seed --force
```

La contrasenya inicial no queda en cap fitxer. L'usuari `Gestor` l'haurà de canviar en el primer accés.

### 5. Permisos, PHP i cachés

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo mkdir -p storage/app/chromium && sudo chown www-data:www-data storage/app/chromium
sudo cp deploy/php/99-gestpartits.ini /etc/php/8.5/fpm/conf.d/ && sudo cp deploy/php/99-gestpartits.ini /etc/php/8.5/cli/conf.d/
sudo systemctl restart php8.5-fpm
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 6. Servidor web i HTTPS (obligatori)

```bash
sudo cp deploy/nginx/gestpartits.conf /etc/nginx/sites-available/   # canvieu el nom del domini
sudo ln -s /etc/nginx/sites-available/gestpartits.conf /etc/nginx/sites-enabled/
sudo certbot --nginx -d partits.el-club.cat
sudo nginx -t && sudo systemctl reload nginx
```

- Amb Apache, feu servir `deploy/apache/gestpartits.conf`.
- Totes dues configuracions redirigeixen l'HTTP a HTTPS i només executen `public/index.php`.
- Les capçaleres de seguretat (CSP, HSTS, etc.) les envia l'aplicació.
- Si hi ha un proxy invers davant, indiqueu-ne les IP a `TRUSTED_PROXIES`.

### 7. Cua i tasques programades

```bash
sudo cp deploy/systemd/gestpartits-queue.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now gestpartits-queue
sudo cp deploy/cron/gestpartits /etc/cron.d/gestpartits      # schedule:run cada minut + còpia diària
```

Tasques programades:

| Hora | Tasca |
|---|---|
| Cada dia a les 03:30 | Neteja d'imatges de versions antigues (`IMAGE_KEEP_DAYS`) |
| Cada dia a les 03:40 | Enllaços de contrasenya caducats |
| Cada setmana | Treballs fallits de més de 30 dies |

## Actualització

```bash
cd /var/www/gestpartits
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart gestpartits-queue php8.5-fpm
php artisan up
```

## Còpies de seguretat

`deploy/backup.sh` (el cron l'executa cada dia a les 02:15) desa a `/var/backups/gestpartits`:

- `db-*.sql.gz`: `mariadb-dump --single-transaction`;
- `storage-*.tar.gz`: escuts i imatges generades (`storage/app/private`);
- `env-*`: còpia del `.env`, que conté la clau `APP_KEY`. Sense aquesta clau no es poden desxifrar ni les sessions ni els identificadors de WhatsApp.

Es conserven 30 dies (`KEEP_DAYS`). Copieu aquest directori fora del servidor (rsync o restic) amb permisos restringits.

```bash
# Restauració
gunzip -c /var/backups/gestpartits/db-AAAAMMDD-HHMM.sql.gz | sudo mariadb gestpartits
sudo tar -xzf /var/backups/gestpartits/storage-AAAAMMDD-HHMM.tar.gz -C /var/www/gestpartits
```

## WhatsApp

La plataforma oficial de WhatsApp no publica en grups existents. L'aplicació separa la lògica de negoci dels proveïdors (`WhatsAppProviderInterface`):

| Proveïdor | Com s'activa | Destins | Què fa |
|---|---|---|---|
| **Manual** (via A, per defecte) | Sempre | Grups, canal, llistes, telèfons | Prepara el text. El gestor el copia o l'obre a WhatsApp, el publica i el marca com a enviat |
| **Passarel·la** (via B) | `WHATSAPP_GATEWAY_ENABLED=true` i `WHATSAPP_GATEWAY_URL` / `_TOKEN` | Grups `…@g.us`, canals `…@newsletter`, telèfons | Enviament automàtic a través d'una passarel·la HTTP amb un **número dedicat** del club. Incompleix les condicions de WhatsApp i pot provocar el bloqueig del número. Contracte de l'API a `app/Infrastructure/WhatsApp/WebGatewayProvider.php` |
| **WhatsApp Business (Meta)** | `WHATSAPP_META_ENABLED=true`, `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_META_TOKEN` | Només telèfons | Cal una plantilla aprovada per Meta (camp «Plantilla aprovada per Meta») |
| **Twilio** | `WHATSAPP_TWILIO_ENABLED=true`, `TWILIO_*` | Només telèfons | |
| **Simulació** | Fora de producció o `WHATSAPP_LOG_ENABLED=true` | Tots | Escriu el missatge al registre |

- **Destins:** es configuren a **Configuració → WhatsApp**:
  - **Equips:** grups de cada equip, que reben un missatge per partit.
  - **Club:** canal o llista, que rep un resum de la jornada i, si s'activa, les imatges.
- **Seguretat:** els identificadors es desen xifrats i no es tornen a mostrar.
- **Enviament:** res no s'envia fins que el gestor ho confirma. Un mateix missatge no es pot enviar dues vegades, i els que tenen un estat desconegut (temps d'espera esgotat) no es reintenten automàticament.

## Imatges

`IMAGE_RENDERER` pot ser:
- `chromium` (recomanat): necessita `CHROMIUM_BINARY`, per defecte `/usr/bin/chromium`;
- `gd`: per a servidors sense Chromium, amb les fonts `GD_FONT_REGULAR` i `GD_FONT_BOLD`.

Les imatges són de 1080 × 1920 i en PNG. Si un dia té més de 8 partits es fa servir el disseny compacte, i amb més de 10 es generen diverses pàgines. L'escut i els colors es canvien a **Configuració → Aparença**.

## Usuaris

- Des de l'aplicació: **Usuaris** (només administradors).
- Des de la terminal:

  ```bash
  php artisan app:user:create entrenador --name="Entrenador Aleví" --email=entrenador@example.org --role=manager
  ```

- **Recuperació de contrasenya:** per correu, amb PHPMailer per SMTP. Només funciona per als usuaris que tenen el correu informat.

## Proves i qualitat

```bash
php artisan test               # backend: PHPUnit sobre la base de dades gestpartits_test (MariaDB)
php artisan test --group=chromium   # només la prova que genera una imatge real amb Chromium
npm test                       # frontend: Vitest
npm run typecheck && npm run lint
vendor/bin/pint --test         # estil del codi PHP
```

Les fixtures de `tests/fixtures/scheduling/*.json` les executen tant PHPUnit (detector PHP) com Vitest (validador TypeScript del calendari), perquè els dos validadors no divergeixin.

## Components de shadcn/ui

En afegir components amb `npx shadcn@latest add …`, el CLI instal·la el paquet npm `cn` i en canvia els imports. Després de cada `add`:

```bash
npm uninstall cn
grep -rl 'from "cn"' resources/frontend/src | xargs -r sed -i 's#from "cn"#from "@/lib/utils"#'
```

Hi ha una prova que falla si el paquet `cn` torna a aparèixer a les dependències.

## Seguretat (resum)

Detall a `docs/fase-7-seguretat-produccio.md`:

- **Accés:** contrasenyes amb Argon2id; canvi obligatori de la contrasenya inicial; límit d'intents per usuari i IP; sessió regenerada en iniciar-la.
- **Navegador:** cookies `HttpOnly`, `Secure` i `SameSite`; CSRF; CSP amb nonce; HSTS a producció.
- **Autorització:** policies i gates, amb proves sobre totes les rutes.
- **Pujades:** validades pel contingut, tornades a codificar i desades amb un nom aleatori fora de `public`.
- **Dades i registres:** auditoria només d'insercions; registres sense contrasenyes, tokens ni telèfons; identificadors de WhatsApp xifrats.
- **Concurrència:** transaccions, `SELECT … FOR UPDATE` i bloqueig optimista (`409`).
