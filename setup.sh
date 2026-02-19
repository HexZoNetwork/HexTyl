#!/bin/bash

# HexTyl Panel Setup Script (Powered by Pterodactyl)
# Handles dependencies, database, and Nginx setup.

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting HexTyl Panel Setup...${NC}"

# Check for Root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}This script must be run as root.${NC}" 
   exit 1
fi

# 1. Install Dependencies
echo -e "${YELLOW}[1/4] Installing System Dependencies...${NC}"
apt-get update -q
apt-get install -y -q software-properties-common curl apt-transport-https ca-certificates gnupg

# Add PHP Repository (ondrej/php)
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -q
fi

# Install PHP and extensions
apt-get install -y -q php8.3 php8.3-{common,cli,gd,mysql,mbstring,bcmath,xml,fpm,curl,zip,intl} mariadb-server nginx tar unzip git redis-server composer

# Ensure MariaDB is running
if [ ! -d "/run/mysqld" ]; then
    mkdir -p /run/mysqld
    chown mysql:mysql /run/mysqld
fi
service mariadb start

# 2. Configure Database
echo -e "${YELLOW}[2/4] Configuring Database...${NC}"
read -p "Enter Database Name (default: hextyl): " DB_NAME
DB_NAME=${DB_NAME:-hextyl}
read -p "Enter Database User (default: hextyl): " DB_USER
DB_USER=${DB_USER:-hextyl}
read -s -p "Enter Database Password: " DB_PASS
echo ""
read -p "Domain Name (e.g., panel.example.com): " DOMAIN
echo ""

# Wait for MariaDB to be ready
echo "Waiting for MariaDB to start..."
while ! mysqladmin ping --silent; do
    sleep 1
done

mysql -u root -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
# Create for 127.0.0.1
mysql -u root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mysql -u root -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1' WITH GRANT OPTION;"
# Create for localhost (socket fallback) to prevent 'Access denied' if Laravel uses socket
mysql -u root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -u root -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost' WITH GRANT OPTION;"
mysql -u root -e "FLUSH PRIVILEGES;"

# Verify connection immediately
echo "Verifying database connection..."
if ! mysql -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 -e "quit" 2>/dev/null; then
    echo -e "${RED}Error: Failed to connect to database with created user! Check permissions.${NC}"
    exit 1
fi

echo -e "${GREEN}Database configured.${NC}"

# 3. Panel Setup
echo -e "${YELLOW}[3/4] Installing Panel Dependencies...${NC}"
# Assuming we are in the panel directory or cloning it. 
# For this script, we assume it's running IN the panel directory or we copy files to /var/www/hextyl
APP_DIR="/var/www/hextyl"

if [ "$PWD" != "$APP_DIR" ]; then
    echo "Creating application directory at $APP_DIR..."
    mkdir -p $APP_DIR
    cp -r . $APP_DIR
    cd $APP_DIR
fi

# Create required storage directories
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

