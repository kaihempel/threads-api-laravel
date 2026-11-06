<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use KaiHempel\Threads\Data\Page;
use KaiHempel\Threads\Data\Reply;
use KaiHempel\Threads\Enums\ReplyApprovalStatus;

/**
 * Reply management (threads_read_replies / threads_manage_replies).
 * Replies themselves are created with $client->post()->replyTo($id).
 */
final class RepliesResource extends Resource
{
    public const array DEFAULT_FIELDS = [
        'id', 'text', 'username', 'permalink', 'timestamp', 'media_type', 'media_url', 'shortcode',
        'has_replies', 'is_reply', 'is_reply_owned_by_me', 'root_post', 'replied_to', 'hide_status',
    ];

    /**
     * Top level replies of a post.
     *
     * @param  list<string>|string|null  $fields
     * @return Page<Reply>
     */
    public function list(string $mediaId, ?bool $reverse = null, ?string $before = null, ?string $after = null, array|string|null $fields = null, ?int $limit = null): Page
    {
        return $this->page("$mediaId/replies", $reverse, $before, $after, $fields, $limit);
    }

    /**
     * Whole conversation (all nested replies, flat).
     *
     * @param  list<string>|string|null  $fields
     * @return Page<Reply>
     */
    public function conversation(string $mediaId, ?bool $reverse = null, ?string $before = null, ?string $after = null, array|string|null $fields = null, ?int $limit = null): Page
    {
        return $this->page("$mediaId/conversation", $reverse, $before, $after, $fields, $limit);
    }

    /**
     * Replies written by the token owner (GET /{user-id}/replies).
     *
     * @param  list<string>|string|null  $fields
     * @return Page<Reply>
     */
    public function mine(?string $before = null, ?string $after = null, array|string|null $fields = null, ?int $limit = null): Page
    {
        return $this->page("{$this->userId}/replies", null, $before, $after, $fields, $limit);
    }

    /**
     * Hide a top level reply (including its nested replies).
     */
    public function hide(string $replyId): bool
    {
        return $this->success($this->request->post("$replyId/manage_reply", ['hide' => true]));
    }

    public function unhide(string $replyId): bool
    {
        return $this->success($this->request->post("$replyId/manage_reply", ['hide' => false]));
    }

    /**
     * Replies waiting for approval.
     *
     * @param  list<string>|string|null  $fields
     * @return Page<Reply>
     */
    public function pending(string $mediaId, ?ReplyApprovalStatus $status = null, ?bool $reverse = null, ?string $before = null, ?string $after = null, array|string|null $fields = null): Page
    {
        $response = $this->request->get("$mediaId/pending_replies", [
            'fields' => $this->fields($fields, [...self::DEFAULT_FIELDS, 'reply_approval_status']),
            'approval_status' => $status,
            'reverse' => $reverse,
            'before' => $before,
            'after' => $after,
        ]);

        return Page::fromResponse($response, Reply::fromArray(...));
    }

    public function approve(string $replyId): bool
    {
        return $this->success($this->request->post("$replyId/manage_pending_reply", ['approve' => true]));
    }

    public function ignore(string $replyId): bool
    {
        return $this->success($this->request->post("$replyId/manage_pending_reply", ['approve' => false]));
    }

    /**
     * @param  list<string>|string|null  $fields
     * @return Page<Reply>
     */
    private function page(string $path, ?bool $reverse, ?string $before, ?string $after, array|string|null $fields, ?int $limit): Page
    {
        $response = $this->request->get($path, [
            'fields' => $this->fields($fields, self::DEFAULT_FIELDS),
            'reverse' => $reverse,
            'before' => $before,
            'after' => $after,
            'limit' => $limit,
        ]);

        return Page::fromResponse($response, Reply::fromArray(...));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function success(array $response): bool
    {
        return (bool) ($response['success'] ?? false);
    }
}
