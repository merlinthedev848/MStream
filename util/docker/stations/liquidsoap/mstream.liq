#<-----
MStream Common Runtime
----->#

# Default Settings for MStream Instances
init.daemon := false

log.stdout := true
log.file := false

settings.harbor.bind_addrs := ["0.0.0.0"]
settings.encoder.metadata.export := ["artist","title","album","song"]

settings.autocue.amplify_behavior := "keep"

#<-----
Settings
----->#

let settings.mstream = ()

let settings.mstream.liquidsoap_api_port = settings.make(
    description="The port number used by Liquidsoap for its HTTP API.",
    8004
)

let settings.mstream.api_url = settings.make(
    description="The API URL used by MStream API calls.",
    ""
)

let settings.mstream.api_key = settings.make(
    description="An API key authorized to make internal API calls.",
    ""
)

let settings.mstream.media_path = settings.make(
    description="The base path for local media, or 'api' to make API calls to retrieve media.'",
    "api"
)

let settings.mstream.fallback_path = settings.make(
    description="The path for the fallback error message played when no audio is available.",
    "/usr/local/share/icecast/web/error.mp3"
)

let settings.mstream.temp_path = settings.make(
    description="A temporary path to write ephemeral files (like recordings and HLS segments).",
    "/tmp"
)

let settings.mstream.compute_autocue = settings.make(
    description="Whether to compute autocue metadata on-the-fly if MStream does not have values specified.",
    false
)

let settings.mstream.default_fade = settings.make(
    description="The default fade-in/fade-out value (in seconds).",
    0.0
)

let settings.mstream.default_cross = settings.make(
    description="The default cross duration (in seconds).",
    0.0
)

let settings.mstream.enable_crossfade = settings.make(
    description="Whether to enable crossfading by default.",
    false
)

let settings.mstream.crossfade_type = settings.make(
    description="The type of crossfade (if enabled), between 'normal' and 'smart'.",
    "normal"
)

let settings.mstream.crossfade_smart_high = settings.make(
    description="Smart crossfade: the dB level above which a track is considered 'loud'.",
    -15.0
)

let settings.mstream.crossfade_smart_medium = settings.make(
    description="Smart crossfade: the dB level below which a track is considered quiet ('medium').",
    -32.0
)

let settings.mstream.crossfade_smart_margin = settings.make(
    description="Smart crossfade: the dB difference above which two tracks are considered too different to overlap.",
    8.0
)

let settings.mstream.request_timeout = settings.make(
    description="The timeout for requests (i.e. song requests and 'Next Song' resolution), in seconds.",
    20.0
)

let settings.mstream.http_timeout = settings.make(
    description="The timeout for HTTP API requests, in seconds.",
    10.0
)

let settings.mstream.live_broadcast_text = settings.make(
    description="The placeholder metadata when live DJs connect with no initial metadata.",
    "Live Broadcast"
)

let settings.mstream.apply_amplify = settings.make(
    description="Apply amplification metadata to the stream signal.",
    true
)

#<-----
Variables
----->#

let mstream = ()

# True if the first "next song" hasn't been received yet, false if it has.
let mstream.autodj_is_loading = ref(true)

# Number of attempts that have been made to ping the AutoDJ for the next song.
let mstream.autodj_ping_attempts = ref(0)

# The username/display name of the last authenticated DJ.
let mstream.last_authenticated_dj = ref("")
let mstream.last_authenticated_dj_name = ref("")

# The username/display name of the current live DJ.
let mstream.live_dj = ref("")
let mstream.live_dj_name = ref("")

# Whether Liquidsoap is currently transitioning to a live stream.
let mstream.to_live = ref(false)

# Whether a DJ is successfully authenticated (and thus live broadcasting is enabled).
let mstream.live_enabled = ref(false)

# The last non-jingle artist and title metadata.
let mstream.last_title = ref("")
let mstream.last_artist = ref("")

#<-----
Utility Functions
----->#

%include "./crossfade.liq"
%include "./utilities.liq"

# Transport for HTTPS outputs.
https_transport = http.transport.ssl()
ignore(https_transport)

#<-----
Liquidsoap HTTP API
----->#

def mstream.http_api_log_requests(req, res, next)
    started_at = time()
    next(req, res)
    req_duration = time() - started_at

    log(label="http", "#{req.method} #{req.path} #{res.status_code.current()} #{req_duration}")
end

harbor.http.middleware.register(mstream.http_api_log_requests)

def mstream.http_api_check_token(req, res, next)
    if req.headers["x-liquidsoap-api-key"] != settings.mstream.api_key() then
        res.status_code(401)
    else
        next(req, res)
    end
