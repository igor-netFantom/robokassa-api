<?php

declare(strict_types=1);

namespace tests\data;

use Http\Discovery\Psr18Client;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WrongPsr18ClientWithoutStreamFactory implements ClientInterface, RequestFactoryInterface
{
    public function __construct(public $client = new Psr18Client())
    {
    }

    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->client->createRequest($method, $uri);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }
}