<?php

declare(strict_types=1);

namespace tests\data;

use Http\Discovery\Psr18Client;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class WrongPsr18ClientWithoutRequestFactory implements ClientInterface, StreamFactoryInterface
{
    public function __construct(public $client = new Psr18Client())
    {
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return $this->client->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->client->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->client->createStreamFromResource($resource);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }
}
