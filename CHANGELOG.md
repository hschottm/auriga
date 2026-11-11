# Changelog

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
