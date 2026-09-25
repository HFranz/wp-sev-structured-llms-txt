=== Structured llms.txt ===
Contributors: hfranz
Tags: llms.txt, ai, seo, woocommerce, multisite
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Structured llms.txt for AI assistants: posts and products grouped by category, works with Yoast, Rank Math & more, Multisite ready.

== Description ==

**Give AI assistants a clean, structured map of your site, not just a flat list of links.**

[llms.txt](https://llmstxt.org/) is a proposed standard: a Markdown file at `/llms.txt` that gives AI assistants and large language models a compact overview of a website's content. Think of it as a sitemap written for LLMs instead of search engines.

A plain list of every URL is easy to generate, but hard to make sense of. Structured llms.txt organizes your content the way a human editor would: pages first, then posts grouped by category, then (with WooCommerce) products grouped by product category, each with a meaningful one-line description. You decide which categories appear and in which order.

See it live: [sevmatic.com/en/llms.txt](https://sevmatic.com/en/llms.txt)

**Example output**

    # My Bakery

    > Fresh bread and pastries, baked daily in Karlsruhe.

    ## Pages

    - [Home](https://example.com/): Artisan bakery with sourdough bread, cakes and catering.
    - [About us](https://example.com/about/): Three generations of baking tradition.

    ## Posts

    ### Recipes

    - [Classic sourdough](https://example.com/sourdough/): Step-by-step guide for your first sourdough loaf.

    ## Products

    ### Cakes

    - [Cheesecake](https://example.com/product/cheesecake/): Creamy baked cheesecake, available whole or by the slice.

**Why Structured llms.txt?**

Several SEO plugins now include a basic llms.txt feature. This plugin focuses on what they usually don't:

* **Structure, not just a list:** posts and products are grouped by category, in an order you set by drag & drop. Each post appears exactly once, under its primary category.
* **Works with your SEO plugin, whichever it is:** descriptions and "noindex" settings are read from Yoast SEO, Rank Math, SEOPress or All in One SEO. No SEO plugin? Excerpts are used instead.
* **Built for multilingual Multisite:** each site gets its own llms.txt, and language versions can link to each other automatically.
* **WooCommerce ready:** products are listed automatically as soon as WooCommerce is active.
* **Lightweight:** no file is written to disk, no server write access needed, and the output is cached.

**Features**

* Serves `/llms.txt` as a virtual, cached endpoint. Nothing is written to disk.
* Automatically lists every published page, with the static front page first.
* Groups every published post by its primary category (compatible with Yoast SEO's "Primary category" setting), newest first within each category.
* Drag & drop ordering of post categories and product categories on the settings screen; uncheck a category to hide it.
* WooCommerce: every published product is listed, grouped by primary product category.
* Link descriptions come from Yoast SEO, Rank Math, SEOPress or All in One SEO meta descriptions (checked in that order), falling back to the excerpt.
* Per-item "Exclude from llms.txt" checkbox for pages, posts and products (for example legal pages).
* Content marked "noindex" in any of the four supported SEO plugins is excluded automatically.
* WordPress Multisite: activate network-wide or per site. Each site generates and caches its own llms.txt.
* Multisite language linking: point this llms.txt to its counterparts on other sites in the network, with labels derived from each site's language.
* Optional custom tagline, shown as a blockquote below the site name.
* Live preview of the generated llms.txt on the settings screen.
* The cache clears automatically whenever content, categories or settings change.
* Section headings follow the site language (for example "## Seiten" and "## Beiträge" on German sites).

**Limitations**

Structured llms.txt lists pages, posts and (if WooCommerce is active) products. Other custom post types are not included. On a Multisite network, each site's llms.txt describes only that site's own content; content from several sites is never merged into one document.

== Installation ==

1. Install the plugin via **Plugins → Add New** or upload the `sev-structured-llms-txt` folder to `/wp-content/plugins/`.
2. Activate the plugin (per site, or network-wide on Multisite).
3. Open **Settings → llms.txt** to set an optional tagline, choose and order the post (and product) categories, and on Multisite link other language sites.
4. Visit `https://your-site.com/llms.txt` to see the result.

== Frequently Asked Questions ==

= My SEO plugin already has an llms.txt feature. Is there a conflict? =

Only one llms.txt can be served at `/llms.txt`, so please use only one tool for it. Disable the llms.txt option in your SEO plugin, and check your web root for a physical `llms.txt` file: some plugins write a real file, and a real file is always served by the web server before WordPress gets a chance to respond. Delete it (or rename it) and Structured llms.txt takes over. Everything else in your SEO plugin keeps working as before, and its meta descriptions and "noindex" settings are still used.

= Will AI assistants actually read my llms.txt? =

llms.txt is a proposed standard, and support differs between AI providers and tools. It costs nothing to offer, it helps any assistant or agent that looks for it, and it gives you control over how your site is summarized. It is not a replacement for classic SEO, and no plugin can guarantee that a specific AI service uses it.

= Does it support WooCommerce products? =

Yes, automatically. If WooCommerce is active, every published product is listed in a `## Products` section, grouped by product category, with its own order under **Settings → llms.txt**. Without WooCommerce, this section is simply omitted.

= Does it require an SEO plugin? =

No. Yoast SEO, Rank Math, SEOPress and All in One SEO are used automatically if installed, as a source for descriptions and "noindex" settings. Without any of them, the page or post excerpt is used instead.

= Does this create a physical llms.txt file? =

No. The file is served dynamically through a rewrite rule and cached, so it always reflects your current content without any file write access.

= How is the description behind each link determined? =

If Yoast SEO, Rank Math, SEOPress or All in One SEO has a meta description for the item, it is used (checked in that order). Otherwise the item's own excerpt is used, or a short excerpt generated from its content.

= How do I exclude a page or post, like my privacy policy? =

Open it in the block editor and check "Exclude from llms.txt" in the sidebar panel of the same name. Items marked "noindex" in a supported SEO plugin are excluded automatically too, since content hidden from search engines is usually not meant for LLMs either.

= How do I control the order of the category sections? =

Go to **Settings → llms.txt** and drag the categories into the order you want. Uncheck any category you don't want listed at all.

= Does it work with WordPress Multisite? =

Yes. Activate it network-wide or on individual sites. Each site generates and caches its own llms.txt. If you run one site per language, you can link the llms.txt files of these sites to each other on the settings screen.

= I get a 404 at /llms.txt. What can I do? =

Go to **Settings → Permalinks** and click "Save Changes" once. This refreshes the rewrite rules. Also make sure no physical `llms.txt` file or server rule (for example in `.htaccess` or the Nginx config) intercepts the URL.

== Screenshots ==

1. Settings screen: tagline and post category order, with drag & drop.
2. Settings screen: full page, including the live llms.txt preview at the bottom.

== Changelog ==

= 1.4.0 =
* Titles, category names, and descriptions are now decoded and stripped of markup before being written to llms.txt: WordPress-escaped characters (e.g. "AI &amp; Software" or a `wptexturize()`-inserted "&#8211;" or "&hellip;") and soft hyphens (`&shy;`) no longer leak into the output as literal HTML entities.
* Moved the alternate-language-version link to directly below the tagline, before `## Pages`, matching the llms.txt specification's placement of blockquote metadata right after the H1; it's now also written as a proper Markdown link, `[URL](URL)`, instead of a bare URL.

= 1.3.0 =
* Pages are now sorted by last-modified date, oldest first, with the static front page (if configured) always at the top. This matches how Yoast SEO orders pages in its XML sitemap. Previously pages were ordered by menu order, then by title.

= 1.2.2 =
* Added "Support" and "Rate this plugin" links to the plugin's row on the Plugins screen.

= 1.2.1 =
* Shortened the short description to fit the 150-character limit of WordPress.org.

= 1.2.0 =
* New: WooCommerce support. Published products are listed in a `## Products` section, grouped by product category, with drag & drop ordering. The "Exclude from llms.txt" checkbox and "noindex" detection apply to products too.

= 1.1.5 =
* Bundled translation files are no longer shipped in the package; translations are delivered via translate.wordpress.org.

= 1.1.4 =
* Code quality: resolved Plugin Check and PHPCS warnings.

= 1.1.3 =
* Added a formal German translation (de_DE_formal, "Deutsch (Sie)").

= 1.1.2 =
* Fixed: the German translation now also loads on sites where the plugin was installed manually.

= 1.1.1 =
* Fixed: WordPress redirected `/llms.txt` to `/llms.txt/`.

= 1.1.0 =
* New: pages and posts marked "noindex" in Yoast SEO, Rank Math, SEOPress or All in One SEO are excluded automatically.
* Added a German (de_DE) translation.

= 1.0.0 =
* Initial release.
