<?php

declare(strict_types=1);

use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\Publishing\CarouselItem;
use KaiHempel\Threads\Publishing\PostDraft;
use KaiHempel\Threads\Publishing\PostValidator;
use KaiHempel\Threads\Publishing\TextAttachment;
use KaiHempel\Threads\Publishing\Violation;

function rules(PostDraft $draft): array
{
    return array_map(fn (Violation $v): string => $v->rule, (new PostValidator)->violations($draft));
}

it('accepts a valid text post', function () {
    expect(rules(new PostDraft(text: 'Hallo Threads')))->toBe([]);
});

it('requires text for text posts', function () {
    expect(rules(new PostDraft))->toBe(['text_required'])
        ->and(rules(new PostDraft(text: '   ')))->toBe(['text_required']);
});

it('limits the text to 500 characters counted as unicode characters', function () {
    expect(rules(new PostDraft(text: str_repeat('ä', 500))))->toBe([])
        ->and(rules(new PostDraft(text: str_repeat('😀', 500))))->toBe([])
        ->and(rules(new PostDraft(text: str_repeat('a', 501))))->toBe(['text_too_long']);
});

it('requires https urls for images and videos', function () {
    expect(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg')))->toBe([])
        ->and(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'http://cdn.example.com/a.jpg')))->toBe(['url_https'])
        ->and(rules(new PostDraft(mediaType: MediaType::Video, videoUrl: 'not a url')))->toBe(['url_https'])
        ->and(rules(new PostDraft(mediaType: MediaType::Video)))->toBe(['url_required']);
});

it('validates the alt text', function () {
    expect(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', altText: str_repeat('a', 1000))))->toBe([])
        ->and(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', altText: str_repeat('a', 1001))))->toBe(['alt_text_too_long'])
        ->and(rules(new PostDraft(text: 'Text', altText: 'alt')))->toBe(['alt_text_requires_media']);
});

it('validates carousels', function () {
    $item = CarouselItem::image('https://cdn.example.com/a.jpg');

    expect(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: [$item, CarouselItem::video('https://cdn.example.com/b.mp4')])))->toBe([])
        ->and(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: [$item])))->toBe(['carousel_item_count'])
        ->and(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: array_fill(0, 21, $item))))->toBe(['carousel_item_count'])
        ->and(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: array_fill(0, 20, $item))))->toBe([])
        ->and(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: [$item, CarouselItem::image('ftp://x')])))->toBe(['url_https'])
        ->and(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: [$item, new CarouselItem(MediaType::Text, 'https://x.de')])))->toBe(['carousel_item_type'])
        ->and(rules(new PostDraft(mediaType: MediaType::Carousel, carousel: [$item, CarouselItem::image('https://cdn.example.com/b.jpg', str_repeat('a', 1001))])))->toBe(['alt_text_too_long']);
});

