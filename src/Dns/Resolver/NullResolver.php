<?php

namespace React\Dns\Resolver;

use React\Dns\Resolver\Resolver;
use React\Promise\When;

class NullResolver extends Resolver
{
    public function query()
    {
        return When::reject(new \Exception('Query rejected by NullResolver'));
    }
}
