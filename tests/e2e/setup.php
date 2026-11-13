<?php
/**
 * Test-only setup, run once in the Playground site by e2e.mjs: three users
 * and two pages.
 *
 * @package Replyze
 */

require '/wordpress/wp-load.php';

wp_insert_user(
	array(
		'user_login' => 'alice',
		'user_email' => 'Alice.Example@Example.com',
		'user_pass'  => 'alicepass',
		'first_name' => 'Alice',
		'last_name'  => 'Example',
		'role'       => 'subscriber',
	)
);
wp_insert_user(
	array(
		'user_login'   => 'bob',
		'user_email'   => 'bob@example.com',
		'user_pass'    => 'bobpass',
		'display_name' => 'Bob B',
		'role'         => 'subscriber',
	)
);
$carol = wp_insert_user(
	array(
		'user_login'   => 'carol',
		'user_email'   => 'carol@example.com',
		'user_pass'    => 'carolpass',
		'display_name' => 'Carol',
		'role'         => 'subscriber',
	)
);
// 300 characters of two bytes each: identify() must send 255 characters.
update_user_meta( $carol, 'first_name', str_repeat( html_entity_decode( '&eacute;', ENT_QUOTES, 'UTF-8' ), 300 ) );

wp_insert_post(
	array(
		'post_title'   => 'Hidden page',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => 'No chat here.',
	)
);
wp_insert_post(
	array(
		'post_title'   => 'Forced page',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => 'Chat here [replyze]',
	)
);