end

harbor.http.middleware.register(mstream.http_api_check_token)

def mstream.start_http_api(~port=null) =
    port = port ?? settings.mstream.liquidsoap_api_port()
    server.harbor(port=port)
end

#<-----
API Utilities
----->#

def mstream.api_call(~timeout=null, endpoint_url, payload) =
    timeout = timeout ?? settings.mstream.http_timeout()
    full_url = "#{settings.mstream.api_url()}/#{endpoint_url}"

    log(
        level=3,
        label="mstream.api",
        "API #{endpoint_url} - Sending POST request to '#{full_url}' with body: #{payload}"
    )

    try
        response = http.post(full_url,
            headers=[
                ("Content-Type", "application/json"),
                ("User-Agent", "Liquidsoap MStream"),
                ("X-Liquidsoap-Api-Key", "#{settings.mstream.api_key()}")
            ],
            timeout=timeout,
            data=payload
        )

        log(
            level=3,
            label="mstream.api",
            "API #{endpoint_url} - Response (#{response.status_code}): #{response}"
        )

        if response.status_code != 200 then
            null
        else
            "#{response}"
        end
    catch err do
        log(
            level=2,
            label="mstream.api",
            "API #{endpoint_url} - Error: #{error.kind(err)} - #{error.message(err)}"
        )

        null
    end
end

# MStream protocol implementation.
def mstream.media_protocol(~rlog:_,~maxtime,arg) =
    station_media_dir = settings.mstream.media_path()
    if station_media_dir == "api" then
        timeout = 1000.0 * (maxtime - time())

        j = json()
        j.add("uri", arg)

        try
            api_response = mstream.api_call(timeout=timeout, "cp", json.stringify(j))

            if null.defined(api_response) then
                let json.parse (
                    {
                        uri,
                        isTemp
                    } : {
                        uri: string,
                        isTemp: bool
                    }
                ) = null.get(api_response)

                if isTemp then
                    "tmp:#{uri}"
                else
                    "#{uri}"
                end
            else
                null
            end
        catch err do
            log(
                level=1,
                label="mstream.media_protocol",
                "ERROR parsing JSON: #{err}"
            )

            null
        end
    else
        "#{station_media_dir}/#{arg}"
    end
end

protocol.add(
    "media",
    mstream.media_protocol,
    doc="Pull files from MStream media directory.",
    syntax="media:uri"
)

#<-----
AutoCue
----->#

def mstream.autocue(
    ~request_metadata,
    ~file_metadata,
    filename
) =
    label="mstream.autocue"

    # Check if `"mstream_autocue"` is present and set to `"true"`. If so, use mstream autocue implementation:
    if list.assoc(default="false", "mstream_autocue", request_metadata) == "true" then
        def mstream_optional_start_next =
            if list.assoc.mem("autocue_start_next", request_metadata) then
                { start_next = float_of_string(list.assoc("autocue_start_next", request_metadata)) }
            else
                { }
            end
        end

        def mstream_optional_amplify =
            if list.assoc.mem("liq_amplify", request_metadata) then
                { amplify = list.assoc("liq_amplify", request_metadata) }
            else
                { }
            end
        end

        autocue_result = {
            cue_in = float_of_string(list.assoc("autocue_cue_in", request_metadata)),
            cue_out = float_of_string(list.assoc("autocue_cue_out", request_metadata)),
            fade_in = float_of_string(list.assoc("autocue_fade_in", request_metadata)),
            fade_out = float_of_string(list.assoc("autocue_fade_out", request_metadata)),
            ...mstream_optional_start_next,
            ...mstream_optional_amplify
        }

        log(
            level=3,
            label=label,
            "MStream AutoCue data: #{autocue_result}."
        )

        autocue_result
    elsif settings.mstream.compute_autocue() then
        log(
            level=2,
            label=label,
            "Cache missed; computing AutoCue values."
        )

        let autocue_result = autocue.internal.implementation(
            request_metadata=request_metadata,
            file_metadata=file_metadata,
            filename
        )

        # Save cache back to MStream.
        if list.assoc(default="", "mstream_cache_key", request_metadata) != "" then
            cache_key = list.assoc("mstream_cache_key", request_metadata);
            log(
                level=2,
                label=label,
                "Saving cache to MStream (key #{cache_key})"
            )

            def f() =
                _ = mstream.api_call(
                    timeout=5.0,
                    "savecache",
                    json.stringify({
                        cache_key = cache_key,
                        data = autocue_result
                    })
                )
            end

            thread.run(fast=false, f)
        end

        autocue_result
    else
        null
    end
