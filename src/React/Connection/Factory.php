<?php

namespace React\Connection;

use React\SocketClient\ConnectionManager;

use React\Socket\Server;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise\When;

class Factory
{
    private $loop;
    private $resolver;
    private $factoryClient = null;

    // TODO: consider whether it's okay to call the factory with no resolver in case it's not needed?
    public function __construct(LoopInterface $loop, Resolver $resolver)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
    }

    public function createClient($host, $port)
    {
        return $this->getFactoryClient()->getConnection($host, $port);
    }

    // TODO: argument order seems a bit inconsistent considering createClient() has the argument the other way around...
    public function createServer($port, $host = '127.0.0.1')
    {
        try{
            $server = new Server($this->loop);
            $server->listen($port, $host);

            return When::resolve($server);
        }
        catch (Exception $e) {
            return When::reject($e);
        }
    }

    public function setFactoryClient($factoryClient)
    {
        $this->factoryClient = $factoryClient;
    }

    public function getFactoryClient()
    {
        // no client factory given, use default one
        if ($this->factoryClient === null) {
            // todo: rename ConnectionManager
            $this->factoryClient = new ConnectionManager($this->loop, $this->resolver);
        }
        return $this->factoryClient;
    }
}
