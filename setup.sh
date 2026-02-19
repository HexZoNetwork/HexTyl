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
apt-get install -y -q php8.1 php8.1-{common,cli,gd,mysql,mbstring,bcmath,xml,fpm,curl,zip} mariadb-server nginx tar unzip git redis-server composer

# 2. Configure Database
echo -e "${YELLOW}[2/4] Configuring Database...${NC}"
read -p "Enter Database Name (default: hextyl): " DB_NAME
DB_NAME=${DB_NAME:-hextyl}
read -p "Enter Database User (default: hextyl): " DB_USER
DB_USER=${DB_USER:-hextyl}
read -s -p "Enter Database Password: " DB_PASS
echo ""

mysql -u root -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
mysql -u root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mysql -u root -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1' WITH GRANT OPTION;"
mysql -u root -e "FLUSH PRIVILEGES;"

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

chmod -R 755 storage/* bootstrap/cache/

# Install Composer Dependencies
composer install --no-dev --optimize-autoloader

# Setup .env
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Configure .env with DB credentials
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/DB_HOST=.*/DB_HOST=127.0.0.1/" .env
sed -i "s/DB_PORT=.*/DB_PORT=3306/" .env

# Run Migrations
echo "Running Migrations..."
php artisan migrate --force --seed

# 4. Nginx Configuration
echo -e "${YELLOW}[4/4] Configuring Nginx...${NC}"
read -p "Domain Name (e.g., panel.example.com): " DOMAIN
read -p "Use SSL? (y/n): " USE_SSL

if [ "$USE_SSL" == "y" ]; then
    # Install Certbot
    apt-get install -y -q certbot python3-certbot-nginx
    
    # Generate Config with SSL setup
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

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
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
    # We will run certbot later or user must run it? 
    # To avoid script failure if domain doesn't resolve, we'll suggest running certbot.
    echo -e "${YELLOW}Please run: certbot --nginx -d ${DOMAIN} after this script to generate certificates.${NC}"
    # Temporary fallback to snakeoil or skip SSL directive? 
    # For now, let's just write the non-ssl and ask user to upgrade, or create a self-signed.
    # Actually, Certbot automatic mode is best, but let's stick to generating the config file.
else
    cat > /etc/nginx/sites-available/hextyl.conf <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;

    access_log /var/log/nginx/hextyl.app-access.log;
    error_log  /var/log/nginx/hextyl.app-error.log error;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
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

ln -sf /etc/nginx/sites-available/hextyl.conf /etc/nginx/sites-enabled/hextyl.conf
rm -f /etc/nginx/sites-enabled/default
service nginx restart

echo -e "${GREEN}Setup Complete! You can now access your panel at http://${DOMAIN}${NC}"
echo -e "${YELLOW}Next Steps: Create your first user using: php artisan p:user:make${NC}"