end

enable_autocue_metadata()
autocue.register(name="mstream", mstream.autocue)
settings.autocue.preferred := "mstream"

#<-----
AutoDJ
----->#

# AutoDJ Next Song Script
def mstream.autodj_next_song() =
    try
        api_response = mstream.api_call(
            "nextsong",
            ""
        )

        if null.defined(api_response) then
            let json.parse (
                {
                    uri,
                } : {
                    uri: string,
                }
            ) = null.get(api_response)

            request.create(uri)
        else
            null
        end
    catch err do
        log(
            level=1,
            label="mstream.autodj",
            "ERROR parsing JSON: #{err}"
        )

        null
    end
end

# Delayed ping for AutoDJ Next Song
def mstream.wait_for_next_song(autodj) =
    mstream.autodj_ping_attempts := mstream.autodj_ping_attempts() + 1

    if source.is_ready(autodj) then
        log(
            level=2,
            label="mstream.autodj",
            "AutoDJ is ready!"
        )

        mstream.autodj_is_loading := false
        -1.0
    elsif mstream.autodj_ping_attempts() > 200 then
        log(
            level=1,
            label="mstream.autodj",
            "ERROR: AutoDJ could not be initialized within the specified timeout."
        )

        mstream.autodj_is_loading := false
        -1.0
    else
        0.5
    end
end

def mstream.enable_autodj(s) =
    dynamic = request.dynamic(
        id="next_song",
        timeout=settings.mstream.request_timeout(),
        retry_delay=10.,
        mstream.autodj_next_song
    )

    dynamic_startup = fallback(
        id = "dynamic_startup",
        track_sensitive = false,
        [
            dynamic,
            source.available(
                blank(id = "autodj_startup_blank", duration = 120.),
                predicate.activates({mstream.autodj_is_loading()})
            )
        ]
    )

    s = fallback(id="autodj_fallback", track_sensitive = true, [dynamic_startup, s])

    ref_dynamic = ref(dynamic);
    thread.run.recurrent(delay=0.25, { mstream.wait_for_next_song(ref_dynamic()) })

    s
end

#<-----
Live Streamers/DJs
----->#

# DJ Authentication
def mstream.dj_auth(auth_info) =
    try
        api_response = mstream.api_call(
            timeout=5.0,
            "auth",
            json.stringify(auth_info)
        )

        if null.defined(api_response) then
            let json.parse (
                {
                    allow,
                    username,
                    display_name,
                } : {
                    allow: bool,
                    username: string?,
                    display_name: string?,
                }
            ) = null.get(api_response)

            if allow then
                mstream.last_authenticated_dj := null.get(default='', username)
                mstream.last_authenticated_dj_name := null.get(
                    default=settings.mstream.live_broadcast_text(),
                    display_name
                )

                true
            else
                false
            end
        else
            false
        end
    catch err do
        log(
            level=1,
            label="mstream.streamer",
            "ERROR parsing JSON: #{err}"
        )

        false
    end
end

def mstream.live_connected(header) =
    dj = mstream.last_authenticated_dj()
    dj_name = mstream.last_authenticated_dj_name()

    log(
        level=2,
        label="mstream.dj",
        "DJ Source connected! Last authenticated DJ: #{dj} (#{dj_name}) - #{header}"
    )

    mstream.live_enabled := true
    mstream.live_dj := dj
    mstream.live_dj_name := dj_name

    def f() =
        _ = mstream.api_call(
            timeout=5.0,
            "djon",
            json.stringify({
                user = mstream.live_dj()
            })
        )
    end

    thread.run(fast=false, f)
end

def mstream.live_disconnected() =
    mstream.live_enabled := false

    def f() =
        _ = mstream.api_call(
            timeout=5.0,
            "djoff",
            json.stringify({
                user = mstream.live_dj()
            })
        )

        mstream.live_dj := ""
        mstream.live_dj_name := ""
    end

    thread.run(fast=false, f)
end

#<-----
Live-aware Crossfade
----->#

