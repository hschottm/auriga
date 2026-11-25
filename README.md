# Auriga for Contao 5.7

Version **1.4.23**, available on `master`, targets **Contao 5.7.x**, **PHP 8.3+** and Symfony 7.4.
For Contao 4.13, use tag `1.2.1`.

## Installation / update

In a Contao 5.7 project:

```sh
composer require hschottm/auriga:1.4.23 --with-all-dependencies
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

### MediafileBrowser layout

The browser loads `bundles/hschottmauriga/css/mediafile-browser.css` automatically.
Desktop tables use compact text, vertically centered cells and separate date/time
lines. Below 992px each entry becomes a card with labelled metadata and a
full-width audio player. Folder icons remain outside the links. Long names wrap
beside their icon without forcing horizontal scrolling.

Column classes are `mediafile-browser-name`, `mediafile-browser-modified`,
`mediafile-browser-size`, `mediafile-browser-title` and `mediafile-browser-actions`.
Custom styles can override the defaults, for example:

```css
.mediafile-browser {
    --media-browser-font-size: .875rem;
    --media-browser-player-width: 17rem;
    --media-browser-border: #dee2e6;
}
```

Existing custom templates must adopt the new column classes and `data-label`
attributes to use the mobile layout. The original `entry.date` remains available;
`entry.dateOnly`, `entry.timeOnly` and `entry.datetime` are also provided.

### Song metadata editing in 1.4.2

Members of the song module's cover administrator groups can edit title, artist,
album, year and label code in a separate frontend form. Only the current song is
updated for title, artist and year; existing cover assignments remain unchanged.
Album and label code corrections also update songs matching the original album
and label code exactly, regardless of artist. Both corrections
use the original group even when artist, album and label code change together, and
are saved in one transaction. Matching is case-sensitive. Text limits
match the backend fields (255 characters for title/artist/album, 100 for label
code); year is optional or exactly four digits. Successful saves redirect to the
song and reset its session cover search context.

The `song_editor` Twig block is inherited by the supplied Bootstrap cover
variant. Overrides replacing the entire `content` block must incorporate this
block. The form uses Bootstrap 5 classes and also works without Bootstrap.
Validation: `tests/song-metadata.php` exercises authorization, CSRF rejection,
input validation, database updates, redirects and output escaping in the isolated
Contao test installation.

### Active adventure in 1.4.7

The adventure list marks the entry matching the `adventure` URL parameter with
`isActive`. The default template adds `active` to its row and link and
`aria-current="page"` to the link. No selection or an unknown ID leaves all rows
unselected. The Bootstrap example retains the active row class.

Copy `examples/css/adventurelist-active.css` into your site stylesheet for a blue
highlight. The CSS targets the link so existing `row_attributes` overrides still
work. To also mark rows in an existing Bootstrap override, use:

```twig
{% block row_attributes %}
    class="m-0 p-0{{ adventure.isActive|default(false) ? ' active' : '' }}"
{% endblock %}
```

Templates replacing the entire `table_body` or `content` must incorporate the
active link markup themselves.

### Chapter navigation in 1.4.8

The broadcast template uses inline Lucide chevrons for first, previous, next and
last chapter. Unavailable destinations are disabled spans rather than links.
The module automatically loads `bundles/hschottmauriga/css/chapter-navigation.css`
with keyboard focus styles and touch-friendly controls. The Lucide license is
included under `src/Resources/public/images/LUCIDE-LICENSE.txt`.

Overrides inheriting `navigation_controls` receive the SVG controls automatically.
To remove an existing outer Bootstrap border, use the updated wrapper:

```twig
{% block navigation_wrapper %}
    <span class="auriga-chapter-navigation">{{ block('navigation_controls') }}</span>
{% endblock %}
```

Validation: Contao integration suite and `tests/chapter-navigation.php` cover
rendering, navigation URLs and disabled destinations.

### Special music cover layout in 1.4.9

The special music element automatically loads `special-music.css`. Covers and
placeholders use a 4rem square with centered cropping, cells are vertically
aligned, and years do not wrap. Override `--auriga-cover-size` on
`.auriga-special-music` to adjust the size. Derived templates inherit the wrapper
class; templates replacing the whole content block must retain it explicitly.

### Removing an incorrect album cover

Members of the configured cover administrator groups can remove an existing
cover assignment in the song view. This affects every song with the exact same
album title and label code, regardless of artist. Image files and other albums
remain untouched. An explicit empty mapping prevents automatic cover inheritance;
a new cover can still be selected manually. The separate `cover_remove` Twig
block is also inherited by Bootstrap variants overriding `cover_display`.

### Adventure transcripts

Enable “Geschichte” in the broadcast editor to display “Abenteuergeschichte”.
The optional `storycontent` field stores long plain-text transcripts with line
breaks in a LONGTEXT column. Disabling Story hides the field without deleting
its contents. Run Contao's database migration after updating.

Non-empty transcripts appear after background music in the broadcast view.
Markdown is rendered using CommonMark; line breaks are preserved and embedded HTML is escaped. Customize the `story_section`,
`story_heading` and `story_content` Twig blocks in derived templates.

### Markdown transcripts (1.4.15)

The adventure transcript supports CommonMark headings, emphasis, lists and links:

```markdown
## Small Talk an der Treppe

