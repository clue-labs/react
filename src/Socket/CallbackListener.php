<?php

class CallbackListener implements Listener
{
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function onConnection(Connection $connection, Socket $socket)
    {
        call_user_func($this->callback, $connection, $socket);
    }
}
