<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

use Illuminate\Contracts\Translation\Translator;
use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\ThreadsCapabilities;

/**
 * Local checks before any API call. Limits come from ThreadsCapabilities.
 */
final class PostValidator
{
    private const string URL_PATTERN = '~\bhttps?://[^\s<>"]+~iu';

    /** @var array<string, string>|null */
    private static ?array $fallbackMessages = null;

    public function __construct(private readonly ?Translator $translator = null) {}

    /**
     * @throws ValidationException
     */
    public function validate(PostDraft $draft): void
    {
        $violations = $this->violations($draft);

        if ($violations !== []) {
            throw ValidationException::withViolations($violations);
        }
    }

    /**
     * @return list<Violation>
     */
    public function violations(PostDraft $draft): array
    {
        $v = [];

        foreach ($draft->conflicts as $conflict) {
            $v[] = $this->violation('media', 'media_conflict', ['conflict' => $conflict]);
        }

        $text = $draft->text;
        $textLength = $text === null ? 0 : mb_strlen($text, 'UTF-8');

        if ($draft->mediaType === MediaType::Text && ($text === null || trim($text) === '') && $draft->textAttachment === null && $draft->pollOptions === []) {
            $v[] = $this->violation('text', 'text_required');
        }

        if ($textLength > ThreadsCapabilities::MAX_TEXT_LENGTH) {
            $v[] = $this->violation('text', 'text_too_long', ['max' => ThreadsCapabilities::MAX_TEXT_LENGTH, 'length' => $textLength]);
        }

        // media
        if ($draft->mediaType === MediaType::Image) {
            $this->checkUrl($v, 'image_url', $draft->imageUrl);
        }

        if ($draft->mediaType === MediaType::Video) {
            $this->checkUrl($v, 'video_url', $draft->videoUrl);
        }

        if ($draft->altText !== null) {
            if (! $draft->mediaType->hasMedia()) {
                $v[] = $this->violation('alt_text', 'alt_text_requires_media');
            } elseif (mb_strlen($draft->altText, 'UTF-8') > ThreadsCapabilities::MAX_ALT_TEXT_LENGTH) {
                $v[] = $this->violation('alt_text', 'alt_text_too_long', ['max' => ThreadsCapabilities::MAX_ALT_TEXT_LENGTH]);
            }
        }

        if ($draft->mediaType === MediaType::Carousel) {
            $this->checkCarousel($v, $draft);
        }

        // poll
        if ($draft->pollOptions !== []) {
            $this->checkPoll($v, $draft);
        }

        // links
        if ($draft->linkAttachment !== null) {
            if ($draft->mediaType !== MediaType::Text) {
                $v[] = $this->violation('link_attachment', 'link_attachment_requires_text');
            }
            $this->checkUrl($v, 'link_attachment', $draft->linkAttachment, httpsOnly: false);
        }

        $links = $this->countLinks($text, $draft->linkAttachment);
        if ($links > ThreadsCapabilities::MAX_LINKS) {
            $v[] = $this->violation('text', 'link_limit', ['max' => ThreadsCapabilities::MAX_LINKS, 'count' => $links]);
        }

        if ($draft->autoPublish && $draft->mediaType !== MediaType::Text) {
            $v[] = $this->violation('auto_publish_text', 'auto_publish_requires_text');
        }

        if ($draft->topicTag !== null) {
            $tagLength = mb_strlen($draft->topicTag, 'UTF-8');
            if ($tagLength < 1 || $tagLength > ThreadsCapabilities::MAX_TOPIC_TAG_LENGTH || preg_match('/[.&]/u', $draft->topicTag) === 1) {
                $v[] = $this->violation('topic_tag', 'topic_tag_invalid', ['max' => ThreadsCapabilities::MAX_TOPIC_TAG_LENGTH]);
            }
        }

        foreach ($draft->allowlistedCountryCodes as $code) {
            if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
                $v[] = $this->violation('allowlisted_country_codes', 'country_code_invalid', ['code' => $code]);
            }
        }

        $this->checkSpoilers($v, $draft, $textLength);

        if ($draft->textAttachment !== null) {
            if ($draft->mediaType !== MediaType::Text) {
                $v[] = $this->violation('text_attachment', 'text_attachment_requires_text');
            }
            if (mb_strlen($draft->textAttachment->plaintext, 'UTF-8') > ThreadsCapabilities::MAX_TEXT_ATTACHMENT_LENGTH) {
                $v[] = $this->violation('text_attachment', 'text_attachment_too_long', ['max' => ThreadsCapabilities::MAX_TEXT_ATTACHMENT_LENGTH]);
            }
            if ($draft->textAttachment->linkUrl !== null) {
                $this->checkUrl($v, 'text_attachment.link_attachment_url', $draft->textAttachment->linkUrl, httpsOnly: false);
            }
        }

