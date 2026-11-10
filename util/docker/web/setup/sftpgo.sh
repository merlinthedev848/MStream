#!/bin/bash
set -e
set -x

export SFTPGO_VERSION=2.6.4

# Per-architecture LS installs
ARCHITECTURE=amd64
if [[ "$(uname -m)" = "aarch64" ]]; then
    ARCHITECTURE=arm64
fi

wget -O /tmp/sftpgo.deb "https://github.com/drakkan/sftpgo/releases/download/v${SFTPGO_VERSION}/sftpgo_${SFTPGO_VERSION}-1_${ARCHITECTURE}.deb"

dpkg -i /tmp/sftpgo.deb
apt-get install -y -f --no-install-recommends
rm -f /tmp/sftpgo.deb

mkdir -p /var/mstream/sftpgo/persist \
  /var/mstream/sftpgo/backups \
  /var/mstream/sftpgo/env.d

cp /bd_build/web/sftpgo/sftpgo.json /var/mstream/sftpgo/sftpgo.json

touch /var/mstream/sftpgo/sftpgo.db
chown -R mstream:mstream /var/mstream/sftpgo

# Create sftpgo temp dir
mkdir -p /tmp/sftpgo_temp
touch /tmp/sftpgo_temp/.tmpreaper
chmod -R 777 /tmp/sftpgo_temp
