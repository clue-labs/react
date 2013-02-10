<?php

namespace React\HttpClient;

use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\Stream\Stream;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;

class ConnectionManagerResolver implements ConnectionManagerInterface
{
    protected $connectionManager;
    protected $resolver;

    public function __construct(ConnectionManagerInterface $connectionManager, Resolver $resolver)
    {
        $this->connectionManager = $connectionManager;
        $this->resolver = $resolver;
    }

    public function getConnection($host, $port)
    {
        $connectionManager = $this->connectionManager;

        return $this->resolveHostname($host)->then(function ($address) use ($port, $connectionManager) {
            return $connectionManager->getConnection($address, $port);
        });
    }

    protected function resolveHostname($host)
    {
        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            return new FulfilledPromise($host);
        }

        return $this->resolver->resolve($host);
    }
}

