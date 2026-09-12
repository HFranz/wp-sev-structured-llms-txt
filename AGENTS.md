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
- `includes/class-product-category-order.php` – `Product_Category_Order`: dasselbe wie `Category_Order`, aber für
  WooCommerce's `product_cat`-Taxonomie (eigene Option `sevllms_product_category_order`, eigene Term-Order/Admin-Rows).
  Bewusst eine separate Klasse statt eines Taxonomie-Parameters an `Category_Order`, da `get_categories()` fest an die
  `category`-Taxonomie gebunden ist und `Product_Category_Order` stattdessen `get_terms()` nutzt. Ist WooCommerce nicht
  aktiv, existiert die Taxonomie nicht; `get_terms()` liefert dann `WP_Error`, was hier zu einem leeren Array
  normalisiert wird – kein explizites „ist WooCommerce aktiv"-Gate nötig.
- `includes/class-content-selector.php` – `Content_Selector`: `get_pages()` liefert alle veröffentlichten, nicht
  ausgeschlossenen Seiten sortiert nach `post_modified` aufsteigend (am längsten nicht geändert zuerst), mit der
  statischen Startseite (`show_on_front` = `page`, `page_on_front`) fest an erster Stelle, sofern konfiguriert und
  in der Liste enthalten — das entspricht der Reihenfolge, die Yoast SEO in seiner XML-Sitemap für Seiten verwendet
  (`get_first_links()`/`ORDER BY post_modified ASC` in `class-post-type-sitemap-provider.php`). „Ausgeschlossen"
  heißt: manuelles `_sevllms_exclude`-Flag ODER von einem SEO-Plugin auf „noindex" gesetzt (siehe `Noindex_Resolver`
  oben).
  `get_grouped_posts()` liefert Beiträge gruppiert nach primärer Kategorie (Yoast-Primary-Category falls gesetzt und
  gültig, sonst die Kategorie mit der niedrigsten Term-ID), in der von `Category_Order` vorgegebenen Reihenfolge, je
  Gruppe neueste zuerst. Unkategorisierte Beiträge landen unter dem Sonderschlüssel
  `Content_Selector::UNCATEGORIZED_KEY`, nur falls nicht leer. `get_grouped_products()` ist das Pendant für
  WooCommerce-Produkte (`post_type` `product`, Taxonomie `product_cat`, Yoast-Meta
  `_yoast_wpseo_primary_product_cat`, geordnet über `Product_Category_Order`). Ist WooCommerce nicht aktiv, existieren
  schlicht keine Posts vom Typ `product`, `get_posts()` liefert dann ein leeres Array – auch hier kein explizites Gate
  nötig, das Verhalten ergibt sich allein aus der Datenlage.
- `includes/class-alternate-sites.php` – `Alternate_Sites::resolve()`: löst die in den Settings konfigurierte
  Liste von Ziel-Sites (`sevllms_alternate_sites`, je Eintrag `site_id` + optionales `label`) zu Label→URL-Paaren
  auf. Fehlt ein Label, wird es aus der Locale der Ziel-Site abgeleitet (`switch_to_blog()` + `get_locale()` +
  eingebaute Locale→Sprachname-Map, Fallback: roher Locale-Code), filterbar über `sevllms_alternate_site_label`.
  Nur relevant auf Multisite; auf Single-Site liefert `resolve()` immer ein leeres Array.
