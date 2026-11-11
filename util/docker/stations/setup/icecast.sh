#!/bin/bash
set -e
set -x

# Install Icecast 2 and configure standard MStream paths.
DEBIAN_FRONTEND=noninteractive apt-get install -q -y --no-install-recommends \
    icecast2 libxml2 openssl

ln -sf /usr/bin/icecast2 /usr/local/bin/icecast
ln -sf /usr/share/icecast2 /usr/local/share/icecast

# Package error.mp3 + bitrate/format-matched fallback-* into the Icecast webroot
# so AutoDJ mounts with an empty fallback_mount resolve to real files on source drop.
bash /bd_build/stations/icecast/install_web_assets.sh
