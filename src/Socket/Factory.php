<?php

namespace React\Socket;

use React\EventLoop\LoopInterface;
use React\Promise\ResolverInterface;
use React\Promise\When;

class Factory
{
    private $loop;
    private $resolver;

    public function __construct(LoopInterface $loop, ResolverInterface $resolver = null)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
    }

    public function createServer($listenAddress)
    {
        return $this->resolve($listenAddress)->then(array($this, 'createSocket'));
    }

    public function createListener($listenAddress, ListenerInterface $listener)
    {
        return $this->createServer($listenAddress)->then(function (Socket $socket) use ($listener) {
            $socket->on('connection', array($listener, 'onConnection'));

            return $socket;
        });
    }

    public function createCallback($listenAddress, callable $callback)
    {
        return $this->createListener($listenAddress, new CallbackListener($callback));
    }

    private function resolve($address)
    {
        return When::resolve('tcp://' . $address);
    }

    private function createSocket($address)
    {
        $this->master = @stream_socket_server($address, $errno, $errstr);
        if (false === $this->master) {
            $message = "Could not bind to $address: $errstr";
            throw new ConnectionException($message, $errno);
        }
        stream_set_blocking($this->master, 0);

        return new Server($socket, $this->loop);
    }
}
