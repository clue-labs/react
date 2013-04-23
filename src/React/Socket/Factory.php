<?php

namespace React\Socket;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;

class Factory
{
    public function __construct(LoopInterface $loop, Resolver $resolver = null)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
    }
    
    public function createServer($address)
    {
        $loop = $this->loop;
        
        return $this->resolve($address)->then(function ($parts) use ($loop) {
            $server = new Server($loop);
            $server->listen($parts['port'], $parts['host']);
            
            return $server;
        });
    }
    
    protected function resolve($address)
    {
        if (strpos($address, '://') === false) {
            // no scheme given => assume tcp
            $address = 'tcp://' . $address;
        }
        
        $parts = parse_url($address);
        if ($parts === false) {
            throw new InvalidArgumentException('Invalid socket address given');
        }
        
        // only scheme, host and port should be given
        if (count($parts) !== 3 || !isset($parts['port'])) {
            throw new InvalidArgumentException('Invalid socket address given');
        }
        
        if ($parts['scheme'] !== 'tcp') {
            throw new InvalidArgumentException('Invalid socket scheme given. Only TCP supported');
        }
        
        if (false !== filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            // host is a valid IP => no need to use DNS resolver
            return When::resolve($parts);
        }
        
        if ($this->resolver === null) {
            throw new Exception('Hostname given, but no Resolver passed to Factory');
        }
        
        return $this->resolver->resolve($parts['host'])->then(function ($host) use ($parts) {
            $parts['host'] = $host;
            return $parts;
        });
    }
}
