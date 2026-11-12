=== Replyze — AI Customer Support Chat ===
Contributors: replyze
Tags: live chat, customer support, ai chatbot, chat widget, help desk
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds your Replyze chat widget to WordPress: AI answers from your own content, a shared inbox for your team, and live chat.

== Description ==

[Replyze](https://replyze.chat) is a customer support platform: an AI agent that answers from your website and documents, a shared inbox where your team takes over, and a chat widget for your site. This plugin adds your Replyze widget to WordPress without editing theme files.

You need a Replyze account. The plugin itself is free; Replyze's plans and prices are on [replyze.chat/pricing](https://replyze.chat/pricing).

= What the plugin does =

* Adds the widget to every page, loaded asynchronously in the footer.
* Hides it where you choose: from signed-in administrators, on whole content types (for example Products), or on single pages and posts by ID.
* The `[replyze]` shortcode shows the widget on a page even where those rules hide it.
* Optionally tells Replyze who your signed-in users are (name and email), so your team sees who they are talking to, including WooCommerce customers. With the identity secret from Replyze, each user is signed on your server (HMAC-SHA256), so the same person is one verified contact on every device and nobody can pretend to be them. The secret never leaves your server.
* When a signed-in user signs out, the widget forgets them on that browser.
* Optionally shows the widget in the page's language (for multilingual sites).
* With WooCommerce active, the settings page shows how to connect the store in Replyze with a read-only REST API key, so the AI can answer product, stock and price questions. The plugin never stores that key.

== External services ==

This plugin connects your site to Replyze, the service that runs the chat. It does nothing until you paste your widget token.

* **The widget script** is loaded from `https://cdn.replyze.chat/widget.js` on the pages that show the widget.
* **The chat** then talks to `https://client.replyze.chat`: the messages a visitor sends, the page they are on, their browser language and the visitor data the widget collects, as described in Replyze's privacy policy.
* **Signed-in users**, only if you turn on "Tell Replyze who signed-in users are": their name and email, and with an identity secret a user id of the form `wp:your-site-host:123` with its signature.

Replyze [Terms of Service](https://replyze.chat/terms) · [Privacy Policy](https://replyze.chat/privacy)

== Installation ==

1. In WordPress, go to *Plugins → Add New Plugin → Upload Plugin*, choose the plugin ZIP (`replyze.zip` from [the GitHub releases](https://github.com/Replyze/wordpress-live-chat/releases)), then *Install Now* and *Activate*.
2. Go to *Settings → Replyze Chat*.
3. Paste your widget token (it starts with `wgt_`; in Replyze under *Settings → Integrations → Install widget*) and save.

To recognise signed-in users, turn on "Tell Replyze who signed-in users are". For verified users, turn on identity verification in Replyze (*Settings → Chat Widget → Identity*) and paste its secret in the plugin.

== Frequently Asked Questions ==

= Do I need a Replyze account? =

Yes. The plugin shows your Replyze workspace's widget. You can create a workspace at [client.replyze.chat](https://client.replyze.chat/register).

= Where do I find my widget token? =

In Replyze: *Settings → Integrations → Install widget*. It starts with `wgt_`.

= Can I hide the widget on some pages? =

Yes: from signed-in administrators, on whole content types, or on single pages and posts by ID. Developers can also use the `replyze_show_widget` filter.

= Does it slow my site down? =

The widget script loads asynchronously in the footer (on WordPress 6.3 and later with the `async` strategy), so it doesn't hold up the page.

= I use a page cache. Is identifying users safe? =

The user's details are only added to pages for signed-in users, which caching plugins normally don't cache. If yours caches pages for signed-in users, leave "Tell Replyze who signed-in users are" off.

= What happens without the identity secret? =

Signed-in users are passed on unverified: your team sees their name and email, but they don't become verified contacts, and Replyze never lets them take over an email that belongs to a verified contact. With the secret (Replyze: Settings → Chat Widget → Identity), each user is one verified contact on every device.

= Is this plugin GPL? =

Yes, GPLv2 or later.

== Screenshots ==

1. The settings page.

== Changelog ==

= 1.2.0 =
* Signed-in users and WooCommerce customers can be identified in the chat, optionally verified with the identity secret from Replyze; the widget forgets them when they sign out.
* Hide the widget on whole content types (posts, pages, products...), also on their archive pages.
* The `[replyze]` shortcode now shows the widget on its page even where the rules hide it.
* Optionally show the widget in the page's language.
* The widget is loaded with `wp_enqueue_script` (async on WordPress 6.3+).
* The widget token is checked when saved.

= 1.1.0 =
* With WooCommerce active, the settings page shows how to connect the store to Replyze (a read-only REST API key, pasted in the Replyze dashboard) so the AI can answer product, stock and price questions. The plugin never stores the key.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.2.0 =
Recognise signed-in users, hide the widget by content type, and a working [replyze] shortcode.
