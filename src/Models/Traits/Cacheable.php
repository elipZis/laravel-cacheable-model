<?php

namespace ElipZis\Cacheable\Models\Traits;

use ElipZis\Cacheable\Database\Query\CacheableQueryBuilder;
use Illuminate\Database\Connection;

/**
 * If a model supports general repository caching
 */
trait Cacheable {

    /**
     * @return CacheableQueryBuilder
     */
    protected function newBaseQueryBuilder() {
        return new CacheableQueryBuilder(
            $this->getConnection(),
            static::class,
            $this->getCacheableProperties()
        );
    }

    /**
     * @return array
     */
    public function getCacheableProperties(): array {
        return [
            'ttl'        => 300,
            'prefix'     => 'cacheable',
            'identifier' => 'id',
            'logLevel'   => 'error'
        ];
    }

    /**
     * Get the database connection for the model.
     *
     * @return Connection
     */
    abstract public function getConnection();
}
