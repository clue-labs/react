<?php

namespace React\Socket;

use React\Socket\ConnectionInterface;
use React\Http\ServerInterface;

interface ListenerInterface
{
    public function onConnection(ConnectionInterface $connection, ServerInterface $server);
}
