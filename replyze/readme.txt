=== Replyze — AI Customer Support Chat ===
Contributors: replyze
Tags: live chat, customer support, ai chatbot, chat widget, help desk
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds your Replyze chat widget to WordPress: AI answers from your own content, a shared inbox for your team, and live chat.

== Description ==

[Replyze](https://replyze.chat/?utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=readme) is a customer support service: an AI agent that answers from your website and documents, a shared inbox where your team takes over, and a chat widget for your site. This plugin adds your Replyze widget to WordPress without editing theme files.

You need a Replyze account. You can create a free one at [client.replyze.chat](https://client.replyze.chat/register?utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=readme). Plans are on [replyze.chat/pricing](https://replyze.chat/pricing?utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=readme).

= What the plugin does =

* Adds the widget to every page, loaded asynchronously in the footer.
* Hides it where you choose: from signed-in administrators, on whole content types (for example Products), or on single pages and posts by ID.
* The `[replyze]` shortcode shows the widget on a page even where those rules hide it.
* Optionally tells Replyze who your signed-in users are (name and email), so your team sees who they are talking to, including WooCommerce customers.
* With the identity secret from Replyze, each user is signed on your server (HMAC-SHA256), so the same person is one verified contact on every device and nobody can pretend to be them. The secret never leaves your server.
* When a signed-in user signs out, or someone else signs in on the same browser, the chat forgets the previous person on that browser.
* Optionally shows the widget in the page's language (for multilingual sites).
* With WooCommerce active, the settings page shows how to connect the store in Replyze with a read-only REST API key, so the AI can answer product, stock and price questions. The plugin never stores that key.

= For developers =

* `replyze_show_widget` filter: decide per page whether the widget loads.
* `replyze_identity` filter: change what the widget is told about the signed-in user, or return `null` to not identify them.
* `REPLYZE_API_URL` and `REPLYZE_WIDGET_URL` constants in `wp-config.php` load the widget from somewhere else, for example a staging environment.

The source code and issue tracker are on [GitHub](https://github.com/Replyze/wordpress-live-chat).

== External services ==

This plugin connects your site to Replyze, the customer support service that runs the chat. It loads and sends nothing until you save a widget token in Settings → Replyze Chat. After that, on every page where the widget shows:

* The visitor's browser loads the widget script from Replyze's CDN, `https://cdn.replyze.chat/widget.js`.
* The widget talks to the Replyze API at `https://client.replyze.chat`. On each page it sends the page address and title, the referrer, the browser, operating system, device type, language, time zone and screen size, and a random visitor id that it keeps in a first-party cookie (`rz_track_id`) and in the browser's local storage. When the visitor chats, it sends their messages, the files they attach and the name and email they enter.
* Only if you turn on "Tell Replyze who signed-in users are": for a signed-in user, their name and email, and with an identity secret also a user id of the form `wp:your-site-host:123` and its HMAC-SHA256 signature. The identity secret itself is never sent.

The settings page only links to the Replyze dashboard; nothing is sent from the admin area.

Replyze [Terms of Service](https://replyze.chat/terms?utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=readme) and [Privacy Policy](https://replyze.chat/privacy?utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=readme).

== Installation ==

1. Download `replyze.zip` from the [latest GitHub release](https://github.com/Replyze/wordpress-live-chat/releases/latest). In WordPress, go to *Plugins → Add New Plugin → Upload Plugin*, choose the ZIP, then *Install Now* and *Activate*.
2. Go to *Settings → Replyze Chat*.
3. Paste your widget token and save. It starts with `wgt_`; in Replyze it is under *Settings → Integrations → Install widget*.

The widget now shows on your site. Signed-in administrators don't see it by default, so open the site in a private window to check.

To recognise signed-in users, turn on "Tell Replyze who signed-in users are". For verified users, turn on identity verification in Replyze (*Settings → Chat Widget → Identity*) and paste its secret in the plugin.

== Frequently Asked Questions ==

= Do I need a Replyze account? =

Yes. The plugin shows your Replyze workspace's widget. You can create a free account at [client.replyze.chat](https://client.replyze.chat/register?utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=readme).

= Where do I find my widget token? =

In Replyze: *Settings → Integrations → Install widget*. It starts with `wgt_`.

= I saved the token but I don't see the chat. =

Signed-in administrators don't see it by default: open the site in a private window, or turn off "Hide it from administrators who are signed in". Also check that your site's domain is allowed in Replyze (*Settings → Chat Widget*), and that a page cache or script optimisation plugin isn't serving an old page or delaying the script.

= Can I hide the widget on some pages? =

Yes: from signed-in administrators, on whole content types, or on single pages and posts by ID. Developers can also use the `replyze_show_widget` filter.

= Does it slow my site down? =

The widget script loads asynchronously in the footer (on WordPress 6.3 and later with the `async` strategy), so it doesn't hold up the page.

= I use a page cache. Is identifying users safe? =

The user's details are only added to pages for signed-in users, which caching plugins normally don't cache. If yours caches pages for signed-in users, leave "Tell Replyze who signed-in users are" off.

= What happens without the identity secret? =

Signed-in users are passed on unverified: your team sees their name and email, but they don't become verified contacts, and Replyze never lets them take over an email that belongs to a verified contact. With the secret (Replyze: *Settings → Chat Widget → Identity*), each user is one verified contact on every device.

= I made a new identity secret in Replyze. =

Paste the new secret in the plugin, or remove the saved one. Replyze refuses users signed with an old secret, so until then signed-in users aren't recognised.

= What is removed when I delete the plugin? =

Its settings, including the identity secret, on every site of a multisite network. Your chats stay in Replyze.

= Is this plugin GPL? =

Yes, GPLv2 or later.

== Screenshots ==

1. The settings page.

== Changelog ==

= 1.2.2 =
* Fixed: with scripts delayed by an optimisation plugin, or a second copy of the widget on the page, the chat could miss a sign-out or a sign-in: they now reach the running widget directly.
* The links to Replyze on the settings page and here say where they come from.

= 1.2.1 =
* Fixed: after a signed-in user signed out, the chat could bring their conversation back on the next page instead of forgetting it.
* Fixed: when another user signed in on the same browser without the identity secret, the chat kept the previous user's conversation.
* Fixed: a name longer than 255 characters made Replyze refuse the whole identification.
* Uninstall now removes the settings on every site of a large multisite network, not only the first 100.
* A refused token or identity secret no longer shows its message twice.
* The sign-up link on the settings page says the account comes from this plugin.
* Tested up to WordPress 7.1.

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

= 1.2.2 =
Sign-outs and sign-ins also reach the chat when page scripts are delayed by an optimisation plugin.

= 1.2.1 =
Signing out now reliably clears the chat on that browser, also when another user signs in.

= 1.2.0 =
Recognise signed-in users, hide the widget by content type, and a working [replyze] shortcode.
