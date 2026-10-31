=== IconPress ===
Tags: icons, icon wall, link wall, advertising, favicons, grid
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2026.10.23
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://matthewxmurphy.com/donate

An icon wall of linked icons with placement slots, date windows, click counting, and house ads for empty slots. The WordPress rebuild of Mafia Icons (Joomla, 2010).

== Description ==

IconPress renders a tidy grid of small icons, each one a link. It began life in 2008 as Mafia Icons, a Joomla 1.5 component by JoomlaMafia, and this is its modern WordPress rebuild by the original author.

The wall is a fixed grid of slots (6 across by 4 down at 32x32 out of the box, all adjustable). Fill a slot with an icon, a title, and an outbound link. Slots can carry a start date and an end date, so placements run for a set term and then retire themselves. A slot that is empty, unpublished, or expired never sits blank: it shows your house ad (your own text and link, for example your advertise page), so the wall always looks full and keeps selling the empty space.

The wall ships preloaded. On activation every slot is filled with a seeded house entry carrying one of the bundled default icons (the original JoomlaMafia house icons, the blank slot art, and a generated AD tile), and the first slots carry the author's own entries. No slot ever renders blank, even the blanks have icons. Edit, replace, or clear any seeded entry like any other slot.

There are two tiers. The free tier renders 16x16 and 32x32 icons. IconPress Pro adds 64x64 and 128x128 slot sizes and the store: automatic slot selling through Stripe. A buyer picks a slot, pays on Stripe hosted checkout, and the payment webhook places their icon, link, and ad text on the wall for the purchased term, claiming a seeded house entry first. You set the price, currency, and term, and you choose whether new slots go live at once or wait for your approval. Stripe keys are your own, entered in the plugin settings; card data never touches your site.

Features:

* Fixed slot grid with a visual coordinate manager in the admin (A1, B2, and so on), modeled on the original drag-and-drop grid manager
* Per-slot icon (media library upload or image URL, favicons welcome), ad text, outbound URL, alt text, and rel (nofollow, sponsored, follow, external)
* Start and end dates per placement, with an Expiring screen and a dashboard widget that list placements by days left, with one-click email to the advertiser
* Price and notes fields per slot for your own bookkeeping, plus the advertiser email (admin only, never displayed)
* Click counting through a short redirect, like the original hit counter, with an option to link directly instead
* Settings for icon size (16 or 32 on the free tier; Pro adds 64 and 128), grid width and height, link target, tooltip border, background, font weight and opacity, and cell background, matching the original configuration
* A preloaded wall: seeded house entries with bundled default icons fill every slot on activation, so the wall never renders blank
* Pro store: sell slots automatically through Stripe Checkout, with a signed webhook that places paid slots, a moderation choice (auto-live or hold for approval), and a Sales screen listing every order
* Three ways to show the wall: the [iconpress_wall] shortcode, the IconPress Wall Gutenberg block, and the IconPress Wall widget
* Pure CSS hover tooltips. No jQuery and no JavaScript on the front end
* A subtle "Powered by IconPress" credit line under the wall, on by default and removable in Settings, in the spirit of the original "Created with Mafia Icons" line
* Optional sample data: the five advertisers from the original 2010 installer, loadable from the manager screen

IconPress is free. If it earns its keep on your site, you can say thanks with a $2.50 donation at the donate link above.

== Installation ==

1. Upload the `iconpress` folder to `/wp-content/plugins/`, or upload the zip under Plugins, Add New, Upload Plugin.
2. Activate IconPress. It creates its slots table and preloads the grid with seeded house entries carrying the bundled default icons.
3. Go to IconPress, Icons Manager and click any slot to fill it, or leave the house entries in place.
4. Put the wall on a page with the [iconpress_wall] shortcode, the IconPress Wall block, or the IconPress Wall widget.
5. Set your house ad text and link under IconPress, Settings.

== Frequently Asked Questions ==

= How do I sell placements with this? =

That is what it was built for, back in 2008. Fill a slot with the advertiser icon and link, set the price you agreed and the end date for the term, and record their email. When the term runs out, the slot flips back to your house ad on its own, and the Expiring screen tells you who to nudge for a renewal.

With IconPress Pro you can also sell hands-free: set the price, currency, and term in Settings, enter your Stripe keys, register the webhook endpoint shown there, and put the [iconpress_buy] shortcode on a page. Paid orders land on the Sales screen and on the wall.

= What does Pro add? =

Two things: the big slot sizes (64x64 and 128x128; the free tier renders 16 and 32) and the Stripe store for automatic selling. Everything else, the preloaded wall included, is in the free tier.

= Why does an empty slot show an ad for my own site? =

That is the original design. An empty slot is inventory. The default text is "Advertise YOUR Site Here!" and you point it at your own advertise page in Settings.

= Can I turn off the Powered by line? =

Yes. IconPress, Settings, Credit line. It is on by default and it is one checkbox to remove.

= Does it slow my site down? =

The wall is one small stylesheet and plain HTML with a CSS-only tooltip. There is no front-end JavaScript at all.

= What happens when I uninstall? =

The slots table and the plugin settings are removed. Images you uploaded for icons stay in your media library.

== Screenshots ==

1. The Icons Manager: the coordinate grid and the slot list.
2. Editing a slot: icon picker, dates, price, and notes.
3. The wall on the front end with a hover tooltip.

== Changelog ==

= 2026.10.2 =
* Owner design revision. The wall now ships preloaded: activation seeds every slot with house entries carrying bundled default icons (the original JoomlaMafia house icons, blank slot art, and a generated AD tile), with the author's own entries first, so no slot renders blank.
* Two tiers from one codebase. Free renders 16x16 and 32x32. Pro adds 64x64 and 128x128 sizes (the 48 size is retired) and the Stripe store: a [iconpress_buy] form, Stripe Checkout sessions, a signature-verified webhook that places paid slots for the purchased term, auto-live or hold-for-approval moderation, and a Store Sales screen.
* Seeded entries are marked as house entries in the manager and can be edited, replaced, or cleared; store purchases claim them first.

= 2026.10.1 =
* First release. WordPress rebuild of Mafia Icons (Joomla 1.5, v1.0 July 2008 and v1.1 April 2010): slot grid, date-window placements, house ads for empty slots, click counting, expiring placements list and dashboard widget, shortcode, block, and widget.