def mstream.live_aware_crossfade_impl(old, new) =
    log.info(label="mstream.crossfade", "Crossfading")
    list.iter(
        fun (m) -> log.info(label="mstream.crossfade", "Old metadata: #{fst(m)} -> #{snd(m)}"),
        metadata.cover.remove(old.metadata)
    )
    list.iter(
        fun (m) -> log.info(label="mstream.crossfade", "New metadata: #{fst(m)} -> #{snd(m)}"),
        metadata.cover.remove(new.metadata)
    )
    if mstream.to_live() then
        # If going to the live show, play a simple sequence
        # fade out AutoDJ, do (almost) not fade in streamer
        log.info(label="mstream.crossfade", "Fading to live...")
        sequence([
            fade.out(duration=settings.mstream.default_fade(), old.source),
            fade.in(duration=settings.mstream.default_fade(), new.source)
        ])
    elsif settings.mstream.enable_crossfade() then
        if settings.mstream.crossfade_type() == "smart" then
            log.info(label="mstream.crossfade", "Smart crossfade...")
            cross.smart(
                old,
                new,
                high=settings.mstream.crossfade_smart_high(),
                medium=settings.mstream.crossfade_smart_medium(),
                margin=settings.mstream.crossfade_smart_margin(),
                fade_in=settings.mstream.default_fade(),
                fade_out=settings.mstream.default_fade()
            )
        else
            log.info(label="mstream.crossfade", "Simple crossfade...")
            cross.simple(
                old.source,
                new.source,
                fade_in=settings.mstream.default_fade(),
                fade_out=settings.mstream.default_fade()
            )
        end
    else
        # Otherwise, use a beautiful add
        log.info(label="mstream.crossfade", "Add crossfade...")
        add(normalize=false, [
            fade.in(
                initial_metadata=new.metadata,
                duration=settings.mstream.default_fade(),
                new.source
            ),
            fade.out(
                initial_metadata=old.metadata,
                duration=settings.mstream.default_fade(),
                old.source
            )
        ])
    end
end

def mstream.apply_crossfade(s) =
    cross(
        duration=settings.mstream.default_cross(),
        mstream.live_aware_crossfade_impl,
        s
    )
end

#<-----
Feedback API
----->#

# Send metadata changes back to MStream
def mstream.send_feedback(m) =
    if (m["is_error_file"] != "true") then
        if (m["title"] != mstream.last_title() or m["artist"] != mstream.last_artist()) then
            mstream.last_title := m["title"]
            mstream.last_artist := m["artist"]

            # Only send some metadata to MStream
            def fl(k, _) =
                tags = ["song_id", "media_id", "playlist_id", "sq_id", "artist", "title"]
                list.mem(k, tags)
            end

            feedback_meta = list.assoc.filter((fl), metadata.cover.remove(m))

            j = json()
            for item = list.iterator(feedback_meta) do
                let (tag, value) = item
                j.add(tag, value)
            end

            payload = json.stringify(compact=true, j)
            def send_async() =
                _ = mstream.api_call(
                    "feedback",
                    payload
                )
            end
            thread.run(fast=false, send_async)
        end
    end
end

#<-----
Fallback
----->#

def mstream.add_fallback(s) =
    error_file = single(id="error_jingle", "annotate:liq_disable_autocue=\"true\":#{settings.mstream.fallback_path()}")

    def tag_error_file(_) =
        [("is_error_file", "true")]
    end
    error_file = metadata.map(tag_error_file, error_file)

    s = mksafe(fallback(id="safe_fallback", track_sensitive = false, [s, error_file]))
    s
end

def mstream.handle_jingle_mode(s) =
    last_metadata = ref([])

    def handle_jingle_mode(m) =
        if (m["jingle_mode"] == "true") then
            last_metadata()
        else
            last_metadata.set(m)
            m
        end
    end

    s = metadata.map(update=false, strip=true, handle_jingle_mode, s)
    s
end

def mstream.apply_amplify(s) =
    if (settings.mstream.apply_amplify()) then
        (amplify(1., s) : source)
    else
        s
    end
end

#<-----
Debugging
----->#

def mstream.log_meta(m) =
    label="log_meta"
    l = list.sort.natural(metadata.cover.remove(m))
    list.iter(fun(v) -> log(level=4, label=label, "#{v}"), l)

    nowplaying = ref(m["artist"] ^ " - " ^ m["title"])

    if m["artist"] == "" then
        if string.contains(substring=" - ", m["title"]) then
            let (a, t) = string.split.first(separator=" - ", m["title"])
            nowplaying := a ^ " - " ^ t
        end
    end

    # show `liq_` & other metadata in level 3
    def fl(k, _) =
        tags = ["duration", "media_id", "replaygain_track_gain", "replaygain_reference_loudness"]
        string.contains(prefix="mstream_", k) or string.contains(prefix="liq_", k) or list.mem(k, tags)
    end

    liq = list.assoc.filter((fl), l)
    list.iter(fun(v) -> log(level=3, label=label, "#{v}"), liq)
    log(level=3, label=label, "Now playing: #{nowplaying()}")
end
