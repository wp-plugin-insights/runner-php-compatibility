# Fixtures

Each fixture directory contains a tiny PHP file whose syntax requires a specific minimum PHP version.

- `min-php-5.6`: works on all tested versions
- `min-php-7.0`: uses PHP 7.0 scalar/return types
- `min-php-7.1`: uses PHP 7.1 nullable types
- `min-php-7.2`: uses PHP 7.2 `object` type
- `min-php-7.3`: uses PHP 7.3 trailing comma in function calls
- `min-php-7.4`: uses PHP 7.4 typed properties
- `min-php-8.0`: uses PHP 8.0 constructor property promotion
- `min-php-8.1`: uses PHP 8.1 readonly properties
- `min-php-8.2`: uses PHP 8.2 readonly classes
- `min-php-8.3`: uses PHP 8.3 typed class constants
- `min-php-8.4`: uses PHP 8.4 asymmetric property visibility
- `min-php-8.5`: uses PHP 8.5 asymmetric visibility on a static property
- `commented-php-8.4`: fake PHP 8.4 syntax inside a line comment
- `docblock-php-8.4`: fake PHP 8.4 syntax inside a docblock
- `string-php-8.4`: fake PHP 8.4 syntax inside a string literal
- `multi-file-mixed`: multiple files where the highest requirement should win
- `mixed-features-same-file`: one file with multiple features where the highest requirement should win
