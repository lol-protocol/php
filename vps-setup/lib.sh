#!/bin/bash
# Shared shell functions for VPS setup scripts
# Source this file at the start of each setup script: source "$(dirname "$0")/lib.sh"

# Print colored header for each installation step
print_header() {
    local step_num=$1
    local step_name=$2
    echo "========================================"
    echo "[$step_num] $step_name"
    echo "========================================"
    echo ""
}

# Start and enable a systemd service
service_start_enable() {
    local service=$1
    sudo systemctl start "$service"
    sudo systemctl enable "$service"
}

# Safe UFW rule (only if ufw exists)
ufw_allow() {
    local rule=$1
    if command -v ufw &> /dev/null; then
        sudo ufw allow "$rule" || true
    fi
}

# Verify UFW is actually enabled, not just installed
ufw_check_enabled() {
    if ! sudo ufw status | grep -q "^Status: active"; then
        echo "ERROR: UFW firewall is not active. Did 01-system-update.sh run first?"
        return 1
    fi
    return 0
}

# Check if a required command/package is installed
check_dependency() {
    local command=$1
    local script_name=$2
    local install_guidance=$3

    if ! command -v "$command" &> /dev/null; then
        echo "ERROR: $command is not installed."
        echo "Please run first: $install_guidance"
        exit 1
    fi
}

# Get public IP with fallback
get_public_ip() {
    local ip
    ip=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || echo "")
    if [ -z "$ip" ]; then
        echo "TU_IP_PUBLICA"
    else
        echo "$ip"
    fi
}

# Create Nginx domain log directories
setup_nginx_domain_logs() {
    local domain=$1
    sudo mkdir -p /var/log/nginx/"$domain"
    sudo chown -R www-data:www-data /var/log/nginx/"$domain"
}

# Setup app directories with correct ownership
setup_app_directories() {
    local app_path=$1
    local domain=$2

    sudo mkdir -p "$app_path"
    sudo mkdir -p /var/log/nginx/"$domain"
    sudo chown -R www-data:www-data "$app_path"
    sudo chown -R www-data:www-data /var/log/nginx/"$domain"
}

# Deploy landing page or app files
deploy_files() {
    local source_dir=$1
    local dest_dir=$2

    if [ ! -d "$source_dir" ]; then
        echo "ERROR: Source directory does not exist: $source_dir"
        return 1
    fi

    sudo cp -r "$source_dir"/* "$dest_dir" 2>/dev/null || sudo cp -r "$source_dir" "$dest_dir"
    sudo find "$dest_dir" -type f -exec chmod 644 {} \;
    sudo find "$dest_dir" -type d -exec chmod 755 {} \;
    sudo chown -R www-data:www-data "$dest_dir"
}

# Verify DNS resolution before SSL
verify_dns_resolution() {
    local domain=$1
    local expected_ip=$2

    local resolved_ip
    resolved_ip=$(dig +short -t A "$domain" @8.8.8.8 2>/dev/null | head -1)

    if [ -z "$resolved_ip" ]; then
        echo "WARNING: $domain does not resolve yet. DNS propagation can take 15-60 minutes."
        return 1
    fi

    if [ "$resolved_ip" != "$expected_ip" ]; then
        echo "WARNING: $domain resolves to $resolved_ip, expected $expected_ip"
        return 1
    fi

    return 0
}

# Create or update Nginx site configuration
create_nginx_site() {
    local domain=$1
    local root_path=$2
    local config_type=${3:-static}  # static, php, python-proxy, tomcat-proxy

    setup_nginx_domain_logs "$domain"

    local site_config="/etc/nginx/sites-available/${domain}"
    local location_block=""
    local upstream_block=""

    case "$config_type" in
        php)
            location_block=$(cat <<'EOFPHP'
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
EOFPHP
)
            ;;
        python-proxy)
            upstream_block="upstream gunicorn_app { server 127.0.0.1:8000; }"
            location_block=$(cat <<'EOFPY'
    location / {
        proxy_pass http://gunicorn_app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
EOFPY
)
            ;;
        tomcat-proxy)
            upstream_block="upstream tomcat_app { server 127.0.0.1:8080; }"
            location_block=$(cat <<'EOFTOMCAT'
    location / {
        proxy_pass http://tomcat_app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
EOFTOMCAT
)
            ;;
        *)
            # static
            location_block="    # Static files"
            ;;
    esac

    # Write basic HTTP block (Certbot will add HTTPS later)
    sudo tee "$site_config" > /dev/null <<EOFNGINX
server {
    listen 80;
    listen [::]:80;
    server_name $domain www.$domain;

    root $root_path;
    index index.html index.php;

    access_log /var/log/nginx/$domain/access.log;
    error_log /var/log/nginx/$domain/error.log;

$location_block
}
EOFNGINX

    # Enable the site
    sudo ln -sf /etc/nginx/sites-available/"${domain##*/}" /etc/nginx/sites-enabled/"${domain##*/}"

    # Validate configuration
    sudo nginx -t || return 1
    sudo systemctl reload nginx || return 1
}
