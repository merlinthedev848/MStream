#!/bin/bash

export DEBIAN_FRONTEND=noninteractive

echo "Creating persist directories..."

mkdir -p \
  /var/mstream/storage/acme \
  /var/mstream/storage/geoip \
  /var/mstream/storage/rsas \
  /var/mstream/storage/sftpgo \
  /var/mstream/storage/shoutcast2 \
  /var/mstream/storage/stereo_tool \
  /var/mstream/storage/uploads

chown mstream:mstream /var/mstream/storage/* || true
