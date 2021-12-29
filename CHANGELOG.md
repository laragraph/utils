# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Fix check of `multipart/form-data` in `Content-Type`.

## v1.1.0

### Added

- Recognize `Content-Type: application/graphql+json` in `RequestParser`

## v1.0.0

### Added

- Add `RequestParser` to convert an incoming HTTP request to one or more `OperationParams`
