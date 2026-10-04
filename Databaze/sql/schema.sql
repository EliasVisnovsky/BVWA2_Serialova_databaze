PRAGMA foreign_keys = ON;

-- ---------- UŽIVATELÉ ----------
CREATE TABLE uzivatele (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    jmeno       TEXT NOT NULL,
    prijmeni    TEXT NOT NULL,
    email       TEXT NOT NULL,
    telefon     TEXT NOT NULL,
    pohlavi     TEXT NOT NULL CHECK (pohlavi IN ('male','female','other')),
    foto_cesta  TEXT NOT NULL,
    login       TEXT NOT NULL UNIQUE COLLATE NOCASE,
    heslo       TEXT NOT NULL,
    role        TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
    vytvoreno   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- SERIÁLY ----------
CREATE TABLE serialy (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    nazev                 TEXT NOT NULL,
    popis                 TEXT,
    rok_vydani            INTEGER,
    api_id                TEXT UNIQUE,
    plakat_url            TEXT,
    vytvoril_uzivatel_id  INTEGER,
    FOREIGN KEY (vytvoril_uzivatel_id) REFERENCES uzivatele(id)
        ON DELETE SET NULL
);

-- ---------- ŽÁNRY ----------
CREATE TABLE zanry (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    nazev  TEXT NOT NULL UNIQUE
);

CREATE TABLE serialy_zanry (
    serial_id INTEGER NOT NULL,
    zanr_id   INTEGER NOT NULL,
    PRIMARY KEY (serial_id, zanr_id),
    FOREIGN KEY (serial_id) REFERENCES serialy(id) ON DELETE CASCADE,
    FOREIGN KEY (zanr_id)   REFERENCES zanry(id)   ON DELETE CASCADE
);

-- ---------- HODNOCENÍ ----------
CREATE TABLE hodnoceni (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    serial_id     INTEGER NOT NULL,
    uzivatel_id   INTEGER NOT NULL,
    pocet_hvezd   INTEGER NOT NULL CHECK (pocet_hvezd BETWEEN 1 AND 5),
    komentar      TEXT,
    datum_pridani TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (serial_id, uzivatel_id),
    FOREIGN KEY (serial_id)   REFERENCES serialy(id)   ON DELETE CASCADE,
    FOREIGN KEY (uzivatel_id) REFERENCES uzivatele(id) ON DELETE CASCADE
);

-- ---------- WATCHLIST ----------
CREATE TABLE watchlist (
    uzivatel_id INTEGER NOT NULL,
    serial_id   INTEGER NOT NULL,
    stav        TEXT NOT NULL DEFAULT 'chci_videt'
                CHECK (stav IN ('chci_videt','sleduji','videno')),
    pridano     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (uzivatel_id, serial_id),
    FOREIGN KEY (uzivatel_id) REFERENCES uzivatele(id) ON DELETE CASCADE,
    FOREIGN KEY (serial_id)   REFERENCES serialy(id)   ON DELETE CASCADE
);

-- ---------- ZPRÁVY ----------
CREATE TABLE zpravy (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    odesilatel_id     INTEGER NOT NULL,
    prijemce_id       INTEGER NOT NULL,
    predmet           TEXT NOT NULL,
    sifrovany_text    TEXT NOT NULL,
    cas_odeslani      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    precteno          INTEGER NOT NULL DEFAULT 0,
    smazal_odesilatel INTEGER NOT NULL DEFAULT 0,
    smazal_prijemce   INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (odesilatel_id) REFERENCES uzivatele(id) ON DELETE CASCADE,
    FOREIGN KEY (prijemce_id)   REFERENCES uzivatele(id) ON DELETE CASCADE
);

-- ---------- NOTIFIKACE ----------
CREATE TABLE notifikace (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    uzivatel_id      INTEGER NOT NULL,
    zprava_id        INTEGER,
    obsah_notifikace TEXT NOT NULL,
    cas_vytvoreni    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    precteno         INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (uzivatel_id) REFERENCES uzivatele(id) ON DELETE CASCADE,
    FOREIGN KEY (zprava_id)   REFERENCES zpravy(id)    ON DELETE CASCADE
);

-- Indexy pro časté dotazy (schránka, notifikace)
CREATE INDEX idx_zpravy_prijemce ON zpravy(prijemce_id, precteno);
CREATE INDEX idx_zpravy_odesilatel ON zpravy(odesilatel_id);
CREATE INDEX idx_notifikace_uzivatel ON notifikace(uzivatel_id, precteno);

-- ---------- ZÁKLADNÍ DATA ----------
INSERT INTO zanry (nazev) VALUES
    ('Drama'), ('Komedie'), ('Sci-Fi'), ('Fantasy'),
    ('Thriller'), ('Krimi'), ('Horor'), ('Dokumentární'), ('Animovaný');
-- Admin a ukázková data se vkládají skriptem sql/seed.php
