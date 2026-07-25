# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-07-17

### Added
- Server State widget `sortBy` / `sortDirection` options (config + query), with sortable fields `name`, `cpu_current`, `memory_current`, `updated_at`
- `Rhythm::record()` / `Rhythm::set()` accept `UnitEnum` / `BackedEnum` type and key values

### Security
- Redis ingest (and shared abstract ingest unserialization) restricts `unserialize()` with `allowed_classes` to `RhythmEntry` / `RhythmValue`

## [1.0.0] - Initial Release

Initial release of the Rhythm plugin for CakePHP, providing comprehensive real-time performance monitoring with metric collection, aggregation, and dashboard visualization.
