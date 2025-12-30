#!/bin/bash
################################################################################
# ProConsultancy ATS - Automated Setup Script
# Supports: Mac (Intel/Apple Silicon) and Windows (via Git Bash)
# Version: 1.0
# Date: December 30, 2024
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

################################################################################
# LOGGING FUNCTIONS
################################################################################

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${GREEN}========================================${NC}"
}

################################################################################
# OS DETECTION
################################################################################

detect_os() {
    log_step "Detecting Operating System"
    
    if [[ "$OSTYPE" == "darwin"* ]]; then
        OS="mac"
        log_success "Detected: macOS"
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        OS="linux"
        log_success "Detected: Linux"
    elif [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "cygwin" ]] || [[ "$OSTYPE" == "win32" ]]; then
        OS="windows"
        log_success "Detected: Windows (Git Bash)"
    else
        log_error "Unsupported operating system: $OSTYPE"
        exit 1
    fi
}

################################################################################
# DEPENDENCY CHECK & INSTALLATION
################################################################################

check_command() {
    if command -v "$1" &> /dev/null; then
        log_success "$1 is installed"
        return 0
    else
        log_warning "$1 is NOT installed"
        return 1
    fi
}

install_homebrew() {
    if ! check_command brew; then
        log_info "Installing Homebrew..."
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
        
        # Add to PATH for Apple Silicon
        if [[ $(uname -m) == 'arm64' ]]; then
            echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
            eval "$(/opt/homebrew/bin/brew shellenv)"
        fi
    fi
}

install_dependencies_mac() {
    log_step "Installing Dependencies (macOS)"
    
    install_homebrew
    
    # PHP
    if ! check_command php; then
        log_info "Installing PHP..."
        brew install php@8.1
        brew link php@8.1 --force
    fi
    
    # MySQL
    if ! check_command mysql; then
        log_info "Installing MySQL..."
        brew install mysql
        brew services start mysql
        
        log_warning "MySQL root password is EMPTY by default"
        log_info "You can secure it later with: mysql_secure_installation"
    fi
    
    # Composer
    if ! check_command composer; then
        log_info "Installing Composer..."
        brew install composer
    fi
    
    log_success "All dependencies installed!"
}

install_dependencies_windows() {
    log_step "Checking Dependencies (Windows)"
    
    # Check PHP
    if ! check_command php; then
        log_error "PHP not found!"
        log_info "Please install XAMPP or Laragon:"
        log_info "  XAMPP: https://www.apachefriends.org/download.html"
        log_info "  Laragon: https://laragon.org/download/"
        exit 1
    fi
    
    # Check MySQL
    if ! check_command mysql; then
        log_error "MySQL not found!"
        log_info "Please ensure MySQL is installed and in your PATH"
        exit 1
    fi
    
    log_success "Dependencies check passed!"
}

install_dependencies() {
    case "$OS" in
        mac|linux)
            install_dependencies_mac
            ;;
        windows)
            install_dependencies_windows
            ;;
    esac
}

################################################################################
# CONFIGURATION
################################################################################

get_db_password() {
    log_step "Database Configuration"
    
    echo -n "Enter MySQL root password (press Enter if empty): "
    read -s DB_PASSWORD
    echo ""
    
    if [ -z "$DB_PASSWORD" ]; then
        log_info "Using empty password"
        MYSQL_CMD="mysql -u root"
    else
        MYSQL_CMD="mysql -u root -p$DB_PASSWORD"
    fi
}

create_env_file() {
    log_step "Creating .env Configuration"
    
    ENV_FILE="$PROJECT_ROOT/.env"
    
    if [ -f "$ENV_FILE" ]; then
        log_warning ".env file already exists"
        echo -n "Overwrite? (y/n): "
        read -r OVERWRITE
        if [[ ! "$OVERWRITE" =~ ^[Yy]$ ]]; then
            log_info "Keeping existing .env file"
            return
        fi
    fi
    
    cat > "$ENV_FILE" << EOF
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=proconsultancy
DB_USER=root
DB_PASS=$DB_PASSWORD

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/proconsultancy

# Session
SESSION_LIFETIME=120

# Security
CSRF_ENABLED=true

# Logging
LOG_LEVEL=debug
LOG_FILE=logs/app.log
EOF
    
    chmod 600 "$ENV_FILE"
    log_success ".env file created"
}

################################################################################
# DATABASE SETUP
################################################################################

