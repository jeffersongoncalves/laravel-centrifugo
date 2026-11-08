# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-11-08

First release.

### Added

- `centrifugo` broadcasting driver: publishes through Centrifugo's server HTTP API (`/api/broadcast`), auto-registered so `BROADCAST_CONNECTION=centrifugo` is enough.
- Expiring HS256 subscription tokens authorized by `routes/channels.php` (channel `guards` honoured) and a connection-token route.
- Multi-guard: `centrifugo.guards` + `<x-centrifugo::scripts guard="admin" />`; Centrifugo user ids are `App.Models.Admin.1`-style, so same-id users on different guards never collide.
- Bundled Laravel Echo stand-in on centrifuge-js (`private/channel/listen/stopListening/leave/socketId`, `EchoLoaded`), served by the package with a long-lived cache.

Requires PHP 8.2+, Laravel 12 or 13, Centrifugo v5/v6.

## [Unreleased]
