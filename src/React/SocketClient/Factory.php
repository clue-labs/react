<?php

namespace React\SocketClient;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;

class Factory
{
    private $loop;
    private $resolver;
    private $connector;
    private $secureConnector;

    public function __construct(LoopInterface $loop, Resolver $resolver = null)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
        $this->connector = new Connector($loop, $resolver);
        $this->secureConnector = new SecureConnector($this->connector, $loop);
    }

    public function createClient($address)
    {
        $loop = $this->loop;
        $connector = $this->connector;
        $secureConnector = $this->secureConnector;

        return $this->resolve($address)->then(function ($parts) use ($loop, $connector, $secureConnector) {
            if ($parts['scheme'] === 'tcp') {
                return $connector->create($parts['host'], $parts['port']);
            } else if ($parts['scheme'] === 'ssl' || $parts['scheme'] === 'tls') {
                return $secureConnector->create($parts['host'], $parts['port']);
            } else {
                throw new InvalidArgumentException('Invalid scheme given');
            }
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

        if (!in_array($parts['scheme'], array('tcp', 'ssl', 'tls'))) {
            throw new InvalidArgumentException('Invalid socket scheme given. Only TCP/SSL/TLS supported');
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
