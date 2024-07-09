<?php


use ElipZis\Cacheable\Tests\Mock\CacheableModel;

dataset('model', [
    [
        function () {
            return CacheableModel::query()->create([
                'key' => 'key',
                'value' => 'value',
            ]);
        }
    ]
]);

dataset('models', [
    [
        function () {
            return CacheableModel::query()->create([
                'key' => 'key1',
                'value' => 'value1',
            ]);
        }
    ],
    [
        function () {
            return CacheableModel::query()->create([
                'key' => 'key2',
                'value' => 'value2',
            ]);
        }
    ]
]);
