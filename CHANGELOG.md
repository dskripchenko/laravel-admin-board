# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
