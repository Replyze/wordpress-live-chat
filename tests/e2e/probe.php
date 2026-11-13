<?php
/**
 * Test-only must-use plugin, written into the Playground site by e2e.mjs.
 *
 * ?replyze_probe=1 returns the plugin's option, the users and pages as JSON;
 * ?rz_notrack=1 applies the README's "tracking only after consent" recipe.
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