- `includes/class-generator.php` – `Generator::generate()`: baut das komplette Markdown-Dokument aus den
  Bausteinen oben zusammen (Intro-Block, „## Pages", Alternate-Site-Zeilen, „## Posts" mit „###"-Unterkapiteln je
  Kategorie, und – falls vorhanden – „## Products" mit „###"-Unterkapiteln je Produktkategorie). Leere Sektionen
  werden komplett weggelassen (das ist auch der Mechanismus, über den die Products-Sektion auf Sites ohne
  WooCommerce verschwindet). Über den Filter `sevllms_generated_content` überschreibbar.
- `includes/class-cache.php` – `Cache`: Transient-Cache (`sevllms_cache`, 12h TTL als Backstop) für den generierten
  Content. Wird bei `save_post`/`delete_post` (post, page & product) sowie Kategorie-/Produktkategorie-Änderungen
  automatisch geleert. Die Invalidierung beim Speichern der Plugin-Settings sitzt bewusst *nicht* hier, sondern in
  `Admin_Settings::maybe_clear_cache_after_save()` (siehe unten).
- `includes/class-rewrite.php` – `Rewrite`: registriert die Rewrite-Rule `^llms\.txt$` und liefert den (gecachten)
  Content bei `template_redirect` aus, als `text/plain`. `prevent_canonical_redirect()` hängt am Filter
  `redirect_canonical` und liefert `false`, sobald unsere Query-Var gesetzt ist — sonst hängt WordPress an
  `/llms.txt` einen Trailing Slash an und redirected auf `/llms.txt/` (dasselbe Problem wie bei
  `/.well-known/security.txt`, siehe
  https://wordpress.org/support/topic/well-known-security-txt-redirects-301-403s-redirect_canonical-fix/).
  `flush_current_site()` ist die statische Hilfsfunktion, die beim Aktivieren (pro Site, siehe Bootstrap) die
  Rewrite-Regeln neu registriert und flusht.
- `includes/class-post-meta.php` – `Post_Meta`: „Exclude from llms.txt"-Checkbox-Metabox auf `post`, `page` und
  `product` (Postmeta `_sevllms_exclude`). Die Metabox auf `product` zu registrieren ist ein No-Op, solange
  WooCommerce nicht aktiv ist (der Screen existiert dann schlicht nicht).
- `includes/class-admin-settings.php` – Settings-Seite unter **Settings → llms.txt**: Tagline-Feld, je eine per
  Drag&Drop sortierbare Kategorie-Checkliste für Post- und (falls `product_cat` existiert, siehe
  `taxonomy_exists()`) Produktkategorien (jQuery UI Sortable, WP-Core-Bundle, kein externes JS; beide `<ul>`s teilen
  sich die CSS-Klasse `sevllms-term-order`), auf Multisite ein Alternate-Sites-Repeater (reines Vanilla-JS
  Add/Remove, kein Build-Step), Live-Vorschau und ein „Cache leeren"-Button
  (`admin-post.php?action=sevllms_purge_cache`). `maybe_clear_cache_after_save()` hängt an
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
- `composer lint:php` / `composer fix:php` – PHPCS/PHPCBF, Ruleset in `phpcs.xml.dist` (`WordPress-Extra`, bewusst
  *ohne* `WordPress-Docs`: dessen `Squiz.Commenting.*`-Sniffs verlangen Docblocks auf jeder privaten Property und
  jedem Constructor, was dem hier durchgängig gepflegten Stil aus typisierten Properties ohne Docblock widerspricht).
  `wp-coding-standards/wpcs` verlangt `squizlabs/php_codesniffer:^3.13`, daher ist `php_codesniffer` in
  `composer.json` bewusst auf `^3.13` gepinnt statt `^4.0` (WPCS unterstützt PHPCS 4 noch nicht, Stand 2026-08-31).
  Falls die Zip-Extraktion von `composer install`/`update` mit „Operation not permitted“ auf `chmod`/`utime`
  fehlschlägt (Sandbox-/Mount-Eigenheit), hilft `composer install --prefer-source`.
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
- `sevllms_load_textdomain()` / `load_plugin_textdomain()` wurde bewusst entfernt (Entscheidung von Heinrich,
  2026-08-26), obwohl das Plugin zu diesem Zeitpunkt noch nicht auf WP.org gelistet war. WordPress' automatischer
  Übersetzungs-Loader prüft nur `wp-content/languages/plugins/` (befüllt durch WP.org/translate.wordpress.org),
  nie den `languages/`-Ordner eines Plugins selbst — bis zur Listung auf WP.org lädt eine manuell von GitHub
  installierte Kopie also **keine** mitgelieferten Übersetzungen. Das ist bekannt/gewollt für den Zeitraum bis
  zur Freigabe. `.po`/`.mo`/`.json` werden daher über `.distignore` von der ausgelieferten ZIP ausgeschlossen
  (2026-08-26) — sie werden ohnehin nicht geladen, sobald das Plugin auf WP.org gelistet ist, übernimmt
  translate.wordpress.org die Übersetzungen. `languages/sev-structured-llms-txt.pot` bleibt Teil des Repos
  (Vorlage für Übersetzer) und ist nicht ausgeschlossen.

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
