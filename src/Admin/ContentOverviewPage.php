<?php
/**
 * Site Content overview admin page.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Admin;

use Bindery\Editor\TemplateFieldCollector;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Locale\LocaleManager;
use Bindery\Storage\StorageManager;
use Bindery\Support\Capabilities;

/**
 * A searchable, one-page index of every editable field declared anywhere on
 * the site, with its current value per locale and a direct link to edit it
 * in place.
 *
 * A hand-built theme can end up with dozens of `bindery_attrs()` calls spread
 * across many templates; without this screen, finding "where is the phone
 * number field" means opening pages one at a time and clicking around. This
 * reads {@see TemplateFieldCollector}'s already-persisted declarations (no
 * new storage, no build step, no JS framework -- plain server-rendered HTML)
 * and resolves each field's current value directly.
 */
final class ContentOverviewPage {

	public const MENU_SLUG = 'bindery-content';

	private const HANDLE = 'bindery-content-overview';

	/**
	 * Page-hook suffix, set when the menu is added, used to scope asset loading.
	 */
	private string $hook = '';

	public function __construct(
		private readonly StorageManager $storage,
		private readonly LocaleManager $locales,
		private readonly FieldDefinitionFactory $factory
	) {
	}

	public function registerMenu(): void {
		$this->hook = (string) add_submenu_page(
			'tools.php',
			__( 'Bindery: Site Content', 'bindery' ),
			__( 'Bindery Content', 'bindery' ),
			Capabilities::EDIT_CONTENT,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Attach the search-filter script only on this screen. Registered with no
	 * source file (inline-only) since the behaviour is a few lines of vanilla
	 * JS with no dependencies worth a separate asset for.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}

		wp_register_script( self::HANDLE, false, array(), BINDERY_VERSION, true );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, $this->searchScript() );
	}

	/**
	 * The client-side search filter for the field table. Plain JS, no
	 * dependencies -- kept as a string so it can be attached via
	 * wp_add_inline_script() instead of a literal <script> tag in render().
	 */
	private function searchScript(): string {
		return <<<'JS'
( function () {
	var input = document.getElementById( 'bindery-content-search' );
	var rows = Array.prototype.slice.call( document.querySelectorAll( '#bindery-content-table tbody tr[data-bindery-search]' ) );
	if ( ! input ) {
		return;
	}
	input.addEventListener( 'input', function () {
		var q = input.value.toLowerCase().trim();
		rows.forEach( function ( row ) {
			var hit = '' === q || row.getAttribute( 'data-bindery-search' ).indexOf( q ) !== -1;
			row.style.display = hit ? '' : 'none';
		} );
	} );
} )();
JS;
	}

	/**
	 * One row per unique field key, listing every post that declares it and
	 * its resolved value in each available locale.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_bindery_template_fields'
			),
			ARRAY_A
		);

		/** @var array<string, array{args: array<string, mixed>, posts: list<array{id:int, title:string}>}> $byKey */
		$byKey = array();

		foreach ( (array) $meta_rows as $row ) {
			$post_id = (int) $row['post_id'];
			$decoded = json_decode( (string) $row['meta_value'], true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$post_title = get_the_title( $post_id );
			$post_title = '' !== $post_title ? $post_title : sprintf( '#%d', $post_id );

			foreach ( $decoded as $key => $args ) {
				$key = (string) $key;
				if ( ! isset( $byKey[ $key ] ) ) {
					$byKey[ $key ] = array(
						'args'  => is_array( $args ) ? $args : array(),
						'posts' => array(),
					);
				}
				$byKey[ $key ]['posts'][] = array(
					'id'    => $post_id,
					'title' => $post_title,
				);
			}
		}

		ksort( $byKey );

		$locales = $this->locales->available();
		$out     = array();

		foreach ( $byKey as $key => $entry ) {
			$definition = $this->factory->create( $key, $entry['args'] );
			$firstPost  = $entry['posts'][0]['id'] ?? 0;
			$objectId   = ( 'global' === $definition->scope->value ) ? 0 : $firstPost;

			$values = array();
			foreach ( array_keys( $locales ) as $code ) {
				$storageLocale   = $definition->localeAware ? $code : '';
				$values[ $code ] = $this->storage->get( $objectId, $key, $storageLocale );
			}

			$out[] = array(
				'key'       => $key,
				'type'      => $definition->type,
				'scope'     => $definition->scope->value,
				'localized' => $definition->localeAware,
				'posts'     => $entry['posts'],
				'values'    => $values,
			);
		}

		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::EDIT_CONTENT ) ) {
			return;
		}

		$rows    = $this->rows();
		$locales = $this->locales->available();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Content', 'bindery' ); ?></h1>
			<p><?php esc_html_e( 'Every editable field found anywhere on the site, with its current value and a direct link to edit it.', 'bindery' ); ?></p>

			<p>
				<input
					type="search"
					id="bindery-content-search"
					placeholder="<?php esc_attr_e( 'Search field name or value…', 'bindery' ); ?>"
					style="width:360px; max-width:100%; padding:6px 10px;"
				>
			</p>

			<table class="widefat striped" id="bindery-content-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'bindery' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bindery' ); ?></th>
						<th><?php esc_html_e( 'Found on', 'bindery' ); ?></th>
						<?php foreach ( $locales as $code => $label ) : ?>
							<th><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
						<th><?php esc_html_e( 'Edit', 'bindery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $rows ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'No editable fields have been declared yet -- visit any page once (logged in) to populate this list.', 'bindery' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$searchBlob = strtolower( $row['key'] . ' ' . wp_json_encode( $row['values'] ) );
						?>
					<tr data-bindery-search="<?php echo esc_attr( $searchBlob ); ?>">
						<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
						<td><?php echo esc_html( $row['type'] ); ?>
						<?php
						if ( ! $row['localized'] ) :
							?>
							<span title="<?php esc_attr_e( 'Shared across all languages', 'bindery' ); ?>">🌐</span><?php endif; ?></td>
						<td>
							<?php foreach ( $row['posts'] as $i => $p ) : ?>
								<?php
								if ( $i > 0 ) :
									?>
									, <?php endif; ?>
								<?php $editLink = get_edit_post_link( $p['id'] ); ?>
								<a href="<?php echo esc_url( $editLink ? $editLink : '' ); ?>"><?php echo esc_html( $p['title'] ); ?></a>
							<?php endforeach; ?>
						</td>
						<?php foreach ( array_keys( $locales ) as $code ) : ?>
							<td><?php echo esc_html( $this->preview( $row['values'][ $code ] ?? '' ) ); ?></td>
						<?php endforeach; ?>
						<td>
							<?php
							$editPost  = $row['posts'][0]['id'] ?? 0;
							$permalink = $editPost ? get_permalink( $editPost ) : '';
							?>
							<?php if ( $permalink ) : ?>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'bindery-edit', '1', $permalink ) ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Open & edit', 'bindery' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * A short, printable stand-in for a field's value in the overview table --
	 * an attachment id shows as "Image #123", arrays/repeaters as a row count,
	 * everything else as trimmed plain text.
	 */
	private function preview( mixed $value ): string {
		if ( is_array( $value ) ) {
			return sprintf( '(%d rows)', count( $value ) );
		}

		if ( is_numeric( $value ) && (int) $value > 0 ) {
			// Heuristic: numeric values in this system are almost always an
			// attachment id (image fields); genuine numeric text fields are
			// rare enough on a hand-built theme that this reads better.
			$title = get_the_title( (int) $value );
			if ( '' !== $title ) {
				return sprintf( 'Image #%d (%s)', (int) $value, $title );
			}
		}

		$text = trim( (string) $value );
		if ( '' === $text ) {
			return '—';
		}

		return mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 60 ) . '…' : $text;
	}
}
