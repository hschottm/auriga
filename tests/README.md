# Contao 5.7 tests

The Contao 4.13 stub tests were replaced with an integration harness using real Contao models, services, templates and a database on this branch.

## Offline tests

```sh
export AURIGA_VENDOR_AUTOLOAD=/absolute/path/to/contao57/vendor/autoload.php
php tests/cover-search.php
```

HTTP responses are mocked; these tests do not use a database.

## Integration tests

Prepare a disposable Contao 5.7 installation with this package installed and a dedicated database whose name starts with `auriga57_test_`. Configure a non-empty Symfony `framework.secret` in the test installation. Run Contao setup and migrations using that same test `DATABASE_URL` before the scripts.

```sh
export AURIGA_TEST_PROJECT=/absolute/path/to/disposable-contao57
export DATABASE_URL='mysql://USER:PASSWORD@HOST/auriga57_test_local?serverVersion=mariadb-10.11.19'
php tests/contao57-integration.php
php tests/template-output.php
php tests/contao57-http.php
```

**The integration harness deletes and reseeds fixture tables in that database. Never point it at an existing website.** It writes cover fixtures inside the test project's `files/` directory. The HTTP test must run after the integration test; it requests the prepared fixture page through Contao's full HttpKernel.

The template-output test uses the real Contao Twig/legacy bridge (no PHP-template stubs) to cover escaping, HTML attributes, form tokens and cover states.

The scripts test rendering, backend callbacks, missing records, search input validation, UUID/path handling, real cover persistence and rollback. External image requests are mocked. The manual browser file-upload transport and production data are not covered.
