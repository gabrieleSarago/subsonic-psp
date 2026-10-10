<?php

use App\Repositories\Contracts\RadioStationRepository;
use App\Services\Radio\RadioListStore;
use Tests\Support\Radios;

it('reads the stations in file order', function (): void {
    Radios::file([
        ['id' => 'a', 'name' => 'Alfa', 'streamUrl' => 'http://a.test/1'],
        ['id' => 'b', 'name' => 'Beta', 'streamUrl' => 'http://b.test/2'],
    ]);

    $stations = app(RadioStationRepository::class)->all();

    expect($stations)->toHaveCount(2)
        ->and($stations[0]->id)->toBe('a')
        ->and($stations[1]->id)->toBe('b');
});

it('finds a station by identifier', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://a.test/1']]);

    $repository = app(RadioStationRepository::class);

    expect($repository->find('rmc')?->name)->toBe('RMC')
        ->and($repository->find('assente'))->toBeNull();
});

it('skips a row without a stream url', function (): void {
    Radios::file([
        ['id' => 'a', 'name' => 'Alfa', 'streamUrl' => ''],
        ['id' => 'b', 'name' => 'Beta', 'streamUrl' => 'http://b.test/2'],
    ]);

    expect(app(RadioStationRepository::class)->all())->toHaveCount(1);
});

it('returns nothing when the file is missing', function (): void {
    config(['radios.file' => sys_get_temp_dir().'/psp-radios-missing-'.uniqid().'.json']);
    app()->forgetInstance(RadioListStore::class);

    expect(app(RadioStationRepository::class)->all())->toBeEmpty();
});

it('rereads the file once the cache is dropped', function (): void {
    Radios::file([['id' => 'a', 'name' => 'Alfa', 'streamUrl' => 'http://a.test/1']]);

    $repository = app(RadioStationRepository::class);

    expect($repository->all())->toHaveCount(1);

    // The probe rewrites the file behind the repository and drops the cache, so
    // the next read must find the new list rather than the one it cached.
    file_put_contents(Radios::$path, json_encode([
        ['id' => 'a', 'name' => 'Alfa', 'streamUrl' => 'http://a.test/1'],
        ['id' => 'b', 'name' => 'Beta', 'streamUrl' => 'http://b.test/2'],
    ]));

    expect($repository->all())->toHaveCount(1);

    $repository->forget();

    expect($repository->all())->toHaveCount(2);
});
