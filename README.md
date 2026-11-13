<p align="center">
  <a href="https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Replyze/.github/main/assets/logo-on-dark.png">
      <img src="https://raw.githubusercontent.com/Replyze/.github/main/assets/logo-on-light.png" alt="Replyze" height="48">
    </picture>
  </a>
</p>

# WordPress live chat and AI chatbot plugin

The Replyze plugin for WordPress adds your [Replyze](https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat) chat widget to any WordPress or WooCommerce site without touching theme files. Visitors get answers from an AI agent trained on your own content, your team takes over in a shared inbox when a person is needed, and signed-in users and WooCommerce customers can be recognised in the chat. For site owners and agencies.

**[Create a free account](https://client.replyze.chat/register?utm_source=github&utm_medium=wordpress-live-chat&utm_content=cta-top)** · [Live demo](https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat&utm_content=live-demo) · [Docs](https://github.com/Replyze/replyze)

[![CI](https://github.com/Replyze/wordpress-live-chat/actions/workflows/ci.yml/badge.svg)](https://github.com/Replyze/wordpress-live-chat/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/Replyze/wordpress-live-chat)](https://github.com/Replyze/wordpress-live-chat/releases/latest)
![WordPress 5.7+](https://img.shields.io/badge/WordPress-5.7%2B-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

## Quickstart

1. Download [`replyze.zip`](https://github.com/Replyze/wordpress-live-chat/releases/latest/download/replyze.zip) from the latest release.
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, choose `replyze.zip`, then **Install Now** and **Activate**.
3. Go to **Settings → Replyze Chat**, paste your widget token and click **Save changes**. The token starts with `wgt_`; it is in the [Replyze dashboard](https://client.replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat&utm_content=quickstart) under **Settings → Integrations → Install widget**.

The chat now shows on every page. Signed-in administrators don't see it by default, so check in a private window.

**Try it first:** [open the plugin in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Replyze/wordpress-live-chat/main/playground/blueprint.json). It starts a throwaway WordPress in your browser with the plugin installed from this repository and opens its settings page (the setup screen, since there is no token yet). Nothing is installed on your computer. The blueprint is [`playground/blueprint.json`](playground/blueprint.json).

## What it does

- Adds the widget to every page, loaded asynchronously in the footer (`async` on WordPress 6.3+).
- Hides it where you choose: from signed-in administrators, on whole content types (and their archives), or on single pages and posts by ID.
- `[replyze]` shortcode: shows the widget on a page even where the rules hide it.
- Recognises signed-in users and WooCommerce customers (name and email), optionally verified with an HMAC signature computed on your server.
- Forgets the previous person on a browser when they sign out or someone else signs in.
- Shows the widget in the page's language on multilingual sites (optional).
- With WooCommerce: shows how to connect the store in Replyze with a read-only REST API key, so the AI answers product, stock and price questions.
- Two filters for developers: `replyze_show_widget` and `replyze_identity`.

Requires WordPress 5.7+ and PHP 7.4+. Tested with WordPress 7.1 on PHP 7.4 and 8.5, and with WordPress 5.7 on PHP 7.4.

## Settings

Everything is under **Settings → Replyze Chat**.

| Setting | What it does |
|---|---|
| Widget token | Connects the site to your Replyze workspace. Checked when saved: `wgt_` followed by letters, numbers, `-` and `_`. |
| Show the widget on the public site | The master switch. |
| Hide it from administrators who are signed in | On by default, so you don't chat with yourself while editing. |
| Hide it on these content types | Any public post type (Posts, Pages, Products...). Also hides it on that type's archive pages. |
| Hide it on these pages and posts | Comma-separated IDs, for example `12, 34, 56`. |
| Tell Replyze who signed-in users are | Sends the signed-in user's name and email to the widget, so your team sees who they are talking to. |
| Identity secret | From Replyze: **Settings → Chat Widget → Identity**. With it, each user is signed on your server and becomes one verified contact on every device. |
| Show the widget in the page's language | Uses the WordPress locale of the page. Off: the widget follows the visitor's browser. |

A page or post that contains `[replyze]` always shows the widget, even where the rules above hide it. The shortcode prints nothing itself.

## Signed-in users and WooCommerce customers

With **Tell Replyze who signed-in users are** turned on, every page for a signed-in user calls the widget's `identify()` with:

- `email`: the user's email
- `name`: first and last name, or the display name when those are empty (at most 255 characters)

With an **identity secret** saved, the plugin also adds:

- `user_id`: `wp:<your-site-host>:<WordPress user ID>`, so two sites in one workspace never share a contact
- `user_hash`: `hash_hmac( 'sha256', user_id, identity secret )`, computed in PHP

Replyze checks the hash against the same secret, so a visitor can't pose as another user. The secret stays on your server and is never printed into the page. Without it, users are passed on unverified: your team sees their name and email, but they don't become verified contacts, and Replyze never lets them take over an email that belongs to a verified contact.

If you make a new secret or turn identity verification off in Replyze, paste the new secret in the plugin or remove the saved one: Replyze refuses users signed with an old secret.

**Shared computers.** The plugin keeps an opaque marker of the identified user in the browser (a salted hash, not the user ID or email). When the user signs out, or another user signs in on that browser, the next page with the widget removes the widget's stored chat and visitor cookie before the widget starts and calls `Replyze.reset()`, so the next person starts a new chat.

**Page caches.** User details are only added to pages for signed-in users, which caching plugins normally don't cache. If yours caches pages for signed-in users, leave this setting off.

## WooCommerce

The widget runs on WooCommerce stores like on any other WordPress site, and signed-in customers are recognised as above.

With WooCommerce active, the settings page also shows how to connect the store in the Replyze dashboard with a **read-only** WooCommerce REST API key, so the Replyze AI can answer product, stock and price questions. The key is pasted in Replyze, never in this plugin, and read permission is enough: Replyze doesn't change products or orders.

## For developers

### `replyze_show_widget`

Decide per page whether the widget loads. It receives the result of the settings above.

```php
// No chat on the WooCommerce cart and checkout.
add_filter( 'replyze_show_widget', function ( $show ) {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		return false;
	}
	return $show;
} );
```

### `replyze_identity`

Change what the widget is told about the signed-in user. It receives the array (`email`, `name`, and with a secret `user_id` and `user_hash`) and the `WP_User`. Return `null` to not identify this user.

```php
// Use the WooCommerce billing name when the customer has one.
add_filter( 'replyze_identity', function ( $who, $user ) {
	$billing = trim( get_user_meta( $user->ID, 'billing_first_name', true ) . ' ' . get_user_meta( $user->ID, 'billing_last_name', true ) );
	if ( '' !== $billing ) {
		$who['name'] = $billing;
	}
	return $who;
}, 10, 2 );
```

Keep `user_id` and `user_hash` as they are: the hash signs that exact `user_id`.

### Visitor tracking only after consent

By default the widget keeps a visitor id in a first-party cookie (`rz_track_id`) and reports page views. To start without it, add this to your theme or a small plugin, then call `Replyze.setTracking(true)` from your cookie banner when the visitor agrees. The chat works either way.

```php
add_action( 'wp_enqueue_scripts', function () {
	wp_add_inline_script( 'replyze-widget', 'window.ReplyzeConfig.tracking = false;', 'before' );
}, 20 );
```

### Constants

Define them in `wp-config.php` to load the widget from somewhere else, for example a staging environment:

```php
define( 'REPLYZE_API_URL', 'https://client.replyze.chat' );
define( 'REPLYZE_WIDGET_URL', 'https://cdn.replyze.chat/widget.js' );
```

## FAQ

**Do I need a Replyze account?**
Yes. The plugin shows your Replyze workspace's widget. [Create a free account](https://client.replyze.chat/register?utm_source=github&utm_medium=wordpress-live-chat&utm_content=faq); plans are on [replyze.chat/pricing](https://replyze.chat/pricing?utm_source=github&utm_medium=wordpress-live-chat&utm_content=faq).

**Is it in the wordpress.org plugin directory?**
Not yet. Install it from the ZIP of the [latest release](https://github.com/Replyze/wordpress-live-chat/releases/latest) as in the Quickstart.

**I saved the token but I don't see the chat.**
Signed-in administrators don't see it by default: open the site in a private window. Check that your site's domain is allowed in Replyze (**Settings → Chat Widget**), and that a page cache or script optimisation plugin isn't serving an old page or delaying the script.

**Does it slow my site down?**
The widget script loads asynchronously in the footer, so it doesn't hold up the page.

**What does the plugin send to Replyze?**
Nothing until you save a token. Then visitors' browsers load the widget from `cdn.replyze.chat`, and the widget talks to `client.replyze.chat` (page views and chats). Signed-in users' details are sent only if you turn that setting on. The full list is in the "External services" section of [`readme.txt`](replyze/readme.txt); see also Replyze's [Privacy Policy](https://replyze.chat/privacy?utm_source=github&utm_medium=wordpress-live-chat&utm_content=faq).

**Does it work on multisite?**
Yes. Each site has its own settings and token. Deleting the plugin removes the settings on every site of the network.

**What happens when I delete the plugin?**
`uninstall.php` removes its settings, including the identity secret. Your chats stay in Replyze.

## Development

The plugin is plain PHP with no build step and no runtime dependencies. You need PHP 7.4+ and Composer for the coding standards, and Node.js 24+ to run WordPress locally with [WordPress Playground](https://wordpress.org/playground/).

```bash
git clone https://github.com/Replyze/wordpress-live-chat.git
cd wordpress-live-chat
composer install          # PHPCS with WordPress-Extra, WordPress-Docs and PHPCompatibilityWP
composer lint             # the rules are in phpcs.xml.dist
composer fix              # fixes what PHPCS can fix

# A local WordPress with the plugin folder mounted and activated:
npx @wp-playground/cli@latest server --auto-mount=./replyze --login
```

The last command prints the local URL. The folder is mounted, not copied: edits show on the next page load, and deleting the plugin inside that WordPress deletes your files.

The end-to-end test runs the plugin in WordPress Playground with the live `widget.js` from `cdn.replyze.chat` and a mocked Replyze API (nothing reaches a real workspace). It covers the settings page, the widget tag and config, the hide rules, `identify()` and its HMAC, sign-out and user switch on one browser, and uninstall:

```bash
cd tests/e2e
npm ci
npx playwright install chromium-headless-shell
node e2e.mjs --php 7.4          # also --wp 5.7, --php 8.5
```

CI (`.github/workflows/ci.yml`) runs `php -l` on PHP 7.4 to 8.4, PHPCS, the official [Plugin Check](https://github.com/WordPress/plugin-check-action), builds `replyze.zip` and checks that the version is the same everywhere; `.github/workflows/e2e.yml` runs the end-to-end test on WordPress 5.7 and the latest WordPress, PHP 7.4 and 8.5. Releases are built by `.github/workflows/release.yml`; see [RELEASING.md](RELEASING.md). Changes are listed in [CHANGELOG.md](CHANGELOG.md).

```
replyze/              the plugin; the release ZIP contains this folder
  replyze.php         settings page, widget loading, identity
  uninstall.php       removes the settings on delete
  readme.txt          the wordpress.org readme
tests/e2e/            end-to-end test (WordPress Playground + Playwright)
bin/                  build-zip.sh, changelog-section.sh (used by CI and the release)
playground/           the WordPress Playground demo blueprint
.wordpress-org/       wordpress.org directory assets (screenshots)
```

## Links

- [Replyze](https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat&utm_content=footer): AI customer support and live chat
- [Replyze on GitHub](https://github.com/Replyze): SDKs, examples and the API docs
- Support: support@replyze.chat

## License

GPL-2.0-or-later, like WordPress itself. See [LICENSE](LICENSE).
