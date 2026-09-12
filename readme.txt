=== SEV Structured llms.txt ===
Contributors: hfranz
Tags: llms.txt, ai, seo, multisite, categories
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates a structured llms.txt at /llms.txt, listing pages, posts, and products grouped by category, so AI assistants can find your content.

== Description ==

SEV Structured llms.txt generates a [llms.txt](https://llmstxt.org/) file for your site, served at `/llms.txt`. llms.txt is a proposed standard, Markdown-based document that gives AI assistants and large language models a concise, structured overview of a site's content — similar in spirit to `robots.txt` or a sitemap, but written for LLMs to read.

No physical file is ever written to disk. The document is rendered on request through a virtual URL and cached, so it always reflects your current published content without requiring server write access.

**What the generated document looks like**

* A `# Site Name` heading and a `> tagline` blockquote (your own tagline, or the site's tagline by default).
* A `## Pages` section listing every published page, each as `- [Title](URL): Description`.
* Optionally, one or more `> Label version: URL` lines linking to this llms.txt on other sites in your network (e.g. one site per language).
* A `## Posts` section with one `### Category Name` subsection per post category, newest posts first, in an order you control.
* If WooCommerce is active, a `## Products` section with one `### Category Name` subsection per product category, newest products first, in an order you control.

The description behind each link is taken from your SEO plugin's meta description (Yoast SEO, Rank Math, SEOPress, or All in One SEO, in that order) if one is set, otherwise from the post/page/product excerpt or a trimmed excerpt of its content.

**Features**

* Serves `/llms.txt` as a virtual, cached endpoint — nothing is written to disk.
* Automatically lists every published page.
* Groups every published post by its primary category, in an order you choose (drag & drop in the settings screen), newest post first within each category.
* Uses each post's primary category (compatible with Yoast SEO's "Primary category" setting) to avoid listing a post twice.
* If WooCommerce is active, automatically lists every published product too, grouped by its primary product category the same way posts are, with its own drag & drop order in the settings screen.
* Pulls descriptions from Yoast SEO, Rank Math, SEOPress, or All in One SEO meta descriptions when available, falling back to the excerpt.
* Per-item "Exclude from llms.txt" checkbox on posts, pages, and products, for content that shouldn't be listed (e.g. legal pages).
* Pages, posts, and products marked "noindex" in Yoast SEO, Rank Math, SEOPress, or All in One SEO are excluded automatically.
* Works on WordPress Multisite: activate network-wide or per site. Each site generates and caches its own independent llms.txt.
* On Multisite, link this llms.txt to the equivalent llms.txt of other sites in the network (e.g. a per-language site), with the link label automatically derived from the target site's language.
* Cache is automatically cleared when a post, page, product, category, or product category changes, or when settings are saved.

**Limitations**

This plugin lists pages, posts, and (if WooCommerce is active) products only; other public post types are not included. On a Multisite network, each site's llms.txt only ever describes that site's own content — it does not aggregate content across sites into one document.

== Installation ==

1. Upload the `sev-structured-llms-txt` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu (per site, or network-wide on Multisite).
3. Visit **Settings → llms.txt** to set an optional tagline, choose which post (and, with WooCommerce, product) categories to include and in what order, and (on Multisite) link to other language sites.
4. Your llms.txt is now available at `/llms.txt`.

== Frequently Asked Questions ==

= Does it support WooCommerce products? =

Yes, automatically. If WooCommerce is active, every published product is listed in a `## Products` section, grouped by product category the same way posts are grouped by category, with its own order in **Settings → llms.txt**. If WooCommerce isn't active, this section is simply omitted.

= Does this create a physical llms.txt file? =

No. It is served dynamically through a rewrite rule and cached, so it always reflects your current content without needing any file write access.

= How is the description behind each link determined? =

If Yoast SEO, Rank Math, SEOPress, or All in One SEO has a meta description set for that page/post, it is used (checked in that order). Otherwise, the post's own excerpt is used, or a short excerpt generated from its content.

= How do I exclude a page or post, like my privacy policy? =

Open it in the block editor and check "Exclude from llms.txt" in the sidebar panel of the same name. Alternatively, marking it "noindex" in Yoast SEO, Rank Math, SEOPress, or All in One SEO excludes it automatically too, since content hidden from search engines is assumed to not be meant for LLMs either.

= How do I control the order of the category sections? =

Go to **Settings → llms.txt** and drag the categories into the order you want. Uncheck any category you don't want listed at all.

= Does it work with WordPress Multisite installations? =

Yes. The plugin can be activated network-wide or on individual sites. Each site generates and caches its own llms.txt independently; activating it network-wide does not combine content from multiple sites into one document. If you run separate sites per language, you can link this llms.txt to the equivalent one on other sites in the network from the settings screen.

= Does it require an SEO plugin? =

No. Yoast SEO, Rank Math, SEOPress, and All in One SEO are used automatically if installed, purely as an optional source for link descriptions. Without any of them, the post/page excerpt is used instead.

== Screenshots ==
1. Settings screen: tagline, category order, and live preview.

== Changelog ==

= 1.3.0 =
* Pages are now listed oldest-modified first, with the static front page (if configured) pinned first, matching how Yoast SEO orders pages in its XML sitemap. Previously ordered by menu order, then title.

= 1.2.2 =
* Added a "Support" and a star-rating link to the plugin's row on the Plugins list page.

= 1.2.1 =
* Shortened the short description to fit WordPress.org's 150-character limit; the previous one was truncated on import.

= 1.2.0 =
* Added WooCommerce support: if WooCommerce is active, published products are now listed in a `## Products` section, grouped by product category (drag & drop order in the settings screen, same as post categories), with the manual "Exclude from llms.txt" checkbox and SEO-plugin "noindex" detection applying to products too.

= 1.1.5 =
* Removed the explicit `load_plugin_textdomain()` call added in 1.1.2; bundled translations (`.po`/`.mo`/`.json`) are now excluded from the distributed package via `.distignore`.

= 1.1.4 =
* Resolved WordPress Plugin Check / PHPCS warnings in `uninstall.php` (direct DB query) and `sev-structured-llms-txt.php` (`load_plugin_textdomain()`) with justified inline suppressions.

= 1.1.3 =
* Added a `de_DE_formal` ("Deutsch (Sie)") translation alongside `de_DE`; WordPress treats formal German as a fully separate locale, so sites using it were still shown the English defaults.

= 1.1.2 =
* The bundled German (de_DE) translation now actually loads on sites not yet installed from WordPress.org (e.g. a manual/GitHub install), so a German-language site correctly shows "## Seiten"/"## Beiträge" instead of the English defaults.

= 1.1.1 =
* Fixed WordPress redirecting `/llms.txt` to `/llms.txt/` via `redirect_canonical()` (the same issue reported for `/.well-known/security.txt`).

= 1.1.0 =
* Pages and posts marked "noindex" in Yoast SEO, Rank Math, SEOPress, or All in One SEO are now excluded automatically, in addition to the manual "Exclude from llms.txt" checkbox.
* Added `languages/` with a `.pot` template and a German (de_DE) translation.

= 1.0.0 =
* Initial release.
