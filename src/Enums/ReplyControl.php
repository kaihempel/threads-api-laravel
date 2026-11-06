<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * Who may reply to a post (reply_control).
 */
enum ReplyControl: string
{
    case Everyone = 'everyone';
    case AccountsYouFollow = 'accounts_you_follow';
    case MentionedOnly = 'mentioned_only';
    case ParentPostAuthorOnly = 'parent_post_author_only';
    case FollowersOnly = 'followers_only';
}
