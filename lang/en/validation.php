<?php

declare(strict_types=1);

return [
    'media_conflict' => 'Only one media type per post is possible (:conflict).',
    'text_required' => 'The text is required for text posts.',
    'text_too_long' => 'The text may not be longer than :max characters (currently :length).',
    'url_required' => 'The URL (:field) is required.',
    'url_https' => 'The URL (:field) must be a valid, publicly reachable https URL.',
    'url_invalid' => 'The URL (:field) is not a valid URL.',
    'alt_text_requires_media' => 'An alt text is only possible for image and video posts.',
    'alt_text_too_long' => 'The alt text may not be longer than :max characters.',
    'carousel_item_count' => 'A carousel needs between :min and :max items (currently :count).',
    'carousel_item_type' => 'Carousel items must be images or videos.',
    'poll_requires_text' => 'Polls are only possible for text posts.',
    'poll_option_count' => 'A poll needs between :min and :max options (currently :count).',
    'poll_option_length' => 'Each poll option must be between :min and :max characters long.',
    'link_attachment_requires_text' => 'A link attachment is only possible for text posts.',
    'link_limit' => 'A post may contain at most :max links (currently :count).',
    'auto_publish_requires_text' => 'Auto publishing is only possible for text posts.',
    'topic_tag_invalid' => 'The topic tag must be 1 to :max characters long and may not contain "." or "&".',
    'country_code_invalid' => 'The country code ":code" is not a valid ISO 3166-1 alpha-2 code.',
    'spoiler_limit' => 'A post may contain at most :max spoilers.',
    'spoiler_range' => 'The spoiler range lies outside of the text.',
    'text_attachment_requires_text' => 'A text attachment is only possible for text posts.',
    'text_attachment_too_long' => 'The text attachment may not be longer than :max characters.',
];
