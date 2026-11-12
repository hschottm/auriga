# Auriga for Contao 5.7

Version **1.4.1.1**, available on `master` and `contao57`, targets **Contao 5.7.x**, **PHP 8.3+** and Symfony 7.4.
For Contao 4.13, use tag `1.2.1`.

## Installation / update

In a Contao 5.7 project:

```sh
composer require hschottm/auriga:1.4.1.1 --with-all-dependencies
php vendor/bin/contao-console contao:migrate --env=prod
php vendor/bin/contao-console cache:clear --env=prod
```

Finish the upgrade to Contao 4.13 before migrating an existing Contao 4 database to Contao 5. Test the upgrade with a copy of the database and files first. Review the proposed schema changes; do not use `--with-deletes` blindly on an imported database. This package now declares the Auriga table and module-field schemas which were missing from the legacy package.

Existing module types, template names and table names are retained. Version 1.3.1 ships all eight templates as `.html.twig`, including the inactive Popwelt import template. The PHP module/content-element classes still use Contao's supported template bridge; this is not a migration to Contao 6.

Reconcile local overrides of the Auriga templates, especially `mod_auriga_song.html5` and `mod_auriga_dpi.html5`. A project-local `.html5` override takes precedence over the new Twig template: back it up outside `templates/` and port any customizations before removing it. Keep existing custom template names where module settings reference them. Twig forms use `{{ contao.request_token }}`. Each shipped template exposes a `content` block for inheritance, e.g. `{% extends "@Contao/mod_auriga_song.html.twig" %}`. Clear the production cache after updating so the template index is rebuilt. Keep the existing recording and cover files under `files/auriga/` and mark the relevant directories public in Contao's file manager.

Cover search results are cached beneath the environment's Symfony cache directory (`var/cache/<environment>/auriga`). Cover storage must be writable by the PHP process. Cover changes require membership in one of the module's configured administrator groups.

Old database and Popwelt imports remain disabled. No legacy import is run during migration.

## Validation

Tested in an isolated Contao **5.7.15** installation on **PHP 8.3.36** with MariaDB **10.11**:

- Composer installation, Contao setup, container warmup and database schema creation.
- Complete HTTP requests for all five frontend modules, the special-music content element, backend callbacks and module previews.
- Invalid/missing records, deleted target pages, search validation and recording UUIDs/legacy paths.
- Cover selection/download with mocked HTTP responses, real file storage, repair of missing covers and transaction rollback.
- Cover search cache and real Twig rendering: escaping (including stored HTML entities), wrapper attributes, cover form states and CSRF tokens, search options, provider links and the inactive backend template.
- Syntax validation of all eight Twig templates. Special-broadcast markup now closes its span even when the broadcast is not also a story.

See `tests/README.md`. The production data and customized NAS templates are not part of these fixtures.

## Template customization in 1.3.2

`ce_specialmusic.html.twig` exposes `headline`, `table_wrapper`, `table_attributes`,
`table_head`, `table_head_attributes`, `table_body` and `table_row` blocks inside
`content`. The default markup is unchanged. A project override can add Bootstrap
without duplicating the song rows:

```twig
{% extends '@Contao/ce_specialmusic.html.twig' %}

{% block table_wrapper %}
    <div class="table-responsive">{{ parent() }}</div>
{% endblock %}

{% block table_attributes %} class="table table-striped table-hover table-borderless border"{% endblock %}

{% block table_head_attributes %} class="border-top-0 border-start-0 border-end-0 border-dark border-3"{% endblock %}
```

## Broadcast template customization in 1.3.3

`mod_auriga_broadcast.html.twig` exposes `navigation_column_attributes`,
`navigation_wrapper`, `navigation_controls`, `table_wrapper`,
`titlesong_table_wrapper`, `table_attributes` and `table_head_attributes`.
The table attribute blocks apply to both broadcast and background-music tables.
The default output remains unchanged.

