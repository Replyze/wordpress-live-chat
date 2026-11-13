<?php
/**
 * Plugin Name:       Replyze — AI Customer Support Chat
 * Plugin URI:        https://github.com/Replyze/wordpress-live-chat
 * Description:       Adds your Replyze chat widget to this site: AI answers from your own content, a shared inbox for your team, and live chat. Signed-in users can be recognised in the chat.
 * Version:           1.2.1
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Replyze
 * Author URI:        https://replyze.chat
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       replyze
 *
 * @package Replyze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REPLYZE_VERSION', '1.2.1' );
define( 'REPLYZE_OPTION_KEY', 'replyze_settings' );
define( 'REPLYZE_DASHBOARD_URL', 'https://client.replyze.chat' );

// Where the widget comes from. A site can point elsewhere in wp-config.php
// (for example a staging Replyze) by defining these two first.
if ( ! defined( 'REPLYZE_API_URL' ) ) {
	define( 'REPLYZE_API_URL', 'https://client.replyze.chat' );
}
if ( ! defined( 'REPLYZE_WIDGET_URL' ) ) {
	define( 'REPLYZE_WIDGET_URL', 'https://cdn.replyze.chat/widget.js' );
}

/**
 * The plugin: a settings page under Settings → Replyze Chat, and the widget
 * on the public site.
 */
final class Replyze_Plugin {

	/**
	 * The one instance.
	 *
	 * @var Replyze_Plugin|null
	 */
	private static $instance = null;

	/**
	 * The browser's localStorage key for who was identified in the chat on
	 * it (an opaque per-site marker, not the user's id or email), so the
	 * widget forgets them when they sign out or someone else signs in.
	 */
	const SIGNED_IN_FLAG = 'replyze_wp_identified';

	/**
	 * The prefix of the keys widget.js keeps in localStorage (its chat,
	 * contact and visitor key). The plugin removes them itself before the
	 * widget starts when the person on this browser changed: the widget's
	 * own reset() alone runs while it is still restoring the previous
	 * person's chat, and that restore would bring them back.
	 */
	const WIDGET_STORAGE_PREFIX = 'aichat_';

	/**
	 * The widget's visitor cookie, removed at the same moment, so the first
	 * page view after the change isn't counted for the previous person.
	 */
	const WIDGET_VISITOR_COOKIE = 'rz_track_id';

	/**
	 * Longest name the Replyze API accepts in identify().
	 */
	const MAX_NAME_LENGTH = 255;

	/**
	 * The instance.
	 *
	 * @return Replyze_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget' ) );
		add_shortcode( 'replyze', array( $this, 'shortcode' ) );
	}

	/*
	 * Settings
	 * ------------------------------------------------------------
	 */

