# Changelog

## 1.2.0 - 2026-06-29

- Added automatic flat filter detection in `QueryFilter`, allowing `?status=active`
  alongside Spatie's nested `?filter[status]=active` format.
- Changed string filter config entries in `QueryFilter` to default to
  `AllowedFilter::exact()`.

## 1.1.0 - 2026-06-29

- Added `QueryFilter` support class for centralized listing query filtering,
  sorting, field selection, includes, and pagination.
- Added `spatie/laravel-query-builder` as a runtime dependency.
- Added publishable `elyon-standards` config for `QueryFilter` pagination defaults.
- Added controller-level `defaultPerPage` override support for `QueryFilter`.

## 1.0.0 - 2026-06-28

- Initial release.
- `InstallCommand` and `ExportCommand` for project scaffolding.
- Service Provider with auto-discovery.
- Stubs and agent wrappers (AGENTS.md merge via HTML markers).
- Silent `composer` script merging.
- Git hooks via native `git config core.hooksPath .githooks`.
- TDD skill distributed via Laravel Boost at
  `resources/boost/skills/tdd-phpunit-laravel/SKILL.md`.
- Architecture skill distributed via Laravel Boost at
  `resources/boost/skills/architecture-actions/SKILL.md`.
