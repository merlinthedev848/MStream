#!/usr/bin/env bash
# ==============================================================================
#  MStream - 1-Line Turnkey Automated Installer & Setup Wizard
#  Enterprise Audio Broadcast & Stream Management Suite
#  Repository: https://github.com/merlinthedev848/MStream
# ==============================================================================

# If invoked via 'sh' (e.g. 'sudo sh install.sh' or 'dash'), re-exec with 'bash'
if [ -z "${BASH_VERSION:-}" ]; then
    if command -v bash >/dev/null 2>&1; then
        if [ -f "$0" ]; then
            exec bash "$0" "$@"
        fi
    else
        echo "Error: bash is required to run the MStream installer." >&2
        exit 1
    fi
fi

set -e
set -u
if [ -n "${BASH_VERSION:-}" ]; then
    set -o pipefail 2>/dev/null || true
fi

# ANSI Color Palette
CLR_RESET="\033[0m"
CLR_BOLD="\033[1m"
CLR_CYAN="\033[36m"
CLR_BLUE="\033[34m"
CLR_PURPLE="\033[35m"
CLR_GREEN="\033[32m"
CLR_YELLOW="\033[33m"
CLR_RED="\033[31m"

# Print Banner
print_banner() {
    echo -e "${CLR_CYAN}${CLR_BOLD}"
    cat << "EOF"
========================================================================
   __  __ ____  _                               
  |  \/  / ___|| |_ _ __ ___  __ _ _ __ ___    
  | |\/| \___ \| __| '__/ _ \/ _` | '_ ` _ \   
  | |  | |___) | |_| | |  __/ (_| | | | | | |  
  |_|  |_|____/ \__|_|  \___|\__,_|_| |_| |_|  
  ENTERPRISE AUDIO BROADCAST & STREAMING SUITE
========================================================================
EOF
    echo -e "${CLR_RESET}"
}

INSTALL_DIR="/var/mstream"
UNATTENDED=false
DEFAULT_HTTP_PORT="80"
DEFAULT_HTTPS_PORT="443"
DEFAULT_SFTP_PORT="2022"
DEFAULT_RELEASE="latest"

# Parse Command-Line Arguments
while [ $# -gt 0 ]; do
    case "$1" in
        -y|--yes|--unattended)
            UNATTENDED=true
            shift
            ;;
        --dir)
            INSTALL_DIR="$2"
            shift 2
            ;;
        --http-port)
            DEFAULT_HTTP_PORT="$2"
            shift 2
            ;;
        --https-port)
            DEFAULT_HTTPS_PORT="$2"
            shift 2
            ;;
        --sftp-port)
            DEFAULT_SFTP_PORT="$2"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done

print_banner

# Check Root / Sudo Privileges
if [ "${EUID:-$(id -u)}" -ne 0 ]; then
    echo -e "${CLR_RED}[ERROR]${CLR_RESET} This script must be run as root or with sudo:"
    echo -e "  ${CLR_BOLD}curl -fsSL https://raw.githubusercontent.com/merlinthedev848/MStream/main/install.sh | sudo bash${CLR_RESET}"
    echo -e "  or: ${CLR_BOLD}sudo bash install.sh${CLR_RESET}"
    exit 1
fi

# Detect Architecture
ARCH=$(uname -m)
case "$ARCH" in
    x86_64)  ARCH_NAME="x86_64" ;;
    aarch64|arm64) ARCH_NAME="arm64" ;;
    *)
        echo -e "${CLR_RED}[ERROR]${CLR_RESET} Unsupported architecture: $ARCH. MStream supports x86_64 and arm64/aarch64."
        exit 1
        ;;
esac

echo -e "${CLR_GREEN}[PASS]${CLR_RESET} Detected architecture: ${CLR_BOLD}${ARCH_NAME}${CLR_RESET}"

# Install System Package Dependencies (Cached check: skip network index fetch if already present)
echo -e "\n${CLR_BLUE}==>${CLR_RESET} ${CLR_BOLD}Checking system prerequisites...${CLR_RESET}"

install_packages() {
    local missing=0
    for bin in curl git tar awk openssl; do
        if ! command -v "$bin" >/dev/null 2>&1; then
            missing=1
            break
        fi
    done

    if [ "$missing" -eq 0 ]; then
        return 0
    fi

    if command -v apt-get >/dev/null 2>&1; then
        apt-get update -y
        apt-get install -y --no-install-recommends curl git tar gawk openssl ca-certificates util-linux
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y curl git tar gawk openssl ca-certificates util-linux
    elif command -v yum >/dev/null 2>&1; then
        yum install -y curl git tar gawk openssl ca-certificates util-linux
    elif command -v pacman >/dev/null 2>&1; then
        pacman -Sy --noconfirm curl git tar gawk openssl ca-certificates util-linux
    elif command -v zypper >/dev/null 2>&1; then
        zypper --non-interactive install curl git tar gawk openssl ca-certificates util-linux
    fi
}
install_packages >/dev/null 2>&1 || true

# Check & Install Docker
echo -e "${CLR_BLUE}==>${CLR_RESET} ${CLR_BOLD}Verifying Docker Engine and Docker Compose...${CLR_RESET}"

if ! command -v docker >/dev/null 2>&1; then
    echo -e "      ${CLR_YELLOW}[INFO]${CLR_RESET} Docker not detected. Automatically installing official Docker Engine..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable --now docker >/dev/null 2>&1 || service docker start >/dev/null 2>&1 || true
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Docker installed successfully."
else
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Docker is already installed ($(docker --version))."
fi

# Ensure docker service is running
systemctl start docker >/dev/null 2>&1 || service docker start >/dev/null 2>&1 || true

# Check Docker Compose v2
if docker compose version >/dev/null 2>&1; then
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Docker Compose v2 is available ($(docker compose version --short))."
elif command -v docker-compose >/dev/null 2>&1; then
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} docker-compose standalone is available."
else
    echo -e "      ${CLR_YELLOW}[INFO]${CLR_RESET} Installing Docker Compose plugin..."
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update -y && apt-get install -y docker-compose-plugin || true
    fi
    if ! docker compose version >/dev/null 2>&1; then
        DOCKER_CONFIG=${DOCKER_CONFIG:-/usr/local/lib/docker}
        mkdir -p "$DOCKER_CONFIG/cli-plugins"
        curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-${ARCH_NAME}" -o "$DOCKER_CONFIG/cli-plugins/docker-compose"
        chmod +x "$DOCKER_CONFIG/cli-plugins/docker-compose"
    fi
fi

# Determine Working Directory
CURRENT_DIR="$(pwd)"
if [ -f "${CURRENT_DIR}/docker-compose.sample.yml" ] && [ -f "${CURRENT_DIR}/mstream.sample.env" ]; then
    WORK_DIR="${CURRENT_DIR}"
    echo -e "\n${CLR_BLUE}==>${CLR_RESET} Using current repository directory: ${CLR_BOLD}${WORK_DIR}${CLR_RESET}"
else
    WORK_DIR="${INSTALL_DIR}"
    echo -e "\n${CLR_BLUE}==>${CLR_RESET} Target installation directory: ${CLR_BOLD}${WORK_DIR}${CLR_RESET}"
    mkdir -p "${WORK_DIR}"
    cd "${WORK_DIR}"

    if [ ! -d .git ]; then
        echo -e "      Cloning MStream repository..."
        git clone --depth 1 https://github.com/merlinthedev848/MStream.git .
    else
        echo -e "      Updating existing MStream repository..."
        git fetch --depth 1 origin main >/dev/null 2>&1 || true
        git reset --hard origin/main 2>/dev/null || git pull origin main || true
    fi
fi
cd "${WORK_DIR}"

# Safe interactive input function (supports curl | bash and direct run)
prompt_read() {
    prompt_text="$1"
    default_value="$2"
    var_name="$3"
    input_result=""

    if [ "$UNATTENDED" = true ]; then
        eval "$var_name=\"$default_value\""
        return
    fi

    if [ -e /dev/tty ] && [ -r /dev/tty ]; then
        echo -n -e "$prompt_text" > /dev/tty
        read -r input_result < /dev/tty || input_result=""
    elif [ -t 0 ]; then
        echo -n -e "$prompt_text"
        read -r input_result || input_result=""
    fi

    input_result="${input_result:-$default_value}"
    eval "$var_name=\"$input_result\""
}

# Interactive Wizard
HTTP_PORT="${DEFAULT_HTTP_PORT}"
HTTPS_PORT="${DEFAULT_HTTPS_PORT}"
SFTP_PORT="${DEFAULT_SFTP_PORT}"
RELEASE_CHANNEL="${DEFAULT_RELEASE}"

if [ "$UNATTENDED" = false ] && ([ -e /dev/tty ] || [ -t 0 ]); then
    echo -e "\n${CLR_PURPLE}${CLR_BOLD}--- MStream Interactive Setup Wizard ---${CLR_RESET}"
    echo -e "Press ${CLR_BOLD}[Enter]${CLR_RESET} to accept the recommended default value for each prompt.\n"

    # Prompt 1: Release Channel
    echo -e "${CLR_BOLD}[1/4] Select Release Channel:${CLR_RESET}"
    echo -e "  1) Rolling Release (latest features, cutting edge) [Recommended]"
    echo -e "  2) Stable (production-hardened release versions)"
    prompt_read "Enter choice [1]: " "1" CHOICE_REL
    if [ "$CHOICE_REL" = "2" ]; then
        RELEASE_CHANNEL="stable"
    else
        RELEASE_CHANNEL="latest"
    fi

    # Prompt 2: HTTP Port
    prompt_read "Enter HTTP web port [${DEFAULT_HTTP_PORT}]: " "${DEFAULT_HTTP_PORT}" HTTP_PORT

    # Prompt 3: HTTPS Port
    prompt_read "Enter HTTPS secure port [${DEFAULT_HTTPS_PORT}]: " "${DEFAULT_HTTPS_PORT}" HTTPS_PORT

    # Prompt 4: SFTP Port
    prompt_read "Enter SFTP file transfer port [${DEFAULT_SFTP_PORT}]: " "${DEFAULT_SFTP_PORT}" SFTP_PORT

    echo -e "\n${CLR_GREEN}[OK]${CLR_RESET} Configuration selected:"
    echo -e "     Release Channel: ${CLR_BOLD}${RELEASE_CHANNEL}${CLR_RESET}"
    echo -e "     HTTP Port:       ${CLR_BOLD}${HTTP_PORT}${CLR_RESET}"
    echo -e "     HTTPS Port:      ${CLR_BOLD}${HTTPS_PORT}${CLR_RESET}"
    echo -e "     SFTP Port:       ${CLR_BOLD}${SFTP_PORT}${CLR_RESET}"
fi

# Automatic Provisioning of Configurations (Minimized Disk I/O)
echo -e "\n${CLR_BLUE}==>${CLR_RESET} ${CLR_BOLD}Provisioning configuration files...${CLR_RESET}"

# 1. Provision docker-compose.yml (only write if missing or changed)
if [ ! -f docker-compose.yml ] || ! cmp -s docker-compose.sample.yml docker-compose.yml; then
    cp docker-compose.sample.yml docker-compose.yml
fi
echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Synchronized docker-compose.yml"

# 2. Provision .env
if [ ! -f .env ]; then
    cp sample.env .env
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Created .env"
fi

# 3. Provision mstream.env
if [ ! -f mstream.env ]; then
    cp mstream.sample.env mstream.env
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Created mstream.env"
fi

# Generate cryptographically secure database password in a single pass if using default
if grep -q "MYSQL_PASSWORD=mstream_secret" mstream.env 2>/dev/null; then
    RANDOM_PASS=$(openssl rand -hex 16 2>/dev/null || date +%s%N | sha256sum | head -c 32)
    sed -i "s/MYSQL_PASSWORD=mstream_secret/MYSQL_PASSWORD=${RANDOM_PASS}/g" mstream.env
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Generated unique secure database password."
fi

# Batch-apply all wizard port & release selections to .env in a single atomic disk write
awk -v proj="mstream" \
    -v ver="${RELEASE_CHANNEL}" \
    -v env="production" \
    -v img="ghcr.io/merlinthedev848/mstream" \
    -v upd="containrrr/watchtower" \
    -v http="${HTTP_PORT}" \
    -v https="${HTTPS_PORT}" \
    -v sftp="${SFTP_PORT}" \
    -v puid="1000" \
    -v pgid="1000" '
BEGIN {
    updates["COMPOSE_PROJECT_NAME"] = proj
    updates["MSTREAM_VERSION"] = ver
    updates["APPLICATION_ENV"] = env
    updates["MSTREAM_IMAGE"] = img
    updates["MSTREAM_UPDATER_IMAGE"] = upd
    updates["MSTREAM_HTTP_PORT"] = http
    updates["MSTREAM_HTTPS_PORT"] = https
    updates["MSTREAM_SFTP_PORT"] = sftp
    updates["MSTREAM_PUID"] = puid
    updates["MSTREAM_PGID"] = pgid
}
{
    split($0, parts, "=")
    key = parts[1]
    if (key in updates) {
        print key "=" updates[key]
        seen[key] = 1
    } else {
        print $0
    }
}
END {
    for (k in updates) {
        if (!(k in seen)) {
            print k "=" updates[k]
        }
    }
}' .env > .env.tmp && mv .env.tmp .env

# Ensure control scripts are executable and register systemd unit in parallel
chmod +x mstream.sh docker.sh install.sh util/docker/web/scripts/* 2>/dev/null &
CHMOD_PID=$!

# Compose command alias
if docker compose version >/dev/null 2>&1; then
    DC_CMD="docker compose"
else
    DC_CMD="docker-compose"
fi

# Register non-blocking systemd service with real-time I/O & CPU scheduling priority for audio engine
register_systemd_service() {
    if command -v systemctl >/dev/null 2>&1 && [ -d /etc/systemd/system ]; then
        cat << SYSTEMD_EOF > /etc/systemd/system/mstream.service
[Unit]
Description=MStream Broadcast & Audio Streaming Server
After=network-online.target docker.service
Wants=network-online.target
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=${WORK_DIR}
EnvironmentFile=-${WORK_DIR}/.env
Nice=-10
IOSchedulingClass=realtime
IOSchedulingPriority=0
LimitRTPRIO=95
LimitMEMLOCK=infinity
ExecStart=/bin/sh -c '${DC_CMD} up -d'
ExecStop=/bin/sh -c '${DC_CMD} down'
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
SYSTEMD_EOF
        systemctl daemon-reload >/dev/null 2>&1 || true
        systemctl enable mstream.service >/dev/null 2>&1 || true
    fi
}
register_systemd_service &
SYSTEMD_PID=$!

# Start public IP detection asynchronously in background
IP_TMP_FILE=$(mktemp /tmp/mstream_ip.XXXXXX 2>/dev/null || echo "/tmp/mstream_ip_$$")
(curl -4 -s --max-time 3 https://icanhazip.com 2>/dev/null || hostname -I 2>/dev/null | awk '{print $1}' || echo "your-server-ip") > "${IP_TMP_FILE}" &
IP_PID=$!

# Launch Containers (Parallel Image Pulls)
echo -e "\n${CLR_BLUE}==>${CLR_RESET} ${CLR_BOLD}Preparing Docker containers and starting MStream services...${CLR_RESET}"

MSTREAM_TARGET_IMAGE="ghcr.io/merlinthedev848/mstream:${RELEASE_CHANNEL}"
UPDATER_TARGET_IMAGE="containrrr/watchtower:latest"

echo -e "      Pulling container images in parallel..."
docker pull "${UPDATER_TARGET_IMAGE}" >/dev/null 2>&1 &
PULL_UPDATER_PID=$!

PULL_MSTREAM_OK=0
(docker pull "${MSTREAM_TARGET_IMAGE}" >/dev/null 2>&1) &
PULL_MSTREAM_PID=$!

wait "$PULL_UPDATER_PID" 2>/dev/null || true
if wait "$PULL_MSTREAM_PID" 2>/dev/null; then
    PULL_MSTREAM_OK=1
fi
wait "$CHMOD_PID" 2>/dev/null || true

if [ "$PULL_MSTREAM_OK" -eq 1 ]; then
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Pulled ${MSTREAM_TARGET_IMAGE}"
else
    echo -e "      ${CLR_CYAN}[INFO]${CLR_RESET} Building local MStream image (${MSTREAM_TARGET_IMAGE})..."
    DOCKER_BUILDKIT=1 docker build --target final -t "${MSTREAM_TARGET_IMAGE}" .
    echo -e "      ${CLR_GREEN}[PASS]${CLR_RESET} Built ${MSTREAM_TARGET_IMAGE}"
fi

$DC_CMD up -d

# Wait for Web Service Initialization and Nginx listening on port 80/443
echo -e "\n${CLR_BLUE}==>${CLR_RESET} ${CLR_BOLD}Waiting for MStream core services to start up...${CLR_RESET}"
for i in $(seq 1 30); do
    if $DC_CMD exec -T web true >/dev/null 2>&1; then
        break
    fi
    echo -n "."
    sleep 2
done
echo ""

# Ensure database migrations and initial setup run safely
echo -e "${CLR_BLUE}==>${CLR_RESET} ${CLR_BOLD}Verifying automated database migrations and setup...${CLR_RESET}"
$DC_CMD exec -T web composer dump-autoload --optimize 2>/dev/null || true
# app_startup inside the container handles mstream:setup --init automatically; run only as fallback if needed
$DC_CMD exec -T web /usr/local/bin/mstream_cli mstream:setup --init 2>/dev/null || true

# Collect background tasks
wait "$SYSTEMD_PID" 2>/dev/null || true
wait "$IP_PID" 2>/dev/null || true
SERVER_IP=$(cat "${IP_TMP_FILE}" 2>/dev/null | tr -d '[:space:]')
rm -f "${IP_TMP_FILE}" 2>/dev/null || true
SERVER_IP="${SERVER_IP:-your-server-ip}"

# Print Completion Card
echo -e "\n${CLR_GREEN}${CLR_BOLD}"
cat << "EOF"
========================================================================
   MSTREAM INSTALLATION COMPLETED SUCCESSFULLY!
========================================================================
EOF
echo -e "${CLR_RESET}"

echo -e "  ${CLR_BOLD}Access your MStream Web Dashboard:${CLR_RESET}"
if [ "$HTTP_PORT" = "80" ]; then
    echo -e "  ${CLR_CYAN}${CLR_BOLD}http://${SERVER_IP}${CLR_RESET}"
else
    echo -e "  ${CLR_CYAN}${CLR_BOLD}http://${SERVER_IP}:${HTTP_PORT}${CLR_RESET}"
fi

if [ "$HTTPS_PORT" = "443" ]; then
    echo -e "  ${CLR_CYAN}${CLR_BOLD}https://${SERVER_IP}${CLR_RESET} (Once SSL is configured)"
else
    echo -e "  ${CLR_CYAN}${CLR_BOLD}https://${SERVER_IP}:${HTTPS_PORT}${CLR_RESET} (Once SSL is configured)"
fi

echo -e "\n  ${CLR_BOLD}Installation Directory:${CLR_RESET} ${WORK_DIR}"
echo -e "  ${CLR_BOLD}Management Utility:${CLR_RESET}     ./mstream.sh"

echo -e "\n  ${CLR_BOLD}Quick CLI Examples:${CLR_RESET}"
echo -e "    ${CLR_CYAN}./mstream.sh up${CLR_RESET}           # Start services"
echo -e "    ${CLR_CYAN}./mstream.sh down${CLR_RESET}         # Stop services"
echo -e "    ${CLR_CYAN}./mstream.sh update${CLR_RESET}       # Update MStream to latest version"
echo -e "    ${CLR_CYAN}./mstream.sh backup${CLR_RESET}       # Create full system backup"
echo -e "    ${CLR_CYAN}./mstream.sh restore <file>${CLR_RESET} # Restore from backup"

echo -e "\n${CLR_YELLOW}${CLR_BOLD}Next Step:${CLR_RESET} Open the URL in your browser to create your Super Administrator account."
echo -e "${CLR_GREEN}========================================================================${CLR_RESET}\n"
