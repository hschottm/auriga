# Changelog

## 1.4.6 — 2026-11-14

- Replace the large disc placeholder with a square light-gray SVG containing a small, thin gray music note in broadcast lists, background music, special music and search results.
- Keep the existing cover sizing CSS compatible; use a new asset filename to avoid stale disc images in browser caches.

## 1.4.5 — 2026-11-14

- Replace the CD GIF placeholder in broadcast tracks, background music, special music and search results with the Lucide disc SVG; include its license.

## 1.4.4 — 2026-11-14

- Match both album and label code corrections by the original album/label pair, independently of artist. Update both fields together so simultaneous changes cannot affect the destination group.
- Update editor guidance and regression coverage for the new grouping.

## 1.4.3 — 2026-11-14

- Propagate album corrections to songs with the same original artist and album; propagate label code corrections only within the same original artist/album/label group.
- Save the corrections transactionally, match exact values, and explain the affected scope in the editor.
- Test simultaneous artist/album/label changes, other label codes, unrelated artists, and existing destination albums.

## 1.4.2 — 2026-11-14

- Allow members of the configured cover administrator groups to edit the current song’s title, artist, album, year and label code.
- Add an inheritable Bootstrap-compatible `song_editor` form with Contao request tokens, server-side permission checks and field validation.
- Reset session cover suggestions after metadata changes, preserve existing covers, and redirect after successful saves.
- Disable public caching of the personalized song view and add isolated metadata editing integration tests.

## 1.4.1.2 — 2026-11-12

- Add automatically loaded, scoped CSS for a compact MediafileBrowser table with vertically centered cells and separate date/time lines.
- Display entries as labelled cards below 992px with full-width audio controls and wrapping filenames beside their icons.
- Add column classes and configurable CSS variables for font size, player width and borders.
- Preserve download authorization and existing template date data; document custom-template requirements.
- Validate rendered metadata, stylesheet registration, navigation and protected downloads in the isolated Contao integration tests.

## 1.3.0 — 2026-11-11

- Target PHP `^8.3`, Contao `~5.7.0` and Symfony `^7.4`; remove obsolete Composer plugin and UTF-8 helper dependencies.
- Replace removed global classes/functions, mode/root constants and legacy session handling with Contao/Symfony APIs.
- Update bundle signatures, service configuration, CSRF tokens, backend links, date pickers and DCA callbacks.
- Declare all Auriga table schemas and module/content fields for clean installation and migration.
- Support file UUIDs alongside historic recording paths; keep historic signed broadcast timestamps.
- Use the Contao member-group permission for cover administration and honor unlimited image dimensions.
- Keep obsolete import actions disabled, including the Popwelt backend button.
- Replace legacy stub tests with isolated Contao 5.7 integration tests.

## 1.2.1 — 2026-11-11

- PHP compatibility fixes in modules and templates, including corrected `join()` arguments and handling of nullable values.
- Handle missing songs, broadcasts, adventures and related records without PHP errors.
- Use MusicBrainz and Cover Art Archive for cover previews, album searches and cover selection.
- Validate image downloads and uploads; roll back database changes when saving a cover fails.
- Allow missing or invalid covers to be replaced without deleting other songs' cover mappings.
- Cache cover search results for 24 hours and empty/error results for 10 minutes.
- Escape text and attribute output in templates.
- Disable obsolete database, background music and Popwelt imports while retaining their commented source.
- Add regression tests for search, missing records, cover handling, caching and template output.

### Compatibility and update notes

- Requires Contao `~4.13` and PHP `^7.4 || ^8.0`, matching the PHP constraint of Contao 4.13 itself. The existing PHP constraint needed no change.
- Explicitly requires Symfony Cache `~5.4` alongside Symfony HttpClient `~5.4`.
- Reconcile customized Auriga templates with the updated bundled templates, especially `mod_auriga_song.html5`. Overrides otherwise hide the new cover controls and escaping fixes.
- Cover search cache is stored beneath `var/cache/auriga` and must be writable by the web process.
- Validation: PHP 8.3 syntax checks and offline regression tests using installed Symfony 6.4 libraries. Full Contao 4.13/PHP 8.2 integration testing remains pending.