[10:42] **Paul:** „Kann ich reinkommen?“

[Major Renault](/personen/major-renault.html) hatte ihm den Hinweis gegeben.
```

Person links require a corresponding destination page. `[MM:SS]` timestamps
remain readable text, not player controls: the broadcast view currently has no
uniquely associated audio player. Existing plain-text line breaks are preserved.
Embedded HTML is escaped and unsafe link schemes are disabled. The template
receives `broadcast.storycontent` as the source and `broadcast.storyhtml` as
the safely rendered HTML. No additional database migration is required.

### Auriga-Lexikon

Nach dem Update die Contao-Datenbankmigration ausführen. Unter **Auriga → Lexikon** lassen sich Begriffe mit Markdown-Beschreibung und Veröffentlichungsstatus pflegen. Leere Aliasse werden aus dem Begriff erzeugt; doppelte automatisch erzeugte Aliasse erhalten einen eindeutigen Zusatz. Manuell vergebene Aliasse müssen eindeutig sein. Bestehende Aliasse bleiben beim Ändern des Begriffs erhalten.

Ein Frontend-Modul vom Typ **Auriga-Lexikon** anlegen und auf einer Seite einfügen. Es zeigt eine alphabetische Übersicht veröffentlichter Einträge; die Links öffnen deren Detailansicht auf derselben Seite. Diese Links können in Markdown-Beschreibungen und Abenteuergeschichten verwendet werden. Bei einer Seite `lexikon.html` lautet ein Detail-Link beispielsweise `lexikon/lexicon/major-renault.html`. Den tatsächlich erzeugten Link aus der Übersicht kopieren, insbesondere bei abweichenden URL-Einstellungen. Nachträgliche Alias-Änderungen ändern die URL; alte Links werden nicht automatisch weitergeleitet.

Nicht veröffentlichte oder unbekannte Einträge liefern HTTP 404. Markdown wird wie bei Abenteuergeschichten sicher gerendert: HTML wird als Text behandelt und unsichere Link-Schemata werden verworfen. Das Template `mod_auriga_lexicon.html.twig` bietet die Blöcke `lexicon_list`, `lexicon_entry`, `lexicon_entry_heading`, `lexicon_description` und `lexicon_back` für eigene Gestaltung.

## LRC chapter navigation

Both the mediafile browser and the broadcast list automatically enhance their audio
players when a readable UTF-8 `.lrc` sidecar with valid timestamps exists next to
the recording (for example `ptw_3001_2.mp3` and `ptw_3001_2.lrc`). No database
migration or Jellyfin server is required. LRC does not need to be added to the
browser's allowed download extensions.

The player provides previous/next segment buttons, a collapsible clickable segment
list and the current segment title. Seeking preserves playback/pause state;
previous goes to the preceding segment. Without usable marks, the same custom player remains available with disabled
segment buttons. Without JavaScript, native audio controls remain available.
The unified player includes play/pause, seeking, elapsed/total time, volume/mute
and loading/error feedback; starting a recording pauses other players on the page. Timestamps outside the recording duration
are disabled once metadata is loaded. Minutes may exceed 59. LRC offset tags and
multiple timestamps per line are supported. Sidecars are limited to 256 KiB and
symlink sidecars are ignored. Chapter metadata is only read for authorized players;
existing playback authorization and byte-range delivery remain unchanged.

Install the bundle assets and clear the Contao cache after updating. Templates
that inherit the shipped templates automatically gain the player. Full custom
copies overriding the player markup must adopt the shared
`@Contao/auriga_audio_player.html.twig` include. `broadcast.audio` remains a list
of URLs for compatibility; matching chapters are in `broadcast.audioChapters`.

Tests: `php tests/audio-chapters.php` (optionally set `AURIGA_VENDOR_AUTOLOAD`
for Twig rendering checks) and `node tests/audio-chapters-js.cjs`.

## Story-only playback

Place `ptw_3001_2.story` beside `ptw_3001_2.mp3`. Its UTF-8 JSON content is a
chronological list of non-overlapping ranges, for example:

```json
[
  {"start": "03:15", "end": "08:14", "title": "Previously"},
  {"start": "14:48", "end": "21:37", "title": "Chapter 1"}
]
```

Both archive views offer “Full broadcast / Story only” when a valid file exists.
The full broadcast remains the default and uses LRC chapters if available.
Story mode works without an LRC file and uses the story ranges as chapters.
Its time display and seek slider exclude the gaps. Skipping and selecting chapters
preserve pause/play state. Switching into story mode keeps an in-range position,
otherwise moves to the next range (or restarts if past the final range).
Playback pauses after the final range. A `.story` file needs no database entry.

Use `MM:SS`, `MM:SS.mmm` or `HH:MM:SS.mmm` strings and a nonempty title.
Malformed JSON, reversed/overlapping/unordered ranges and sidecar symlinks are
ignored as a whole. Maximum size is 256 KiB / 2000 ranges. Range ends are clamped
to the media duration once known. The original recording is unchanged; browser
seeking may buffer at transitions. This is playback navigation, not sample-exact
editing or an access restriction on the original MP3. Browser scheduling,
especially in background tabs, can affect boundary timing.

## Member playback preference

Explicit full-broadcast/story-only selections are saved on the logged-in member
account and synchronized across supported players on the page. Subsequent visits
(including another device) use the stored preference. Recordings without story
ranges always play in full and do not overwrite the preference. Restoring the
preference does not preload every audio file or start playback.

Run `contao:migrate --env=prod` after updating to add
`tl_member.aurigaPlaybackMode` (default `full`). Saves use authenticated,
CSRF-protected POST requests; failed saves show a message in the player. Public
guest players can switch locally but do not save an account preference.

## Legacy recording assignments (1.4.23)

The backend file tree reads `tl_broadcast.files` as serialized binary file UUIDs.
Historic `tl_broadcast_file.filename` rows and path strings in `tl_broadcast.files`
can be transferred using this explicit, one-time command:

```sh
# Read-only database inspection, writes a JSON report:
php vendor/bin/contao-console auriga:migrate-recordings --env=prod
# Apply unambiguous assignments, with automatic backup:
php vendor/bin/contao-console auriga:migrate-recordings --apply --env=prod
```

No schema change is required. This is not run automatically by `contao:migrate`.
Review the dry-run report first. Both modes print counts and the report filename.
Use `--report=/absolute/path/new-report.json` to select a report destination; existing
reports are never overwritten. Default reports and backups are stored outside the
public directory in `var/auriga-recording-migration/` with mode 0600.

Resolution uses existing MP3 entries in `tl_files`: an exact path or UUID first,
otherwise the filename within `ptw/NNN/` for the broadcast's adventure number.
It does not crawl the filesystem, register new files, or guess across adventures.
Missing `tl_files` entries and ambiguous matches skip the entire broadcast, with
reasons in the JSON report. Check these paths in Contao's file manager before
retrying. Multiple archive roots containing the same adventure/file are ambiguous.

Existing selections retain their order; legacy rows follow `sorting, id` order.
Duplicates are removed and text UUIDs / old paths are normalized to binary UUIDs.
Existing invalid selections are reported instead of being discarded. All updates
use a transaction and reject concurrent changes to `tl_broadcast.files`.
The automatic JSON backup records byte-exact `before` and `after` values in base64
(`null` remains null). Keep this backup for recovery; no MP3 or legacy-table row
is modified. The command is idempotent while the assignments are unchanged, but
running it again after deliberately removing an old assignment in the backend
will import that legacy assignment again. It is not a scheduled synchronization.