See `examples/templates/mod_auriga_broadcast_bootstrap.html.twig` for a Bootstrap
variant that inherits all song rows and navigation controls. Copy it to the
project's `templates/` directory and select `mod_auriga_broadcast_bootstrap` in the
Auriga broadcast module. Existing PHP overrides must be reconciled separately.

## List template customization in 1.3.4

`mod_auriga_broadcastlist.html.twig` now exposes `table_wrapper`,
`table_attributes` and `table_head_attributes`.
`mod_auriga_adventurelist.html.twig` exposes `headline`, `table_wrapper`,
`table_attributes`, `table_body`, `row_attributes`, `numbering_attributes`
and `title_attributes`. Default output is unchanged.

Bootstrap examples for both lists are provided in `examples/templates/`.
Copy the desired example into the project's `templates/` directory and select
its name in the corresponding Auriga module. The adventure list example adds
an initially expanded Bootstrap collapse panel with a generated ID, avoiding
one fixed target shared by different lists. Without a headline, the list remains
visible. Both examples inherit the data rows and preserve escaping and provider
URL validation. Clear the production cache after updating templates.

## Song cover customization in 1.3.5

`mod_auriga_song.html.twig` now exposes `cover_display` and `cover_tools` blocks.
Its default output is unchanged. The Bootstrap example in
`examples/templates/mod_auriga_song_bootstrap.html.twig` replaces only the cover
display with a non-autoplay carousel. Each slide has its own save form with the
candidate index and CSRF token; save controls are shown only to users allowed to
edit covers. Search, upload, errors and song metadata remain inherited.

Copy the example into the project's `templates/` directory and select
`mod_auriga_song_bootstrap` in the song module. Bootstrap 5's bundle must be loaded
by the page layout. Existing PHP overrides must be reconciled separately.
Carousel rendering tests cover selection indexes, CSRF tokens, guest/admin
controls, existing covers and empty/single-result states. Saving the second
candidate was also verified in the isolated integration installation with mocked
image downloads and real database/file persistence.

## Request-token correction in 1.3.6

All six form locations now obtain their CSRF token from `contao.request_token`:
the song cover selection, search and upload forms, the Bootstrap carousel save
form, the DPI search form and the inactive Popwelt import form. The PHP template
accessor `requestToken` is not automatically available as a Twig variable.

Update project-local Twig overrides and copied Bootstrap examples as well:
replace `{{ requestToken }}` with `{{ contao.request_token }}`. Package updates do
not replace these project files. Clear the production cache and open the form
page again before submitting. CSRF protection remains enabled.

Regression tests now render without injected token values and validate the
emitted tokens using Contao's real CSRF token manager.

## Improved cover lookup in 1.3.7

The existing MusicBrainz/Cover Art Archive lookup now examines up to ten releases
per query, ordered by MusicBrainz relevance, and returns at most eight distinct
front-cover URLs. If no usable front cover is found, it tries a conservatively
cleaned edition title with the artist, then the cleaned album title alone.
Fallbacks also run when releases exist but have no covers. Meaningful parenthetical
title parts are retained; remaster/deluxe/expanded/anniversary edition labels are
removed. Duplicate release IDs are queried only once per search.

Numeric 500px thumbnails are preferred, with legacy large, 1200px and original
image URLs as fallbacks. A search is bounded to three distinct MusicBrainz queries,
20 cover metadata requests and a 15-second remote-lookup budget. MusicBrainz
requests within the search instance are spaced at least 1.1 seconds apart.
Service errors do not trigger broader MusicBrainz searches. Existing positive
(24 hours) and empty-result (10 minutes) caching remains; the cache key version
was changed so old limited results are not reused. The URL-list return format
and manual cover selection/storage are unchanged.

## Cover search provider in 1.3.8

Under **System → Settings → Auriga – Cover search**, select MusicBrainz / Cover Art
Archive (the default, no credentials required) or Discogs. For Discogs, enter a
personal API token from your Discogs developer settings. Save the settings.
Existing Bootstrap overrides and the cover save form need no changes.

