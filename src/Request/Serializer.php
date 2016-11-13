<?php
/**
 * This file is part of the Stack package.
 *
 * (c) Andrzej Kostrzewa <andkos11@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Stack\Http\Request;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Stack\Http\AbstractSerializer;
use Stack\Http\Request;
use Stack\Http\Stream;
use Stack\Http\Uri;

/**
 * Class Serializer
 * @package Stack\Http\Request
 */
final class Serializer extends AbstractSerializer
{
    /**
     * @param $message
     * @return mixed
     */
    public static function fromString($message)
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($message);

        return self::fromStream($stream);
    }

    /**
     * @param StreamInterface $stream
     * @return mixed
     */
    public static function fromStream(StreamInterface $stream)
    {
        if (!$stream->isReadable() || !$stream->isSeekable()) {
            throw new \InvalidArgumentException('Message stream must be both readable and seekable');
        }

        $stream->rewind();
        list($method, $requestTarget, $version) = self::getRequestLine($stream);
        $uri = self::createUriFromRequestTarget($requestTarget);
        list($headers, $body) = self::splitStream($stream);

        return (new Request($uri, $method, $body, $headers))
            ->withRequestTarget($requestTarget)
            ->withProtocolVersion($version);
    }

    /**
     * @param StreamInterface $stream
     * @return array
     */
    private static function getRequestLine(StreamInterface $stream)
    {
        $requestLine = self::getLine($stream);
        if (!preg_match(
            '#^(?P<method>[!\#$%&\'*+.^_`|~a-zA-Z0-9-]+) (?P<target>[^\s]+) HTTP/(?P<version>[1-9]\d*\.\d+)$#',
            $requestLine,
            $matches
        )
        ) {
            throw new \UnexpectedValueException('Invalid request line detected');
        }

        return [$matches['method'], $matches['target'], $matches['version']];
    }

    /**
     * @param $requestTarget
     * @return Uri
     */
    private static function createUriFromRequestTarget($requestTarget)
    {
        if (preg_match('#^https?://#', $requestTarget)) {
            return new Uri($requestTarget);
        }

        if (preg_match('#^(\*|[^/])#', $requestTarget)) {
            return new Uri();
        }

        return new Uri($requestTarget);
    }

    /**
     * @param RequestInterface $request
     * @return string
     */
    public static function toString(RequestInterface $request)
    {
        $headers = self::serializeHeaders($request->getHeaders());
        $body = (string)$request->getBody();
        $format = '%s %s HTTP/%s%s%s';

        if (!empty($headers)) {
            $headers = "\r\n" . $headers;
        }

        if (!empty($body)) {
            $headers .= "\r\n\r\n";
        }

        return sprintf(
            $format,
            $request->getMethod(),
            $request->getRequestTarget(),
            $request->getProtocolVersion(),
            $headers,
            $body
        );
    }
}
