# Regression tests

Run from the Auriga repository with PHP and the Composer dependencies of a Contao installation:

```sh
export AURIGA_VENDOR_AUTOLOAD=/absolute/path/to/contao/vendor/autoload.php
php tests/php-compatibility.php
php tests/missing-records.php
php tests/cover-search.php
php tests/cover-selection.php
php tests/template-output.php
```

HTTP requests are mocked; no production database is accessed. These tests do not replace a full installation and frontend/backend test in Contao 4.13. Cover storage tests use temporary files.
