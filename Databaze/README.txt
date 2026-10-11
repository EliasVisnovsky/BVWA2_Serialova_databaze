php/config.php
- Nastavení aplikace: cesta k databázi a šifrovací klíč.

php/db.php
- Vytvoří připojení k SQLite databázi které ostatní skripty používají pro dotazy.

php/crypto.php
- Pro šifrování citlivých údajů. Klíč bere z config.php.

sql/schema.sql
- Zdroj ze kterého se databáze vytváří.

sql/seed.php
- Naplní databázi ukázkovými daty (admin, 8 uživatelů, 15 seriálů, hodnocení, watchlist, zprávy, notifikace).
- Spuštění z kořene projektu: php sql/seed.php  (do neprázdné databáze jen s --force; --admin-pass=... nastaví heslo admina).

data/serialy.db
- SQLite databáze, ve které jsou uložená všechna data aplikace. Obsahuje tabulky, 9 žánrů a ukázková data ze sql/seed.php.

docs/diagram.dbml
- Textový zdroj ER diagramu databáze. 

docs/diagram.png
- Trochu upravený obrázek ER diagramu.

uploads/
- Profilové fotky uživatelů (JPEG, delší strana max. 800 px, název generuje server). seed_*.jpg jsou ukázkové avatary.

js/validace.js
- Pravidla validace (e-mail, telefon, login, heslo, jména, fotka) - musí být shodná s php/validace.php a php/foto.php.

js/registrace.js
- Obsluha registračního formuláře: validace při psaní, odeslání na register.php a zobrazení chyb.

php/validace.php
- Serverová pravidla validace registrace (stejné výrazy jako js/validace.js).

php/foto.php
- Kontrola a zpracování profilové fotky (formát, velikost, rozlišení, překódování na JPEG). Vyžaduje PHP rozšíření gd a fileinfo.

register.php
- Zpracuje registraci nového uživatele. Pro JS vrací JSON (chyby podle polí), bez JS přesměruje / vypíše chyby.

tests/parita.js
- Ověří, že JS a PHP validují stejně: node tests/parita.js  (potřebuje node i php v PATH).

index.php
- Katalog seriálů: hledání podle názvu, filtr podle žánru, řazení (název / hodnocení / rok). Přihlášenému ukazuje jeho stav ve watchlistu a hodnocení.

serial.php
- Detail seriálu (serial.php?id=...): žánry, počet sérií a epizod, průměr a rozložení hvězdiček, tlačítka watchlistu (Chci vidět / Sleduji / Viděno),
  vlastní hodnocení (1-5 hvězdiček + komentář) a hodnocení ostatních. Administrátor může mazat cizí hodnocení.

profil.php
- Můj účet: údaje o uživateli (e-mail a telefon se dešifrují), statistiky a záložky Chci vidět / Sleduji / Viděno / Moje hodnocení.
  Stav seriálu jde změnit nebo seriál odebrat přímo tady, hodnocení se upravuje na detailu seriálu.

profil_uprava.php, js/profil_uprava.js
- Úprava osobních údajů (jméno, příjmení, e-mail, telefon, pohlaví) a výměna profilové fotky (nepovinná, bez výběru zůstane stávající).
  Stejná pravidla jako registrace (php/validace.php, php/foto.php, js/validace.js), e-mail a telefon se ukládají šifrovaně, upravit jde jen vlastní účet.

profil_prihlaseni.php, js/profil_prihlaseni.js
- Změna loginu a/nebo hesla. Každá změna vyžaduje současné heslo; login i nové heslo se kontrolují stejnými pravidly jako při registraci,
  obsazený login se odmítne (login je bez ohledu na velikost písmen unikátní), heslo se ukládá jen jako hash a po změně se vygeneruje nové ID session.

zpravy.php
- Schránka přijatých zpráv a oznámení (předmět i text se dešifrují až při zobrazení). Otevření stránky označí zprávy a notifikace jako přečtené,
  nové jsou zvýrazněné. Zprávu jde smazat jen ze své schránky. Počet nepřečtených je jako odznak v navigaci (php/layout.php).

admin_oznameni.php, js/admin_oznameni.js
- Hromadné oznámení (jen administrátor, kontrola role na serveru + CSRF): jedna zpráva všem ostatním uživatelům, např. o odstávce.
  Pro každého příjemce se vloží řádek do zpravy (předmět i text šifrovaně) a notifikace do notifikace, vše v jedné transakci. Před odesláním se ptá na potvrzení.

watchlist.php, hodnoceni.php
- Obsluha změn (jen POST + CSRF token): přidání / změna stavu / odebrání ze seznamu, uložení / smazání hodnocení.
  Uložení hodnocení zařadí seriál do watchlistu jako "Viděno" (stav "Sleduji" zůstane).

login.php, logout.php
- Přihlášení (password_verify, nové ID session po přihlášení) a odhlášení. Automatické odhlášení po 20 minutách nečinnosti řeší php/auth.php.

php/auth.php
- Session, 20min timeout, CSRF tokeny, jednorázové hlášky (flash), ochrana přesměrování.

php/layout.php
- Společná hlavička/patička stránek (vzhled EPILOG: Tailwind CSS, Alpine.js a GSAP z CDN, bez vlastních css souborů), navigace s odkazem Messages
  a odznakem počtu nepřečtených zpráv, odkaz Oznámení pro administrátora a pomocné funkce pro výpis (hvězdičky, datum, skloňování).

prihlaseni.php
- Přihlašovací a registrační stránka (nahradila prihlaseni_a_registr.html). Formuláře odesílají na login.php a register.php,
  kontrola polí v prohlížeči používá js/validace.js a js/registrace.js.

sql/epizody.php, sql/migrace_epizody.php
- Počty sérií a epizod ukázkových seriálů. Migrace přidá sloupce pocet_serii a pocet_epizod do už existující databáze (php sql/migrace_epizody.php,
  jde spustit opakovaně). Nová databáze je dostane rovnou ze sql/schema.sql a sql/seed.php.