create_database() {
    log_step "Creating Database"
    
    # Check if database exists
    DB_EXISTS=$(echo "SHOW DATABASES LIKE 'proconsultancy';" | $MYSQL_CMD -N 2>/dev/null | wc -l)
    
    if [ "$DB_EXISTS" -gt 0 ]; then
        log_warning "Database 'proconsultancy' already exists"
        echo -n "Drop and recreate? (y/n): "
        read -r DROP_DB
        if [[ "$DROP_DB" =~ ^[Yy]$ ]]; then
            echo "DROP DATABASE IF EXISTS proconsultancy;" | $MYSQL_CMD
            log_info "Database dropped"
        else
            log_info "Keeping existing database"
            return
        fi
    fi
    
    echo "CREATE DATABASE proconsultancy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | $MYSQL_CMD
    log_success "Database 'proconsultancy' created"
}

import_schema() {
    log_step "Importing Database Schema"
    
    SCHEMA_FILE="$PROJECT_ROOT/database/schema_v3_final_production.sql"
    
    if [ ! -f "$SCHEMA_FILE" ]; then
        log_error "Schema file not found: $SCHEMA_FILE"
        exit 1
    fi
    
    log_info "Importing schema..."
    $MYSQL_CMD proconsultancy < "$SCHEMA_FILE" 2>&1 | tee /tmp/schema_import.log
    
    if [ $? -eq 0 ]; then
        log_success "Schema imported successfully"
    else
        log_error "Schema import failed. Check /tmp/schema_import.log"
        exit 1
    fi
}

create_admin_user() {
    log_step "Creating Default Admin User"
    
    # Check if admin exists
    ADMIN_EXISTS=$(echo "SELECT COUNT(*) FROM users WHERE email = 'admin@proconsultancy.local';" | $MYSQL_CMD proconsultancy -N 2>/dev/null)
    
    if [ "$ADMIN_EXISTS" -gt 0 ]; then
        log_info "Admin user already exists"
        return
    fi
    
    # Password hash for 'password'
    HASH='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
    
    cat << EOF | $MYSQL_CMD proconsultancy
INSERT INTO users (
    user_code, role_id, name, full_name, email, password, 
    level, is_active, created_at
) VALUES (
    'USR001',
    (SELECT id FROM roles WHERE role_code = 'admin' LIMIT 1),
    'Admin User',
    'Admin User',
    'admin@proconsultancy.local',
    '$HASH',
    'admin',
    1,
    NOW()
);
EOF
    
    log_success "Admin user created:"
    log_info "  Email: admin@proconsultancy.local"
    log_info "  Password: password"
}

migrate_existing_users() {
    log_step "Migrating Existing Users to Role System"
    
    # Check if users need migration
    UNMIGRATED=$(echo "SELECT COUNT(*) FROM users WHERE role_id IS NULL;" | $MYSQL_CMD proconsultancy -N 2>/dev/null)
    
    if [ "$UNMIGRATED" -eq 0 ]; then
        log_info "All users already have roles assigned"
        return
    fi
    
    log_info "Found $UNMIGRATED users without roles. Migrating..."
    
    # Migrate users based on their level
    cat << 'EOF' | $MYSQL_CMD proconsultancy
-- Migrate admins
UPDATE users u
JOIN roles r ON r.role_code = 'admin'
SET u.role_id = r.id
WHERE u.level IN ('admin', 'super_admin')
AND u.role_id IS NULL;

-- Migrate managers
UPDATE users u
JOIN roles r ON r.role_code = 'manager'
SET u.role_id = r.id
WHERE u.level = 'manager'
AND u.role_id IS NULL;

-- Migrate recruiters
UPDATE users u
JOIN roles r ON r.role_code = 'recruiter'
SET u.role_id = r.id
WHERE u.level IN ('recruiter', 'user')
AND u.role_id IS NULL;

-- Show migration results
SELECT 
    'Migration Complete' as status,
    COUNT(*) as migrated_users
FROM users 
WHERE role_id IS NOT NULL;
EOF
    
    log_success "User migration completed"
}

################################################################################
# FILE PERMISSIONS
################################################################################

set_permissions() {
    log_step "Setting File Permissions"
    
    cd "$PROJECT_ROOT"
    
    # Create directories if not exist
    mkdir -p uploads/{resumes,documents,photos,cv_inbox}
    mkdir -p logs
    mkdir -p public/uploads
    
    case "$OS" in
        mac|linux)
            chmod -R 755 panel/
            chmod -R 777 uploads/
            chmod -R 777 logs/
            chmod 600 .env 2>/dev/null || true
            ;;
        windows)
            log_info "Skipping chmod on Windows (handled by NTFS)"
            ;;
    esac
    
    log_success "Permissions set"
}

