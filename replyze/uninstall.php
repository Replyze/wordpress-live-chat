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
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $replyze_site_id ) {
		switch_to_blog( (int) $replyze_site_id );
		delete_option( 'replyze_settings' );
		restore_current_blog();
	}
}
