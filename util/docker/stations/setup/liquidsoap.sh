#!/bin/bash
set -e
set -x

# Packages required by Liquidsoap
apt-get install -y --no-install-recommends \
    ffmpeg bubblewrap \
    libao4 libfaad2 libfdk-aac2 libgd3 liblo7 libmad0 libmagic1 libportaudio2 \
    libsdl2-image-2.0-0 libsdl2-ttf-2.0-0 libsoundtouch1 libxpm4 libpulse0 \
    libsamplerate0 libtag2 libsrt1.5-openssl liblilv-0-0

# Audio Post-processing
apt-get install -y --no-install-recommends ladspa-sdk

# Per-architecture LS installs
curl -fsSL https://repo.liquidsoap.info/setup.sh | sh -s -- --channel v2.4.6

apt-get install -y --no-install-recommends liquidsoap

# ARCHITECTURE=amd64
# if [[ "$(uname -m)" = "aarch64" ]]; then
#   ARCHITECTURE=arm64
# fi
# wget -O /tmp/liquidsoap.deb "https://github.com/savonet/liquidsoap-release-assets/releases/download/rolling-release-v2.4.x/liquidsoap-ff8dd94_2.4.3-debian-trixie-ocaml4.14.2-1_${ARCHITECTURE}.deb"
# dpkg -i /tmp/liquidsoap.deb
# apt-get install -y -f --no-install-recommends
# rm -f /tmp/liquidsoap.deb

ln -s /usr/bin/liquidsoap /usr/local/bin/liquidsoap

# Add temp directories for caching.
mkdir -p /tmp/liquidsoap_cache
chown -R mstream:mstream /tmp/liquidsoap_cache

mkdir -p /var/mstream/www_tmp/liquidsoap_cache
chown -R mstream:mstream /var/mstream/www_tmp/liquidsoap_cache

# Pre-warm the initial LS opcode cache.
export LIQ_CACHE_SYSTEM_DIR=/tmp/liquidsoap_cache
export LIQ_CACHE_USER_DIR=/var/mstream/www_tmp/liquidsoap_cache

gosu mstream liquidsoap --cache-stdlib

# Add Common MStream Functions
mkdir -p /var/mstream/liquidsoap
cp /bd_build/stations/liquidsoap/* /var/mstream/liquidsoap
chown -R mstream:mstream /var/mstream/liquidsoap
