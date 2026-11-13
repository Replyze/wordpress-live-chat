<?php
/**
 * Test-only must-use plugin, written into the Playground site by e2e.mjs.
 *
 * ?replyze_probe=1 returns the plugin's option, the users and pages as JSON;
 * ?rz_notrack=1 applies the README's "tracking only after consent" recipe;
 * ?rz_late=1 runs the plugin's own script only once widget.js has started,
 * as optimisation plugins that delay inline scripts do.
 *
 * @package Replyze
 */

add_action(
	'init',
	function () {
		if ( ! isset( $_GET['replyze_probe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$log = WP_CONTENT_DIR . '/debug.log';
		header( 'Content-Type: application/json' );
		echo wp_json_encode(
			array(
				'option'    => get_option( 'replyze_settings', 'MISSING' ),
				'active'    => in_array( 'replyze/replyze.php', (array) get_option( 'active_plugins', array() ), true ),
				'wp'        => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'home'      => home_url(),
				'users'     => array_map(
					function ( $u ) {
						return array(
							'id'    => $u->ID,
							'login' => $u->user_login,
						);
					},
					get_users()
				),
				'pages'     => array_map(
					function ( $p ) {
						return array(
							'id'    => $p->ID,
							'title' => $p->post_title,
							'link'  => get_permalink( $p ),
						);
					},
					get_posts(
						array(
							'post_type'   => 'page',
							'numberposts' => -1,
						)
					)
				),
				'debug_log' => file_exists( $log ) ? array_values(
					array_filter(
						file( $log ),
						function ( $line ) {
							return false !== stripos( $line, 'replyze' );
						}
					)
				) : array(),
			)
		);
		exit;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( isset( $_GET['rz_notrack'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_add_inline_script( 'replyze-widget', 'window.ReplyzeConfig.tracking = false;', 'before' );
		}
	},
	20
);

// ?rz_late=1: the widget's config comes first, in <head>; the plugin's inline
// script is not run where WordPress prints it but once widget.js has started.
add_action(
	'wp_head',
	function () {
		if ( ! isset( $_GET['rz_late'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$settings = get_option( 'replyze_settings' );
		echo '<script>window.ReplyzeConfig = ' . wp_json_encode(
			array(
				'token'  => $settings['token'],
				'apiUrl' => 'https://client.replyze.chat',
			)
		) . ";</script>\n";
	},
	1
);

add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		if ( 'replyze-widget' !== $handle || ! isset( $_GET['rz_late'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $tag;
		}
		return preg_replace( '/<script[^>]*id=[\'"]replyze-widget-js-before[\'"][^>]*>/', '<script type="text/rz-late" id="replyze-widget-js-before">', $tag, 1 );
	},
	10,
	2
);

add_action(
	'wp_footer',
	function () {
		if ( ! isset( $_GET['rz_late'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo "<script>(function(){var go=function(){if(!(window.Replyze&&window.Replyze._initialized))return setTimeout(go,50);var s=document.getElementById('replyze-widget-js-before');if(s)(0,eval)(s.textContent);window.__rzLateRan=true};go()})();</script>\n";
	},
	999
);
