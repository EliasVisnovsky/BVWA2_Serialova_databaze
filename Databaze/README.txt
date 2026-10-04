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
