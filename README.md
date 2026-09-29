 BVWA2_SERIALOVA_DATABAZE

Hlavní funkce aplikace

Katalog a hodnocení seriálů: 
Procházení databáze pořadů, zobrazení informací z API a možnost přidávat k jednotlivým titulům vlastní hodnocení.

Uživatelské účty: 
Plnohodnotný registrační a přihlašovací systém. Každý uživatel má vlastní profil s informacemi a automaticky oříznutou profilovou fotografií. 

Interní komunikace: 
Vestavěný systém pro posílání soukromých zpráv mezi uživateli (fungující na principu e-mailových schránek) včetně notifikací na nově příchozí poštu.  

Uživatelské role: 
Rozdělení na běžné uživatele (spravují výhradně svá vlastní data) a administrátory (mají právo upravovat libovolné záznamy v systému a přidělovat administrátorská práva dalším osobám).  

Zabezpečení:
Hesla jsou v databázi ukládána výhradně v hashované podobě.  
Obsah soukromých zpráv a případné další citlivé údaje jsou na serveru šifrovány.  
Všechny vstupy z formulářů procházejí dvojitou validací (na frontendu i backendu) pro ochranu proti SQL Injection a ukládání nekonzistentních dat.  
Systém využívá chráněné relace (sessions) s automatickým odhlášením po 20 minutách nečinnosti a aktivní ochranou proti session hijackingu.  

Prvotní návrh DB:
 <img width="1389" height="797" alt="{D4F38DAE-62B8-417D-851C-D2B3A330655C}" src="https://github.com/user-attachments/assets/5392256f-17e7-4956-b61f-76856cfe6780" />

