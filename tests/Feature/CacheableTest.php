<?php

use ElipZis\Cacheable\Tests\Mock\CacheableModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test table to work on
    Schema::create('cacheable_models', function (Blueprint $table) {
        $table->id();

        $table->string('key');
        $table->text('value')->nullable();

        $table->timestamps();
    });
});

test('select model and have it cached', function (CacheableModel $model) {
    $cacheModel = CacheableModel::query()->where('key', 'key')->first();

    expect(Cache::tags([CacheableModel::class])->has("select * from \"cacheable_models\" where \"key\" = ? limit 1_key"))->toBeTrue()
        ->and($cacheModel)->toBeInstanceOf(CacheableModel::class)
        ->and($cacheModel->id)->toBe($model->id);
})->with('model');

test('select within sub-query and have it cached', function (CacheableModel $model) {
    $cacheModel = CacheableModel::query()->whereIn('id', CacheableModel::query()->select('id'))->first();

    expect(Cache::tags([
        CacheableModel::class,
        CacheableModel::class . '#select "id" from "cacheable_models"',
    ])->has("select * from \"cacheable_models\" where \"id\" in (select \"id\" from \"cacheable_models\") limit 1"))->toBeTrue()
        ->and($cacheModel)->toBeInstanceOf(CacheableModel::class)
        ->and($cacheModel->id)->toBe($model->id);
})->with('model');

test('update model and have cache flushed', function (CacheableModel $model) {
    $cacheModel = CacheableModel::query()->where('key', 'key')->first();
    expect(Cache::tags([CacheableModel::class])->has("select * from \"cacheable_models\" where \"key\" = ? limit 1_key"))->toBeTrue();

    $cacheModel->update([
        'key' => 'anotherkey'
    ]);
    expect(Cache::tags([CacheableModel::class])->has("select * from \"cacheable_models\" where \"key\" = ? limit 1_key"))->toBeFalse();
})->with('model');
