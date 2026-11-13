# Changelog

All notable changes to the Replyze plugin for WordPress. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the plugin uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.1] - 2026-11-13

### Fixed

- After a signed-in user signed out, the chat could bring their conversation back on the next page instead of forgetting it. The widget's `reset()` ran while the widget was still restoring the previous visitor's chat, and that restore won. The plugin now removes the widget's stored chat and visitor cookie before the widget starts, then calls `reset()`.
- When another user signed in on the same browser without an identity secret, the chat kept the previous user's conversation and contact. The plugin now remembers an opaque per-user marker (a salted hash, not the user id or email) and forgets the previous person when it changes.
- A name longer than 255 characters made Replyze refuse the whole `identify()` call. The name is now cut to 255 characters.
- Uninstall removed the settings only on the first 100 sites of a multisite network. It now covers every site.
- A refused token or identity secret showed its message twice on the settings page (WordPress already prints settings messages on every Settings screen).

### Changed

- The sign-up link on the settings page carries `utm_source=wordpress&utm_medium=plugin`, so Replyze sees where the account came from. It used `?source=wordpress`, which Replyze doesn't read.
- The settings page says to update or remove the identity secret after making a new one in Replyze (Replyze refuses users signed with an old secret).
- `Plugin URI` points to this repository.
- Code style: WordPress-Extra, WordPress-Docs and PHPCompatibilityWP (PHP 7.4+) pass with no findings.
- Tested up to WordPress 7.1.

## [1.2.0] - 2026-11-12

### Added

- Signed-in users and WooCommerce customers can be identified in the chat (`identify()` with name and email), optionally verified with the identity secret from Replyze (`user_id` and an HMAC-SHA256 `user_hash` computed in PHP).
- Hide the widget on whole content types (posts, pages, products...), also on their archive pages.
- Optionally show the widget in the page's language (the WordPress locale).
- Filters `replyze_show_widget` and `replyze_identity`; constants `REPLYZE_API_URL` and `REPLYZE_WIDGET_URL`.

### Changed

- The `[replyze]` shortcode shows the widget on its page even where the rules hide it.
- The widget is loaded with `wp_enqueue_script` (async on WordPress 6.3+) instead of a hand-written tag in `wp_footer`.
- The widget token is checked when saved.

## [1.1.0] - 2026-11-08

Not published as a release.

### Added

- With WooCommerce active, the settings page shows how to connect the store to Replyze with a read-only REST API key, pasted in the Replyze dashboard, so the AI can answer product, stock and price questions. The plugin never stores the key.

## [1.0.0] - 2026-04-14

Not published as a release.

### Added

- Settings page with the widget token, an on/off switch, hiding from administrators and hiding on pages by ID.
- The widget script loaded asynchronously in the footer.
- The `[replyze]` shortcode.
- Uninstall removes the settings, on every site of a multisite network.

[Unreleased]: https://github.com/Replyze/wordpress-live-chat/compare/v1.2.0...HEAD
[1.2.1]: https://github.com/Replyze/wordpress-live-chat/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/Replyze/wordpress-live-chat/releases/tag/v1.2.0
