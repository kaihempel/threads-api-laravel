<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Threads app credentials
    |--------------------------------------------------------------------------
    |
    | Use the "Threads App ID" and "Threads App Secret" from the Meta App
    | Dashboard (App settings > Basic) - not the Facebook/Instagram app id.
    | Credentials can be overridden at runtime via Threads::using().
    |
    */

    'app_id' => env('THREADS_APP_ID'),
    'app_secret' => env('THREADS_APP_SECRET'),
    'redirect_uri' => env('THREADS_REDIRECT_URI'),

    // Scopes requested by authorizationUrl() unless others are passed.
    'scopes' => ['threads_basic', 'threads_content_publish'],

    /*
    |--------------------------------------------------------------------------
    | Hosts and API version
    |--------------------------------------------------------------------------
    */

    'graph_url' => env('THREADS_GRAPH_URL', 'https://graph.threads.net'),
    'authorize_url' => env('THREADS_AUTHORIZE_URL', 'https://threads.com/oauth/authorize'),
    'version' => env('THREADS_API_VERSION', 'v1.0'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    |
    | Automatic retries are only applied to idempotent GET requests on
    | connection errors and 5xx responses. POST requests (container creation,
    | publishing) are never retried blindly to avoid duplicate posts.
    |
    */

    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'retries' => 2,
        'retry_delay_ms' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Publishing
    |--------------------------------------------------------------------------
    */

    'publishing' => [
        'initial_wait_seconds' => 30,   // Meta recommendation before the first status check
        'poll_interval_seconds' => 60,  // Meta recommendation: at most once per minute
        'poll_max_seconds' => 300,      // Meta recommendation: at most 5 minutes
        'enforce_quota' => true,        // check threads_publishing_limit before publishing
        'publish_retries' => 1,         // controlled retries when publish failed but container is still FINISHED
        'fetch_permalink' => true,      // load the permalink after a successful publish
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Dispatch ContainerCreated, PostPublished and TokenRefreshed events.
    |
    */

    'events' => true,

];
