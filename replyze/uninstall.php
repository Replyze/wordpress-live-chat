<?php
/**
 * Deleting the plugin from Plugins: its settings go too (with the identity
 * secret), on every site of a network.
 *
 * @package Replyze
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'replyze_settings' );

if ( is_multisite() ) {
	// Every site: without 'number' => 0, get_sites() returns only the first 100.
	$replyze_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $replyze_site_ids as $replyze_site_id ) {
		switch_to_blog( (int) $replyze_site_id );
		delete_option( 'replyze_settings' );
		restore_current_blog();
	}
}
