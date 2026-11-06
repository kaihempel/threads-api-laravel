<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * Threads API permissions. Before App Review only Threads testers can authorize the app.
 */
enum Scope: string
{
    case Basic = 'threads_basic';
    case ContentPublish = 'threads_content_publish';
    case ReadReplies = 'threads_read_replies';
    case ManageReplies = 'threads_manage_replies';
    case ManageInsights = 'threads_manage_insights';
    case Delete = 'threads_delete';
    case LocationTagging = 'threads_location_tagging';

    /**
     * @return list<self>
     */
    public static function publishing(): array
    {
        return [self::Basic, self::ContentPublish];
    }
}
