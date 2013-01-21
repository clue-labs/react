<?php

namespace React\Connection;

// replaces SocketClient\ConnectionManagerInterface

interface FactoryClientInterface
{
    // return a connection promise for given $host:$port
    public function createClient($host, $port);
}
