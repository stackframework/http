<?php
/**
 * This file is part of the Stack package.
 *
 * (c) Andrzej Kostrzewa <andkos11@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Stack\Http;

use Psr\Http\Message\StreamInterface;
use Stack\Http\Stream\RelativeStream;

/**
 * Class AbstractSerializer
 * @package Stack\Http
 */
abstract class AbstractSerializer
{
    /**
     *
     */
    const CR = "\r";
    /**
     *
     */
    const CRLF = "\r\n";
    /**
     *
     */
    const LF = "\n";

    /**
     * @param StreamInterface $stream
     * @return array
     */
    protected static function splitStream(StreamInterface $stream)
    {
        $headers = [];
        $currentHeader = false;
        while ($line = self::getLine($stream)) {
            if (preg_match(';^(?P<name>[!#$%&\'*+.^_`\|~0-9a-zA-Z-]+):(?P<value>.*)$;', $line, $matches)) {
                $currentHeader = $matches['name'];
                if (!isset($headers[$currentHeader])) {
                    $headers[$currentHeader] = [];
                }
                $headers[$currentHeader][] = ltrim($matches['value']);
                continue;
            }

            if (!$currentHeader) {
                throw new \UnexpectedValueException('Invalid header detected');
            }

            if (!preg_match('#^[ \t]#', $line)) {
                throw new \UnexpectedValueException('Invalid header continuation');
            }

            // Append continuation to last header value found
            $value = array_pop($headers[$currentHeader]);
            $headers[$currentHeader][] = $value . ltrim($line);
        }

        // use RelativeStream to avoid copying initial stream into memory
        return [$headers, new RelativeStream($stream, $stream->tell())];
    }

    /**
     * @param StreamInterface $stream
     * @return string
     */
    protected static function getLine(StreamInterface $stream)
    {
        $line = '';
        $crFound = false;
        while (!$stream->eof()) {
            $char = $stream->read(1);

            if ($crFound && $char === self::LF) {
                $crFound = false;
                break;
            }

            // CR NOT followed by LF
            if ($crFound && $char !== self::LF) {
                throw new \UnexpectedValueException('Unexpected carriage return detected');
            }

            // LF in isolation
            if (!$crFound && $char === self::LF) {
                throw new \UnexpectedValueException('Unexpected line feed detected');
            }

            // CR found; do not append
            if ($char === self::CR) {
                $crFound = true;
                continue;
            }

            // Any other character: append
            $line .= $char;
        }

        // CR found at end of stream
        if ($crFound) {
            throw new \UnexpectedValueException("Unexpected end of headers");
        }

        return $line;
    }

    /**
     * @param array $headers
     * @return string
     */
    protected static function serializeHeaders(array $headers)
    {
        $lines = [];
        foreach ($headers as $header => $values) {
            $normalized = self::filterHeader($header);
            foreach ($values as $value) {
                $lines[] = sprintf('%s: %s', $normalized, $value);
            }
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param $header
     * @return mixed
     */
    protected static function filterHeader($header)
    {
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);

        return str_replace(' ', '-', $filtered);
    }
}
