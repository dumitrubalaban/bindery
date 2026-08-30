=== Bindery ===
Contributors: dumitrubalaban
Tags: inline editing, front-end editing, client editing, custom theme, no page builder
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let clients edit your theme's text on the live page — you choose exactly what is editable. Clean markup, no page builder, multilingual.

== Description ==

**Bindery gives any WordPress theme a clean, locked, edit-in-place experience — without a page builder and without giving up hand-written markup.**

Custom and AI-built themes are fast and clean, but content-hostile: a client can't change a headline or a phone number without a developer. Page builders fix that but bloat your markup and your editing screen. Bindery is the missing middle — **you decide what a client may change, and they edit only that, right on the live page.**

Everything is built on native WordPress primitives (the Block Bindings API, `block.json`, the REST API), so Bindery never owns your markup and adds no builder lock-in.

= What it does =

* **Edit on the live page.** A floating "✎ Edit page" button turns the regions you declared into inline-editable text. Click, type, click away — saved.
* **No-code setup.** From the **Bindery** settings screen, tick which HTML tags (headings, paragraphs, lists, quotes…) become editable site-wide. That's it.
* **Or mark regions in code.** A one-line template helper (`bindery_attrs()`) makes a single element editable; template tags render declared fields anywhere; eight ready-made blocks cover richer content.
* **Locked by design.** Only declared regions are editable. Layout, structure and anything unmarked stay untouchable — clients can't break the design.
* **Multilingual.** Every field stores a value per language; unedited languages fall back to your code default.
* **Revision history + one-click restore** for every edit.
* **Export / import** all content as JSON for staging → production migrations (also via WP-CLI).
* **Theme-portable.** Verified across Astra, OceanWP, Neve, the default Twenty-* themes and block (FSE) themes; the bundled blocks adapt to your theme's colours.

= Five ways to make content editable =

1. **Settings page** — choose editable tags, zero code.
2. **Attribute helper** — `bindery_attrs()` marks one element editable in your template.
3. **Blocks** — drop a Bindery block into the block editor.
4. **Template tags** — `bindery_field()` / `bindery_value()` render a declared field in PHP.
5. **Auto mode** — make all existing page text editable automatically.

= Built for developers, too =

A small DI container with four filter-driven seams (field types, value sources, storage adapters, locale providers). Values live in their own table, never in your markup; writes are whitelisted to the fields actually on the page and sanitized per type; output is escaped per type. WP-CLI commands for export/import/history/restore. Full API in the bundled `DEVELOPERS.md` and `README.md`.

== Installation ==

1. Upload the `bindery` folder to `/wp-content/plugins/`, or install the ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate **Bindery** through the **Plugins** screen.
3. Open **Bindery** in the admin menu, turn on inline editing and tick which elements are editable.
4. Visit any page on the front end and click **✎ Edit page** to start editing.

No build step or Composer is required to use the plugin — the compiled assets ship in the package.

== Frequently Asked Questions ==

= Does Bindery change my theme's markup? =
No. It resolves a value (a client's saved override, or your code default) and outputs your own clean markup. Editable regions are marked with `data-*` attributes that are only emitted for logged-in users who can edit; visitors get clean HTML.

= Do I have to write code? =
No. With auto content editing enabled on the settings page, your existing page text becomes editable with no code at all. Code helpers exist for developers who want precise, per-region control.

= Where is the content stored? =
In a dedicated table (`wp_bindery_values`), one row per field/page/language — never mixed into your post markup. Your code default is never stored, so improving it in code reaches every site that hasn't overridden the field.

= Can a client break the layout? =
No. Only the regions you declared editable can be changed. Everything else — structure, classes, layout — is untouchable.

= Is it multilingual? =
Yes. Each field stores a value per language, with a language switcher in the edit overlay. Plug in WPML/Polylang via a filter, or use the built-in provider.

= Can I move content between sites? =
Yes — export/import all values as JSON from the settings page or with `wp bindery export` / `wp bindery import`.

= Does it work with page builders? =
Bindery is an alternative to page builders, not an add-on. It works with any classic or block theme and the native block editor.

= Does it work with ACF, custom post type loops, or plain PHP arrays? =
Yes. The repeater helper (`bindery_repeater_attrs()` / `bindery_rows()`) wraps any loop — an ACF repeater field, a `WP_Query` of custom post types, or a hand-written PHP array — and lets a client add, edit, reorder and delete rows through a front-end modal, without changing how your template fetches the data.

= Is Bindery free? =
Yes, Bindery is free and released under the GPL, like WordPress itself. There is no paid tier.

= What happens to my content if I deactivate or uninstall Bindery? =
Deactivating keeps all saved values in the `wp_bindery_values` table untouched. Uninstalling (deleting the plugin from the Plugins screen) removes that table and its data — export your content first with the JSON export tool or `wp bindery export` if you might reinstall later.

= Where can I get support or read the full developer docs? =
Use the **Support** tab on this page for questions and bug reports. The plugin's overview and source code are on GitHub: https://github.com/dumitrubalaban/bindery

== Screenshots ==

1. Editing text in place — the outlined region shows a rich-text toolbar and a Save button right on the live page.
2. Replacing an image — click any declared image region to open the native media library and swap it, no page reload.
3. The native WordPress media library opens right on the front end to pick a replacement image.
4. A repeater in action — clients can add, edit and reorder list items (here, testimonials) through a simple modal, all from a theme-declared loop.
5. One line of code turns a static PHP loop into a client-editable repeater.

== Changelog ==

= 0.1.0 =
* Initial public release.
* No-code settings page: choose which element types are client-editable, per post type.
* Front-end inline edit overlay with per-language switching, accent colour and strict mode.
* Automatic content editing: make existing page text editable without declaring fields.
* Attribute helper and template tags for precise, code-declared editable regions.
* Eight self-contained blocks (editable text, cards, slider, image, button, icon, form, section) plus block patterns.
* Per-language values on a custom table; value precedence (saved override, else code default).
* Whitelisted, capability-gated REST editing; per-type sanitization and escaping.
* Revision history with one-click restore; JSON export/import (settings page and WP-CLI).
* Per-request value caching, multisite-aware activation, accessibility pass.

== Upgrade Notice ==

= 0.1.0 =
First public release of Bindery.
