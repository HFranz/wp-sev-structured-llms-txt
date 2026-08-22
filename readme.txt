=== SEV Structured llms.txt ===
Contributors: hfranz
Tags: llms.txt, ai, seo, multisite, categories
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates a structured llms.txt at /llms.txt, listing pages and posts grouped by category, so AI assistants and LLMs can discover your site's content.

== Description ==

SEV Structured llms.txt generates a [llms.txt](https://llmstxt.org/) file for your site, served at `/llms.txt`. llms.txt is a proposed standard, Markdown-based document that gives AI assistants and large language models a concise, structured overview of a site's content — similar in spirit to `robots.txt` or a sitemap, but written for LLMs to read.

No physical file is ever written to disk. The document is rendered on request through a virtual URL and cached, so it always reflects your current published content without requiring server write access.

**What the generated document looks like**

* A `# Site Name` heading and a `> tagline` blockquote (your own tagline, or the site's tagline by default).
* A `## Pages` section listing every published page, each as `- [Title](URL): Description`.
* Optionally, one or more `> Label version: URL` lines linking to this llms.txt on other sites in your network (e.g. one site per language).
* A `## Posts` section with one `### Category Name` subsection per post category, newest posts first, in an order you control.

The description behind each link is taken from your SEO plugin's meta description (Yoast SEO, Rank Math, SEOPress, or All in One SEO, in that order) if one is set, otherwise from the post/page excerpt or a trimmed excerpt of its content.

**Features**

* Serves `/llms.txt` as a virtual, cached endpoint — nothing is written to disk.
* Automatically lists every published page.
* Groups every published post by its primary category, in an order you choose (drag & drop in the settings screen), newest post first within each category.
* Uses each post's primary category (compatible with Yoast SEO's "Primary category" setting) to avoid listing a post twice.
* Pulls descriptions from Yoast SEO, Rank Math, SEOPress, or All in One SEO meta descriptions when available, falling back to the excerpt.
* Per-post/page "Exclude from llms.txt" checkbox, for content like legal pages that shouldn't be listed.
* Pages and posts marked "noindex" in Yoast SEO, Rank Math, SEOPress, or All in One SEO are excluded automatically.
* Works on WordPress Multisite: activate network-wide or per site. Each site generates and caches its own independent llms.txt.
* On Multisite, link this llms.txt to the equivalent llms.txt of other sites in the network (e.g. a per-language site), with the link label automatically derived from the target site's language.
* Cache is automatically cleared when a post, page, or category changes, or when settings are saved.

**Limitations**

This plugin lists pages and posts only; other public post types are not included. On a Multisite network, each site's llms.txt only ever describes that site's own content — it does not aggregate content across sites into one document.

== Installation ==

1. Upload the `sev-structured-llms-txt` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu (per site, or network-wide on Multisite).
3. Visit **Settings → llms.txt** to set an optional tagline, choose which post categories to include and in what order, and (on Multisite) link to other language sites.
4. Your llms.txt is now available at `/llms.txt`.

== Frequently Asked Questions ==

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

= 1.1.0 =
* Pages and posts marked "noindex" in Yoast SEO, Rank Math, SEOPress, or All in One SEO are now excluded automatically, in addition to the manual "Exclude from llms.txt" checkbox.
* Added `languages/` with a `.pot` template and a German (de_DE) translation.

= 1.0.0 =
* Initial release.
