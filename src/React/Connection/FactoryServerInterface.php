<?php

namespace React\Connection;

// TODO: is a separate interface even needed? just for the sake of consistency?

interface FactoryServerInterface
{
    // return a promise for server socket listening on given $host:$port
    // TODO: argument order and default host?
    public function createServer($port, $host = '127.0.0.1');
}
