<?php

namespace React\Connection;

// temporary adapter to make default ConnectionManager act like a FactoryClient

use React\SocketClient\ConnectionManager;

class FactoryClient extends ConnectionManager implements FactoryClientInterface
{
    public function createClient($host, $port)
    {
        return $this->getConnection($host, $port);
    }
}
