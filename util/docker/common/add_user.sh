#!/bin/bash
set -e
set -x

apt-get install -y --no-install-recommends sudo

# Workaround for sudo errors in containers, see: https://github.com/sudo-project/sudo/issues/42
echo "Set disable_coredump false" >> /etc/sudo.conf

adduser --home /var/mstream --disabled-password --gecos "" mstream

usermod -aG www-data mstream

mkdir -p /var/mstream/www /var/mstream/stations /var/mstream/www_tmp \
  /var/mstream/backups \
  /var/mstream/dbip \
  /var/mstream/storage/uploads \
  /var/mstream/storage/shoutcast2 \
  /var/mstream/storage/stereo_tool \
  /var/mstream/storage/geoip \
  /var/mstream/storage/sftpgo \
  /var/mstream/storage/acme

chown -R mstream:mstream /var/mstream
chmod -R 777 /var/mstream/www_tmp

echo 'mstream ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers
