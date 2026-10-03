php/config.php
- Nastavení aplikace: cesta k databázi a šifrovací klíč.

php/db.php
- Vytvoří připojení k SQLite databázi které ostatní skripty používají pro dotazy.

php/crypto.php
- Pro šifrování citlivých údajů. Klíč bere z config.php.

sql/schema.sql
- Zdroj ze kterého se databáze vytváří.

data/serialy.db
- SQLite databáze, ve které jsou uložená všechna data aplikace. Zatím obsahuje vytvořené tabulky a 9 žánrů.

docs/diagram.dbml
- Textový zdroj ER diagramu databáze. 

docs/diagram.png
- Trochu upravený obrázek ER diagramu.

uploads/
- Prázdná složka pro fotky z profilu uživatelů.

js/
- Prázdná složka pro budoucí js.