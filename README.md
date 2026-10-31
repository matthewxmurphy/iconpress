# IconPress

IconPress is the WordPress icon-wall rebuild of Mafia Icons. The plugin renders
linked icon grids through a shortcode, server-rendered block and widget, with
placement dates and house-ad fallbacks. Existing source credits Matthew C Murphy /
Built Not Begged and preserves the JoomlaMafia history; maintenance is by
[Matthew X Murphy](https://MatthewxMurphy.com) /
[Built Not Begged](https://builtnotbegged.com).

## Source status

This repository adopts the existing 33-file WordPress plugin source and proposes
an unreleased mobile tooltip correction. It is not a new packaged release or a
production installation. Plugin header, constant and stable tag remain
`2026.10.23`; the older block metadata and historical readme changelog identity
`2026.10.2` are preserved. Aligning future release identities and building a new
ZIP are separate work.

The copied plugin contains existing Pro/store code. This source handoff does not
verify or activate Pro, Stripe, licensing, sales or any provider integration.
Only the Free wall and the focused tooltip layout are within this change.

## Use

Copy this directory into `wp-content/plugins/iconpress` in a disposable WordPress
site, then activate IconPress. Use `[iconpress_wall]`, the IconPress Wall block or
the IconPress Wall widget. See [readme.txt](readme.txt) for the inherited product
instructions and history. Do not configure Pro or live payment credentials for
Free layout testing.

## Tooltip correction and checks

Long tooltip text wraps within its box at all widths. At widths up to 600px,
tooltips are fixed near the viewport bottom and centered, with viewport-bounded
width. Hover and keyboard focus still use CSS only; no frontend JavaScript was added. The existing desktop
positioning and all other plugin runtime files remain unchanged.

In a disposable WordPress fixture, check the first and last wall links for the
shortcode, block and widget at 320px, 390px and 1440px. Hover and focus each link;
verify the complete title/description remains readable, every text range fits,
no horizontal scrolling appears, and the tooltip does not cover the focused
icon in the scrolling/focus case. Inspect screenshots before fixture cleanup.
Do not follow seeded advertiser links. Theme-specific clipping/transforms and
assistive-technology acceptance beyond these focused checks remain unverified.

## License

The inherited plugin declares GNU GPL version 2 or later. Original notices and
bundled assets are retained; [COPYING](COPYING) contains the full GNU GPL v2 text.
No adjacent archive, database, customer record, dependency tree or credential is
part of this source repository.
