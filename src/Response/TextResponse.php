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

namespace Stack\Http\Response;

use Psr\Http\Message\StreamInterface;
use Stack\Http\Header;
use Stack\Http\Response;
use Stack\Http\Stream;

/**
 * Class TextResponse
 * @package Stack\Http\Response
 */
class TextResponse extends Response
{
    /**
     * TextResponse constructor.
     * @param string $text
     * @param int $status
     * @param array $headers
     */
    public function __construct($text, $status = 200, array $headers = [])
    {
        parent::__construct(
            $this->createBody($text),
            $status,
            Header::injectContentType('text/plain; charset=utf-8', $headers)
        );
    }

    /**
     * @param $text
     * @return Stream
     */
    private function createBody($text)
    {
        if ($text instanceof StreamInterface) {
            return $text;
        }

        if (!is_string($text)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid content (%s) provided to %s',
                (is_object($text) ? get_class($text) : gettype($text)),
                __CLASS__
            ));
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($text);
        $body->rewind();

        return $body;
    }
}