	/**
	 * What a fresh install starts with.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'token'               => '',
			'enabled'             => 1,
			'exclude_admin'       => 1,
			'excluded_ids'        => '',
			'excluded_post_types' => array(),
			'identify_users'      => 0,
			'identity_secret'     => '',
			'match_language'      => 0,
		);
	}

	/**
	 * The saved settings over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$saved = get_option( REPLYZE_OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$settings = wp_parse_args( $saved, self::defaults() );
		if ( ! is_array( $settings['excluded_post_types'] ) ) {
			$settings['excluded_post_types'] = array();
		}
		return $settings;
	}

	/**
	 * Settings API registration.
	 */
	public function register_settings() {
		register_setting(
			'replyze_settings_group',
			REPLYZE_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Everything the settings form sends, checked. The identity secret is
	 * never sent back to the browser, so an empty field keeps the saved one.
	 *
	 * @param mixed $input The submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = $this->get_settings();
		$clean   = self::defaults();

		$token = isset( $input['token'] ) ? trim( sanitize_text_field( wp_unslash( $input['token'] ) ) ) : '';
		if ( '' === $token || preg_match( '/^wgt_[A-Za-z0-9_-]{4,64}$/', $token ) ) {
			$clean['token'] = $token;
		} else {
			$clean['token'] = $current['token'];
			add_settings_error( REPLYZE_OPTION_KEY, 'replyze_token', __( 'That doesn\'t look like a widget token. It starts with wgt_ and has only letters, numbers, - and _. The previous token was kept.', 'replyze' ) );
		}

		$clean['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$clean['exclude_admin']  = empty( $input['exclude_admin'] ) ? 0 : 1;
		$clean['identify_users'] = empty( $input['identify_users'] ) ? 0 : 1;
		$clean['match_language'] = empty( $input['match_language'] ) ? 0 : 1;

		$ids                   = isset( $input['excluded_ids'] ) ? sanitize_text_field( wp_unslash( $input['excluded_ids'] ) ) : '';
		$ids                   = array_filter( array_map( 'absint', explode( ',', $ids ) ) );
		$clean['excluded_ids'] = implode( ',', array_unique( $ids ) );

		$types                        = isset( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] ) ? $input['excluded_post_types'] : array();
		$known                        = array_keys( $this->public_post_types() );
		$clean['excluded_post_types'] = array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $types ) ), $known ) );

		$secret = isset( $input['identity_secret'] ) ? trim( sanitize_text_field( wp_unslash( $input['identity_secret'] ) ) ) : '';
		if ( ! empty( $input['clear_identity_secret'] ) ) {
			$clean['identity_secret'] = '';
		} elseif ( '' === $secret ) {
			$clean['identity_secret'] = $current['identity_secret'];
		} elseif ( preg_match( '/^[a-f0-9]{64}$/i', $secret ) ) {
			$clean['identity_secret'] = strtolower( $secret );
		} else {
			$clean['identity_secret'] = $current['identity_secret'];
			add_settings_error( REPLYZE_OPTION_KEY, 'replyze_secret', __( 'That doesn\'t look like an identity secret (64 letters and numbers, from Replyze: Settings → Chat Widget → Identity). The previous one was kept.', 'replyze' ) );
		}

		return $clean;
	}

	/**
	 * The post types a visitor can see, for "hide on".
	 *
	 * @return array<string, string> name => label
	 */
	private function public_post_types() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $type ) {
			if ( 'attachment' === $name ) {
				continue;
			}
			$out[ $name ] = $type->labels->name;
		}
		return $out;
	}

	/*
	 * Admin page
	 * ------------------------------------------------------------
	 */

	/**
	 * Settings → Replyze Chat.
	 */
	public function register_admin_menu() {
		add_options_page(
			__( 'Replyze Chat', 'replyze' ),
			__( 'Replyze Chat', 'replyze' ),
			'manage_options',
			'replyze',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * The settings page's look, on that page only.
	 *
	 * @param string $hook The admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_replyze' !== $hook ) {
			return;
		}

		wp_register_style( 'replyze-admin', false, array(), REPLYZE_VERSION );
		wp_enqueue_style( 'replyze-admin' );
		wp_add_inline_style(
			'replyze-admin',
			'.replyze-wrap{max-width:780px}
			.replyze-wrap h1{display:flex;align-items:center;gap:10px;margin-bottom:6px}
			.replyze-logo{width:32px;height:32px;border-radius:8px;background:#6C5CE7;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px}
			.replyze-sub{color:#646970;font-size:14px;margin:0 0 24px}
			.replyze-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin-top:16px}
			.replyze-card h2{margin-top:0;font-size:14px;text-transform:uppercase;letter-spacing:.06em;color:#646970}
			.replyze-card.is-accent{border-left:4px solid #6C5CE7}
			.replyze-card.is-ok{border-left:4px solid #00a32a}
			.replyze-field{margin:14px 0}
			.replyze-field>label,.replyze-field>.replyze-label{display:block;font-weight:600;margin-bottom:6px;color:#1d2327}
			.replyze-field input[type=text],.replyze-field input[type=password]{width:100%;max-width:520px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
			.replyze-help{color:#646970;font-size:13px;margin:4px 0 0}
			.replyze-code{background:#f6f7f7;padding:2px 6px;border-radius:3px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
			.replyze-status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#d1f1dd;color:#00733a}
			.replyze-types{display:flex;flex-wrap:wrap;gap:6px 18px}
			.replyze-steps{margin-left:18px}
			.replyze-steps li{margin-bottom:6px}
			.replyze-cta{display:inline-block}'
		);
	}

	/**
	 * "Settings" next to Deactivate on the Plugins screen.
	 *
	 * @param array<int|string, string> $links The row's links.
	 * @return array<int|string, string>
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=replyze' ) ) . '">' . esc_html__( 'Settings', 'replyze' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * The settings page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = $this->get_settings();
		$has_token  = '' !== $settings['token'];
		$has_secret = '' !== $settings['identity_secret'];
		$name       = REPLYZE_OPTION_KEY;
		?>
		<div class="wrap replyze-wrap">
			<h1>
				<span class="replyze-logo" aria-hidden="true">R</span>
				<?php esc_html_e( 'Replyze Chat', 'replyze' ); ?>
			</h1>
			<p class="replyze-sub">
				<?php esc_html_e( 'Your Replyze chat widget on this site.', 'replyze' ); ?>
				<a href="https://replyze.chat" target="_blank" rel="noopener noreferrer">replyze.chat</a>
			</p>

			<?php if ( ! $has_token ) : ?>
				<div class="replyze-card is-accent">
					<h2><?php esc_html_e( 'Get started', 'replyze' ); ?></h2>
					<ol class="replyze-steps">
						<li>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: link to the Replyze dashboard */
									esc_html__( 'Sign in to Replyze at %s, or create a free account there.', 'replyze' ),
									'<a href="' . esc_url( self::signup_url() ) . '" target="_blank" rel="noopener noreferrer">client.replyze.chat</a>'
								),
								self::allowed_html()
							);
							?>
						</li>
						<li>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: where the token is in Replyze, 2: how a token starts */
									esc_html__( 'Copy your widget token from %1$s. It starts with %2$s.', 'replyze' ),
									'<strong>' . esc_html__( 'Settings → Integrations → Install widget', 'replyze' ) . '</strong>',
									'<span class="replyze-code">wgt_</span>'
								),
								self::allowed_html()
							);
							?>
						</li>
						<li><?php esc_html_e( 'Paste it below and save.', 'replyze' ); ?></li>
					</ol>
				</div>
			<?php else : ?>
				<div class="replyze-card is-ok">
					<h2><?php esc_html_e( 'Widget status', 'replyze' ); ?></h2>
					<p>
						<span class="replyze-status"><?php esc_html_e( 'Token saved', 'replyze' ); ?></span>
						<?php
						if ( ! empty( $settings['enabled'] ) ) {
							esc_html_e( 'The widget shows on your site, except where the rules below hide it.', 'replyze' );
						} else {
							esc_html_e( 'The widget is switched off below.', 'replyze' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'replyze_settings_group' ); ?>

				<div class="replyze-card">
					<h2><?php esc_html_e( 'Connection', 'replyze' ); ?></h2>

					<div class="replyze-field">
						<label for="replyze_token"><?php esc_html_e( 'Widget token', 'replyze' ); ?></label>
						<input type="text" id="replyze_token" name="<?php echo esc_attr( $name ); ?>[token]" value="<?php echo esc_attr( $settings['token'] ); ?>" placeholder="wgt_xxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" spellcheck="false" />
						<p class="replyze-help"><?php esc_html_e( 'In Replyze: Settings → Integrations → Install widget.', 'replyze' ); ?></p>
					</div>

					<div class="replyze-field">
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
							<?php esc_html_e( 'Show the widget on the public site', 'replyze' ); ?>
						</label>
					</div>
				</div>

				<div class="replyze-card">
					<h2><?php esc_html_e( 'Where it shows', 'replyze' ); ?></h2>

					<div class="replyze-field">
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[exclude_admin]" value="1" <?php checked( ! empty( $settings['exclude_admin'] ) ); ?> />
							<?php esc_html_e( 'Hide it from administrators who are signed in', 'replyze' ); ?>
						</label>
					</div>

					<div class="replyze-field">
						<span class="replyze-label"><?php esc_html_e( 'Hide it on these content types', 'replyze' ); ?></span>
						<div class="replyze-types">
							<?php foreach ( $this->public_post_types() as $type => $label ) : ?>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[excluded_post_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $settings['excluded_post_types'], true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="replyze-field">
						<label for="replyze_excluded_ids"><?php esc_html_e( 'Hide it on these pages and posts', 'replyze' ); ?></label>
						<input type="text" id="replyze_excluded_ids" name="<?php echo esc_attr( $name ); ?>[excluded_ids]" value="<?php echo esc_attr( $settings['excluded_ids'] ); ?>" placeholder="12, 34, 56" />
						<p class="replyze-help"><?php esc_html_e( 'Their IDs, separated by commas. Empty: no page is left out.', 'replyze' ); ?></p>
					</div>

					<p class="replyze-help">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: the shortcode */
								esc_html__( 'A page or post with the %s shortcode always shows the widget, even where these rules hide it.', 'replyze' ),
								'<span class="replyze-code">[replyze]</span>'
							),
							self::allowed_html()
						);
						?>
					</p>
				</div>

				<div class="replyze-card">
					<h2><?php esc_html_e( 'Signed-in users', 'replyze' ); ?></h2>

					<div class="replyze-field">
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[identify_users]" value="1" <?php checked( ! empty( $settings['identify_users'] ) ); ?> />
							<?php esc_html_e( 'Tell Replyze who signed-in users are (their name and email)', 'replyze' ); ?>
						</label>
						<p class="replyze-help"><?php esc_html_e( 'Your team then sees who they are talking to, including WooCommerce customers. When they sign out, the chat forgets them on that browser.', 'replyze' ); ?></p>
					</div>

					<div class="replyze-field">
						<label for="replyze_identity_secret"><?php esc_html_e( 'Identity secret (recommended)', 'replyze' ); ?></label>
						<input type="password" id="replyze_identity_secret" name="<?php echo esc_attr( $name ); ?>[identity_secret]" value="" placeholder="<?php echo esc_attr( $has_secret ? __( 'Saved. Leave empty to keep it.', 'replyze' ) : '' ); ?>" autocomplete="new-password" spellcheck="false" />
						<p class="replyze-help"><?php esc_html_e( 'From Replyze: Settings → Chat Widget → Identity. With it, this site signs each user, so the same person is one verified contact on every device, and nobody can pretend to be them. It stays on this server; it is never put in the page.', 'replyze' ); ?></p>
						<p class="replyze-help"><?php esc_html_e( 'If you make a new secret or turn identity verification off in Replyze, paste the new one here or remove this one: Replyze refuses users signed with an old secret.', 'replyze' ); ?></p>
						<?php if ( $has_secret ) : ?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[clear_identity_secret]" value="1" />
								<?php esc_html_e( 'Remove the saved secret', 'replyze' ); ?>
							</label>
						<?php endif; ?>
						<p class="replyze-help"><?php esc_html_e( 'Without the secret, users are passed on unverified: your team sees their name and email, but they don\'t become verified contacts, and an email that belongs to a verified contact isn\'t taken over.', 'replyze' ); ?></p>
					</div>
				</div>

				<div class="replyze-card">
					<h2><?php esc_html_e( 'Language', 'replyze' ); ?></h2>
					<div class="replyze-field">
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[match_language]" value="1" <?php checked( ! empty( $settings['match_language'] ) ); ?> />
							<?php esc_html_e( 'Show the widget in the page\'s language', 'replyze' ); ?>
						</label>
						<p class="replyze-help"><?php esc_html_e( 'Off: the widget follows the visitor\'s browser. A language the widget doesn\'t have falls back to that too.', 'replyze' ); ?></p>
					</div>
				</div>

				<?php submit_button( __( 'Save changes', 'replyze' ) ); ?>
			</form>

			<?php $this->render_woocommerce_card(); ?>

			<p class="replyze-help" style="margin-top:30px">
				<?php
				printf(
					/* translators: %s: version number */
					esc_html__( 'Replyze WordPress plugin %s', 'replyze' ),
					esc_html( REPLYZE_VERSION )
				);
				?>
				·
				<a href="mailto:support@replyze.chat"><?php esc_html_e( 'Support', 'replyze' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * With WooCommerce active: how to let the Replyze AI answer product,
	 * stock and price questions from this store. The connection is made in
	 * the Replyze dashboard with a read-only REST API key; this plugin never
	 * sees or stores the key, it only links to the right screens.
	 */
	private function render_woocommerce_card() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$create_key_url = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys&create-key=1' );
		$connect_url    = add_query_arg(
			array(
				'tab'       => 'integrations',
				'section'   => 'apps',
				'connect'   => 'woocommerce',
				'store_url' => rawurlencode( home_url( '/' ) ),
			),
			REPLYZE_DASHBOARD_URL . '/settings'
		);
		?>
		<div class="replyze-card" style="border-left:4px solid #7f54b3">
			<h2><?php esc_html_e( 'WooCommerce', 'replyze' ); ?></h2>
			<p><?php esc_html_e( 'Let the Replyze AI answer product, stock and price questions from this store, with product cards in the chat.', 'replyze' ); ?></p>
			<ol class="replyze-steps">
				<li>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: link to the WooCommerce REST API key screen */
							esc_html__( 'Create a REST API key in %s: description "Replyze", your user, permissions Read.', 'replyze' ),
							'<a href="' . esc_url( $create_key_url ) . '">' . esc_html__( 'WooCommerce → Settings → Advanced → REST API', 'replyze' ) . '</a>'
						),
						self::allowed_html()
					);
					?>
				</li>
				<li><?php esc_html_e( 'Open Replyze and paste the consumer key and consumer secret there. They are kept encrypted in Replyze, not in this plugin.', 'replyze' ); ?></li>
			</ol>
			<p>
				<a class="button button-primary replyze-cta" href="<?php echo esc_url( $connect_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Connect WooCommerce in Replyze', 'replyze' ); ?>
				</a>
			</p>
			<p class="replyze-help"><?php esc_html_e( 'Read permission is enough: Replyze never changes your products or orders. To disconnect, revoke the key here or disconnect the store in Replyze.', 'replyze' ); ?></p>
		</div>
		<?php
	}

	/*
	 * The widget
	 * ------------------------------------------------------------
	 */

	/**
	 * Whether this page gets the widget.
	 *
	 * @return bool
	 */
	public function should_render_widget() {
		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) || '' === $settings['token'] ) {
			return false;
		}
		if ( is_admin() || is_feed() || is_robots() || is_embed() || wp_doing_ajax() ) {
			return false;
		}
		if ( ! empty( $settings['exclude_admin'] ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		$post = get_queried_object();
		// [replyze] on the page shows it there whatever the rules below say.
		if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'replyze' ) ) {
			return true;
		}

		$types = $settings['excluded_post_types'];
		if ( $types && ( ( is_singular() && $post instanceof WP_Post && in_array( $post->post_type, $types, true ) ) || is_post_type_archive( $types ) ) ) {
			return false;
		}

		if ( '' !== $settings['excluded_ids'] ) {
			$excluded = array_map( 'absint', explode( ',', $settings['excluded_ids'] ) );
			$current  = get_queried_object_id();
			if ( $current && in_array( $current, $excluded, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The widget script, async in the footer, with its settings before it.
	 */
	public function enqueue_widget() {
		/**
		 * Filters whether this page gets the Replyze widget.
		 *
		 * @param bool $show Whether the rules on the settings page show it here.
		 */
		if ( ! apply_filters( 'replyze_show_widget', $this->should_render_widget() ) ) {
			return;
		}

		$settings = $this->get_settings();
		$config   = array(
			'token'  => $settings['token'],
			'apiUrl' => REPLYZE_API_URL,
		);
		if ( ! empty( $settings['match_language'] ) ) {
			// "de_DE": the widget reads the language part, and ignores one it doesn't speak.
			$config['language'] = determine_locale();
		}

		// WordPress 6.3+ loads it async; older versions in the footer. No
		// version: the URL stays the one Replyze serves and versions itself.
		wp_register_script(
			'replyze-widget',
			REPLYZE_WIDGET_URL,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			array(
				'in_footer' => true,
				'strategy'  => 'async',
			)
		);
		wp_add_inline_script( 'replyze-widget', 'window.ReplyzeConfig = ' . wp_json_encode( $config ) . ';' . $this->visitor_script( $settings ), 'before' );
		wp_enqueue_script( 'replyze-widget' );
	}

	/**
	 * Who the signed-in user is, for the widget's identify(). When the person
	 * on this browser changed since the last page (they signed out, or
	 * someone else signed in), the widget first forgets the previous one:
	 * its stored chat is removed before it starts, then its reset() gives
	 * the browser a new visitor id. Nothing when the setting is off.
	 *
	 * @param array<string, mixed> $settings The settings.
	 * @return string JavaScript.
	 */
	private function visitor_script( $settings ) {
		if ( empty( $settings['identify_users'] ) ) {
			return '';
		}

		$who    = $this->identity( $settings );
		$marker = null === $who ? '' : self::visitor_marker( get_current_user_id() );

		// w window, d document, k marker key, p widget storage prefix,
		// c widget visitor cookie, me this user's marker ('' signed out),
		// who the identify() payload (null signed out).
		return '(function(w,d,k,p,c,me,who){'
			. 'var s=null,was=null;try{s=w.localStorage;was=s.getItem(k)}catch(e){}'
			. 'var R=w.Replyze=w.Replyze||{};R._queue=R._queue||[];'
			. 'if(was&&was!==me){'
			. 'try{for(var i=s.length-1;i>=0;i--){var n=s.key(i);if(n&&n.indexOf(p)===0)s.removeItem(n)}}catch(e){}'
			. 'var x=c+"=; path=/; max-age=0; SameSite=Lax";d.cookie=x;d.cookie=x+"; domain="+w.location.hostname.split(".").slice(-2).join(".");'
			. 'R._queue.push(["reset"])}'
			. 'try{if(me)s.setItem(k,me);else if(was)s.removeItem(k)}catch(e){}'
			. 'if(who)R._queue.push(["identify",who])'
			. '})(window,document,'
			. wp_json_encode( self::SIGNED_IN_FLAG ) . ','
			. wp_json_encode( self::WIDGET_STORAGE_PREFIX ) . ','
			. wp_json_encode( self::WIDGET_VISITOR_COOKIE ) . ','
			. wp_json_encode( $marker ) . ','
			. wp_json_encode( $who ) . ');';
	}

	/**
	 * An opaque marker for a user on this site (a salted hash, so the page
	 * holds neither the user id nor the email): it only tells whether the
	 * same person is still signed in on this browser.
	 *
	 * @param int $wp_user_id The WordPress user id.
	 * @return string
	 */
	public static function visitor_marker( $wp_user_id ) {
		return substr( wp_hash( 'replyze-visitor|' . absint( $wp_user_id ) ), 0, 20 );
	}

	/**
	 * The signed-in user for identify(): name and email, plus a user id and
	 * its signature (HMAC-SHA256 under the widget's identity secret) when
	 * the secret is saved. Null for a visitor who isn't signed in.
	 *
	 * @param array<string, mixed> $settings The settings.
	 * @return array<string, string>|null
	 */
	public function identity( $settings ) {
		if ( ! is_user_logged_in() ) {
			return null;
		}
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() || '' === (string) $user->user_email ) {
			return null;
		}

		$name = trim( (string) $user->first_name . ' ' . (string) $user->last_name );
		if ( '' === $name ) {
			$name = trim( (string) $user->display_name );
		}

		$who = array(
			'email' => (string) $user->user_email,
			// Replyze refuses the whole identify() for a longer name.
			'name'  => mb_substr( $name, 0, self::MAX_NAME_LENGTH ),
		);

		$secret = (string) $settings['identity_secret'];
		if ( '' !== $secret ) {
			$who['user_id']   = self::user_id( $user->ID );
			$who['user_hash'] = hash_hmac( 'sha256', $who['user_id'], $secret );
		}

		/**
		 * Filters what the widget is told about the signed-in user.
		 *
		 * @param array<string, string> $who  email, name, and with a secret user_id and user_hash.
		 * @param WP_User               $user The user.
		 */
		return apply_filters( 'replyze_identity', $who, $user );
	}

	/**
	 * The user id Replyze knows this user by: the site's host and the
	 * WordPress user id, so two sites in one workspace never share a contact.
	 *
	 * @param int $wp_user_id The WordPress user id.
	 * @return string
	 */
	public static function user_id( $wp_user_id ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return 'wp:' . strtolower( (string) $host ) . ':' . absint( $wp_user_id );
	}

	/**
	 * Sign-up in Replyze, tagged so Replyze sees the account came from this plugin.
	 *
	 * @return string
	 */
	private static function signup_url() {
		return add_query_arg(
			array(
				'utm_source'  => 'wordpress', // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- a URL parameter value.
				'utm_medium'  => 'plugin',
				'utm_content' => 'settings',
			),
			REPLYZE_DASHBOARD_URL . '/register'
		);
	}

	/**
	 * The markup the settings page's sentences may hold.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'span'   => array( 'class' => true ),
		);
	}

	/**
	 * [replyze]: shows the widget on this page or post even where the rules
	 * hide it (should_render_widget reads it). Prints nothing itself.
	 *
	 * @return string
	 */
	public function shortcode() {
		$settings = $this->get_settings();
		if ( '' === $settings['token'] && current_user_can( 'manage_options' ) ) {
			return '<p style="padding:12px;border:1px dashed #d63638;color:#d63638;border-radius:6px">'
				. esc_html__( 'Replyze: add your widget token in Settings → Replyze Chat.', 'replyze' ) . '</p>';
		}
		return '';
	}

	/**
	 * Activation: the defaults, if it was never set up.
	 */
	public static function activate() {
		$existing = get_option( REPLYZE_OPTION_KEY );
		if ( false === $existing || ! is_array( $existing ) ) {
			add_option( REPLYZE_OPTION_KEY, self::defaults() );
		}
	}
}

Replyze_Plugin::instance();
register_activation_hook( __FILE__, array( 'Replyze_Plugin', 'activate' ) );
