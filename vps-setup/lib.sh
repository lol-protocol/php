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

# Safe UFW rule (only if ufw is active)
ufw_allow() {
    local rule=$1
    if ! sudo ufw status | grep -q "^Status: active"; then
        echo "WARNING: UFW is not active, skipping rule: $rule"
        return 0
    fi
    sudo ufw allow "$rule" || echo "WARNING: Failed to add UFW rule: $rule"
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
    local install_guidance=$2

    if ! command -v "$command" &> /dev/null; then
        echo "ERROR: $command no esta instalado."
        echo "Corre primero: $install_guidance"
        exit 1
    fi
}

# Create Nginx domain log directories.
# Must stay root-owned: Nginx opens logs as root, so a www-data-writable log dir
# lets a compromised app symlink its way to root (CVE-2016-1247).
setup_nginx_domain_logs() {
    local domain=$1
    sudo mkdir -p /var/log/nginx/"$domain"
}

# Setup app directories with correct ownership
setup_app_directories() {
    local app_path=$1
    local domain=$2

    sudo mkdir -p "$app_path"
    sudo chown -R www-data:www-data "$app_path"
    setup_nginx_domain_logs "$domain"
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

# Get public IP (cached if already fetched this session)
get_public_ip() {
    # Check if already cached in this session
    if [ -n "$CACHED_PUBLIC_IP" ]; then
        echo "$CACHED_PUBLIC_IP"
        return 0
    fi

    local ip
    ip=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || echo "")
    if [ -z "$ip" ]; then
        ip="TU_IP_PUBLICA"
    fi

    # Cache for this session
    CACHED_PUBLIC_IP="$ip"
    echo "$ip"
}

# Batch DNS lookups (avoid multiple dig calls)
verify_dns_resolution() {
    local domain=$1
    local expected_ip=$2

    # Single dig call to check both domain and www.domain in one go
    local ips
    ips=$(dig +short -t A "$domain" www."$domain" 2>/dev/null | sort -u)

    if [ -z "$ips" ]; then
        echo "WARNING: $domain does not resolve yet. DNS propagation can take 15-60 minutes."
        return 1
    fi

    # Check if expected IP is in the results
    if ! echo "$ips" | grep -q "^${expected_ip}$"; then
        echo "WARNING: $domain resolves to [$ips], expected $expected_ip"
        return 1
    fi

    return 0
}
