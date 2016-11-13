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

namespace Stack\Http\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Stack\Http\AbstractSerializer;
use Stack\Http\Response;
use Stack\Http\Stream;

/**
 * Class Serializer
 * @package Stack\Http\Response
 */
class Serializer extends AbstractSerializer
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
        list($version, $status, $reasonPhrase) = self::getStatusLine($stream);
        list($headers, $body) = self::splitStream($stream);

        return (new Response($body, $status, $headers))
            ->withStatus((int)$status, $reasonPhrase)
            ->withProtocolVersion($version);
    }

    /**
     * @param StreamInterface $stream
     * @return array
     */
    private static function getStatusLine(StreamInterface $stream)
    {
        $line = self::getLine($stream);
        if (!preg_match(
            '#^HTTP/(?P<version>[1-9]\d*\.\d) (?P<status>[1-5]\d{2})(\s+(?P<reason>.+))?$#',
            $line,
            $matches
        )
        ) {
            throw new \UnexpectedValueException('No status line detected');
        }

        return [$matches['version'], $matches['status'], isset($matches['reason']) ? $matches['reason'] : ''];
    }

    /**
     * @param ResponseInterface $response
     * @return string
     */
    public static function toString(ResponseInterface $response)
    {
        $reasonPhrase = $response->getReasonPhrase();
        $headers = self::serializeHeaders($response->getHeaders());
        $body = (string)$response->getBody();
        $format = 'HTTP/%s %d%s%s%s';

        if (!empty($headers)) {
            $headers = "\r\n" . $headers;
        }

        if (!empty($body)) {
            $headers .= "\r\n\r\n";
        }

        return sprintf(
            $format,
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            ($reasonPhrase ? ' ' . $reasonPhrase : ''),
            $headers,
            $body
        );
    }
}
