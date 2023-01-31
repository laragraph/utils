# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

[See GitHub releases.](https://github.com/laragraph/utils/releases).

## Unreleased

## v1.6.0

### Added

- Support Laravel 10

## v1.5.0

### Added

- Support `webonyx/graphql-php:^15`

## v1.4.0

### Changed

- Consistently throw `BadRequestGraphQLException` on invalid requests

## v1.3.0

### Added

- Support for Laravel 9

## v1.2.0

### Added

- Added support for version `^2` of `thecodingmachine/safe`

## v1.1.1

### Fixed

- Fix parsing of complex content types

## v1.1.0

### Added

- Recognize `Content-Type: application/graphql+json` in `RequestParser`

## v1.0.0

### Added

- Add `RequestParser` to convert an incoming HTTP request to one or more `OperationParams`
