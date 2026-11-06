<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use KaiHempel\Threads\Data\Profile;

final class ProfileResource extends Resource
{
    public const array DEFAULT_FIELDS = ['id', 'username', 'name', 'threads_profile_picture_url', 'threads_biography', 'is_verified'];

    /**
     * Profile of the token owner (GET /me).
     *
     * @param  list<string>|string|null  $fields
     */
    public function me(array|string|null $fields = null): Profile
    {
        return $this->get('me', $fields);
    }

    /**
     * Profile of a Threads user id the token has access to.
     *
     * @param  list<string>|string|null  $fields
     */
    public function get(string $userId, array|string|null $fields = null): Profile
    {
        return Profile::fromArray($this->request->get($userId, [
            'fields' => $this->fields($fields, self::DEFAULT_FIELDS),
        ]));
    }
}
