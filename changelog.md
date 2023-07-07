# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2023-07-07

### Added
- Substractor::macrosAll()
- Substractor::pluck()
- Substractor::pluckAll()

### Changed
- All Substractor methods now supports key patterns

## [2.1.1] - 2022-10-11

### Fixed
- md5() called with `null` parameter causing PHP warnings

## [2.1.0] - 2022-09-19

### Added
- Substractor::replace()
- Pre- and post-redaction

## [2.0.1] - 2022-03-25

### Fixed
- Redaction corrupts the extracted substrings

## [2.0.0] - 2022-03-20

### Changed
- The `*` now matches zero or more (as opposed to one or more) non-whitespace characters

### Fixed
- Substractor::matches() sometimes reports false positives
- Patterns ending in a token or macro yield empty-string match for that token 

### Added
- Support for PHP 8.0
- Support for redaction parameter in all public methods
- Unit testing
- More content and examples in README.md

### Removed
- examples/ directory

## [1.0.0] - 2021-10-12

## [1.0.0-alpha1] - 2020-03-12

### Added
- src/Substractor.php
- autoload.php
- changelog.md
- composer.json
- LICENSE.md
- README.md
- .gitignore
