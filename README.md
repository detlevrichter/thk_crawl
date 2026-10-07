# THK CRAWL
## Content Retrieval and Analysis Workflow Layer

CRAWL ist ein wissenschaftliches Projekt der TH Köln zur automatisierten Erfassung, Analyse und strukturierten Extraktion von Webinhalten. Es wurde im Rahmen des Projekts **Digitalkompetenz.nrw** umgesetzt.

Das System ruft vordefinierte Webseiten (in einer Datenbank hinterlegt) mit einem Headless Browser auf und liest ihre Inhalte aus. Die Inhalte werden in Markdown umgewandelt und an ein Large Language Model (LLM) übergeben. Das LLM extrahiert daraus strukturierte Angebote (z. B. Seminare, Kurse, Veranstaltungen) anhand konfigurierbarer Eigenschaften.

Welche Eigenschaften extrahiert werden, wird in der Datenbank konfiguriert. Dafür sind keine Änderungen am Code nötig.

---

## Inhaltsverzeichnis

- [Projektkontext](#projektkontext)
- [Funktionsübersicht](#funktionsübersicht)
- [Systemarchitektur](#systemarchitektur)
- [Ablauf eines Crawl-Durchlaufs](#ablauf-eines-crawl-durchlaufs)
- [Projektstruktur](#projektstruktur)
- [Datenmodell](#datenmodell)
- [Prompt-Aufbau](#prompt-aufbau)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Konfiguration einer neuen Quelle](#konfiguration-einer-neuen-quelle)
- [Bedienung](#bedienung)
- [Fehlerbehandlung und Debugging](#fehlerbehandlung-und-debugging)
- [Rechtliche und ethische Aspekte](#rechtliche-und-ethische-aspekte)
- [Sicherheitshinweise](#sicherheitshinweise)
- [Bekannte Einschränkungen](#bekannte-einschränkungen)
- [Ausblick](#ausblick)
- [Mitwirken](#mitwirken)
- [Entwicklungsstatus](#entwicklungsstatus)
- [Disclaimer](#disclaimer)

---

## Projektkontext

CRAWL entstand an der TH Köln im Projekt **Digitalkompetenz.nrw**. Ziel dieses Projektabschnitts war ein Werkzeug, das Weiterbildungsangebote verschiedener Anbieter (Seminare, Kurse, Veranstaltungen) automatisch von deren Webseiten erfasst und in eine einheitliche Struktur bringt.

Die Angebote werden dabei nicht nur übernommen, sondern auch bewertet: Das LLM schätzt für jedes Angebot ein Niveau (Einstiegshöhe) und ordnet ihm Werte für die konfigurierten Kompetenzen zu (Tabelle `competency_types`). So lassen sich Angebote vieler Anbieter vergleichen, filtern und einem Kompetenzmodell zuordnen.

Dieser Projektabschnitt umfasste:

- Konzeption und Umsetzung der Crawling-Pipeline (Übersichtsseite → Detailseiten → LLM → Datenbank)
- datenbankgestützte Konfiguration von Quellen, Prompts und Kompetenzen
- Anbindung eines LLM über eine OpenAI-kompatible Schnittstelle
- eine einfache Weboberfläche zum Starten, Überwachen und Abbrechen von Crawl-Läufen
- Berücksichtigung der `robots.txt` der Zielseiten

**Nicht Teil dieses Projektabschnitts** ist die Anbindung an WordPress. Sie wird gesondert dokumentiert. Dazu gehören auch die Views mit dem Präfix `wp_`.

---

## Funktionsübersicht

- Webseitenerfassung mit einem Headless Browser (Puppeteer / Chromium), auch für JavaScript-lastige Seiten
- datenbankgesteuerte Verwaltung der Zielseiten (`crawl_master`)
- automatisches Sammeln von Detailseiten-Links auf Übersichtsseiten, inklusive URL-Paginierung
- Filter für Detail-URLs über Verzeichnis, Include- und Exclude-Regex
- frei definierbare Browser-Aktionen pro Quelle (z. B. „Mehr laden“-Buttons anklicken)
- Bereinigung des HTML (Navigation, Footer, Cookie-Banner usw.) und Umwandlung in Markdown
- Übergabe der Inhalte an ein LLM über eine OpenAI-kompatible API
- strukturierte Extraktion von Angeboten als JSON
- konfigurierbare Extraktionseigenschaften (Kompetenzen, zusätzliche Felder, Prompt-Teile)
- Prüfung der `robots.txt` vor dem Abruf einer Detailseite
- Weboberfläche mit Fortschrittsanzeige, Abbruch-Funktion und Testwerkzeug für einzelne URLs
- PHP-Backend und Node.js-Komponente

---

## Systemarchitektur

```mermaid
flowchart LR
    subgraph DB[(MySQL / MariaDB)]
        CM[crawl_master<br/>Quellen & Prompt-Teile]
        CL[crawl_list<br/>Detail-URLs & Markdown]
        CT[competency_types<br/>Kompetenzen]
        PR[prompt<br/>Basis-Prompt]
        OF[offers]
        OC[offer_competencies]
    end

    UI[Weboberfläche<br/>public/do.php] -->|startet| RUN[run_crawl.php<br/>Hintergrundprozess]
    RUN --> CRAWL[Crawl.php]
    CRAWL -->|ruft auf| PUP[pup.js<br/>Puppeteer / Chromium]
    PUP -->|HTML| CRAWL
    CRAWL -->|Markdown + Prompt| LLM[LLM<br/>OpenAI-kompatible API]
    LLM -->|JSON| CRAWL

    CM --> CRAWL
    CT --> CRAWL
    PR --> CRAWL
    CRAWL --> CL
    CRAWL --> OF
    CRAWL --> OC
```

| Komponente | Technologie | Aufgabe |
|---|---|---|
| Steuerung | PHP 8 | Ablaufsteuerung, Datenbankzugriff, Prompt-Erzeugung, LLM-Anbindung |
| Browser | Node.js + Puppeteer | Seiten rendern, scrollen, Aktionen ausführen, HTML zurückgeben |
| HTML → Markdown | `league/html-to-markdown` | Seiteninhalt für das LLM verdichten |
| LLM-Client | `openai-php/client` + Guzzle | Kommunikation mit einem OpenAI-kompatiblen Endpunkt |
| Token-Zählung | `rajentrivedi/tokenizer-x` | Tokenanzahl im Testwerkzeug anzeigen |
| Datenhaltung | MySQL / MariaDB | Konfiguration und Ergebnisse |
| Oberfläche | PHP + Bootstrap | Crawl starten, überwachen, abbrechen |

---

## Ablauf eines Crawl-Durchlaufs

Ein vollständiger Lauf (`Crawl::runCrawl()`) besteht aus drei Phasen:

### 1. Precrawl – Detailseiten sammeln (`Crawl::preCrawl()`)

1. Alle aktiven Einträge aus `crawl_master` werden gelesen (`Aktiv = 1`).
2. Für jede Quelle ruft `pup.js` die Übersichtsseite (`URL`) auf. Ist `Paginierung = 'URL'` gesetzt, werden die Seiten 1 bis `PaginierungsStopp` (höchstens 49) einzeln abgerufen.
3. Vor dem Auslesen führt `pup.js` die in `customCommands` hinterlegten Aktionen aus, z. B. einen „Mehr laden“-Button mehrfach anklicken.
4. Aus dem HTML werden alle `href`-Links gesammelt und in absolute URLs umgewandelt.
5. Ein Link wird in `crawl_list` übernommen, wenn er
   - den Wert aus `Verzeichnis` enthält,
   - auf `includeRegex` passt (falls gesetzt),
   - **nicht** auf `excludeRegex` passt (falls gesetzt),
   - nicht identisch mit der Übersichtsseite ist.

### 2. Aufräumen (`Crawl::referentialIntegrity()`)

Verwaiste Datensätze werden gelöscht:

- Einträge in `crawl_list` ohne zugehörigen `crawl_master`
- Angebote in `offers` ohne zugehörigen `crawl_list`-Eintrag
- Kompetenzwerte in `offer_competencies` ohne zugehöriges Angebot

### 3. Crawl – Detailseiten auswerten (`Crawl::crawl()`)

Für jede URL in `crawl_list`:

1. Die `robots.txt` des Hosts wird geprüft. Ist die Seite gesperrt, wird sie als fehlerhaft markiert und übersprungen.
2. `pup.js` lädt die Seite. Navigation, Header, Footer, Sidebars, Cookie-Banner und Werbung werden entfernt.
3. Das HTML wird bereinigt (`Show::cleanHtml()`) und in Markdown umgewandelt.
4. Aus Basis-Prompt, Quellen-Prompt und Kompetenzen wird der Systemprompt gebaut (siehe [Prompt-Aufbau](#prompt-aufbau)).
5. Das LLM antwortet mit
   - einem **JSON-Objekt**, wenn die Seite ein einzelnes Angebot beschreibt, oder
   - `FALSE`, wenn es keine Angebotsseite ist (z. B. eine Übersichtsseite).
6. Bei einem gültigen JSON wird das Angebot in `offers` gespeichert (bzw. per URL aktualisiert). Die Kompetenzwerte werden in `offer_competencies` neu geschrieben. Als Anbieter (`provider`) wird der `Name` der Quelle aus `crawl_master` gesetzt.
7. In `crawl_list` wird `status` auf `TRUE` oder `FALSE` gesetzt und das Markdown in `markup` gespeichert.

Zwischen zwei LLM-Anfragen wartet das System `MICRO_SLEEP_TIME` Mikrosekunden, um Rate-Limits einzuhalten.

**Recrawl:** Mit `Crawl::crawl(true)` werden nur Seiten mit `status = 'FALSE'` erneut ausgewertet. Dabei wird das gespeicherte Markdown wiederverwendet. Die Seiten werden also nicht neu abgerufen, nur das LLM wird erneut gefragt (z. B. nach einer Prompt-Anpassung).

---

## Projektstruktur

```
thk_crawl/
├── app/                      # PHP-Klassen (Autoload)
│   ├── Crawl.php             # Kernlogik: Precrawl, Crawl, Fortschritt, robots.txt
│   ├── Prompt.php            # Baut den Systemprompt aus DB-Inhalten
│   ├── Offer.php             # Modell für Angebote (Tabelle offers)
│   ├── OfferCompetency.php   # Kompetenzwerte je Angebot
│   ├── CrawlList.php         # Modell für crawl_list
│   ├── Model.php             # Einfache Active-Record-Basisklasse
│   ├── DB.php                # PDO-Datenbankklasse (liest .env)
│   ├── Arr.php, Levels.php   # Hilfsklassen
│   └── Utils/
│       ├── Show.php          # LLM-Client, HTML-Bereinigung, Chunking
│       └── PuppetierConnection.php
├── public/                   # Document-Root des Webservers
│   ├── do.php                # Oberfläche: Crawl starten / abbrechen / Tabellen leeren
│   ├── start_crawl.php       # Startet run_crawl.php als Hintergrundprozess
│   ├── run_crawl.php         # Einstiegspunkt für einen kompletten Lauf (auch CLI)
│   ├── progress.php          # Liefert Fortschritt als JSON, beendet Prozess (kill)
│   ├── empty_tables.php      # Leert offers, offer_competencies, crawl_list
│   ├── precrawl.php          # Führt nur den Precrawl aus
│   ├── index.php             # Testwerkzeug: einzelne URL auslesen + Tokens zählen
│   └── dist/                 # Bootstrap, Logo, Screenshots (dist/img)
├── migrations/               # Anpassungen für ältere Datenbanken
├── pup.js                    # Puppeteer-Skript (Headless Browser)
├── config.sample.php         # Vorlage für config.php
├── dump.sql.gz               # Schema + Grunddaten (Kategorien, Kompetenzen, Prompt)
├── composer.json             # PHP-Abhängigkeiten
└── package.json              # Node.js-Abhängigkeiten
```

---

## Datenmodell

| Tabelle | Zweck |
|---|---|
| `crawl_master` | Eine Zeile pro Quelle (Anbieter-Website): Start-URL, Filter, Paginierung, Browser-Aktionen und quellenspezifische Prompt-Teile |
| `crawl_list` | Alle gefundenen Detailseiten mit Status (`TRUE`/`FALSE`) und dem extrahierten Markdown |
| `offers` | Die extrahierten Angebote: Titel, Beschreibung, Niveau, Anbieter, URL |
| `offer_competencies` | Kompetenzwerte je Angebot (`offer_id`, `competency`, `score`) |
| `competency_types` | Definition der Kompetenzen: `slug`, `label`, `description` (geht in den Prompt), `example`, `type`, `standard` |
| `prompt` | Bausteine des Basis-Prompts (`preprompt`, `title`, `description`, `provider`, `level`, `postprompt`) |
| `categories` | Zielgruppen-Kategorien (`Verwaltung`, `Lehrende`, `Studierende`, `Default`) |
| `category_competency_type` | Zuordnung Kategorie ↔ Kompetenz |
| `categories_crawl_master` | Zuordnung Kategorie ↔ Quelle |
| `crawl_master_competency_types` | Zuordnung Quelle ↔ Kompetenz |

> Die Views mit dem Präfix `wp_` gehören zur WordPress-Anbindung. Sie sind nicht Teil dieses Projektabschnitts und werden gesondert dokumentiert.

### Kompetenzmodell (`competency_types`)

Der Dump enthält 13 Kompetenzfacetten und ein zusätzliches Filtermerkmal:

| Slug | Bezeichnung |
|---|---|
| `01_Textverarbeitung` | Erstellung und Bearbeitung digitaler Inhalte: Textverarbeitung |
| `02_Tabellenkalkulation` | Erstellung und Bearbeitung digitaler Inhalte: Tabellenkalkulation |
| `03_Praesentation` | Erstellung und Bearbeitung digitaler Inhalte: Präsentation |
| `04_Bild` | Bild-, Video- und Audiobearbeitung |
| `05_Kollaboration` | Digitale Kommunikation und Zusammenarbeit: E-Mail und gemeinsame Dateiorganisation |
| `06_Videokonferenzen` | Digitale Kommunikation und Zusammenarbeit: Videokonferenzen |
| `07_Projektmanagement` | Digitale Kommunikation und Zusammenarbeit: Projektmanagement |
| `08_Lehre` | Wissensvermittlung mithilfe digitaler Werkzeuge: Digitale Lehre |
| `09_Lernmanagementsysteme` | Wissensvermittlung mithilfe digitaler Werkzeuge: Lernmanagementsysteme |
| `10_Recherche` | Digitale Recherche und Informationsbewertung |
| `11_Literaturverwaltungsprogramme` | Digitale Recherche und Informationsbewertung: Literaturverwaltungsprogramme |
| `12_Datenschutz` | Schutz von Daten im digitalen Raum |
| `13_KI` | Generative Künstliche Intelligenz |
| `Sprache` | Sprache, in der das Angebot durchgeführt wird (Typ `filter`, Standard: `Deutsch`) |

**Bewertung:**

- **Kompetenzen** (Typ `float`): Das LLM bewertet jede Facette mit `0` (trifft nicht zu), `0,3` (geringe Relevanz), `0,7` (deutlicher Schwerpunkt) oder `1` (zentrale Kompetenz). Die Abstufungen stehen in der `description` der jeweiligen Facette.
- **Filtermerkmale** (Typ `filter`): Der Wert wird als Text übernommen, z. B. `Englisch`.
- **Niveau** (`offers.level`): `0` = Einstieg/Grundkurs, `0.5` = nicht eindeutig einzuordnen (z. B. Austauschformate), `1` = Fortgeschrittene.

Die Werte stehen in `offer_competencies` (eine Zeile pro Angebot und Kompetenz).

### Wichtige Felder in `crawl_master`

| Feld | Bedeutung |
|---|---|
| `Name` | Name des Anbieters; wird als `provider` im Angebot gespeichert |
| `URL` | Übersichtsseite, auf der die Detail-Links gesammelt werden |
| `Aktiv` | `1` = Quelle wird gecrawlt, `0` = Quelle wird übersprungen |
| `Verzeichnis` | Zeichenkette, die eine Detail-URL enthalten muss (z. B. `/veranstaltung/`) |
| `includeRegex` | PHP-Regex inkl. Begrenzer (z. B. `~/kurs/\d+~`), auf die eine Detail-URL passen muss. Leer = kein Filter |
| `excludeRegex` | PHP-Regex inkl. Begrenzer; passende URLs werden verworfen. Leer = kein Filter |
| `Paginierung` | `URL` = Paginierung über einen URL-Parameter, sonst keine Paginierung |
| `PaginierungsEigenschaft` | Der Teil der URL, der die Seitenzahl enthält, z. B. `?page=1` |
| `PaginierungsStopp` | Letzte abzurufende Seite (Zahl, kleiner als 50) |
| `customCommands` | JSON mit Browser-Aktionen für `pup.js` (siehe unten). `{}` = keine Aktionen |
| `FilterFormular` | Optional. JSON zum Absenden eines Filterformulars vor dem Auslesen. Wird im aktuellen Code noch nicht ausgewertet |
| `Elemente` | JSON-Feld. Wird im aktuellen Code noch nicht ausgewertet |
| `Detailseite`, `Ebene`, `Seite` | Beschreibende Angaben zur Quelle. Werden im aktuellen Ablauf nicht ausgewertet |
| `PrePrompt` / `PostPrompt` | Text, der vor bzw. nach dem Basis-Prompt eingefügt wird |
| `FieldsPrompt` | Zusätzliche Felder für das JSON, eine Zeile pro Feld im Format `feldname: Beschreibung` |
| `FieldsExample` | Beispielwerte für diese Felder, eine Zeile pro Feld im Format `feldname: Beispielwert` |
| `Kommentar` | Freitext für interne Notizen |

### Browser-Aktionen (`customCommands`)

`pup.js` kann vor dem Auslesen Aktionen ausführen. Erlaubt sind ein einzelnes Objekt oder ein Array:

```json
[
  { "action": "click", "selector": "#cookie-accept" },
  { "action": "clickUntilStable", "selector": ".load-more", "maxClicks": 10 }
]
```

| Aktion | Wirkung |
|---|---|
| `click` | Wartet auf den CSS-Selektor und klickt das Element einmal an |
| `clickUntilStable` | Klickt das Element wiederholt an (Standard: höchstens 5-mal) und wartet jeweils, bis das Netzwerk ruhig ist. Endet früher, wenn das Element nicht mehr existiert |

---

## Prompt-Aufbau

Der Systemprompt wird bei jeder Detailseite in `Prompt::get()` aus der Datenbank zusammengesetzt. Prompts lassen sich so anpassen, ohne den Code zu ändern:

```
crawl_master.PrePrompt
prompt[preprompt]

<fields>
title: prompt[title]
description: prompt[description]
level: prompt[level]
<slug>: competency_types.description      ← eine Zeile je Kompetenz
crawl_master.FieldsPrompt                  ← quellenspezifische Zusatzfelder
</fields>

prompt[postprompt]

<example>
{ JSON-Beispiel aus competency_types.example und crawl_master.FieldsExample }
</example>
crawl_master.PostPrompt
```

Das Markdown der Detailseite wird als User-Nachricht gesendet. Ist der Inhalt länger als `MAX_STRLEN` Zeichen, wird er in mehrere Teile zerlegt. Jeder Teil wird dann einzeln an das LLM geschickt.

**Neue Kompetenz hinzufügen:** Eine Zeile in `competency_types` anlegen (`slug`, `label`, `description`, `example`, `type`). Ab dem nächsten Lauf fragt der Prompt das Feld automatisch ab und speichert den Wert in `offer_competencies`.

---

## Voraussetzungen

- Linux-Server (der Hintergrundprozess und die Abbruch-Funktion nutzen `&`, `kill` und `posix_kill`)
- PHP >= 8.1 mit den Erweiterungen `pdo_mysql`, `mbstring`, `curl` und `posix`
- Composer
- Node.js >= 18.x und npm
- Bibliotheken für Chromium (werden von Puppeteer benötigt; unter Debian/Ubuntu z. B. `libnss3`, `libatk-bridge2.0-0`, `libgbm1`, `libasound2`)
- MySQL oder MariaDB (MariaDB >= 10.2 wegen `ADD IF NOT EXISTS` in den Migrationen)
- Zugriff auf ein LLM mit OpenAI-kompatibler API (Endpunkt und API-Key)
- Webserver (z. B. Apache oder nginx) für die Weboberfläche

---

## Installation

### 1. Repository klonen

```bash
git clone https://github.com/detlevrichter/thk_crawl.git
cd thk_crawl
```

### 2. PHP-Abhängigkeiten installieren

```bash
composer install
```

Die Pakete werden anhand von `composer.json` und `composer.lock` installiert.

### 3. Node.js-Abhängigkeiten installieren

```bash
npm install
```

Die Module werden anhand von `package.json` und `package-lock.json` installiert. Puppeteer lädt dabei eine passende Chromium-Version herunter. Soll ein bereits installierter Chrome/Chromium verwendet werden, kann der Pfad über die Umgebungsvariable `CHROME_PATH` gesetzt werden.

### 4. Konfiguration

#### `.env` (im Hauptverzeichnis)

Enthält die Zugangsdaten zur Datenbank:

```ini
hostname=localhost
database=Datenbank
username=Benutzer
password=sicheresPasswort
```

Diese Datei darf nicht ins Repository committed werden (sie steht bereits in `.gitignore`).

#### `config.php` (im Hauptverzeichnis)

`config.sample.php` nach `config.php` kopieren und anpassen:

```bash
cp config.sample.php config.php
```

```php
<?php
define('ROOT_DIR', __DIR__);
define('API_KEY', 'api-key');                     // API-Key des LLM-Anbieters
define('API_ENDPOINT', 'https://llm.example/v1'); // Basis-URL der OpenAI-kompatiblen API
define('MAX_STRLEN', 2048000);                    // max. Zeichen pro LLM-Anfrage, längere Inhalte werden geteilt
define('NODEJS_EXE', "/usr/bin/node ");           // Pfad zur Node.js-Binary
define('MICRO_SLEEP_TIME', 1000000);              // Pause zwischen LLM-Anfragen in µs (1.000.000 = 1 s)
define('LLM_MODEL', 'meta-llama-3.1-70b-instruct'); // verwendetes Modell
```

#### Verzeichnisse mit Schreibrechten

Für die Weboberfläche und die Screenshots von Puppeteer müssen zwei Verzeichnisse existieren und für den Webserver-Benutzer beschreibbar sein:

```bash
mkdir -p public/tmp public/dist/img
chown www-data:www-data public/tmp public/dist/img
```

- `public/tmp/` – Fortschrittsdatei (`crawl_progress.json`) und Prozess-ID (`crawl_pid.txt`)
- `public/dist/img/` – Screenshots, die `pup.js` beim Abruf anlegt (`screen.png`, `screen2.png`, `screen3.png`). Fehlt dieses Verzeichnis, schlägt der Seitenabruf fehl.

### 5. Datenbank einrichten

Datenbank anlegen und den Dump einspielen:

```bash
mysql -u root -p -e "CREATE DATABASE thk_crawl CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
gunzip -c dump.sql.gz | mysql -u root -p thk_crawl
```

Der Dump enthält das aktuelle Schema sowie die Grunddaten:

- Kategorien (`categories`) und ihre Zuordnung zu Kompetenzen (`category_competency_type`)
- das Kompetenzmodell (`competency_types`)
- den Basis-Prompt (`prompt`)

Die Tabelle `crawl_master` ist leer. Vor dem ersten Lauf müssen dort die Quellen eingetragen werden (siehe [Konfiguration einer neuen Quelle](#konfiguration-einer-neuen-quelle)).

Die Skripte in `migrations/` sind nur zum Aktualisieren älterer Datenbanken gedacht. Bei einer Neuinstallation aus dem Dump werden sie nicht benötigt.

### 6. Webserver einrichten

Der Document-Root des Webservers zeigt auf das Verzeichnis `public/`. Beispiel für Apache:

```apache
<VirtualHost *:80>
    ServerName crawl.example.local
    DocumentRoot /var/www/thk_crawl/public

    <Directory /var/www/thk_crawl/public>
        AllowOverride All
        Require ip 10.0.0.0/8      # Zugriff einschränken, siehe Sicherheitshinweise
    </Directory>
</VirtualHost>
```

---

## Konfiguration einer neuen Quelle

Beispiel: Ein Anbieter listet seine Kurse unter `https://www.beispiel.de/kurse?seite=1` bis `?seite=4`. Die Detailseiten liegen unter `https://www.beispiel.de/kurse/detail/...`.

```sql
INSERT INTO crawl_master
  (Name, URL, Aktiv, Verzeichnis, Paginierung, PaginierungsEigenschaft, PaginierungsStopp,
   Detailseite, customCommands, excludeRegex, includeRegex, Elemente, Ebene,
   FieldsPrompt, FieldsExample, PrePrompt, PostPrompt, Seite, Kommentar)
VALUES
  ('Beispiel-Akademie',
   'https://www.beispiel.de/kurse?seite=1',
   1,
   '/kurse/detail/',
   'URL', '?seite=1', '4',
   'Nein',
   '[{"action":"click","selector":"#accept-cookies"}]',
   '~\\.pdf$~',
   '',
   '{}',
   '', '', '', '', '', '', 'Testquelle');
```

Optional kann die Quelle über `categories_crawl_master` einer Zielgruppe (z. B. `Lehrende`) zugeordnet werden.

Zusätzliche Felder (z. B. Preis und Ort) lassen sich pro Quelle über `FieldsPrompt` und `FieldsExample` abfragen:

```
FieldsPrompt:
price: Preis des Kurses in Euro als Zahl, 0 wenn kostenlos
location: Veranstaltungsort oder "online"

FieldsExample:
price: 249
location: Köln
```

**Tipp:** Vor dem Anlegen einer Quelle die Übersichtsseite mit dem Testwerkzeug `public/index.php` aufrufen. Es zeigt, welches HTML der Headless Browser tatsächlich sieht und wie viele Tokens der Inhalt ungefähr umfasst.

---

## Bedienung

### Weboberfläche

`http://<server>/do.php` aufrufen:

| Schaltfläche | Funktion |
|---|---|
| **Crawl starten** | Startet `run_crawl.php` als Hintergrundprozess. Der Fortschritt wird jede Sekunde aktualisiert |
| **Crawl abbrechen** | Beendet den laufenden Prozess über seine PID |
| **Tabellen leeren** | Leert `offers`, `offer_competencies` und `crawl_list` (nach Rückfrage). Die Konfiguration bleibt erhalten |

Weil der Prozess im Hintergrund läuft, kann das Browserfenster geschlossen werden. Beim nächsten Aufruf von `do.php` wird der aktuelle Stand wieder angezeigt.

### Kommandozeile / Cron

Ein vollständiger Lauf lässt sich auch ohne Weboberfläche starten, z. B. für einen regelmäßigen Cron-Job:

```bash
php public/run_crawl.php /var/www/thk_crawl/public/tmp/crawl_progress.json
```

Beispiel für einen nächtlichen Lauf um 2 Uhr:

```cron
0 2 * * * cd /var/www/thk_crawl && php public/run_crawl.php >> /var/log/thk_crawl.log 2>&1
```

Nur den Precrawl ausführen (Detail-URLs sammeln, ohne LLM):

```bash
php public/precrawl.php
```

Eine einzelne Seite mit dem Headless Browser abrufen (Ausgabe: HTML):

```bash
node pup.js "https://www.beispiel.de/kurse" '{}'
```

---

## Fehlerbehandlung und Debugging

| Situation | Verhalten |
|---|---|
| Seite durch `robots.txt` gesperrt | `crawl_list.status = 'FALSE'`, Seite wird übersprungen |
| HTTP-Fehler, „Access Denied“ oder weniger als 500 Zeichen Inhalt | `pup.js` gibt `FALSE Access Denied` zurück |
| Fehler im Browser (Timeout usw.) | `pup.js` gibt `ERROR: <Meldung>` zurück |
| LLM antwortet mit `FALSE` | Keine Angebotsseite → `status = 'FALSE'` |
| LLM nicht erreichbar | Antwort „Keine Antwort von der AI …“ → `status = 'FALSE'` |
| JSON nicht auswertbar | Angebot wird übersprungen, Fehler wird geloggt |

Hilfreiche Werkzeuge:

- **Debug-Log von Puppeteer:** In `pup.js` `debugLogSetting = true` setzen. Dann werden URL, HTTP-Status und Fehler in `pup-debug.log` geschrieben.
- **Screenshots:** `public/dist/img/screen.png` (nach dem Laden) und `screen2.png` (nach den Aktionen) zeigen, was der Browser zuletzt gesehen hat.
- **Gespeichertes Markdown:** `crawl_list.markup` enthält den Text, der an das LLM ging. Damit lässt sich prüfen, ob die relevanten Informationen überhaupt im Input standen.
- **Fehlgeschlagene Seiten:** `SELECT url, markup FROM crawl_list WHERE status = 'FALSE';`

---

## Rechtliche und ethische Aspekte

- Vor dem Abruf jeder Detailseite wird die `robots.txt` des Hosts beachtet (Regeln für `User-agent: *`).
- Zwischen den Anfragen werden Pausen eingehalten, um die Zielserver nicht zu überlasten.
- Es werden nur öffentlich zugängliche Seiten abgerufen.
- Vor der Aufnahme einer neuen Quelle sollten die Nutzungsbedingungen des Anbieters geprüft werden. Die Verwendung der Daten muss mit Urheber- und Datenbankrecht vereinbar sein.
- Personenbezogene Daten (z. B. Namen von Dozierenden) sollten nicht gezielt extrahiert werden. Der Prompt ist entsprechend zu gestalten.
- LLM-Ausgaben können fehlerhaft sein. Die extrahierten Daten sind maschinell erzeugt und sollten vor einer Veröffentlichung stichprobenartig geprüft werden.

---

## Sicherheitshinweise

- `.env` und `config.php` niemals ins Repository committen.
- API-Keys nicht öffentlich machen.
- Produktionsserver mit restriktiven Zugriffsrechten betreiben.
- **Das Verzeichnis `public/` hat keine eigene Authentifizierung.** Den Zugriff über den Webserver absichern (IP-Beschränkung, HTTP-Basic-Auth oder VPN). Über die Oberfläche lassen sich Prozesse starten und beenden, Tabellen leeren und mit `index.php` beliebige URLs vom Server aus abrufen.
- In `public/do.php` und `public/empty_tables.php` den Platzhalter `DEIN_GEHEIMER_HASH` durch einen eigenen, zufälligen Wert ersetzen.
- Puppeteer läuft mit `--no-sandbox`. Den Crawler daher nicht als `root` betreiben, idealerweise in einem Container oder unter einem eigenen Benutzer.

---

## Bekannte Einschränkungen

- Paginierung wird nur über URL-Parameter unterstützt (höchstens 49 Seiten). Andere Varianten lassen sich teilweise über `clickUntilStable` abbilden.
- Detail-Links werden nur aus `href`-Attributen gelesen. Links, die erst per JavaScript-Klick entstehen, werden nicht erkannt.
- Pro Detailseite wird genau ein Angebot erwartet. Seiten mit mehreren Angeboten werden vom LLM in der Regel mit `FALSE` beantwortet.
- Hintergrundprozess und Abbruch-Funktion funktionieren nur unter Linux/Unix.
- Die Qualität der Ergebnisse hängt stark vom eingesetzten Modell und vom Prompt ab.
- Ein Teil des Codes (`Prompt copy.php`, `index2.php`, `chat.php`, `curl.php`, `t.js`) ist experimentell oder veraltet.

---

## Ausblick

Mögliche nächste Schritte:

- Verwaltungsoberfläche für `crawl_master` und `competency_types` statt direkter Datenbankpflege
- vollständige Migrationen für alle Spalten und ein reproduzierbares Schema
- Erkennung geänderter Seiten (Hash des Markdowns), damit nur neue oder geänderte Seiten an das LLM gehen
- Validierung der LLM-Antworten gegen ein JSON-Schema (bzw. „Structured Outputs“ des LLM)
- Authentifizierung für die Weboberfläche
- Container-Setup (Docker) für eine einfache Installation
- automatisierte Tests und Evaluation der Extraktionsqualität

---

## Mitwirken

Fehler und Verbesserungsvorschläge bitte als [Issue](https://github.com/detlevrichter/thk_crawl/issues) melden. Dafür gibt es Vorlagen für Bug-Reports und Feature-Requests. Pull Requests sind sehr willkommen!

---

## Wissenschaftlicher Kontext

CRAWL wurde im Rahmen des Projekts **Digitalkompetenz.nrw** an der TH Köln entwickelt. Es dient der automatisierten, strukturierten Informationsgewinnung aus Webquellen mittels KI-gestützter Analyse.

## Entwicklungsstatus

Forschungs- und Entwicklungsprojekt.
Nicht als produktives Web-Scraping-Framework gedacht.
Sicher sind noch Fehlfunktionen und Bugs enthalten. Bug-Reports (Issues) oder direkter Kontakt sind erwünscht. Über Pull Requests würden wir uns sehr freuen!

## Disclaimer

Dieses Projekt befindet sich in einem aktiven Forschungs- und Entwicklungsstadium.
CRAWL wurde in einem wissenschaftlichen Kontext entwickelt und ist derzeit nicht als produktionsreifes System zu verstehen.

Der Code enthält noch offene TODOs, experimentelle Komponenten sowie Stellen, die weiter konsolidiert, refaktoriert und dokumentiert werden müssen. Änderungen an Architektur, Schnittstellen und Konfigurationsmechanismen sind im weiteren Projektverlauf möglich.

Es wird keine Gewähr für Vollständigkeit, Stabilität oder Einsatzfähigkeit in produktiven Umgebungen übernommen. Die Nutzung erfolgt auf eigene Verantwortung.