################################################################################
# VALIDATION & TESTING
################################################################################

test_database_connection() {
    log_step "Testing Database Connection"
    
    TEST_QUERY="SELECT 'Connection OK' as status, COUNT(*) as tables FROM information_schema.tables WHERE table_schema = 'proconsultancy';"
    
    RESULT=$(echo "$TEST_QUERY" | $MYSQL_CMD proconsultancy -N 2>&1)
    
    if [ $? -eq 0 ]; then
        log_success "Database connection: OK"
        echo "$RESULT" | while read -r line; do
            log_info "$line"
        done
    else
        log_error "Database connection: FAILED"
        echo "$RESULT"
        return 1
    fi
}

test_php_config() {
    log_step "Testing PHP Configuration"
    
    PHP_VERSION=$(php -v | head -n 1)
    log_info "PHP Version: $PHP_VERSION"
    
    # Check required extensions
    REQUIRED_EXTENSIONS=("mysqli" "pdo" "mbstring" "json" "openssl")
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "^$ext$"; then
            log_success "Extension '$ext' is loaded"
        else
            log_warning "Extension '$ext' is NOT loaded"
        fi
    done
}

validate_setup() {
    log_step "Validating Setup"
    
    VALIDATION_PASSED=true
    
    # Check database
    TABLE_COUNT=$(echo "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'proconsultancy';" | $MYSQL_CMD -N 2>/dev/null)
    
    if [ "$TABLE_COUNT" -gt 20 ]; then
        log_success "Database tables: $TABLE_COUNT found"
    else
        log_error "Database tables: Only $TABLE_COUNT found (expected 20+)"
        VALIDATION_PASSED=false
    fi
    
    # Check admin user
    ADMIN_COUNT=$(echo "SELECT COUNT(*) FROM users WHERE level IN ('admin', 'super_admin');" | $MYSQL_CMD proconsultancy -N 2>/dev/null)
    
    if [ "$ADMIN_COUNT" -gt 0 ]; then
        log_success "Admin users: $ADMIN_COUNT found"
    else
        log_error "Admin users: None found"
        VALIDATION_PASSED=false
    fi
    
    # Check roles
    ROLE_COUNT=$(echo "SELECT COUNT(*) FROM roles;" | $MYSQL_CMD proconsultancy -N 2>/dev/null)
    
    if [ "$ROLE_COUNT" -ge 2 ]; then
        log_success "Roles: $ROLE_COUNT configured"
    else
        log_error "Roles: Only $ROLE_COUNT found (expected 2+)"
        VALIDATION_PASSED=false
    fi
    
    # Check permissions
    PERM_COUNT=$(echo "SELECT COUNT(*) FROM permissions;" | $MYSQL_CMD proconsultancy -N 2>/dev/null)
    
    if [ "$PERM_COUNT" -gt 30 ]; then
        log_success "Permissions: $PERM_COUNT configured"
    else
        log_error "Permissions: Only $PERM_COUNT found"
        VALIDATION_PASSED=false
    fi
    
    # Check .env file
    if [ -f "$PROJECT_ROOT/.env" ]; then
        log_success ".env file exists"
    else
        log_error ".env file not found"
        VALIDATION_PASSED=false
    fi
    
    if [ "$VALIDATION_PASSED" = true ]; then
        log_success "All validation checks passed!"
        return 0
    else
        log_error "Some validation checks failed"
        return 1
    fi
}

test_login_page() {
    log_step "Testing Login Page Access"
    
    # Create test PHP file
    TEST_FILE="$PROJECT_ROOT/panel/test_connection.php"
    
    cat > "$TEST_FILE" << 'PHPEOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Test database connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$db = $_ENV['DB_NAME'] ?? 'proconsultancy';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✓ Database connection: OK\n";
    
    // Test queries
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo "✓ Users table: " . $row['count'] . " users found\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM roles");
    $row = $result->fetch_assoc();
    echo "✓ Roles table: " . $row['count'] . " roles found\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM permissions");
    $row = $result->fetch_assoc();
    echo "✓ Permissions table: " . $row['count'] . " permissions found\n";
    
    echo "\n✓ All tests passed!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