chmod -R 755 storage/* bootstrap/cache/

# Install Composer Dependencies
# Setup .env
# Generate .env directly to avoid copy/sed issues
echo "Generating .env file..."

# Generate APP_KEY using PHP CLI to avoid Artisan boot issues
APP_KEY_VAL="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
echo "Generated Application Key: ${APP_KEY_VAL}"

cat > .env <<EOF
APP_ENV=production
APP_DEBUG=false
APP_KEY=${APP_KEY_VAL}
APP_TIMEZONE=UTC
APP_URL=http://${DOMAIN}
APP_LOCALE=en
APP_THEME=pterodactyl

LOG_CHANNEL=stack
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=60

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Pterodactyl Panel"

HASHIDS_SALT=$(tr -dc 'a-zA-Z0-9' < /dev/urandom | head -c 20)
HASHIDS_LENGTH=8
EOF

# Configure .env with DB credentials
# (Debug echos removed)

# Install Composer Dependencies FIRST
echo "Installing Composer Dependencies..."
composer install --no-dev --optimize-autoloader --no-scripts

# Now that vendor exists, we can run artisan commands
# Clear config cache (Key is already set in .env)
php artisan config:clear

# Discovery
composer dump-autoload --optimize
composer dump-autoload --optimize

# Clear again to be safe
php artisan config:clear
php artisan view:clear

# Run Migrations
echo "Running Migrations..."
# Force DB credentials via environment variables to bypass any .env loading issues
DB_CONNECTION=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE=${DB_NAME} \
DB_USERNAME=${DB_USER} \
DB_PASSWORD=${DB_PASS} \
php artisan migrate --force --seed

# 4. Nginx Configuration
echo -e "${YELLOW}[4/4] Configuring Nginx...${NC}"
# Use existing DOMAIN (captured earlier)
read -p "Use SSL? (y/n): " USE_SSL

if [ "$USE_SSL" == "y" ]; then
    # Install Certbot
    apt-get install -y -q certbot python3-certbot-nginx
    
    # 1. Stop Nginx to release port 80 for standalone verification
    service nginx stop || true
    
    # 2. Generate Certificate via Standalone (robust)
    # We implicitly accept TOS and use a placeholder email or prompt? 
    # Let's use register-unsafely-without-email to allow automation without prompt
    certbot certonly --standalone -d ${DOMAIN} --non-interactive --agree-tos --register-unsafely-without-email
    
    # 3. Write Config referencing the NEW certs
    cat > /etc/nginx/sites-available/hextyl.conf <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;

    access_log /var/log/nginx/hextyl.app-access.log;
    error_log  /var/log/nginx/hextyl.app-error.log error;

    # allow larger file uploads and longer script runtimes
    client_max_body_size 100m;
    client_body_timeout 120s;

    sendfile off;

    # Security Headers
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Robots-Tag none;
    add_header Content-Security-Policy "frame-ancestors 'self'";
    add_header X-Frame-Options DENY;
    add_header Referrer-Policy same-origin;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
else
    cat > /etc/nginx/sites-available/hextyl.conf <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;

    access_log /var/log/nginx/hextyl.app-access.log;
    error_log  /var/log/nginx/hextyl.app-error.log error;

    # allow larger file uploads and longer script runtimes
    client_max_body_size 100m;
    client_body_timeout 120s;

    sendfile off;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
fi

# 5. Queue & Cron Setup
echo -e "${YELLOW}[5/5] Configuring Background Services...${NC}"

# Create Queue Worker Service
cat > /etc/systemd/system/pteroq.service <<EOF
# Pterodactyl Queue Worker File
# ----------------------------------

[Unit]
Description=Pterodactyl Queue Worker
After=redis-server.service

[Service]
# On some systems the user and group might be different.
# Some systems use \`apache\` or \`nginx\` as the user and group.
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --queue=high,standard,low --sleep=3 --tries=3
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF

# Enable and Start Queue Worker
echo "Enabling Queue Worker..."
systemctl daemon-reload
systemctl enable --now pteroq.service

# Setup Cronjob (runs every minute)
echo "Setting up Crontab..."
CRON_CMD="* * * * * php ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
# Check if cron already exists to avoid duplication
(crontab -l 2>/dev/null | grep -F "${CRON_CMD}") || (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -

# 6. Frontend Setup (Fixes ManifestDoesNotExistException)
echo -e "${YELLOW}[6/6] Building Frontend Assets...${NC}"

# Install Node.js 22.x (Required by package.json)
if ! command -v node &> /dev/null; then
    echo "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y -q nodejs
fi

# Install Yarn
if ! command -v yarn &> /dev/null; then
    echo "Installing Yarn..."
    npm install -g yarn
fi

# Build Assets
echo "Installing frontend dependencies..."
yarn install

# Ensure assets directory exists for 'clean' script
mkdir -p public/assets

echo "Compiling assets for production..."
yarn run build:production

# Finalize Nginx / Permissions
ln -sf /etc/nginx/sites-available/hextyl.conf /etc/nginx/sites-enabled/hextyl.conf
rm -f /etc/nginx/sites-enabled/default
service nginx restart

# Fix Permissions (Critical for 500 Error)
echo -e "${YELLOW}Setting file permissions...${NC}"
chown -R www-data:www-data $APP_DIR/*
# Ensure storage is writable
chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache
echo -e "${GREEN}Setup Complete! You can now access your panel at http://${DOMAIN} or https://${DOMAIN}${NC"
echo -e "${YELLOW}Next Steps: Create your first user using: php artisan p:user:make${NC}"
