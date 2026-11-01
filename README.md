# IconPress

IconPress is the WordPress icon-wall rebuild of Mafia Icons. The plugin renders
linked icon grids through a shortcode, server-rendered block and widget, with
placement dates and house-ad fallbacks. The author and maintainer is
[Matthew C. Murphy](https://MatthewxMurphy.com) /
[Built Not Begged](https://builtnotbegged.com).
The inherited JoomlaMafia history and original notices are retained.

## Source status

The current **unreleased development candidate** is `2026.10.31.001-dev`.
`VERSION`, the plugin header/constant, block version and readme Development
version agree. The readme Stable tag is `trunk`; this candidate creates no stable
release or Git tag. Its calendar spelling follows `YYYY.M.DD.XXX` in
America/Chicago, with `-dev` identifying this source/package proposal.

The original 33-file source and old ZIP remain separate historical artifacts.
This work changes the adopted checkout's metadata and packaging only; it retains
the previously corrected tooltip CSS and the existing Free implementation.
Historical `2026.10.23`, `2026.10.2` and `2026.10.1` identities are not rewritten
into a claim that those releases used this new package.

The copied plugin contains existing Pro/store code. This source handoff does not
verify or activate Pro, Stripe, licensing, sales or any provider integration.
Only the default Free wall is part of the development package's native install
acceptance. No Pro/license/payment configuration is supplied.

## Build the development ZIP

Use Python 3's standard library from this checkout:

```sh
python3 -m unittest discover -s tests -p 'test_package_development.py'
python3 scripts/package-development.py
```

The command creates `dist/iconpress-2026.10.31.001-dev.zip`. Use
`--output-dir /path/to/owned/output` to choose another destination. Existing
artifacts are refused rather than replaced. The fixed payload contains the 33
plugin files plus `README.md`, `CHANGELOG.md`, `COPYING` and `VERSION`, under one
`iconpress/` root. Git metadata, builder/tests, archives, uploads and unrelated
workspace files are excluded. Missing, linked or mixed-version inputs are
refused before output. ZIP member dates follow the candidate calendar date for
repeatable packaging; they do not record a release event.

Install the generated ZIP with WordPress's normal plugin installer in a fresh
disposable site. Verify the installed header, constant and registered block
version, activate once, and render a synthetic Free wall with its served CSS.
Leave advertiser destinations and Pro/store settings untouched. This new
package-install check does not repeat the earlier wall/tooltip acceptance.

## Use

Copy this directory into `wp-content/plugins/iconpress` in a disposable WordPress
site, then activate IconPress. Use `[iconpress_wall]`, the IconPress Wall block or
the IconPress Wall widget. See [readme.txt](readme.txt) for the inherited product
instructions and history. Do not configure Pro or live payment credentials for
Free layout testing.

## Tooltip correction and checks

Long tooltip text wraps within its box at all widths. At widths up to 600px,
tooltips are fixed near the viewport bottom and centered, with viewport-bounded
width. Hover and keyboard focus still use CSS only; no frontend JavaScript was
added. Existing desktop positioning and Free runtime behavior remain unchanged.

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
Maintained metadata, packaging source and adjacent block JSON attribution use
`GPL-2.0-or-later`, with Matthew C. Murphy's 2026 copyright and the author/support
links above. The block JSON keeps only supported WordPress metadata fields.
No adjacent archive, database, customer record, dependency tree or credential is
part of this source repository.

## Engineering adoption

This scoped maintenance follows the referenced
[engineering SOP](https://github.com/matthewxmurphy/developer-standards/blob/main/docs/ENGINEERING-SOP.md)
for coherent metadata, preserved licensing, a reviewed package boundary and
focused native verification. Complete project documentation, a portable-checker
integration, CI/ruleset enforcement and a release ledger remain adoption work;
their presence or completion is not claimed here. Internal standards tooling is
not redistributed in this public repository. Production rollout and its recovery
plan require separate authorization; the existing uninstall behavior is described
in [readme.txt](readme.txt).