it('validates polls', function () {
    expect(rules(new PostDraft(text: 'Frage?', pollOptions: ['Ja', 'Nein'])))->toBe([])
        ->and(rules(new PostDraft(pollOptions: ['Ja', 'Nein', 'Vielleicht', 'Egal'])))->toBe([])
        ->and(rules(new PostDraft(text: 'Frage?', pollOptions: ['Ja'])))->toBe(['poll_option_count'])
        ->and(rules(new PostDraft(text: 'Frage?', pollOptions: ['a', 'b', 'c', 'd', 'e'])))->toBe(['poll_option_count'])
        ->and(rules(new PostDraft(text: 'Frage?', pollOptions: ['Ja', str_repeat('x', 26)])))->toBe(['poll_option_length'])
        ->and(rules(new PostDraft(text: 'Frage?', pollOptions: ['Ja', ' '])))->toBe(['poll_option_length'])
        ->and(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', pollOptions: ['Ja', 'Nein'])))->toBe(['poll_requires_text']);
});

it('limits the number of links including the link attachment', function () {
    $five = 'https://a.de https://b.de https://c.de https://d.de https://e.de';

    expect(rules(new PostDraft(text: $five)))->toBe([])
        ->and(rules(new PostDraft(text: $five, linkAttachment: 'https://a.de')))->toBe([])
        ->and(rules(new PostDraft(text: $five, linkAttachment: 'https://f.de')))->toBe(['link_limit'])
        ->and(rules(new PostDraft(text: $five.' http://f.de')))->toBe(['link_limit']);
});

it('counts links', function () {
    $validator = new PostValidator;

    expect($validator->countLinks(null))->toBe(0)
        ->and($validator->countLinks('Siehe https://example.com/a, und http://example.org.'))->toBe(2)
        ->and($validator->countLinks('https://example.com/a', 'https://example.com/a'))->toBe(1)
        ->and($validator->countLinks('kein Link', 'https://example.com'))->toBe(1);
});

it('allows link attachments only on text posts', function () {
    expect(rules(new PostDraft(text: 'Text', linkAttachment: 'https://example.com')))->toBe([])
        ->and(rules(new PostDraft(text: 'Text', linkAttachment: 'no url')))->toBe(['url_invalid'])
        ->and(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', linkAttachment: 'https://example.com')))->toBe(['link_attachment_requires_text']);
});

it('allows auto publish only for text posts', function () {
    expect(rules(new PostDraft(text: 'Text', autoPublish: true)))->toBe([])
        ->and(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', autoPublish: true)))->toBe(['auto_publish_requires_text']);
});

it('validates the topic tag', function () {
    expect(rules(new PostDraft(text: 'Text', topicTag: 'laravel')))->toBe([])
        ->and(rules(new PostDraft(text: 'Text', topicTag: 'laravel.php')))->toBe(['topic_tag_invalid'])
        ->and(rules(new PostDraft(text: 'Text', topicTag: 'a&b')))->toBe(['topic_tag_invalid'])
        ->and(rules(new PostDraft(text: 'Text', topicTag: '')))->toBe(['topic_tag_invalid'])
        ->and(rules(new PostDraft(text: 'Text', topicTag: str_repeat('a', 51))))->toBe(['topic_tag_invalid']);
});

it('validates country codes', function () {
    expect(rules(new PostDraft(text: 'Text', allowlistedCountryCodes: ['DE', 'AT'])))->toBe([])
        ->and(rules(new PostDraft(text: 'Text', allowlistedCountryCodes: ['DEU'])))->toBe(['country_code_invalid']);
});

it('validates spoilers', function () {
    expect(rules(new PostDraft(text: 'Hallo Welt', spoilers: [['offset' => 6, 'length' => 4]])))->toBe([])
        ->and(rules(new PostDraft(text: 'Hallo Welt', spoilers: [['offset' => 6, 'length' => 5]])))->toBe(['spoiler_range'])
        ->and(rules(new PostDraft(text: 'Hallo Welt', spoilers: [['offset' => -1, 'length' => 2]])))->toBe(['spoiler_range'])
        ->and(rules(new PostDraft(text: 'Hallo Welt', spoilers: array_fill(0, 11, ['offset' => 0, 'length' => 1]))))->toBe(['spoiler_limit']);
});

it('validates text attachments', function () {
    expect(rules(new PostDraft(text: 'Teaser', textAttachment: new TextAttachment(str_repeat('a', 10000)))))->toBe([])
        ->and(rules(new PostDraft(textAttachment: new TextAttachment('Nur Anhang'))))->toBe([])
        ->and(rules(new PostDraft(text: 'Teaser', textAttachment: new TextAttachment(str_repeat('a', 10001)))))->toBe(['text_attachment_too_long'])
        ->and(rules(new PostDraft(text: 'Teaser', textAttachment: new TextAttachment('a', 'kein link'))))->toBe(['url_invalid'])
        ->and(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', textAttachment: new TextAttachment('a'))))->toBe(['text_attachment_requires_text']);
});

it('reports media conflicts', function () {
    expect(rules(new PostDraft(mediaType: MediaType::Image, imageUrl: 'https://cdn.example.com/a.jpg', conflicts: ['image, video'])))->toBe(['media_conflict']);
});

it('throws a validation exception with field, rule and grouped errors', function () {
    try {
        (new PostValidator)->validate(new PostDraft(text: str_repeat('a', 501), topicTag: 'a.b'));
        $this->fail('Expected exception');
    } catch (ValidationException $e) {
        expect($e->field())->toBe('text')
            ->and($e->rule())->toBe('text_too_long')
            ->and($e->getMessage())->toBe('The text may not be longer than 500 characters (currently 501).')
            ->and($e->violations)->toHaveCount(2)
            ->and($e->violations[0]->parameters)->toBe(['max' => 500, 'length' => 501])
            ->and(array_keys($e->errors()))->toBe(['text', 'topic_tag'])
            ->and($e->isRetryable())->toBeFalse();
    }
});

it('translates messages through the laravel translator', function () {
    app()->setLocale('de');

    $violations = (new PostValidator(app('translator')))->violations(new PostDraft(text: str_repeat('a', 501)));

    expect($violations[0]->message)->toBe('Der Text darf höchstens 500 Zeichen lang sein (aktuell 501).');
});

it('falls back to english messages without translator', function () {
    $violations = (new PostValidator)->violations(new PostDraft(mediaType: MediaType::Image, imageUrl: 'http://x.de/a.jpg'));

    expect($violations[0]->message)->toBe('The URL (image_url) must be a valid, publicly reachable https URL.');
});

it('has the same translation keys in all languages', function () {
    $en = require __DIR__.'/../../lang/en/validation.php';
    $de = require __DIR__.'/../../lang/de/validation.php';

    expect(array_keys($de))->toEqualCanonicalizing(array_keys($en));
});
