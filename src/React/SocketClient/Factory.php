<?php

namespace React\SocketClient;

use React\Socket\ConnectionException;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;

class Factory
{
    private $loop;
    private $resolver;
    private $connector;
    private $secureConnector;
    private $context;
    private $timeout;

    public function __construct(LoopInterface $loop, Resolver $resolver = null)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
        $this->connector = new Connector($loop, $resolver);
        $this->secureConnector = new SecureConnector($this->connector, $loop);

        $this->context = array('ssl' => array(
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
            )
        );

        $this->timeout = ini_get("default_socket_timeout");
    }

    public function createClient($address)
    {
        $loop = $this->loop;
        $connector = $this->connector;
        $secureConnector = $this->secureConnector;

        return $this->resolve($address)->then(function ($parts) use ($loop, $connector, $secureConnector) {
            if ($parts['scheme'] === 'tcp') {
                return $this->timeout($connector->create($parts['host'], $parts['port']));
            } else if ($parts['scheme'] === 'ssl' || $parts['scheme'] === 'tls') {
                return $this->timeout($secureConnector->create($parts['host'], $parts['port']));
            } else {
                throw new InvalidArgumentException('Invalid scheme given');
            }
        });
    }

    // Number of seconds until the connect() system call should timeout.
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    // http://php.net/manual/en/context.socket.php

    // Used to specify the IP address (either IPv4 or IPv6) and/or the port number that PHP will use to access the network. The syntax is ip:port. Setting the IP or the port to 0 will let the system choose the IP and/or port.
    public function setBind($bindto)
    {
        $this->context['socket']['bindto'] = $bindto;
    }

    // http://php.net/manual/en/context.ssl.php

    // Path to local certificate file on filesystem. It must be a PEM encoded file which contains your certificate and private key. It can optionally contain the certificate chain of issuers.
    // Passphrase with which your local_cert file was encoded.
    public function setSslCertificate($path, $passphrase = null)
    {
        $this->context['ssl']['cert_local'] = $path;
        $this->context['ssl']['passphrase'] = $passphrase;
    }

    // Require verification of SSL certificate used (false)
    // Allow self-signed certificates. Requires verify_peer (false)
    public function setSslVerifyPeer($toggle = true, $allowSelfSigned = false)
    {
        $this->context['ssl']['verify_peer'] = $path;
        $this->context['ssl']['allow_self_signed'] = $passphrase;
    }

    // Location of Certificate Authority file on local filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer.
    public function setSslCertificateAutorityFile($cafile)
    {
        $this->context['ssl']['cafile'] = $cafile;
    }

    // If cafile is not specified or if the certificate is not found there, the directory pointed to by capath is searched for a suitable certificate. capath must be a correctly hashed certificate directory.
    public function setSslCertificateAuthorityPath($capath)
    {
        $this->context['ssl']['capath'] = $capath;
    }

    protected function timeout(PromiseInterface $promise)
    {
        $deferred = new Deferred();
        $timedout = false;

        $tid = $this->loop->addTimer($this->timeout, function() use ($deferred, &$timedout) {
            $deferred->reject(new ConnectionException('Connection attempt timed out'));
            $timedout = true;
            // TODO: find a proper way to actually cancel the connection
            // $promise->cancel()
        });

        $loop = $this->loop;
        $promise->then(function ($connection) use ($tid, $loop, &$timedout, $deferred) {
            if ($timedout) {
                // connection successfully established but timeout already expired => close successful connection
                $connection->end();
            } else {
                $loop->removeTimeout($tid);
                $deferred->resolve($connection);
            }
        }, function ($error) use ($loop, $tid) {
            $loop->removeTimeout($tid);
            throw $error;
        });
        return $deferred->promise();
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
