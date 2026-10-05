#!/bin/bash
# Movida desde vps-setup/lib.sh: ningun script la llamaba.
# Verify UFW is actually enabled, not just installed
ufw_check_enabled() {
    if ! sudo ufw status | grep -q "^Status: active"; then
        echo "ERROR: UFW firewall is not active. Did 01-system-update.sh run first?"
        return 1
    fi
    return 0
}
