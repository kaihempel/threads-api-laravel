# threads-api-laravel

[![Tests](https://github.com/kaihempel/threads-api-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/kaihempel/threads-api-laravel/actions/workflows/tests.yml)

Schlankes, testbares Laravel-Paket für die [Meta Threads API](https://developers.facebook.com/docs/threads):
OAuth, Veröffentlichen von Posts (Text, Bild, Video, Karussell, Umfrage, Zitat, Antwort), Container-Status,
Kontingente, Profil- und Medienabruf, Reply-Moderation und Insights.

Das Paket ist ein reiner Client: **keine UI, keine Routen, keine Migrationen, kein Scheduler**. Tokens, Posts und
Zeitpläne verwaltet die Anwendung; das Paket liefert dafür idempotente Bausteine.

- PHP `^8.3`, Laravel `^12.0 | ^13.0`
- HTTP über `Illuminate\Http\Client` (→ `Http::fake()` in Tests)
- Readonly-DTOs, Backed Enums, typisierte Exceptions, stateless

## Installation

Solange das Paket nicht auf Packagist liegt, als VCS-Repository einbinden:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/kaihempel/threads-api-laravel" }
]
```

```bash
composer require kaihempel/threads-api-laravel
php artisan vendor:publish --tag=threads-config   # optional
php artisan vendor:publish --tag=threads-lang     # optional
```

Service Provider und Facade (`Threads`) werden per Auto-Discovery registriert.

### `.env`

```dotenv
THREADS_APP_ID=           # "Threads App ID" (App Dashboard › App settings › Basic)
THREADS_APP_SECRET=       # "Threads App Secret" – nicht die Facebook-/Instagram-App-ID!
THREADS_REDIRECT_URI=https://example.com/threads/callback
# optional
THREADS_GRAPH_URL=https://graph.threads.net
THREADS_AUTHORIZE_URL=https://threads.com/oauth/authorize
THREADS_API_VERSION=v1.0
```

### Meta-App einrichten

1. App im [Meta App Dashboard](https://developers.facebook.com/apps) mit dem Use-Case **„Access the Threads API“** anlegen.
2. Unter *Threads API › Settings* die **Redirect Callback URL** exakt eintragen (HTTPS, Trailing Slash beachten).
   Lokal z. B. über einen Tunnel (ngrok, Expose).
3. Vor dem App-Review können sich nur **Threads-Tester** verbinden (App roles › Threads Testers, Einladung in der
   Threads-App unter *Einstellungen › Konto › Website-Berechtigungen* annehmen).
4. Benötigte Berechtigungen anfragen (siehe [Scopes](#scopes)).

Alle weiteren Optionen (Timeouts, Retries, Polling, Kontingentprüfung, Events) stehen in [`config/threads.php`](config/threads.php).

## Schnellstart

```php
use KaiHempel\Threads\Enums\ReplyControl;
use KaiHempel\Threads\Facades\Threads;

$threads = Threads::for($accessToken);          // oder Threads::for($tokenSet)

$me     = $threads->profile()->me();            // Profile-DTO
$quota  = $threads->quota()->publishing();      // PublishingLimit-DTO ($quota->remaining())

$result = $threads->post()
    ->text('Hallo Threads')
    ->image('https://cdn.example.com/a.jpg', alt: 'Ein Bild')
    ->replyControl(ReplyControl::AccountsYouFollow)
    ->topicTag('laravel')
    ->publish();                                // PublishResult

$result->mediaId;    // "18050000000000001"
$result->permalink;  // "https://www.threads.com/@user/post/…"
```

## OAuth

```php
use KaiHempel\Threads\Auth\StateGenerator;
use KaiHempel\Threads\Enums\Scope;

// 1. Redirect
$state = StateGenerator::generate();
session(['threads_state' => $state]);

return redirect(Threads::auth()->authorizationUrl(
    state: $state,
    scopes: [Scope::Basic, Scope::ContentPublish, Scope::ManageReplies],   // optional, Default aus Config
));

// 2. Callback
abort_unless(StateGenerator::matches(session('threads_state'), $request->query('state')), 403);

$short = Threads::auth()->handleCallback($request->query());   // TokenSet (1 h), wirft AuthorizationDeniedException bei "Abbrechen"
$long  = Threads::auth()->toLongLived($short);                 // TokenSet (60 Tage), nur serverseitig

$channel->update([
    'threads_user_id' => $long->userId,
    'token'           => $long->toArray(),   // z. B. Eloquent-Cast "encrypted:array"
    'token_expires_at'=> $long->expiresAt,
]);

// 3. Refresh (z. B. täglicher Job)
$token = TokenSet::fromArray($channel->token);

if ($token->needsRefresh(daysBefore: 10) && $token->canRefresh()) {
    $channel->update(['token' => Threads::auth()->refresh($token)->toArray()]);
}
```

| Methode | Endpunkt | Ergebnis |
|---|---|---|
| `authorizationUrl()` | `threads.com/oauth/authorize` | Redirect-URL |
| `exchangeCode($code)` | `POST /oauth/access_token` | Short-Lived-Token (1 h) |
| `toLongLived($token)` | `GET /access_token` (`th_exchange_token`) | Long-Lived-Token (60 Tage) |
| `refresh($token)` | `GET /refresh_access_token` (`th_refresh_token`) | neues 60-Tage-Token |

- Es gibt **kein separates Refresh-Token** – das Long-Lived-Token selbst wird getauscht.
- `refresh()` mit einem `TokenSet` prüft lokal: Token jünger als 24 h → `TokenNotRefreshableException`,
  abgelaufen → `TokenExpiredException` (Nutzer muss neu autorisieren).
- `toLongLived()` und `refresh()` verweigern den Aufruf ohne `app_secret` (`ConfigurationException`).
- Das Paket speichert nichts und sendet keine Notifications; es feuert `TokenRefreshed` bzw. wirft Exceptions.

### Zugangsdaten je Projekt

```php
Threads::using($project->threads_app_id, $project->threads_app_secret, $project->redirect_uri)->auth()->…;
Threads::using($appId, $secret)->for($token)->post()->…;
```

## Publizieren

### Synchron (`publish()`)

`publish()` führt die komplette Pipeline aus:

1. Kontingent prüfen (`publishing.enforce_quota`) – Posts bzw. bei `replyTo()` das Antwort-Kontingent
2. Container erstellen (Karussell: erst die Kinder, dann der Parent)
3. `initial_wait_seconds` warten (entfällt bei Text-Posts)
4. Status pollen (`poll_interval_seconds`, max. `poll_max_seconds`) bis `FINISHED`
5. Publish – schlägt der Call mit Timeout/5xx fehl, entscheidet der Container-Status: `PUBLISHED` = Erfolg
   (kein Doppelpost), `FINISHED` = kontrollierter Retry (`publish_retries`), `ERROR`/`EXPIRED` = terminal
6. Permalink nachladen (`fetch_permalink`)

### Zweistufig (empfohlen für Queues und Zeitplanung)

```php
// Job 1 – rechtzeitig vor dem Termin (Videos brauchen Verarbeitungszeit, Container verfallen nach 24 h)
$container = Threads::for($token)->post()->video($signedUrl)->text('…')->createContainer();
$post->update(['threads_container_id' => $container->id]);

// Job 2 – zum Termin; darf beliebig oft wiederholt werden
$result = Threads::for($token)->publishing()->publish($post->threads_container_id);
```

`publishing()->publish($id)` prüft vorher den Status und ist damit **idempotent**:

| Container-Status | Verhalten |
|---|---|
| `FINISHED` | wird veröffentlicht |
| `PUBLISHED` | `PublishResult` mit `recovered = true` (kein zweiter Post; `mediaId` ist dann `null`) |
| `IN_PROGRESS` | `ContainerNotReadyException` (retrybar) |
| `ERROR` / `EXPIRED` | `ContainerFailedException` mit `ContainerError`-Enum – neuer Container nötig |

Für Antworten `publish($id, isReply: true)` übergeben, damit das Antwort-Kontingent geprüft wird.
Zusätzlich sollte die Anwendung Unique-Jobs je Post + Kanal verwenden.

### Post-Typen

```php
use KaiHempel\Threads\Publishing\CarouselItem;

$post = Threads::for($token)->post();

$post->text('Nur Text');
$post->text('Bild')->image('https://…/a.jpg', alt: 'Alternativtext');
$post->text('Video')->video('https://…/v.mp4');
$post->text('Release-Notes')->carousel([
    CarouselItem::image('https://…/1.jpg', 'Alt 1'),
    CarouselItem::video('https://…/2.mp4'),
]);
$post->text('Lesetipp')->link('https://example.com/artikel');        // Link-Karte (nur Text-Posts)
$post->text('Was meint ihr?')->poll('Ja', 'Nein', 'Vielleicht');      // 2–4 Optionen (nur Text-Posts)
$post->text('Spannend!')->quote($mediaId);                            // Zitat-Post
$post->text('Danke!')->replyTo($mediaId);                             // Antwort
$post->text('Sofort')->autoPublish();                                 // ein einziger API-Call (nur Text)

// weitere Optionen
$post->topicTag('laravel')                     // genau ein Topic-Tag (statt Hashtag-Listen)
     ->replyControl(ReplyControl::FollowersOnly)
     ->location($locationId)                   // aus locations()->search()
     ->onlyInCountries('DE', 'AT', 'CH')       // allowlisted_country_codes
     ->spoiler(offset: 0, length: 5)           // max. 10 Spoiler
     ->textAttachment($langtext, linkUrl: 'https://…');   // bis 10.000 Zeichen
```

> **Medien werden nicht hochgeladen.** Threads lädt Bilder und Videos selbst von einer **öffentlich erreichbaren
> URL**. Die Anwendung muss (signierte) URLs bereitstellen, die mindestens so lange gültig sind wie die Verarbeitung
> dauert. Das Paket prüft nur das URL-Format (`https`), nicht die Erreichbarkeit.

### Lokale Validierung

Vor jedem API-Call prüft der `PostValidator` die Limits aus `ThreadsCapabilities`. Bei Verstößen wird eine
`ValidationException` geworfen, die Feld + Regel-Code + übersetzte Meldung (de/en) enthält:

```php
try {
    $post->publish();
} catch (ValidationException $e) {
    $e->field();     // "text"
    $e->rule();      // "text_too_long"
    $e->errors();    // ['text' => ['Der Text darf höchstens 500 Zeichen lang sein (aktuell 512).']]
    return back()->withErrors($e->errors());   // Inertia-freundlich
}

$post->violations();          // Prüfen ohne Exception
$post->withoutValidation();   // abschalten, falls Meta die Limits ändert
```

### Capabilities

```php
$caps = Threads::capabilities();   // ThreadsCapabilities::all()
$caps->maxTextLength;              // 500
$caps->supportsStories;            // false – Threads kennt keine Stories
$caps->toArray();                  // für das Frontend
```

## Weitere Ressourcen

```php
$client = Threads::for($token);

// Medien
$page = $client->media()->list(limit: 25, since: now()->subWeek());   // Page<Post>
$next = $client->media()->list(after: $page->after);
$post = $client->media()->get($mediaId);                             // inkl. Karussell-Kinder

// Replies
$client->replies()->list($mediaId, reverse: false);                  // Top-Level
$client->replies()->conversation($mediaId);                          // alles, flach
$client->replies()->mine();                                          // eigene Antworten
$client->replies()->hide($replyId);   $client->replies()->unhide($replyId);
$client->replies()->pending($mediaId);                               // Approvals
$client->replies()->approve($replyId); $client->replies()->ignore($replyId);

// Insights
$client->insights()->media($mediaId, ['views', 'likes', 'replies'])->value('likes');
$client->insights()->account(['views', 'clicks'], since: now()->subDays(30), until: now());
$client->insights()->account(['followers_count']);                   // ohne since/until

// Sonstiges
$client->locations()->search('Berlin');
$client->publishing()->permalink($mediaId);
$client->delete($mediaId);                                           // threads_delete, 100/24 h
```

## Fehlerbehandlung

Alle Exceptions erben von `ThreadsException` und tragen HTTP-Status, Meta-`code`/`error_subcode`, `fbtrace_id` und
den bereinigten Roh-Body (**ohne Tokens und Secrets**). `isRetryable()` sagt, ob ein späterer Versuch sinnvoll ist.

| Auslöser | Exception | `isRetryable()` |
|---|---|---|
| Token ungültig/abgelaufen (Code 190) | `TokenExpiredException` | nein → Refresh / Re-Auth |
| Refresh vor 24 h bzw. Short-Lived-Token | `TokenNotRefreshableException` | nein |
| Nutzer hat Autorisierung abgebrochen | `AuthorizationDeniedException` | nein |
| sonstige OAuth-Fehler | `AuthenticationException` | nein |
| fehlende Berechtigung (Code 10, 200–299) | `PermissionException` | nein |
| Rate-Limit (Code 4, 17, 32, 613, HTTP 429) | `RateLimitException` (`retryAfter`) | ja |
| Tageskontingent erschöpft | `QuotaExceededException` (`limit`, `resetEstimate`) | ja |
| Konto eingeschränkt (Subcode 4279013) | `AccountRestrictedException` | nein |
| `THREADS_API__LINK_LIMIT_EXCEEDED`, lokale Prüfung | `ValidationException` | nein |
| Container `ERROR`/`EXPIRED` | `ContainerFailedException` (`containerError`) | nein |
| Container nicht rechtzeitig fertig | `ContainerTimeoutException` (`containerId`) | ja |
| Publish auf Container `IN_PROGRESS` | `ContainerNotReadyException` | ja |
| fehlende Konfiguration | `ConfigurationException` | nein |
| 5xx, Netzwerk, Sonstiges | `ApiException` | je nach Fehler |

Automatische Retries gibt es nur für **GET**-Requests (Netzwerkfehler und 5xx). POST-Requests werden nie blind
wiederholt; der Publish-Retry läuft ausschließlich über die Container-Status-Prüfung.

## Events

| Event | Wann |
|---|---|
| `ContainerCreated` | nach jedem `POST /{user-id}/threads` (auch Karussell-Kinder) |
| `PostPublished` | nach erfolgreichem Publish (auch bei Recovery, `result->recovered`) |
| `TokenRefreshed` | nach `auth()->refresh()` |

Abschaltbar über `threads.events = false`.

## Limits

| Thema | Limit |
|---|---|
| Posts | 250 pro rollierende 24 h und Profil (Karussell = 1 Post) |
| Antworten / Löschungen / Location-Suchen | 1.000 / 100 / 500 pro 24 h |
| Text | 500 Zeichen, max. 5 Links |
| Text-Attachment | 10.000 Zeichen |
| Karussell | 2–20 Elemente (Bild/Video gemischt) |
| Alt-Text | 1.000 Zeichen |
| Umfrage | 2–4 Optionen à 1–25 Zeichen, nur Text-Posts |
| Bild | JPEG/PNG, max. 8 MB, max. 10:1, min. 320 px breit |
| Video | max. 5 Minuten, max. 1 GB |
| Container | 24 h gültig |

## Scopes

| Scope | Zweck |
|---|---|
| `threads_basic` | Pflicht für alle Aufrufe |
| `threads_content_publish` | Posts veröffentlichen, Publishing-Limit |
| `threads_read_replies` | Replies/Conversation lesen |
| `threads_manage_replies` | Replies veröffentlichen, verstecken, Approvals |
| `threads_manage_insights` | Insights |
| `threads_delete` | Posts löschen |
| `threads_location_tagging` | Location-Suche und -Tagging |

## Testen in der Anwendung

```php
use KaiHempel\Threads\Testing\FakeThreads;

FakeThreads::make()
    ->on('POST', '*/threads_publish', FakeThreads::response('error_server', status: 500))
    ->on('GET', 'v1.0/17890000000000001', FakeThreads::response('container_published'))
    ->fake($this->app);   // registriert Http::fake() und einen FakeSleeper (kein echtes Warten)

$result = Threads::for('TOKEN')->post()->text('Hallo')->publish();
```

Ohne eigene Routen beantwortet `FakeThreads` alle Endpunkte mit den Fixtures aus
[`src/Testing/fixtures`](src/Testing/fixtures); `FakeThreads::fixture('profile', [...])` liefert sie als Array.

## Entwicklung

```bash
composer test            # Pest
composer test-coverage   # Pest mit Coverage (min. 90 %)
composer analyse         # PHPStan Level 8 (Larastan)
composer format          # Laravel Pint

THREADS_LIVE_TOKEN=… vendor/bin/pest --group=live   # optionale Live-Tests mit Tester-Konto
```

Die GitHub Action [`tests.yml`](.github/workflows/tests.yml) testet PHP 8.3–8.5 × Laravel 12/13 (inkl. `prefer-lowest`)
und prüft Pint, PHPStan, Coverage und `composer audit`.

## Hinweise zu ungeprüften API-Details

Einige Details waren laut Spezifikation nur aus Sekundärquellen belegt und sollten gegen die Live-Doku bzw. per
Live-Test geprüft werden:

- `poll_attachment` wird als `{"option_a": …, "option_b": …, "option_c": …, "option_d": …}` gesendet.
- Spoiler werden als `text_entities` mit `entity_type = SPOILER`, `offset`, `length` gesendet.
- Filter der Pending-Replies wird als `approval_status` gesendet.
- Subcode `4279013` (Konto eingeschränkt) und Subcode `2207042` (Publishing-Limit) für das Fehler-Mapping.
- Token wird als `access_token`-Parameter (GET/DELETE: Query, POST: Form-Body) übergeben, nicht als Bearer-Header.
- Nicht umgesetzt (offen laut Spezifikation): Ghost-Posts, GIF-Attachments, `threads_share_to_instagram`,
  Webhooks, Mentions, Keyword-Suche und Profile-Discovery.

## Lizenz

MIT, siehe [LICENSE.md](LICENSE.md).
