<?php

namespace ElipZis\Cacheable\Tests\Mock;

use ElipZis\Cacheable\Models\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class CacheableModel extends Model
{
    use Cacheable;

    /**
     * @var string[]
     */
    protected $fillable = [
        'key',
        'value',
    ];
}
