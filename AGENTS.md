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
- `includes/class-category-order.php` – `Category_Order`: liest/schreibt die Options-gesteuerte Reihenfolge und
  Ein-/Ausschluss-Liste der Kategorien (`sevllms_category_order`, Array von Term-IDs). Ohne gespeicherte
  Konfiguration: alle Kategorien, absteigend nach Post-Anzahl (`default_order()`). `admin_rows()` liefert die
  vollständige Liste für den Settings-Screen (konfigurierte zuerst, Rest alphabetisch angehängt, unchecked).
- `includes/class-content-selector.php` – `Content_Selector`: `get_pages()` liefert alle veröffentlichten, nicht
  ausgeschlossenen Seiten in Standard-Seitenreihenfolge (`menu_order`, dann Titel). `get_grouped_posts()` liefert
  Beiträge gruppiert nach primärer Kategorie (Yoast-Primary-Category falls gesetzt und gültig, sonst die
  Kategorie mit der niedrigsten Term-ID), in der von `Category_Order` vorgegebenen Reihenfolge, je Gruppe neueste
  zuerst. Unkategorisierte Beiträge landen unter dem Sonderschlüssel `Content_Selector::UNCATEGORIZED_KEY`, nur
  falls nicht leer.
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
  Content. Wird bei `save_post`/`delete_post` (post & page), Kategorie-Änderungen und beim Speichern der
  Plugin-Settings automatisch geleert.
- `includes/class-rewrite.php` – `Rewrite`: registriert die Rewrite-Rule `^llms\.txt$` und liefert den (gecachten)
  Content bei `template_redirect` aus, als `text/plain`. `flush_current_site()` ist die statische Hilfsfunktion,
  die beim Aktivieren (pro Site, siehe Bootstrap) die Rewrite-Regeln neu registriert und flusht.
- `includes/class-post-meta.php` – `Post_Meta`: „Exclude from llms.txt"-Checkbox-Metabox auf `post` und `page`
  (Postmeta `_sevllms_exclude`).
- `includes/class-admin-settings.php` – Settings-Seite unter **Settings → llms.txt**: Tagline-Feld, per Drag&Drop
  sortierbare Kategorie-Checkliste (jQuery UI Sortable, WP-Core-Bundle, kein externes JS), auf Multisite ein
  Alternate-Sites-Repeater (reines Vanilla-JS Add/Remove, kein Build-Step), Live-Vorschau und ein
  „Cache leeren"-Button (`admin-post.php?action=sevllms_purge_cache`).

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
- Getestet wird reine Logik ohne echte DB: Fallback-Kette in `Description_Resolver`, Reihenfolge-/Default-Logik in
  `Category_Order`, Gruppierung/Exclude-Filter in `Content_Selector`, Label-Auflösung in `Alternate_Sites`, und die
  Gesamt-Assembly (inkl. Weglassen leerer Sektionen) in `Generator`.
- Ausführen: `composer test` bzw. `vendor/bin/phpunit` (kein Docker/wp-env erforderlich).

## Weitere Dev-Workflows
- `composer lint:php` / `composer fix:php` – PHPCS/PHPCBF (WPCS).
- Keine Build-Pipeline für JS/CSS – die Admin-Seite nutzt reines Vanilla-JS/jQuery-UI-Sortable ohne Bundler.
- `uninstall.php` entfernt alle `sevllms_*`-Optionen, den Cache-Transient und die `_sevllms_exclude`-Postmeta auf
  jeder Site (Multisite-Loop analog zu `sev-calculate-price-for-booking-calendar/uninstall.php`).

## Beim Ändern von Code beachten
- Neue Cache-Invalidierungs-Hooks gehören in `Cache::register()`, nicht verstreut in anderen Klassen.
- Änderungen an der Gruppierungs-/Reihenfolge-Logik (`Content_Selector`, `Category_Order`) immer mit Tests
  absichern, da sie die sichtbare Struktur der ausgelieferten `llms.txt` direkt bestimmen.
- Neue SEO-Plugin-Integrationen (weitere Meta-Description-Quellen) gehören in
  `Description_Resolver::SEO_META_KEYS`, in der Reihenfolge der Marktverbreitung.
