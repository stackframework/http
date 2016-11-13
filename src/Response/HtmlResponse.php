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

use Psr\Http\Message\StreamInterface;
use Stack\Http\Header;
use Stack\Http\Response;
use Stack\Http\Stream;

/**
 * Class HtmlResponse
 * @package Stack\Http\Response
 */
class HtmlResponse extends Response
{
    /**
     * HtmlResponse constructor.
     * @param string $html
     * @param int $status
     * @param array $headers
     */
    public function __construct($html, $status = 200, array $headers = [])
    {
        parent::__construct(
            $this->createBody($html),
            $status,
            Header::injectContentType('text/html; charset=utf-8', $headers)
        );
    }

    /**
     * @param $html
     * @return Stream
     */
    private function createBody($html)
    {
        if ($html instanceof StreamInterface) {
            return $html;
        }

        if (!is_string($html)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid content (%s) provided to %s',
                (is_object($html) ? get_class($html) : gettype($html)),
                __CLASS__
            ));
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($html);
        $body->rewind();

        return $body;
    }
}
