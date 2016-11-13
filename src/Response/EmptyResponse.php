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

use Stack\Http\Response;
use Stack\Http\Stream;

/**
 * Class EmptyResponse
 * @package Stack\Http\Response
 */
class EmptyResponse extends Response
{
    /**
     * EmptyResponse constructor.
     * @param int $status
     * @param array $headers
     */
    public function __construct($status = 204, array $headers = [])
    {
        $body = new Stream('php://temp', 'r');
        parent::__construct($body, $status, $headers);
    }

    /**
     * @param array $headers
     * @return static
     */
    public static function withHeaders(array $headers)
    {
        return new static(204, $headers);
    }
}
