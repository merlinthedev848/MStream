#!/bin/bash

MSTREAM_PUID="${MSTREAM_PUID:-1000}"
MSTREAM_PGID="${MSTREAM_PGID:-1000}"

PUID="${PUID:-$MSTREAM_PUID}"
PGID="${PGID:-$MSTREAM_PGID}"

if id -u mstream >/dev/null 2>&1; then
    groupmod -o -g "$PGID" mstream || true
    usermod -o -u "$PUID" mstream || true
fi

echo "Docker 'mstream' User UID: $PUID"
echo "Docker 'mstream' User GID: $PGID"
