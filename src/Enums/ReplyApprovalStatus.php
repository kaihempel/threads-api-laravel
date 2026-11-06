<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

enum ReplyApprovalStatus: string
{
    case Pending = 'pending';
    case Ignored = 'ignored';
}
