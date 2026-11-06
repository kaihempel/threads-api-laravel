<?php

declare(strict_types=1);

use KaiHempel\Threads\Data\Container;
use KaiHempel\Threads\Data\Insights;
use KaiHempel\Threads\Data\Location;
use KaiHempel\Threads\Data\Page;
use KaiHempel\Threads\Data\Post;
use KaiHempel\Threads\Data\Profile;
use KaiHempel\Threads\Data\PublishingLimit;
use KaiHempel\Threads\Data\Reply;
use KaiHempel\Threads\Enums\ContainerError;
use KaiHempel\Threads\Enums\ContainerStatus;
use KaiHempel\Threads\Enums\HideStatus;
use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Enums\PostMediaType;
use KaiHempel\Threads\Enums\ReplyApprovalStatus;
use KaiHempel\Threads\Testing\FakeThreads;

it('maps containers', function () {
    $container = Container::fromArray(FakeThreads::fixture('container_error'));

    expect($container->id)->toBe('17890000000000001')
        ->and($container->status)->toBe(ContainerStatus::Error)
        ->and($container->error)->toBe(ContainerError::FailedDownloadingVideo)
        ->and($container->isReady())->toBeFalse()
        ->and(Container::fromArray(['id' => '1', 'status' => 'finished'])->isReady())->toBeTrue()
        ->and(Container::fromArray(['id' => '1', 'status' => 'SOMETHING_NEW'])->status)->toBe(ContainerStatus::Error)
        ->and(Container::fromArray(['id' => '1'])->status)->toBeNull();
});

it('maps container error messages', function () {
    expect(ContainerError::fromApi('INVALID_ASPEC_RATIO'))->toBe(ContainerError::InvalidAspectRatio)
        ->and(ContainerError::fromApi('INVALID_ASPECT_RATIO'))->toBe(ContainerError::InvalidAspectRatio)
        ->and(ContainerError::fromApi('something else'))->toBe(ContainerError::Unknown)
        ->and(ContainerError::fromApi(null))->toBeNull();
});

it('knows container status semantics', function () {
    expect(ContainerStatus::Error->isFailed())->toBeTrue()
        ->and(ContainerStatus::Expired->isFailed())->toBeTrue()
        ->and(ContainerStatus::Published->isFailed())->toBeFalse()
        ->and(ContainerStatus::Published->isTerminal())->toBeTrue()
        ->and(ContainerStatus::InProgress->isTerminal())->toBeFalse()
        ->and(ContainerStatus::Finished->isPublishable())->toBeTrue()
        ->and(MediaType::Image->hasMedia())->toBeTrue()
        ->and(MediaType::Carousel->hasMedia())->toBeFalse();
});

it('maps profiles', function () {
    $profile = Profile::fromArray(FakeThreads::fixture('profile'));

    expect($profile->id)->toBe('1234567890')
        ->and($profile->username)->toBe('threads_tester')
        ->and($profile->name)->toBe('Threads Tester')
        ->and($profile->profilePictureUrl)->toBe('https://scontent.cdninstagram.com/profile.jpg')
        ->and($profile->biography)->toBe('Hello from the Threads API')
        ->and($profile->isVerified)->toBeFalse();
});

it('maps posts with children', function () {
    $post = Post::fromArray(FakeThreads::fixture('media'));

    expect($post->mediaType)->toBe(PostMediaType::CarouselAlbum)
        ->and($post->text)->toBe('Release-Notes')
        ->and($post->timestamp->toIso8601String())->toBe('2026-11-03T10:00:00+00:00')
        ->and($post->isQuotePost)->toBeFalse()
        ->and($post->children)->toHaveCount(2)
        ->and($post->children[1]->mediaType)->toBe(PostMediaType::Video)
        ->and(PostMediaType::fromApi('NEW_TYPE'))->toBe(PostMediaType::Unknown)
        ->and(PostMediaType::fromApi(null))->toBeNull()
        ->and(Post::fromArray(['id' => '1', 'timestamp' => 'not a date'])->timestamp)->toBeNull();
});

