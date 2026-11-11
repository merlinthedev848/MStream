#!/bin/bash
# Install Icecast webroot assets used as mount fallbacks when a source drops.
# Generates bitrate/format-matched fallback-* files from the packaged error jingle
# (see util/generate_fallback_files.liq for the Liquidsoap equivalent).
set -e
set -x

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_WEB="${SCRIPT_DIR}/web"
ICECAST_WEB="/usr/local/share/icecast/web"

mkdir -p "${ICECAST_WEB}"

if [[ ! -f "${SOURCE_WEB}/error.mp3" ]]; then
  echo "Missing packaged Icecast error jingle at ${SOURCE_WEB}/error.mp3" >&2
  exit 1
fi

cp -f "${SOURCE_WEB}/error.mp3" "${ICECAST_WEB}/error.mp3"

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg is required to generate Icecast fallback-* assets" >&2
  exit 1
fi

SOURCE_ERROR="${ICECAST_WEB}/error.mp3"
BITRATES=(32 48 64 96 128 192 256 320)

for bitrate in "${BITRATES[@]}"; do
  ffmpeg -y -hide_banner -loglevel error \
    -i "${SOURCE_ERROR}" \
    -c:a libmp3lame -b:a "${bitrate}k" -ar 44100 -ac 2 \
    "${ICECAST_WEB}/fallback-[${bitrate}].mp3"

  ffmpeg -y -hide_banner -loglevel error \
    -i "${SOURCE_ERROR}" \
    -c:a aac -b:a "${bitrate}k" -ar 44100 -ac 2 \
    "${ICECAST_WEB}/fallback-[${bitrate}].aac"

  ffmpeg -y -hide_banner -loglevel error \
    -i "${SOURCE_ERROR}" \
    -c:a libopus -b:a "${bitrate}k" -ar 48000 -ac 2 \
    "${ICECAST_WEB}/fallback-[${bitrate}].opus"

  # Ogg/Vorbis set matches util/generate_fallback_files.liq (no 32kbps variant).
  if [[ "${bitrate}" -ne 32 ]]; then
    ffmpeg -y -hide_banner -loglevel error \
      -i "${SOURCE_ERROR}" \
      -c:a libvorbis -b:a "${bitrate}k" -ar 44100 -ac 2 \
      "${ICECAST_WEB}/fallback-[${bitrate}].ogg"
  fi
done

# Assert the assets AutoDJ empty-fallback mounts and Liquidsoap expect.
test -f "${ICECAST_WEB}/error.mp3"
test -f "${ICECAST_WEB}/fallback-[128].mp3"
test -f "${ICECAST_WEB}/fallback-[64].mp3"
test -f "${ICECAST_WEB}/fallback-[192].aac"
test -f "${ICECAST_WEB}/fallback-[128].ogg"
test -f "${ICECAST_WEB}/fallback-[128].opus"

echo "Installed Icecast fallback web assets into ${ICECAST_WEB}"