The token field always renders empty. Leave it empty to retain the token, enter a
new token to replace it, or select the delete checkbox to remove it. Deletion wins
if both are submitted. The token is stored in the installation's
`system/config/localconfig.php`, outside the public document root; keep this local
configuration and its backups out of source control. It is not encrypted at rest.
The secret is not included in Contao's configuration change log or frontend HTML.
Only Discogs API requests receive the authorization header; image downloads do not.

Discogs searches releases by album and artist, then tries a cleaned edition title,
then the album alone if no covers were found. At most three API calls and eight
unique cover images are returned, within a 15-second budget. Missing tokens,
authorization errors and rate limits leave the page usable with no suggestions;
they do not silently switch providers. Results are cached separately per provider
and token (24 hours for matches, 10 minutes for empty results). Existing saved
covers are unchanged. Search uses the Discogs API's `cover_image` URLs.

## Manual Discogs search in 1.3.9

Manual Discogs searches use the entered text in separate `artist`,
`release_title`, `q`, `label` and `track` queries. Results from all five searches
are interleaved and deduplicated, with an eight-cover cap and the same 15-second
budget. Discogs does not expose a dedicated LC label-code filter: label codes
are attempted through general search (`q`); matching depends on Discogs indexing.
Catalogue numbers (`catno`) are not the same as label codes. Manual searches remain
manual when navigating between cover candidates, even if the entered text equals
the original album title. Automatic lookup retains its album/artist fallbacks;
MusicBrainz behavior is unchanged. Manual and automatic results use separate caches.

## File directory configuration

`auriga.coverpath` (`auriga/covers/`) and `auriga.fileroot`
(`auriga/recordings/`) are relative to Contao's file directory. Auriga prefixes
Contao's `contao.upload_path` (normally `files`) when resolving cover files and
recording paths. Filesystem access additionally prefixes the project directory.
Existing files, stored file paths and UUIDs do not need to be moved or migrated.
Old configuration overrides starting with `files/` or the configured upload path
remain supported without adding the prefix twice. `filedownloadroot` remains the
external download URL and is unchanged.

## MediafileBrowser content element (1.4.0)

After updating the package, run Contao's database migrations and clear the
production cache. Add the **MediafileBrowser** content element in the file element
category, then select its root folder with the file picker. The browser lists the
current directory, with folders first, naturally sorted names, modification dates
and file sizes. Folder links open subdirectories; the parent link stops at the
configured root. Multiple browsers on a page have separate navigation parameters.

Clicking a file downloads it through the rendered content element, including files
in non-public folders. To restrict access to members, protect the page or content
element and choose the allowed member groups. A non-public folder alone does not
restrict who can use an unprotected browser element. Avoid publicly exposing the
folder or any ancestor if direct access must be prevented. Hidden files and
symlinks are excluded. This browser is read-only: no uploads, rename or deletion.

The Twig template `ce_mediafile_browser.html.twig` provides `headline`, `directory`,
`listing` and `entry` blocks for customization. Its table uses Bootstrap-compatible
classes and also works without Bootstrap. The download response is private and
not stored in caches. German and English labels are included.

Tests: `tests/mediafile-browser.php` covers filesystem boundaries and listing;
`tests/mediafile-browser-integration.php` uses the isolated Contao test installation
for template rendering, navigation, download responses and guest access checks.

### File types, playback and private files

Set **Allowed file types** to a comma-separated list such as `mp3` (default) or
`mp3,m4a,flac`. Direct file requests enforce the same filter; directories remain
navigable. Recognized audio formats have a native HTML audio player with
`preload="none"` and a separate download action. Playback uses a streamed inline
response supporting byte ranges; actual codec support depends on the browser.

