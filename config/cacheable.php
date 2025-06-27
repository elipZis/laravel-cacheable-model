<?php

//Default values for the Cacheable trait - Can be overridden per model
return [
    //How long should cache last in general?
    //Set TTL to 0 for disable caching (ex. config('app.debug') ? 0 : 300)
    'ttl' => 300,
    //By what should cache entries be prefixed?
    'prefix' => 'cacheable',
    //What is the identifying, unique column name?
    'identifier' => 'id',
    //Do you need logging?
    'logging' => [
        'channel' => null, //Which channel should be used?
        'enabled' => false,
        'level' => 'debug',
    ],
];
