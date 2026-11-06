# Changelog

Alle nennenswerten Änderungen an diesem Paket werden hier dokumentiert.
Format nach [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach [SemVer](https://semver.org/lang/de/).
Bis 1.0 sind Breaking Changes in Minor-Versionen möglich.

## [Unreleased]

### Hinzugefügt
- OAuth: `authorizationUrl()`, `handleCallback()`, `exchangeCode()`, `toLongLived()`, `refresh()` inkl. lokaler Prüfung (≥ 24 h alt, nicht abgelaufen), `TokenSet` mit `needsRefresh()` / `canRefresh()`, `StateGenerator`.
- Laufzeit-Zugangsdaten über `Threads::using($appId, $secret)`.
- Publishing: Text, Bild, Video, Karussell (2–20), Auto-Publish, Reply-Control, Antworten, Zitate, Link-Attachment, Umfragen, Topic-Tag, Alt-Text, Location, Länder-Allowlist, Spoiler, Text-Attachments.
- Zweistufiger Ablauf (`createContainer()` / `publishing()->publish($id)`) mit idempotenter Publish-Logik (Recovery über Container-Status, kontrollierter Retry).
- `ContainerPoller` mit injizierbarem `Sleeper` (Meta-Empfehlung: 30 s Wartezeit, 1×/Minute pollen, max. 5 Minuten).
- Lokale Validierung (`PostValidator`) mit Übersetzungen (de/en), abschaltbar per `withoutValidation()`.
- Kontingentprüfung vor dem Publish (Posts bzw. Antworten), `quota()->publishing()`.
- Profil, Medienliste/-abruf mit Pagination, Replies/Conversation, Hide/Unhide, Pending-Replies (Approve/Ignore), Insights (Post + Account), Location-Suche, Löschen.
- Typisierte Exceptions für beide Fehlerformate (Graph-Envelope und flache OAuth-Fehler); Tokens/Secrets werden aus Meldungen und Roh-Bodies entfernt.
- Events `ContainerCreated`, `PostPublished`, `TokenRefreshed` (abschaltbar).
- `ThreadsCapabilities` mit allen Plattform-Limits.
- Test-Helfer `FakeThreads`, `FakeSleeper` und JSON-Fixtures für Verbraucher-Tests.
- GitHub-Actions-Workflow: PHP 8.3–8.5 × Laravel 12/13 (inkl. `prefer-lowest`), Pint, PHPStan Level 8, Coverage ≥ 90 %, `composer audit`.
