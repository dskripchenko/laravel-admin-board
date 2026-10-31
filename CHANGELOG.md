# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.1] - 2026-11-01

### Fixed
- **The board routes no longer spend the panel's request limit.** A board
  open in a tab reports the cursor up to ten times a second and polls; with
  the panel's `admin.api.throttle` (240 a minute per user, shared by the
  whole panel) that left the rest of the panel answering 429 within a
  minute. The board routes now drop the panel's limit and take their own,
  `admin-board.throttle` (`'1800,1'` by default, counted apart; null turns
  it off).

## [1.0.0] - 2026-11-01

### Added
- **Boards**: tables `admin_boards` (the snapshot, the number of the last
  batch, the lock, an optional owner record) and `admin_board_batches` (the log
  of recent batches, trimmed to `admin-board.keep`).
- **The board server**: a PHP port of `BoardServer` and `applyOps` of
  `@dskripchenko/board` — batches ordered under the board's row lock, resends
  answered with their number, 500 operations per batch and 10 000 objects per
  board, a locked board keeping only `meta` operations. Checked against
  fixtures recorded from the reference implementation.
- **The board API** of the panel: `POST {api}/boards/{id}/snapshot`, `batches`,
  `poll` (410 when the client is behind the log) and `presence` (30 s, in the
  cache), behind the panel's session, CSRF, authentication and API middleware.
- **Permissions** `admin.boards.view` and `admin.boards.edit`, and
  `BoardAccess::authorizeUsing()` / `scopeUsing()` for per-board access.
- **Panel**: the Boards resource, the board page, `HasBoard` for any model,
  `BoardWidget` for dashboards, `BoardLayout::for()` — the core's
  `Layout::board()` when it ships, a `board` layout of the same props until then.
- **Realtime** (optional, `dskripchenko/laravel-realtime`): `batch` and
  `presence` events on the private channel `admin-board.{id}`, authorized by
  the view check.