        return $v;
    }

    /**
     * Counts the links in the text, plus the link attachment unless it is also part of the text.
     */
    public function countLinks(?string $text, ?string $linkAttachment = null): int
    {
        $found = [];

        if ($text !== null && preg_match_all(self::URL_PATTERN, $text, $matches) > 0) {
            $found = array_map(static fn (string $url): string => rtrim($url, '.,;:!?)'), $matches[0]);
        }

        if ($linkAttachment !== null && ! in_array($linkAttachment, $found, true)) {
            $found[] = $linkAttachment;
        }

        return count($found);
    }

    /**
     * @param  list<Violation>  $v
     */
    private function checkCarousel(array &$v, PostDraft $draft): void
    {
        $count = count($draft->carousel);

        if ($count < ThreadsCapabilities::MIN_CAROUSEL_ITEMS || $count > ThreadsCapabilities::MAX_CAROUSEL_ITEMS) {
            $v[] = $this->violation('carousel', 'carousel_item_count', [
                'min' => ThreadsCapabilities::MIN_CAROUSEL_ITEMS,
                'max' => ThreadsCapabilities::MAX_CAROUSEL_ITEMS,
                'count' => $count,
            ]);
        }

        foreach ($draft->carousel as $index => $item) {
            if (! $item->mediaType->hasMedia()) {
                $v[] = $this->violation("carousel.$index", 'carousel_item_type');

                continue;
            }

            $this->checkUrl($v, "carousel.$index", $item->url);

            if ($item->altText !== null && mb_strlen($item->altText, 'UTF-8') > ThreadsCapabilities::MAX_ALT_TEXT_LENGTH) {
                $v[] = $this->violation("carousel.$index.alt_text", 'alt_text_too_long', ['max' => ThreadsCapabilities::MAX_ALT_TEXT_LENGTH]);
            }
        }
    }

    /**
     * @param  list<Violation>  $v
     */
    private function checkPoll(array &$v, PostDraft $draft): void
    {
        if ($draft->mediaType !== MediaType::Text) {
            $v[] = $this->violation('poll', 'poll_requires_text');
        }

        $count = count($draft->pollOptions);
        if ($count < ThreadsCapabilities::MIN_POLL_OPTIONS || $count > ThreadsCapabilities::MAX_POLL_OPTIONS) {
            $v[] = $this->violation('poll', 'poll_option_count', [
                'min' => ThreadsCapabilities::MIN_POLL_OPTIONS,
                'max' => ThreadsCapabilities::MAX_POLL_OPTIONS,
                'count' => $count,
            ]);
        }

        foreach ($draft->pollOptions as $index => $option) {
            $length = mb_strlen(trim($option), 'UTF-8');
            if ($length < ThreadsCapabilities::MIN_POLL_OPTION_LENGTH || $length > ThreadsCapabilities::MAX_POLL_OPTION_LENGTH) {
                $v[] = $this->violation("poll.$index", 'poll_option_length', [
                    'min' => ThreadsCapabilities::MIN_POLL_OPTION_LENGTH,
                    'max' => ThreadsCapabilities::MAX_POLL_OPTION_LENGTH,
                ]);
            }
        }
    }

    /**
     * @param  list<Violation>  $v
     */
    private function checkSpoilers(array &$v, PostDraft $draft, int $textLength): void
    {
        if ($draft->spoilers === []) {
            return;
        }

        if (count($draft->spoilers) > ThreadsCapabilities::MAX_SPOILERS) {
            $v[] = $this->violation('spoilers', 'spoiler_limit', ['max' => ThreadsCapabilities::MAX_SPOILERS]);
        }

        foreach ($draft->spoilers as $index => $spoiler) {
            if ($spoiler['offset'] < 0 || $spoiler['length'] < 1 || $textLength < $spoiler['offset'] + $spoiler['length']) {
                $v[] = $this->violation("spoilers.$index", 'spoiler_range');
            }
        }
    }

    /**
     * @param  list<Violation>  $v
     */
    private function checkUrl(array &$v, string $field, ?string $url, bool $httpsOnly = true): void
    {
        if ($url === null || trim($url) === '') {
            $v[] = $this->violation($field, 'url_required');

            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $valid = filter_var($url, FILTER_VALIDATE_URL) !== false
            && ($httpsOnly ? $scheme === 'https' : in_array($scheme, ['http', 'https'], true));

        if (! $valid) {
            $v[] = $this->violation($field, $httpsOnly ? 'url_https' : 'url_invalid');
        }
    }

    /**
     * @param  array<string, scalar>  $parameters
     */
    private function violation(string $field, string $rule, array $parameters = []): Violation
    {
        return new Violation($field, $rule, $this->message($rule, $parameters + ['field' => $field]), $parameters);
    }

    /**
     * @param  array<string, scalar>  $parameters
     */
    private function message(string $rule, array $parameters): string
    {
        $key = 'threads::validation.'.$rule;

        if ($this->translator !== null) {
            $message = $this->translator->get($key, $parameters);

            if (is_string($message) && $message !== $key) {
                return $message;
            }
        }

        self::$fallbackMessages ??= require dirname(__DIR__, 2).'/lang/en/validation.php';
        $message = self::$fallbackMessages[$rule] ?? $rule;

        foreach ($parameters as $name => $value) {
            $message = str_replace(':'.$name, (string) $value, $message);
        }

        return $message;
    }
}
