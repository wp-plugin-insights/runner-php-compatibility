# runner-php-compatibility

PHP runner that estimates the effective minimum supported PHP version for a WordPress plugin by scanning its source code with PHP CodeSniffer and PHPCompatibility.

## Purpose

This runner reuses the shared PHP RabbitMQ boilerplate from `runner-dummy` and adds static PHP compatibility analysis for WordPress plugins.

## Input contract

Each incoming message must be JSON with:

```json
{
  "plugin": "akismet",
  "src": "/path/to/unpacked/plugin"
}
```

- `plugin`: WordPress plugin slug
- `src`: absolute or repo-local path to the unpacked plugin source

## Output contract

The runner publishes an envelope like:

```json
{
  "runner": "runner-php-compatibility",
  "plugin": "akismet",
  "src": "/path/to/unpacked/plugin",
  "report": {
    "status": "ok",
    "score": {
      "grade": "A+",
      "reasoning": "The plugin declares Requires PHP 7.2 and the code scan also detects 7.2 as the lowest required PHP version."
    },
    "metrics": {
      "detected_min_php": "7.2",
      "declared_min_php": "7.2",
      "declared_min_php_source": "/path/to/unpacked/plugin/readme.txt",
      "tested_versions": ["5.6", "7.0", "7.1", "7.2"],
      "summary": "Lowest required PHP version: 7.2"
    },
    "issues": [],
    "details": {}
  },
  "received_at": "2026-03-20T10:00:00+00:00",
  "completed_at": "2026-03-20T10:00:05+00:00"
}
```

## How detection works

- Runs `phpcs` with a local ruleset that extends `PHPCompatibilityWP`
- Adds custom PHPCS sniffs for newer PHP 8.x features that are not covered reliably enough for this runner
- Tests a fixed set of PHP versions from low to high
- Finds the first version with zero compatibility findings
- Looks for a root-level `readme.txt` first and falls back to a root-level `readme.md`
- Extracts `Requires PHP` from that readme and compares it to the detected minimum PHP version
- Uses that comparison to assign a grade

## Readme precedence

For grading, the runner only inspects root-level readme files in the plugin directory:

- `readme.txt`
- `readme.md`

If both exist, `readme.txt` takes precedence. Nested readmes are ignored.

## Custom PHPCS rules

The local PHPCS ruleset lives in `phpcs/WPPluginInsightsCompatibility/ruleset.xml:1`.

It extends `PHPCompatibilityWP` and adds project-specific sniffs for newer language features that this runner needs to detect reliably:

- property promotion (`8.0`)
- readonly properties (`8.1`)
- readonly classes (`8.2`)
- typed class constants (`8.3`)
- asymmetric property visibility (`8.4`)
- asymmetric visibility on static properties (`8.5`)

These custom sniffs live under `phpcs/WPPluginInsightsCompatibility/Sniffs/Classes:1`.

The runner is intended to execute on PHP `8.5` so PHPCS can tokenize the newest syntax correctly while still checking plugin compatibility against older target versions.

## Setup

```bash
cd runner-php-compatibility
cp .env.example .env
composer install
php bin/runner
```

## Test without RabbitMQ

```bash
mkdir -p /tmp/plugins/akismet
echo '{"plugin":"akismet","src":"/tmp/plugins/akismet"}' | php bin/process-message
```

If you get a missing `vendor/autoload.php` error, run:

```bash
composer install
```

## Run tests

```bash
composer test
```

The integration tests use fixture plugins in `tests/fixtures:1` to verify the detected minimum PHP version from 5.6 through 8.5.

## Notes

- The current version list is fixed in code
- Only PHP files are scanned
- The `report` format is intentionally simple and can evolve later
