<?php

use App\Providers\AgentServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\LlmServiceProvider;

return [
    AppServiceProvider::class,
    LlmServiceProvider::class,
    AgentServiceProvider::class,
];
