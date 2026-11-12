<p align="center">
  <a href="https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Replyze/.github/main/assets/logo-on-dark.png">
      <img src="https://raw.githubusercontent.com/Replyze/.github/main/assets/logo-on-light.png" alt="Replyze" height="48">
    </picture>
  </a>
</p>

# WordPress live chat and AI chatbot plugin

The Replyze plugin for WordPress adds your [Replyze](https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat) chat widget to any WordPress or WooCommerce site without touching theme files. Visitors get answers from an AI agent trained on your own content, your team takes over in a shared inbox when a person is needed, and signed-in users and WooCommerce customers can be recognised in the chat.

- Adds the widget to every page, loaded asynchronously in the footer
- Hides it where you choose: from signed-in administrators, on whole content types (and their archives), or on single pages and posts by ID
- `[replyze]` shortcode: shows the widget on a page even where the rules hide it
- Recognises signed-in users (name and email), optionally verified with an HMAC signature computed on your server
- Shows the widget in the page's language on multilingual sites (optional)
- Two filters for developers: `replyze_show_widget` and `replyze_identity`

Requires WordPress 5.7+, PHP 7.4+ and a Replyze account ([sign up](https://client.replyze.chat/register?utm_source=github&utm_medium=wordpress-live-chat)).

## Install

1. Get the plugin ZIP: download `replyze.zip` from [Releases](https://github.com/Replyze/wordpress-live-chat/releases), or build it from this repository:

   ```bash
   git clone https://github.com/Replyze/wordpress-live-chat.git
   cd wordpress-live-chat
   zip -r replyze.zip replyze
   ```

2. In WordPress: **Plugins → Add New Plugin → Upload Plugin**, choose `replyze.zip`, then **Install Now** and **Activate**.
3. Open **Settings → Replyze Chat** and paste your widget token. You find it in the [Replyze dashboard](https://client.replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat) under **Settings → Integrations → Install widget**. It starts with `wgt_`.

The widget now shows on your site. Open any page in a private window to see it (administrators who are signed in don't see it by default).

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
- `name`: first and last name, or the display name when those are empty

With an **identity secret** saved, the plugin also adds:

- `user_id`: `wp:<your-site-host>:<WordPress user ID>`, so two sites in one workspace never share a contact
- `user_hash`: `HMAC-SHA256(user_id, identity secret)`, computed in PHP

The secret stays on your server and is never printed into the page. Without it, users are passed on unverified: your team sees their name and email, but they don't become verified contacts, and Replyze never lets them take over an email that belongs to a verified contact.

When a user signs out, the widget forgets them on that browser on the next page they open, which matters on shared computers.

**Page caches:** user details are only added to pages for signed-in users, which caching plugins normally don't cache. If yours caches pages for signed-in users, leave this setting off.

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

### Constants

Define them in `wp-config.php` to load the widget from somewhere else, for example a staging environment:

```php
define( 'REPLYZE_API_URL', 'https://client.replyze.chat' );
define( 'REPLYZE_WIDGET_URL', 'https://cdn.replyze.chat/widget.js' );
```

## FAQ

**Do I need a Replyze account?**
Yes. The plugin shows your Replyze workspace's widget. Plans are on [replyze.chat/pricing](https://replyze.chat/pricing?utm_source=github&utm_medium=wordpress-live-chat).

**Does it slow my site down?**
The widget script loads asynchronously in the footer (with the `async` strategy on WordPress 6.3 and later), so it doesn't hold up the page.

**What does the plugin send to Replyze?**
Nothing until you paste a token. Then the widget script loads from `cdn.replyze.chat` and the chat talks to `client.replyze.chat`. Details of signed-in users are sent only if you turn that setting on. See [Privacy](https://replyze.chat/privacy?utm_source=github&utm_medium=wordpress-live-chat).

**What happens when I delete the plugin?**
`uninstall.php` removes its settings, on every site of a multisite network too.

## Repository layout

```
replyze/            the plugin; zip this folder to install it
  replyze.php       settings page, widget loading, identity
  uninstall.php     removes the settings on delete
  readme.txt        the WordPress plugin readme
```

## Links

- [Replyze](https://replyze.chat/?utm_source=github&utm_medium=wordpress-live-chat): AI customer support and live chat
- [Install on WordPress](https://replyze.chat/integrations/wordpress?utm_source=github&utm_medium=wordpress-live-chat)
- Support: support@replyze.chat

## License

GPL-2.0-or-later, like WordPress itself. See [LICENSE](LICENSE).