it('maps paginated responses', function () {
    $page = Page::fromResponse(FakeThreads::fixture('media_list'), Post::fromArray(...));

    expect($page)->toHaveCount(2)
        ->and($page->items->first()->topicTag)->toBe('laravel')
        ->and($page->before)->toBe('BEFORE_CURSOR')
        ->and($page->after)->toBe('AFTER_CURSOR')
        ->and($page->hasMore())->toBeTrue()
        ->and($page->isEmpty())->toBeFalse()
        ->and(iterator_to_array($page))->toHaveCount(2)
        ->and(Page::fromResponse([], Post::fromArray(...))->isEmpty())->toBeTrue();
});

it('maps replies', function () {
    $page = Page::fromResponse(FakeThreads::fixture('replies'), Reply::fromArray(...));
    $first = $page->items[0];

    expect($first->rootPostId)->toBe('18050000000000001')
        ->and($first->repliedToId)->toBe('18050000000000001')
        ->and($first->hasReplies)->toBeTrue()
        ->and($first->hideStatus)->toBe(HideStatus::NotHushed)
        ->and($first->isHidden())->toBeFalse()
        ->and($page->items[1]->isHidden())->toBeTrue()
        ->and(Reply::fromArray(FakeThreads::fixture('pending_replies')['data'][0])->approvalStatus)->toBe(ReplyApprovalStatus::Pending)
        ->and(HideStatus::fromApi('whatever'))->toBe(HideStatus::Unknown);
});

it('maps publishing limits', function () {
    $limit = PublishingLimit::fromArray(FakeThreads::fixture('publishing_limit'));

    expect($limit->quotaUsage)->toBe(4)
        ->and($limit->quotaTotal)->toBe(250)
        ->and($limit->quotaDuration)->toBe(86400)
        ->and($limit->remaining())->toBe(246)
        ->and($limit->isExhausted())->toBeFalse()
        ->and($limit->replyRemaining())->toBe(999)
        ->and($limit->isReplyQuotaExhausted())->toBeFalse()
        ->and($limit->deleteRemaining())->toBe(100)
        ->and($limit->isDeleteQuotaExhausted())->toBeFalse()
        ->and($limit->locationSearchQuotaTotal)->toBe(500);

    $exhausted = PublishingLimit::fromArray(FakeThreads::fixture('publishing_limit_exhausted'));

    expect($exhausted->isExhausted())->toBeTrue()
        ->and($exhausted->remaining())->toBe(0)
        ->and($exhausted->isReplyQuotaExhausted())->toBeTrue()
        ->and($exhausted->deleteRemaining())->toBeNull();
});

it('maps insights (time series and total values)', function () {
    $insights = Insights::fromResponse(FakeThreads::fixture('account_insights'));

    expect($insights)->toHaveCount(3)
        ->and($insights->value('views'))->toBe(250)
        ->and($insights->get('views')->isTimeSeries())->toBeTrue()
        ->and($insights->get('views')->values[1]->endTime->toDateString())->toBe('2026-11-02')
        ->and($insights->value('clicks'))->toBe(12)
        ->and($insights->get('followers_count')->isTimeSeries())->toBeFalse()
        ->and($insights->value('followers_count'))->toBe(321)
        ->and($insights->has('likes'))->toBeFalse()
        ->and($insights->value('likes'))->toBeNull()
        ->and(array_keys(iterator_to_array($insights)))->toBe(['views', 'clicks', 'followers_count'])
        ->and(Insights::fromResponse(['data' => []])->isEmpty())->toBeTrue();
});

it('maps locations', function () {
    $location = Location::fromArray(FakeThreads::fixture('location_search')['data'][0]);

    expect($location->id)->toBe('106078429431815')
        ->and($location->city)->toBe('Berlin')
        ->and($location->country)->toBe('DE')
        ->and($location->latitude)->toBe(52.5167)
        ->and($location->address)->toBeNull();
});
