<?php

namespace ElipZis\Cacheable\Database\Query;

use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class CacheableQueryBuilder extends Builder
{
    /**
     * @var string
     */
    protected string $modelClass;

    /**
     * @var string
     */
    protected string $modelIdentifier;

    /**
     * @var int|null
     */
    protected mixed $ttl;

    /**
     * @var string|null
     */
    protected mixed $prefix;

    /**
     * @var string|null
     */
    protected mixed $logLevel;

    /**
     * @var array
     */
    protected array $cacheableProperties;

    /**
     * @var bool
     */
    protected bool $enabled = true;

    /**
     * @param Connection $conn
     * @param string $modelClass
     * @param array $cacheableProperties
     */
    public function __construct(Connection $conn, string $modelClass, array $cacheableProperties)
    {
        parent::__construct($conn);
        $this->modelClass = $modelClass;
        $this->cacheableProperties = $cacheableProperties;

        //Prefill some members
        $this->modelIdentifier = $cacheableProperties['identifier'] ?? null;
        $this->ttl = $cacheableProperties['ttl'] ?? null;
        $this->prefix = $cacheableProperties['prefix'] ?? null;
        $this->logLevel = $cacheableProperties['logLevel'] ?? null;
    }

    /**
     * @return $this
     */
    public function withoutCache(): static
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * Check the cache based on the query beforehand and return
     * a cached value or cache it if not already.
     *
     * @return array
     */
    protected function runSelect()
    {
        if (! $this->enabled) {
            return parent::runSelect();
        }

        //Use the query as common cache key
        $cacheKey = $this->getCacheKey();

        //If cached, return
        if (Cache::has($cacheKey)) {
            $this->log("Found cache entry for {$cacheKey}");

            return Cache::get($cacheKey);
        }

        //If not, run normally -> this is what to cache and return
        $retVal = parent::runSelect();

        //Cache before return by class (and optional identifiers)
        $modelClasses = $this->getIdentifiableModelClasses($this->getIdentifiableValue());
        //Are tags supported? Makes life easier!
        if (Cache::getStore() instanceof TaggableStore) {
            $this->log("Using taggable store to cache value of {$cacheKey} for {$this->ttl} ttl");
            Cache::tags($modelClasses)->put($cacheKey, $retVal, $this->ttl);
        } else {
            $this->log("Using cache to store value of {$cacheKey} for {$this->ttl} ttl");
            Cache::put($cacheKey, $retVal, $this->ttl);

            //Cache the query if not, for purging purposes
            foreach ($modelClasses as $modelClass) {
                $modelCacheKey = $this->getModelCacheKey($modelClass);
                $queries = [];
                if (Cache::has($modelCacheKey)) {
                    $queries = Cache::get($modelCacheKey);
                }
                $queries[] = $cacheKey;
                Cache::put($modelCacheKey, $queries);
            }
        }

        return $retVal;
    }

    /**
     * @return string[]
     */
    protected function getIdentifiableModelClasses(mixed $value = null): array
    {
        $retVals = [$this->modelClass];
        if ($value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $retVals[] = "{$this->modelClass}#{$v}";
                }
            } else {
                $retVals[] = "{$this->modelClass}#{$value}";
            }
        }

        return $retVals;
    }

    /**
     * @return mixed|null
     */
    protected function getIdentifiableValue(): mixed
    {
        foreach ($this->wheres as $where) {
            if ($where['column'] === $this->modelIdentifier) {
                return $where['value'];
            }
        }

        return null;
    }

    /**
     * Check if identifier query (id-driven)
     *
     * @return bool
     */
    protected function isIdentifiableQuery(): bool
    {
        foreach ($this->wheres as $where) {
            if ($where['column'] === $this->modelIdentifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * Purge all model queries and results
     *
     * @param mixed|null $identifier
     * @return bool
     */
    public function forget(mixed $identifier = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $this->log("Flushing cache for {$this->modelClass}");

        //If tag-support, just flush all results
        $modelClasses = $this->getIdentifiableModelClasses($identifier);
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($modelClasses)->flush();
        } else {
            //If not, forget based on the cached queries
            foreach ($modelClasses as $modelClass) {
                $modelCacheKey = $this->getModelCacheKey($modelClass);
                $queries = Cache::get($modelCacheKey);
                if (! empty($queries)) {
                    foreach ($queries as $query) {
                        Cache::forget($query);
                    }

                    Cache::forget($modelCacheKey);
                }
            }
        }

        return true;
    }

    /**
     * Build a cache key based on the SQL statement and its bindings
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        if (! empty($bindings)) {
            $bindings = Arr::join($this->getBindings(), '_');

            return $sql . '_' . $bindings;
        }

        return $sql;
    }

    /**
     * @param string|null $modelClass
     * @return string
     */
    protected function getModelCacheKey(string $modelClass = null): string
    {
        return $this->prefix . '_' . ($modelClass ?? $this->modelClass);
    }

    /**
     * @param string $message
     * @param string $level
     * @return bool
     */
    protected function log(string $message, string $level = 'debug')
    {
        if ($this->logLevel) {
            Log::log($this->logLevel, "[Cacheable] {$message}");
        } else {
            Log::log($level, "[Cacheable] {$message}");
        }

        return true;
    }

    /**
     * @param array $values
     * @return int
     */
    public function update(array $values)
    {
        $this->forget();

        return parent::update($values);
    }

    /**
     * @param array $values
     * @return int
     */
    public function updateFrom(array $values)
    {
        $this->forget();

        return parent::updateFrom($values);
    }

    /**
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        $this->forget();

        return parent::insert($values);
    }

    /**
     * @param array $values
     * @param       $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->forget();

        return parent::insertGetId($values, $sequence);
    }

    /**
     * @param array $values
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        $this->forget();

        return parent::insertOrIgnore($values);
    }

    /**
     * @param array $columns
     * @param       $query
     * @return int
     */
    public function insertUsing(array $columns, $query)
    {
        $this->forget();

        return parent::insertUsing($columns, $query);
    }

    /**
     * @param array $values
     * @param       $uniqueBy
     * @param       $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->forget();

        return parent::upsert($values, $uniqueBy, $update);
    }

    /**
     * @param $id
     * @return int
     */
    public function delete($id = null)
    {
        $this->forget();

        return parent::delete($id);
    }

    /**
     * @return void
     */
    public function truncate()
    {
        $this->forget();
        parent::truncate();
    }
}
