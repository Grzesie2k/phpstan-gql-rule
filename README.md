# PHPStan GraphQL Rule
Custom PHPStan rule for GraphQL queries in rules.

## Installation
```sh
php composer.phar require --dev grzesie2k/php-stan-gql-rule
```
and include rule in `phpstan.neon`:
```yaml
includes:
  - vendor/grzesie2k/phpstan-gql-rule/rules.neon
```
