<?php

use App\Services\PspFeedService;

beforeEach(function (): void {
    $this->feeds = app(PspFeedService::class);
});

it('always has at least one part, even with nothing to serve', function (): void {
    expect($this->feeds->partCount(0))->toBe(1)
        ->and($this->feeds->partCount(1))->toBe(1)
        ->and($this->feeds->partCount(100))->toBe(1)
        ->and($this->feeds->partCount(101))->toBe(2)
        ->and($this->feeds->partCount(250))->toBe(3);
});

it('follows the configured limit rather than the one hundred of the device', function (): void {
    config(['navidrome.feed_page_size' => 25]);
    app()->forgetInstance(PspFeedService::class);

    $feeds = app(PspFeedService::class);

    expect($feeds->partCount(100))->toBe(4)
        ->and($feeds->partCount(101))->toBe(5);
});

it('treats a limit of zero as one, rather than dividing by it', function (): void {
    config(['navidrome.feed_page_size' => 0]);
    app()->forgetInstance(PspFeedService::class);

    $feeds = app(PspFeedService::class);

    expect($feeds->partCount(5))->toBe(5);
});

it('counts how many items each part holds', function (): void {
    expect($this->feeds->partSize(250, 1))->toBe(100)
        ->and($this->feeds->partSize(250, 2))->toBe(100)
        ->and($this->feeds->partSize(250, 3))->toBe(50)
        ->and($this->feeds->partSize(0, 1))->toBe(0);
});
