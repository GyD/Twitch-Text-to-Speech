CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    twitch_id TEXT NOT NULL UNIQUE,
    login TEXT NOT NULL,
    display_name TEXT NOT NULL,
    profile_image_url TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tts_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    channel TEXT NOT NULL,
    volume REAL NOT NULL DEFAULT 1,
    rate REAL NOT NULL DEFAULT 1,
    voice_name TEXT,
    announce_chatter INTEGER NOT NULL DEFAULT 0,
    mods_only INTEGER NOT NULL DEFAULT 0,
    vips_only INTEGER NOT NULL DEFAULT 0,
    tagged_only INTEGER NOT NULL DEFAULT 0,
    ignore_replies INTEGER NOT NULL DEFAULT 0,
    ignore_leading_mentions INTEGER NOT NULL DEFAULT 0,
    ignore_known_bots INTEGER NOT NULL DEFAULT 1,
    ignore_streamer INTEGER NOT NULL DEFAULT 1,
    ignore_emotes INTEGER NOT NULL DEFAULT 1,
    exclude_commands INTEGER NOT NULL DEFAULT 1,
    exclude_links INTEGER NOT NULL DEFAULT 1,
    excluded_chatters_json TEXT NOT NULL DEFAULT '[]',
    max_message_length INTEGER NOT NULL DEFAULT 250,
    cooldown_ms INTEGER NOT NULL DEFAULT 1000,
    overlay_token TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);