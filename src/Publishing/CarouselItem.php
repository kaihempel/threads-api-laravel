<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

use KaiHempel\Threads\Enums\MediaType;

final readonly class CarouselItem
{
    public function __construct(
        public MediaType $mediaType,
        public string $url,
        public ?string $altText = null,
    ) {}

    public static function image(string $url, ?string $alt = null): self
    {
        return new self(MediaType::Image, $url, $alt);
    }

    public static function video(string $url, ?string $alt = null): self
    {
        return new self(MediaType::Video, $url, $alt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        return [
            'media_type' => $this->mediaType,
            'is_carousel_item' => true,
            $this->mediaType === MediaType::Video ? 'video_url' : 'image_url' => $this->url,
            'alt_text' => $this->altText,
        ];
    }
}
