# AGENTS.md

> Allgemeine WordPress-Sicherheits-/Coding-Regeln (Escaping, Nonces, WPCS, i18n, Hooks-only) sind in
> [`.github/instructions/wordpress.instructions.md`](.github/instructions/wordpress.instructions.md) definiert und
> greifen automatisch für alle Dateien in diesem Plugin (`applyTo: wp-content/plugins/**`). Dieses Dokument
> ergänzt sie um projektspezifisches Architektur- und Workflow-Wissen.

## Zweck & Architektur
Dieses Plugin generiert eine strukturierte `llms.txt` (siehe https://llmstxt.org) unter der virtuellen URL
`/llms.txt` — ein Markdown-Dokument, das Seiten und (nach Kategorie gruppierte) Beiträge auflistet, damit
LLMs/AI-Assistenten die Inhalte einer Site diskret erfassen können. Es gibt **keine physische Datei** auf der
Platte; der Endpoint wird per Rewrite-Rule live gerendert und per Transient gecacht.

- `sev-structured-llms-txt.php` – Bootstrap. Lädt alle `includes/`-Klassen, registriert sie in `plugins_loaded`,
  und kümmert sich um Activation/Deactivation (Rewrite-Flush), inkl. Netzwerk-Aktivierung (iteriert alle Sites via
  `switch_to_blog()`) und neu angelegte Sites in einem netzwerkweit aktiven Setup (`wp_initialize_site`).
- `includes/class-description-resolver.php` – `Description_Resolver::resolve()`: liefert den Beschreibungstext
  hinter einem Link. Prüft der Reihe nach die Meta-Description-Felder von Yoast, RankMath, SEOPress, AIOSEO;
  fällt sonst auf den Post-Excerpt und zuletzt auf `wp_trim_words()` des Contents zurück. Über den Filter
  `sevllms_description` überschreibbar.
- `includes/class-noindex-resolver.php` – `Noindex_Resolver::is_noindex()`: prüft, ob Yoast, RankMath, SEOPress
  oder AIOSEO den Post/die Seite auf „noindex" gesetzt haben (jeweils das plugin-eigene Meta-Feld). Wird von
  `Content_Selector::is_included()` genauso wie das manuelle Exclude-Flag behandelt — eine für Suchmaschinen
  unsichtbare Seite (z. B. Impressum/Datenschutz per Yoast auf noindex) soll auch nicht in der llms.txt auftauchen,
  ohne dass man sie zusätzlich manuell ausschließen muss. Über den Filter `sevllms_is_noindex` überschreibbar.
- `includes/class-category-order.php` – `Category_Order`: liest/schreibt die Options-gesteuerte Reihenfolge und
  Ein-/Ausschluss-Liste der Kategorien (`sevllms_category_order`, Array von Term-IDs). Ohne gespeicherte
  Konfiguration: alle Kategorien, absteigend nach Post-Anzahl (`default_order()`). `admin_rows()` liefert die
  vollständige Liste für den Settings-Screen (konfigurierte zuerst, Rest alphabetisch angehängt, unchecked).
- `includes/class-content-selector.php` – `Content_Selector`: `get_pages()` liefert alle veröffentlichten, nicht
  ausgeschlossenen Seiten in Standard-Seitenreihenfolge (`menu_order`, dann Titel). „Ausgeschlossen" heißt: manuelles
  `_sevllms_exclude`-Flag ODER von einem SEO-Plugin auf „noindex" gesetzt (siehe `Noindex_Resolver` oben).
  `get_grouped_posts()` liefert Beiträge gruppiert nach primärer Kategorie (Yoast-Primary-Category falls gesetzt und
  gültig, sonst die Kategorie mit der niedrigsten Term-ID), in der von `Category_Order` vorgegebenen Reihenfolge, je
  Gruppe neueste zuerst. Unkategorisierte Beiträge landen unter dem Sonderschlüssel
  `Content_Selector::UNCATEGORIZED_KEY`, nur falls nicht leer.
- `includes/class-alternate-sites.php` – `Alternate_Sites::resolve()`: löst die in den Settings konfigurierte
  Liste von Ziel-Sites (`sevllms_alternate_sites`, je Eintrag `site_id` + optionales `label`) zu Label→URL-Paaren
  auf. Fehlt ein Label, wird es aus der Locale der Ziel-Site abgeleitet (`switch_to_blog()` + `get_locale()` +
  eingebaute Locale→Sprachname-Map, Fallback: roher Locale-Code), filterbar über `sevllms_alternate_site_label`.
  Nur relevant auf Multisite; auf Single-Site liefert `resolve()` immer ein leeres Array.
- `includes/class-generator.php` – `Generator::generate()`: baut das komplette Markdown-Dokument aus den
  Bausteinen oben zusammen (Intro-Block, „## Pages", Alternate-Site-Zeilen, „## Posts" mit „###"-Unterkapiteln je
  Kategorie). Leere Sektionen werden komplett weggelassen. Über den Filter `sevllms_generated_content`
  überschreibbar.
- `includes/class-cache.php` – `Cache`: Transient-Cache (`sevllms_cache`, 12h TTL als Backstop) für den generierten
  Content. Wird bei `save_post`/`delete_post` (post & page) und Kategorie-Änderungen automatisch geleert. Die
  Invalidierung beim Speichern der Plugin-Settings sitzt bewusst *nicht* hier, sondern in
  `Admin_Settings::maybe_clear_cache_after_save()` (siehe unten).
- `includes/class-rewrite.php` – `Rewrite`: registriert die Rewrite-Rule `^llms\.txt$` und liefert den (gecachten)
  Content bei `template_redirect` aus, als `text/plain`. `prevent_canonical_redirect()` hängt am Filter
  `redirect_canonical` und liefert `false`, sobald unsere Query-Var gesetzt ist — sonst hängt WordPress an
  `/llms.txt` einen Trailing Slash an und redirected auf `/llms.txt/` (dasselbe Problem wie bei
  `/.well-known/security.txt`, siehe
  https://wordpress.org/support/topic/well-known-security-txt-redirects-301-403s-redirect_canonical-fix/).
  `flush_current_site()` ist die statische Hilfsfunktion, die beim Aktivieren (pro Site, siehe Bootstrap) die
  Rewrite-Regeln neu registriert und flusht.
- `includes/class-post-meta.php` – `Post_Meta`: „Exclude from llms.txt"-Checkbox-Metabox auf `post` und `page`
  (Postmeta `_sevllms_exclude`).
- `includes/class-admin-settings.php` – Settings-Seite unter **Settings → llms.txt**: Tagline-Feld, per Drag&Drop
  sortierbare Kategorie-Checkliste (jQuery UI Sortable, WP-Core-Bundle, kein externes JS), auf Multisite ein
  Alternate-Sites-Repeater (reines Vanilla-JS Add/Remove, kein Build-Step), Live-Vorschau und ein
  „Cache leeren"-Button (`admin-post.php?action=sevllms_purge_cache`). `maybe_clear_cache_after_save()` hängt an
  `admin_init` und leert den Cache, sobald `options.php` nach dem Speichern mit `?page=<slug>&settings-updated=…`
  auf diese Seite zurückleitet — unabhängig davon, ob es der allererste Save eines Feldes ist (dann feuert WP
  `add_option_{$option}` statt `update_option_{$option}`) oder ob eine Checkbox-Liste komplett leer abgeschickt
  wurde (dann feuert für dieses Feld gar kein Options-Hook). Ein Hook auf `update_option_{$option}` je Setting in
  `Cache::register()` würde beide Fälle verpassen, deshalb sitzt die Invalidierung hier statt dort.

**Datenfluss:** Request auf `/llms.txt` → `Rewrite::maybe_serve()` → `Cache::get()` (Cache-Hit: sofort ausliefern)
→ bei Cache-Miss `Generator::generate()` → `Content_Selector` + `Alternate_Sites` + `Description_Resolver` bauen
das Dokument zusammen → `Cache::set()` → Ausgabe.

## Namespace & Konventionen
- Alle Klassen liegen im Namespace `SevStructuredLlmsTxt`.
- Jede Datei beginnt mit `if ( ! defined( 'ABSPATH' ) ) { die(); }`.
- Strikte Typisierung, Scalar-Type-Hints und Return-Types überall in `includes/`.
- Options-Präfix `sevllms_`, Postmeta-Key `_sevllms_exclude`.
- Multisite-Semantik: Netzwerkweite Aktivierung bedeutet „auf jeder Site automatisch aktiv", **keine**
  Content-Aggregation über Sites hinweg. Jede Site hat ihre eigenen Optionen, ihren eigenen Cache und ihre eigene
  `/llms.txt`. Sprachversionen werden bewusst nur als Links zwischen unabhängig konfigurierten Sites abgebildet
  (`Alternate_Sites`), nicht automatisch über WPML/Polylang erkannt.
- Es wird nie eine physische `llms.txt`-Datei geschrieben; alles läuft über die virtuelle Rewrite-Route + Cache.

## Tests (kein WP-Testsuite/wp-env!)
- `tests/bootstrap.php` definiert eigene, minimale Stubs für WP-Funktionen (Vorbild:
  `sev-webp-migrator-for-w3tc/tests/bootstrap.php`), lädt dann die Plugin-Klassen direkt.
- Getestet wird reine Logik ohne echte DB: Fallback-Kette in `Description_Resolver`, SEO-Plugin-Erkennung in
  `Noindex_Resolver`, Reihenfolge-/Default-Logik in `Category_Order`, Gruppierung/Exclude-/Noindex-Filter in
  `Content_Selector`, Label-Auflösung in `Alternate_Sites`, und die Gesamt-Assembly (inkl. Weglassen leerer
  Sektionen) in `Generator`.
- Ausführen: `composer test` bzw. `vendor/bin/phpunit` (kein Docker/wp-env erforderlich).

## Weitere Dev-Workflows
- `composer lint:php` / `composer fix:php` – PHPCS/PHPCBF (WPCS).
- Keine Build-Pipeline für JS/CSS – die Admin-Seite nutzt reines Vanilla-JS/jQuery-UI-Sortable ohne Bundler.
- `uninstall.php` entfernt alle `sevllms_*`-Optionen, den Cache-Transient und die `_sevllms_exclude`-Postmeta auf
  jeder Site (Multisite-Loop analog zu `sev-calculate-price-for-booking-calendar/uninstall.php`).
- `languages/sev-structured-llms-txt.pot` nach Änderungen an übersetzbaren Strings neu erzeugen mit
  `wp i18n make-pot . languages/sev-structured-llms-txt.pot --domain=sev-structured-llms-txt --exclude=tests,vendor,.git,.github`.
  Die mitgelieferten Übersetzungen (`languages/sev-structured-llms-txt-de_DE.po` und
  `languages/sev-structured-llms-txt-de_DE_formal.po`, kompiliert zu `.mo` via `wp i18n make-mo languages/`) werden
  bei Änderungen manuell nachgezogen. Beide Locales werden mitgeliefert, weil WordPress `de_DE` (Deutsch) und
  `de_DE_formal` (Deutsch, Sie) als komplett getrennte Locales mit eigenen `.mo`-Dateinamen behandelt — ohne die
  `_formal`-Datei bleibt eine Site mit „Deutsch (Sie)" als Standardsprache unübersetzt, obwohl `de_DE` vorhanden
  ist. Da unsere Strings ohnehin durchgehend in der Sie-Form formuliert sind, ist der Inhalt beider Dateien
  identisch bis auf den `Language:`-Header.
- Solange das Plugin **nicht** auf WP.org gelistet ist, lädt `sevllms_load_textdomain()` (Bootstrap, Hook `init`)
  das mitgelieferte `.mo` explizit per `load_plugin_textdomain()` aus dem eigenen `languages/`-Ordner — WordPress'
  automatischer Übersetzungs-Loader prüft nur `wp-content/languages/plugins/`, nie den `languages/`-Ordner eines
  Plugins selbst, und dieser Ordner wird erst durch WP.org befüllt, sobald das Plugin dort gelistet und über
  translate.wordpress.org übersetzt ist. Deshalb sind `.po`/`.mo` aktuell **nicht** über `.distignore`
  ausgeschlossen (nötig, damit z. B. ein lokal installiertes ZIP von GitHub auf einer deutschen Site tatsächlich
  „## Seiten"/„## Beiträge" statt „## Pages"/„## Posts" anzeigt). Sobald das Plugin auf WP.org live ist: `.po`,
  `.mo`, `.json` wieder in `.distignore` aufnehmen (siehe Korrektur in `sev-simple-hreflang` 1.2.0) und prüfen, ob
  `sevllms_load_textdomain()` dann noch nötig ist oder mit WordPress' automatischem Laden kollidiert (seit WP 6.7
  ggf. „translation loaded too early"-Hinweis, falls beide Mechanismen greifen).

## Beim Ändern von Code beachten
- Neue Cache-Invalidierungs-Hooks für Content-Änderungen (Posts, Terms, …) gehören in `Cache::register()`. Die
  Ausnahme ist die Settings-Seite selbst: dort lieber am `settings-updated`-Redirect-Flag festmachen (siehe
  `Admin_Settings::maybe_clear_cache_after_save()`) statt an `update_option_{$option}`, aus den oben genannten
  Gründen (verpasster erster Save, verpasste leere Checkbox-Listen).
- Änderungen an der Gruppierungs-/Reihenfolge-Logik (`Content_Selector`, `Category_Order`) immer mit Tests
  absichern, da sie die sichtbare Struktur der ausgelieferten `llms.txt` direkt bestimmen.
- Neue SEO-Plugin-Integrationen (weitere Meta-Description-Quellen) gehören in
  `Description_Resolver::SEO_META_KEYS`, in der Reihenfolge der Marktverbreitung.
- Neue SEO-Plugin-Integrationen für die Noindex-Erkennung gehören als eigene `from_*()`-Methode in
  `Noindex_Resolver`, analog zu den bestehenden vier.