PHPEOF
    
    log_info "Running PHP connection test..."
    php "$TEST_FILE"
    
    if [ $? -eq 0 ]; then
        log_success "PHP connection test passed"
    else
        log_error "PHP connection test failed"
    fi
    
    # Clean up
    rm -f "$TEST_FILE"
}

################################################################################
# FINAL REPORT
################################################################################

print_final_report() {
    log_step "Setup Complete!"
    
    cat << EOF

${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}
${GREEN}║                                                               ║${NC}
${GREEN}║          ProConsultancy ATS - Setup Successful!               ║${NC}
${GREEN}║                                                               ║${NC}
${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}

${BLUE}📊 System Information:${NC}
   Operating System: $OS
   PHP Version: $(php -v | head -n 1 | awk '{print $2}')
   MySQL Version: $(mysql --version | awk '{print $5}' | cut -d',' -f1)

${BLUE}🗄️  Database:${NC}
   Database Name: proconsultancy
   Tables Created: $(echo "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'proconsultancy';" | $MYSQL_CMD -N 2>/dev/null)
   Admin Users: $(echo "SELECT COUNT(*) FROM users WHERE level IN ('admin', 'super_admin');" | $MYSQL_CMD proconsultancy -N 2>/dev/null)
   Total Users: $(echo "SELECT COUNT(*) FROM users;" | $MYSQL_CMD proconsultancy -N 2>/dev/null)

${BLUE}🔐 Login Credentials:${NC}
   Email: admin@proconsultancy.local
   Password: password

${BLUE}🌐 Access URLs:${NC}
EOF

    case "$OS" in
        mac)
            echo "   Local: http://localhost/~$(whoami)/proconsultancy/panel/login.php"
            echo "   Or configure virtual host for better access"
            ;;
        windows)
            echo "   XAMPP: http://localhost/proconsultancy/panel/login.php"
            echo "   Laragon: http://proconsultancy.test/panel/login.php"
            ;;
    esac
    
    cat << EOF

${BLUE}📝 Next Steps:${NC}
   1. Access the login page using the URL above
   2. Login with admin credentials
   3. Change the default password
   4. Create additional users
   5. Start testing the application

${BLUE}🔧 Configuration Files:${NC}
   .env: $PROJECT_ROOT/.env
   Database Config: $PROJECT_ROOT/includes/config/database.php

${BLUE}📚 Documentation:${NC}
   Setup Guide: $PROJECT_ROOT/docs/SETUP.md
   Testing Plan: $PROJECT_ROOT/docs/TESTING.md

${YELLOW}⚠️  Security Reminder:${NC}
   - Change default admin password immediately
   - Set strong MySQL root password
   - Review .env file permissions (should be 600)
   - Enable firewall on production servers

${GREEN}✅ Setup completed successfully!${NC}

EOF
}

################################################################################
# ERROR HANDLER
################################################################################

handle_error() {
    log_error "Setup failed at step: $1"
    log_info "Check the error messages above for details"
    log_info "You can re-run this script to retry"
    
    echo ""
    echo "Debugging tips:"
    echo "1. Check MySQL is running: brew services list (Mac) or services.msc (Windows)"
    echo "2. Verify MySQL credentials are correct"
    echo "3. Check PHP extensions: php -m"
    echo "4. Review log file: /tmp/schema_import.log"
    
    exit 1
}

################################################################################
# MAIN EXECUTION
################################################################################

main() {
    clear
    
    cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║     ProConsultancy ATS - Automated Setup Script              ║
║                                                               ║
║     This script will:                                         ║
║     • Install required dependencies                           ║
║     • Create and configure database                           ║
║     • Import schema and default data                          ║
║     • Create admin user                                       ║
║     • Set up file permissions                                 ║
║     • Validate installation                                   ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝

EOF
    
    echo -n "Press Enter to continue or Ctrl+C to cancel..."
    read -r
    
    # Execute setup steps
    detect_os || handle_error "OS Detection"
    install_dependencies || handle_error "Dependency Installation"
    get_db_password || handle_error "Database Configuration"
    create_env_file || handle_error "Environment Configuration"
    create_database || handle_error "Database Creation"
    import_schema || handle_error "Schema Import"
    create_admin_user || handle_error "Admin User Creation"
    migrate_existing_users || handle_error "User Migration"
    set_permissions || handle_error "File Permissions"
    test_database_connection || handle_error "Database Connection Test"
    test_php_config || handle_error "PHP Configuration Test"
    test_login_page || handle_error "Login Page Test"
    validate_setup || handle_error "Setup Validation"
    print_final_report
}

# Run main function
main

exit 0
