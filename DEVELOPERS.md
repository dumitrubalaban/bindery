# Bindery — Developer Guide

Bindery lets you declare **editable regions** in clean, hand-written theme code and hand a client a **locked, multilingual editor** over only what you allowed — built on the native WordPress Block Bindings API. You keep full control of markup and CSS; the client edits values, never structure.

- [Template API](#template-api)
- [Field options](#field-options)
- [Field types](#field-types)
- [Scopes](#scopes-page-vs-global)
- [Block Bindings usage](#block-bindings-usage)
- [The Editable Text block](#the-editable-text-block)
- [Multilingual](#multilingual)
- [Value sources](#value-sources)
- [Extension points](#extension-points)
- [REST API](#rest-api)
- [Locking the editor](#locking-the-editor)
- [Uninstall / data deletion](#uninstall--data-deletion)

## Template API

```php
// Echo a rendered, escaped field (declares it on first use):
bindery_field( 'hero_title', [ 'type' => 'h1', 'default' => 'Welcome' ] );

// Get the rendered HTML string instead of echoing:
$html = bindery_get_field( 'hero_title' );

// Get the raw resolved value (override ?? default), e.g. for attributes:
$title = bindery_value( 'hero_title' );

// Loop a repeater:
foreach ( bindery_rows( 'services', [ 'fields' => [ 'title' => 'h3', 'body' => 'richtext' ] ] ) as $row ) {
	echo esc_html( $row['title'] );
	echo wp_kses_post( $row['body'] );
}

// Declare a field up-front (optional; first use auto-declares):
bindery_register_field( 'footer_phone', [ 'type' => 'text', 'scope' => 'global', 'default' => '+1 555 0100' ] );
```

**Precedence:** a stored override (for the current locale) always wins; otherwise the code `default` is used. The default is never persisted — improving it in code reaches every site that hasn't overridden the field.

## Field options

| Option | Default | Meaning |
|---|---|---|
| `type` | `text` | Field type, or an HTML tag shorthand (`h1`–`h6`, `p`, `span`, …) → tagged text, or `link` → url anchor. |
| `tag` | — | Explicit HTML element for scalar rendering. |
| `default` | `''` | Code default shown until overridden. |
| `scope` | `page` | `page` (per-post) or `global` (site-wide). |
| `source` | `stored` | Where the override comes from (see [sources](#value-sources)). |
| `localized` | `true` | Whether the value is per-locale. `false` = one value for all languages. |
| `capability` | `bindery_edit_content` | Capability required to edit this field. |
| `label` | key | Human label in the editor. |
| `attrs` | — | `[ name => value ]` HTML attributes for the rendered element. |

Type-specific args: `text` → `args['text']` (anchor text for `url`), `image` → `size`, `alt`; `url`/`link` → `text`; `repeater` → `fields` (`[ subKey => typeOrTag ]`); `callback` source → `callback`; `option` source → `option`; `acf` source → `acf_field`.

## Field types

`text`, `richtext` (allowed post HTML via `wp_kses_post`), `url` (`link`), `image` (attachment id or URL), `number`, `repeater`. Register your own — see [extension points](#extension-points).

## Scopes: page vs global

- **page** — value belongs to one post (`object_id` = post id). The homepage H1.
- **global** — one value site-wide (`object_id` 0). The footer phone number.

## Block Bindings usage

Bind any block attribute to a Bindery field directly in block markup:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{
  "source":"bindery/field","args":{"key":"hero_sub","default":"Find your stay"}
}}}} -->
<p>placeholder</p>
<!-- /wp:paragraph -->
```

`bindery/field` resolves through the same value engine. Page-scoped fields resolve against the block's `postId` context; global fields against 0.

## The Editable Text block

`bindery/editable-text` is a self-contained, server-rendered block (ships its own `style`/`viewScript`, loaded only when present). Set its **Field key**, **default**, and **HTML tag** in the block sidebar; it renders the resolved field.

## Multilingual

The same declaration is written once; values are stored per-locale. The active locale is filter-driven, so custom routing (e.g. RO/RU/EN path prefixes) works with no third-party plugin:

```php
add_filter( 'bindery/current_locale',    fn() => 'ro_RO' );          // active request locale
add_filter( 'bindery/default_locale',    fn() => 'en_US' );          // fallback
add_filter( 'bindery/available_locales', fn() => [ 'en_US' => 'English', 'ro_RO' => 'Română' ] );
```

WPML/Polylang adapters implement `Bindery\Contracts\LocaleProvider` and register via the `bindery/locale_provider` filter.

## Value sources

| Source | Reads from |
|---|---|
| `stored` (default) | Bindery's value table |
| `option` | a WP option (`args['option']`) |
| `callback` | a callable (`args['callback']`) — dynamic data (WooCommerce, an API…) |
| `acf` | Advanced Custom Fields, if active (`args['acf_field']`); falls back to default otherwise |

## Extension points

Everything is registry- and filter-driven; extend without forking.

| Hook | Type | Purpose |
|---|---|---|
| `bindery/service_providers` | filter | Add a `ServiceProvider` class to the boot cycle. |
| `bindery/register` | action | Container ready; eager registration. |
| `bindery/register_field_types` | action | Register a `FieldType`. |
| `bindery/register_sources` | action | Register a `ValueSource`. |
| `bindery/storage_adapter` | filter | Swap the `StorageAdapter` (table → meta → remote). |
| `bindery/locale_provider` | filter | Swap the `LocaleProvider`. |
| `bindery/current_locale` / `default_locale` / `available_locales` | filter | Drive locales. |
| `bindery/resolve_value` | filter | Post-process any resolved value. |
| `bindery/post_fields` | filter | Adjust the editable fields discovered for a post. |
| `bindery/lock_editor` | filter | Lock the editor to content-only for a post. |

```php
// Example: a custom field type.
add_action( 'bindery/register_field_types', function ( $registry ) {
	$registry->register( new My_Color_Field() ); // implements Bindery\Contracts\FieldType
} );
```

## REST API

Namespace `bindery/v1` (used by the editor; capability + per-post checked):

- `GET  /values?post=ID&locale=xx` — discovered fields + stored values.
- `POST /values { post, locale, values }` — save overrides (whitelisted to the page's actual fields, sanitized per type).
- `GET  /locales` — available locales.

## Locking the editor

```php
// Lock all editors to content-only (clients edit content, not layout):
add_filter( 'bindery/lock_editor', '__return_true' );

// Or per-post:
add_filter( 'bindery/lock_editor', fn( $lock, $post ) => $post && 'page' === $post->post_type, 10, 2 );
```

## Editor preview (WYSIWYG)

Bindery blocks should look and behave in the editor the way they will on the front end. Two layers cooperate:

1. **Per-block assets (Bindery's job, automatic).** Each block's `block.json` `style`/`editorStyle`/`viewScript` is loaded in the editor by WordPress whenever the block is present — so a block's own CSS (and editor JS) ride along with no extra wiring.
2. **Theme look (the theme's one-liner).** Load your front-end styles into the editor canvas so the design context matches:

   ```php
   add_theme_support( 'editor-styles' );
   add_theme_support( 'wp-block-styles' );
   add_editor_style( 'editor.css' ); // or your front-end stylesheet
   ```

**Dynamic / script-driven blocks (sliders, carousels, anything that needs its `viewScript` running).** Front-end view scripts do not execute in the editor canvas by default. For a faithful, interactive preview, render the block server-side in `edit.js` with `ServerSideRender` (it runs your `render.php`, including any Bindery-resolved values) and enqueue the block's view script for the editor. Edit such blocks through the Inspector/sidebar fields rather than inline. Plain text/heading regions stay inline-editable (`RichText`) as shown by `bindery/editable-text`.

## Uninstall / data deletion

Uninstalling keeps all data by default. To wipe Bindery data on uninstall, enable one of:

```php
define( 'BINDERY_DELETE_DATA', true );                       // wp-config.php
update_option( 'bindery_delete_data_on_uninstall', true );   // or an option
```
