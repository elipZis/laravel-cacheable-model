<?php

namespace ElipZis\Cacheable\Database\Query;

use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Capture all select queries and decide if to cache or not.
 *
 * Forget caches in case of altering statements.
 */
class CacheableQueryBuilder extends Builder
{
    /**
     * @var string
     */
    protected string $modelClass;

    /**
     * @var string|null
     */
    protected ?string $modelIdentifier;

    /**
     * @var int|null
     */
    protected ?int $ttl;

    /**
     * @var string|null
     */
    protected ?string $prefix;

    /**
     * @var bool
     */
    protected bool $logEnabled = false;

    /**
     * @var string|null
     */
    protected ?string $logLevel;

    /**
     * @var bool
     */
    protected bool $enabled = true;

    /**
     * @var array
     */
    protected array $cacheableProperties;

    /**
     * @param Connection $conn
     * @param Grammar|null $grammar
     * @param Processor|null $processor
     * @param string|null $modelClass
     * @param array $cacheableProperties
     */
    public function __construct(
        Connection $conn,
        Grammar    $grammar = null,
        Processor  $processor = null,
        string     $modelClass = null,
        array      $cacheableProperties = []
    ) {
        parent::__construct($conn, $grammar, $processor);
        $this->modelClass = $modelClass ?? static::class;
        $this->cacheableProperties = $cacheableProperties;

        //Prefill some members
        $this->modelIdentifier = $cacheableProperties['identifier'] ?? null;
        $this->ttl = $cacheableProperties['ttl'] ?? null;
        $this->prefix = $cacheableProperties['prefix'] ?? null;
        $this->logEnabled = Arr::get($cacheableProperties, 'logging.enabled', false);
        $this->logLevel = Arr::get($cacheableProperties, 'logging.level', null);
    }

    /**
     * Pass our configuration to newly created queries
     *
     * @return $this|CacheableQueryBuilder
     */
    public function newQuery()
    {
        return new static(
            $this->connection,
            $this->grammar,
            $this->processor,
            $this->modelClass,
            $this->cacheableProperties
        );
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

        //Check if taggable store
        $isTaggableStore = Cache::getStore() instanceof TaggableStore;
        //and create additional identifiers
        $modelClasses = $this->getIdentifiableModelClasses($this->getIdentifiableValue());

        //If cached, return
        if (($isTaggableStore && Cache::tags($modelClasses)->has($cacheKey)) || Cache::has($cacheKey)) {
            $this->log("Found cache entry for '{$cacheKey}'");

            return $isTaggableStore ? Cache::tags($modelClasses)->get($cacheKey) : Cache::get($cacheKey);
        }

        //If not, run normally -> this is what to cache and return
        $retVal = parent::runSelect();

        //Are tags supported? Makes life easier!
        if ($isTaggableStore) {
            $this->log("Using taggable store to cache value of {$cacheKey} for {$this->ttl} ttl for " . implode(',', $modelClasses));
            Cache::tags($modelClasses)->put($cacheKey, $retVal, $this->ttl);
        } else {
            $this->log("Using cache to store value of {$cacheKey} for {$this->ttl} ttl for " . implode(',', $modelClasses));
            Cache::put($cacheKey, $retVal, $this->ttl);

            //Cache the query if not, for purging purposes
            foreach ($modelClasses as $modelClass) {
                $modelCacheKey = $this->getModelCacheKey($modelClass);
                $queries = Cache::get($modelCacheKey, []);
                $queries[] = $cacheKey;
                Cache::put($modelCacheKey, $queries);
            }
        }

        return $retVal;
    }

    /**
     * Check if to cache against just the class or a specific identifiable e.g. id
     *
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
     * @param array|null $wheres
     * @return mixed
     */
    protected function getIdentifiableValue(array $wheres = null): mixed
    {
        $wheres = $wheres ?? $this->wheres;
        foreach ($wheres as $where) {
            if (isset($where['type']) && $where['type'] === 'Nested') {
                return $this->getIdentifiableValue($where['query']->wheres);
            }
            if (isset($where['column']) && $where['column'] === $this->modelIdentifier) {
                return $where['value'] ?? $where['values'];
            }
        }

        return null;
    }

    /**
     * Check if identifier query (id-driven)
     *
     * @param array|null $wheres
     * @return bool
     */
    protected function isIdentifiableQuery(array $wheres = null): bool
    {
        return $this->getIdentifiableValue($wheres) !== null;
    }

    /**
     * Purge all model queries and results
     *
     * @param mixed|null $identifier
     * @return bool
     */
    public function flushCache(mixed $identifier = null): bool
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
        if (! $this->logEnabled) {
            return false;
        }

        if ($this->logLevel) {
            Log::log($this->logLevel, "[Cacheable] {$message}");
        } else {
            Log::log($level, "[Cacheable] {$message}");
        }

        return true;
    }

    /**
     * Disable cache for this query
     *
     * @return $this
     */
    public function withoutCache(): static
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Enable logging for this query
     *
     * @return $this
     */
    public function withLogging(): static
    {
        $this->logEnabled = true;

        return $this;
    }

    /**
     * Change the ttl for this query
     *
     * @param int $ttl
     * @return $this
     */
    public function withTtl(int $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @param array $values
     * @return int
     */
    public function update(array $values)
    {
        $this->flushCache();

        return parent::update($values);
    }

    /**
     * @param array $values
     * @return int
     */
    public function updateFrom(array $values)
    {
        $this->flushCache();

        return parent::updateFrom($values);
    }

    /**
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        $this->flushCache();

        return parent::insert($values);
    }

    /**
     * @param array $values
     * @param       $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->flushCache();

        return parent::insertGetId($values, $sequence);
    }

    /**
     * @param array $values
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        $this->flushCache();

        return parent::insertOrIgnore($values);
    }

    /**
     * @param array $columns
     * @param       $query
     * @return int
     */
    public function insertUsing(array $columns, $query)
    {
        $this->flushCache();

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
        $this->flushCache();

        return parent::upsert($values, $uniqueBy, $update);
    }

    /**
     * @param $id
     * @return int
     */
    public function delete($id = null)
    {
        $this->flushCache($id);

        return parent::delete($id);
    }

    /**
     * @return void
     */
    public function truncate()
    {
        $this->flushCache();

        parent::truncate();
    }
}
