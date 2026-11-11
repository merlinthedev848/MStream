# Known Issues

This file will include the majority of the known issues we're aware of with MStream and that are in the stages of
review / investigation / fixing. We don't provide a date on the fixes but rest assured we're aware of them.

## Metadata Updates with Live DJs

When a live streamer/DJ connects to your stream, only the first metadata _update_ is received, rather than the initial
metadata when the client connects. This is often reported as MStream "missing" the first song that a DJ broadcasts.
This is an [upstream issue](https://github.com/savonet/liquidsoap/issues/2109) with Liquidsoap that we are continuing to
follow.

**Shipped workarounds:** empty harbor metadata is replaced with the configured live-broadcast placeholder
(`metadata.map(insert_missing, live)`); the latest non-empty harbor metadata chunk is captured and re-inserted when
falling over to live; WebDJ sends its current title/artist immediately on socket open (and again after connect
confirmation). Connecting while playing a very short intro/ID track remains a useful belt-and-suspenders tip for
broadcast clients that never emit an initial metadata chunk.

## WebDJ Audio Quality Issues

The WebDJ feature historically could broadcast crackly or intermittent audio on some browsers (especially Chromium) when
the client used main-thread `ScriptProcessorNode` copies and browser microphone processing (AEC/AGC/NS). See the
original report lineage in [AzuraCast #5116](https://github.com/AzuraCast/AzuraCast/issues/5116).

MStream's WebDJ path has been upgraded toward the modern webcast.js MediaRecorder flow:

- GainNode mix/cue bus (no ScriptProcessor on the encode path)
- AnalyserNode metering instead of ScriptProcessor RMS taps
- Preferred Opus mime selection (`audio/ogg;codecs=opus` when supported, else WebM Opus)
- 48 kHz / 256 kbps defaults and 250 ms MediaRecorder timeslices
- Microphone capture with echo cancellation / AGC / noise suppression disabled
- Existing webcast socket ring-buffer / backpressure / reconnect hardening retained

Residual browser MediaRecorder quirks can still appear on some Chromium builds; Firefox + HTTPS remains the most
reliable combination for live browser DJ sessions.

## Can't Skip Songs After DJ Disconnects

Many users have requested the ability for Liquidsoap to skip to the next song automatically after a DJ disconnects.
This is also an [upstream issue](https://github.com/savonet/liquidsoap/issues/2107) with Liquidsoap that we are
continuing to follow.

**Shipped workaround:** the live harbor `check_live` frame hook skips AutoDJ both when entering live and when leaving
live (gated on `live_enabled` so brief harbor readiness flaps mid-show do not skip). Harbor `on_disconnect` runs
synchronously so the leave-live skip happens on the next frame. Crossfade edge cases with buffered fade-out fragments
may still surface with unusual cue points; report those with Liquidsoap logs if they persist.

## Playlist Scheduling Problems

Users intermittently report problems with our AutoDJ scheduler not working correctly. These issues are often very
difficult for us to diagnose, even with the appropriate logs, as they are intermittent and hard to reproduce on other
installations. If you're a developer and you're interested in helping us with this, please let us know.

One known issue is that "Scheduled" type playlists always take priority over "General Rotation" playlists, which only
play when _no_ scheduled playlist is available to play. This is the intended functionality of MStream, but some users
prefer non-exclusive schedule entries. The current workaround to this is to make scheduled copies of the General
Rotation playlists (even possibly scheduling them for all-day blocks), but we are looking into better solutions for
this.

## Ansible Issues

Our Ansible installation is no longer officially supported with our team's developer resources. Ansible installations
represent less than 5% of our total installed user base, and contribute much more to our support burden than that. If
you're experiencing Ansible issues, we would gladly review and accept any pull requests to help resolve it, but we will
not be devoting our own resources toward these issues.
