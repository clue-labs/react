<?php

namespace React\Dns\Query;

use React\Dns\BadServerException;
use React\Dns\Model\Message;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Socket\Connection;

class Executor implements ExecutorInterface
{
    private $loop;
    private $parser;
    private $dumper;
    private $timeout;

    public function __construct(LoopInterface $loop, Parser $parser, BinaryDumper $dumper, $timeout = 5)
    {
        $this->loop = $loop;
        $this->parser = $parser;
        $this->dumper = $dumper;
        $this->timeout = $timeout;
    }

    public function query($nameserver, Query $query)
    {
        $request = $this->prepareRequest($query);

        $queryData = $this->dumper->toBinary($request);

        $deferred = new Deferred();
        $deferred->timer = $this->loop->addTimer($this->timeout, function () use ($name, $deferred) {
            $timer->getData()->close();
            $deferred->reject(new TimeoutException(sprintf("DNS query for %s timed out", $name)));
        });

        $deferred->then(null, function() {
            $deferred->timer->cancel();
        });

        if (strlen($queryData) > 512) {
            $this->doQueryTcp($nameserver, $queryData, $query->name, $deferred);
        } else {
            $this->doQueryUdp($nameserver, $queryData, $query->name, $deferred);
        }

        return $deferred->promise();
    }

    public function prepareRequest(Query $query)
    {
        $request = new Message();
        $request->header->set('id', $this->generateId());
        $request->header->set('rd', 1);
        $request->questions[] = (array) $query;
        $request->prepare();

        return $request;
    }

    public function doQueryUdp($nameserver, $queryData, $name, Deferred $result)
    {
        $parser = $this->parser;

        $this->createConnection($nameserver, 'udp')->then(function ($socket) use ($queryData, $name, $parser, $nameserver, $result) {
            $socket->send($queryData);

            $socket->once('message', function($data) use ($socket, $parser, $nameserver, $result) {
                $socket->close();

                $response = new Message();
                if ($parser->parseChunk($data, $response) === null) {
                    return $result->reject(new BadServerException('Incomplete chunk via UDP received'));
                }

                if ($response->header->isTruncated()) {
                    return $this->doQueryTcp($nameserver, $queryData, $name, $result);
                }

                return $result->resolve($response);
            });
        });
    }

    public function doQueryTcp($nameserver, $queryData, $name, Deferred $result)
    {
        $parser = $this->parser;

        $this->createConnection($nameserver, 'tcp')->then(function ($conn) use ($parser, $queryData, $result) {
            $response = new Message();

            $conn->write($queryData);

            $conn->on('data', function ($data) use ($conn, $parser, $response, $transport, $result, $timer) {
                $responseReady = $parser->parseChunk($data, $response);

                if (!$responseReady) {
                    return;
                }

                $conn->end();

                if ($response->header->isTruncated()) {
                    return $result->reject(new BadServerException('The server set the truncated bit although we issued a TCP request'));
                }

                return $result->resolve($response);
            });
        });
    }

    protected function generateId()
    {
        return mt_rand(0, 0xffff);
    }

    protected function createConnection($nameserver, $transport)
    {
        $fd = stream_socket_client("$transport://$nameserver");
        $conn = new Connection($fd, $this->loop);

        return When::resolve($conn);
    }
}