Select **Member groups for private files** on the browser element. Non-public
files require both a frontend member login and membership in one of those groups,
regardless of whether the element itself is protected. No selected groups means
private downloads and playback are denied. Public visibility is inherited from
ancestor folders in Contao's file records. Public files may be accessed by guests
who can view the element. Both playback and downloads recheck access on each
request. Protect the element/page as well if filenames must be hidden from guests;
otherwise guests can browse the list but see no player or download link for private
files. Never expose private directories through manually created public symlinks.

The browser's **Title** column (after Size) reads embedded MP3 titles from ID3v2.2,
ID3v2.3 or ID3v2.4, falling back to ID3v1. Latin-1, UTF-8 and UTF-16 text are
supported. Missing titles and non-MP3 entries leave the cell empty. Titles are
escaped by Twig. The reader seeks over large picture frames and padding instead of loading the
whole tag or recording. It reads at most 4096 frame headers and a 64 KiB title
frame. For older tag-wide unsynchronisation, a bounded 1 MiB prefix is decoded.
Unsupported compressed/encrypted titles or titles beyond these limits may fall
back to ID3v1; audio files are not modified.

Directories whose names start with `@` (including Synology `@eaDir`) are excluded
from listings and cannot be accessed through direct navigation or file requests.

## MediafileBrowser update 1.4.0.1

- Added the MP3 ID3 title column after file size.
- Excluded directories starting with `@`, including Synology `@eaDir`, from listing and direct access.
- Linked only folder names, leaving icons outside links; removed `..` from the parent-directory label.

MediafileBrowser follow-up releases use a fourth version component (`1.4.0.x`).

## Member-only players in broadcast lists

The broadcast-list module now offers an **Archive folder** picker and **Allowed
member groups**. Run database migrations after updating to add these module fields.
The column after the provider/source column is rendered only for logged-in members
of an allowed group. No selected groups means no player column and no playback.
Each audio request rechecks this permission, including when the archive is public.

Files are looked up under `<archive>/ptw/<adventure.numbering padded to 3 digits>/`
using the filename from the existing `tl_broadcast.files` references (including
Contao file UUIDs). This preserves filenames originally imported from the old
recording database; filenames are not generated from the broadcast title or date.
Only existing, readable MP3 files within the archive receive a player. Missing
recordings leave that cell empty. Playback is streamed with byte-range support;
old direct external download links are no longer supplied by this module.

The bundled Bootstrap broadcast-list variant inherits the changed markup.
Project-local templates that copied the full old table must be updated: wrap both
the final header and cell in `showArchivePlayer`, and render `broadcast.audio`
URLs through audio players. The original exposes an `archive_player` block.

## Release 1.4.0.2

- Added configurable archive folders and member groups to broadcast lists, with protected audio playback for existing recordings.
- Hide the entire player column for unauthorized visitors.
- Keep MediafileBrowser icons and filenames together on one line.
- Database migration is required for the new module settings.

## DPI template blocks in 1.4.1

The original DPI search template now provides `headline`, `search_form`,
`hidden_fields`, `search_fields`, `search_input`, `field_select`, `submit_button`,
`results_select`, `result_info`, `table_wrapper`, `table_head`, `table_body`,
`table_row` and `pagination` blocks, plus attribute/class blocks for Bootstrap
styling. The original retains its framework-independent presentation and now uses
wrapping labels for its controls. Field names, CSRF token, search options, results
and pagination remain in the original template.

Copy `examples/templates/mod_auriga_dpi_bootstrap.html.twig` to your project's
`templates/` directory and select it in the DPI module. This small inherited
variant styles the form and results table without duplicating the data loops.
Existing project overrides are not replaced automatically. No database migration
is required for this release.

## MediafileBrowser update 1.4.1.1

- Read ID3v2 titles even when the tag exceeds 1 MiB, instead of falling back to truncated ID3v1 titles.
- Seek past large picture frames and padding, keeping memory usage bounded.
- Added regression tests for titles before and after large picture frames in ID3v2.3 and ID3v2.4.
- No database migration required.
