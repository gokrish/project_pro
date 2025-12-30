#!/bin/bash
set -e

APP_NAME="proconsultancy"
DB_NAME="proconsultancy"
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${BLUE}[INFO]${NC} $1"; }
ok()  { echo -e "${GREEN}[OK]${NC} $1"; }
err() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

clear
echo "🚀 ProConsultancy macOS Setup (Valet)"

### 1️⃣ Dependencies
log "Installing dependencies..."
brew install php mysql composer nginx dnsmasq >/dev/null 2>&1 || true

### 2️⃣ Valet
if ! command -v valet >/dev/null; then
    log "Installing Laravel Valet..."
    composer global require laravel/valet
    echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.zshrc
    source ~/.zshrc
fi

valet install || err "Valet install failed"
ok "Valet running"

### 3️⃣ Link project
cd "$(dirname "$PROJECT_ROOT")"
valet unlink "$APP_NAME" >/dev/null 2>&1 || true
valet link "$APP_NAME"
ok "Valet linked: http://$APP_NAME.test"

### 4️⃣ Database
log "Configuring MySQL"
read -s -p "MySQL root password (Enter if empty): " DB_PASS
echo ""

MYSQL="mysql -u root"
[ -n "$DB_PASS" ] && MYSQL="mysql -u root -p$DB_PASS"

echo "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4;" | $MYSQL \
    || err "MySQL connection failed"

ok "Database ready"

### 5️⃣ .env
log "Creating .env"
cat > "$PROJECT_ROOT/.env" <<EOF
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USER=root
DB_PASS=$DB_PASS

APP_ENV=local
APP_DEBUG=true
APP_URL=http://$APP_NAME.test
EOF
chmod 600 "$PROJECT_ROOT/.env"
ok ".env created"

### 6️⃣ Permissions
log "Fixing permissions"
mkdir -p "$PROJECT_ROOT/uploads" "$PROJECT_ROOT/logs"
chmod -R 777 "$PROJECT_ROOT/uploads" "$PROJECT_ROOT/logs"
ok "Permissions set"

### 7️⃣ HTTP Test
log "Testing HTTP routing"
HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}" "http://$APP_NAME.test/panel/login.php")

if [ "$HTTP_STATUS" != "200" ]; then
    err "HTTP test failed (Status: $HTTP_STATUS)"
fi

ok "HTTP routing OK"

### DONE
echo ""
echo "✅ SETUP COMPLETE"
echo "🌐 Open: http://$APP_NAME.test/panel/login.php"
echo "🔐 Admin: admin@proconsultancy.local / password"
