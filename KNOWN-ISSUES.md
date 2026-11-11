# Known Issues

This file will include the majority of the known issues we're aware of with MStream and that are in the stages of
review / investigation / fixing. We don't provide a date on the fixes but rest assured we're aware of them.

## Metadata Updates with Live DJs

When a live streamer/DJ connects to your stream, only the first metadata _update_ is received, rather than the initial
metadata when the client connects. This is often reported as MStream "missing" the first song that a DJ broadcasts.
This is an [upstream issue](https://github.com/savonet/liquidsoap/issues/2109) with Liquidsoap that we are continuing to
follow. The easy workaround for most users is to connect while playing a very short track (such as an intro or station
ID), then immediately transition to another song after connecting, which triggers a second metadata update and fixes the
issue.

## WebDJ Audio Quality Issues

The WebDJ feature, on some browsers and in some situations, can broadcast sound that seems "crackly" and intermittent to
stream listeners. This is a known issue, but the cause of it is currently
unknown. [#5116](https://github.com/merlinthedev848/MStream/issues/5116)

The upstream Webstreamer software that we use has been updated and may possibly resolve this, but incorporating it will
require significant development effort on our end.

## Can't Skip Songs After DJ Disconnects

Many users have requested the ability for Liquidsoap to skip to the next song automatically after a DJ disconnects.
This is also an [upstream issue](https://github.com/savonet/liquidsoap/issues/2107) with Liquidsoap that we are
continuing to follow.

## Playlist Scheduling Problems

Users intermittently report problems with our AutoDJ scheduler not working correctly. These issues are often very
difficult for us to diagnose, even with the appropriate logs, as they are intermittent and hard to reproduce on other
installations. If you're a developer and you're interested in helping us with this, please let us know.

By default, a **scheduled** playlist occupies an exclusive AutoDJ priority bucket above unscheduled playlists of the
same type: while any exclusive scheduled General Rotation playlist is eligible, unscheduled General Rotation will not
play. That remains the default and matches long-standing MStream behavior.

For **General Rotation** playlists only, you can opt into non-exclusive mixing: on the playlist **Advanced** tab, enable
**Include in general rotation while scheduled (non-exclusive)**. While its schedule windows are active, that playlist
joins the same weighted pool as other General Rotation playlists instead of suppressing them. Once-per-x / once-per-hour
types stay exclusive when scheduled (safe default for IDs and jingles). Interrupting schedules always remain exclusive.

The Schedule tab copy documents this operator-facing meaning. When playlists are also written into Liquidsoap as a
failover, General Rotation non-exclusive schedules use a schedule-gated source in the standard `random()` pool so the
backup path matches the PHP AutoDJ; playlist groups and request rules remain PHP-only.

## Ansible Issues

Our Ansible installation is no longer officially supported with our team's developer resources. Ansible installations
represent less than 5% of our total installed user base, and contribute much more to our support burden than that. If
you're experiencing Ansible issues, we would gladly review and accept any pull requests to help resolve it, but we will
not be devoting our own resources toward these issues.
