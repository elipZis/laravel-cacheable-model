<?php

namespace ElipZis\Cacheable\Models\Traits;

use ElipZis\Cacheable\Database\Query\CacheableQueryBuilder;
use Illuminate\Database\Connection;

/**
 * Make a model support general query caching
 */
trait Cacheable
{
    /**
     * @return CacheableQueryBuilder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        return new CacheableQueryBuilder(
            $conn,
            $conn->getQueryGrammar(),
            $conn->getPostProcessor(),
            static::class,
            $this->getCacheableProperties()
        );
    }

    /**
     * @return array
     */
    public function getCacheableProperties(): array
    {
        return [
            'ttl' => config('cacheable.ttl', 300),
            'prefix' => config('cacheable.prefix', 'cacheable'),
            'identifier' => config('cacheable.identifier', 'id'),
            'logging' => [
                'enabled' => config('cacheable.logging.enabled', false),
                'level' => config('cacheable.logging.level', 'debug', ),
            ],
        ];
    }

    /**
     * Get the database connection for the model.
     *
     * @return Connection
     */
    abstract public function getConnection();
}
