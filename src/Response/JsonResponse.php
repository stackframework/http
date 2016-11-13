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

use Stack\Http\Header;
use Stack\Http\Response;
use Stack\Http\Stream;

/**
 * Class JsonResponse
 * @package Stack\Http\Response
 */
class JsonResponse extends Response
{
    /**
     *
     */
    const DEFAULT_JSON_FLAGS = 79;

    /**
     * JsonResponse constructor.
     * @param string $data
     * @param int $status
     * @param array $headers
     * @param int $encodingOptions
     */
    public function __construct(
        $data,
        $status = 200,
        array $headers = [],
        $encodingOptions = self::DEFAULT_JSON_FLAGS
    ) {
        $body = new Stream('php://temp', 'wb+');
        $body->write($this->jsonEncode($data, $encodingOptions));
        $body->rewind();
        $headers = Header::injectContentType('application/json', $headers);
        parent::__construct($body, $status, $headers);
    }

    /**
     * @param $data
     * @param $encodingOptions
     * @return string
     */
    private function jsonEncode($data, $encodingOptions)
    {
        if (is_resource($data)) {
            throw new \InvalidArgumentException('Cannot JSON encode resources');
        }

        json_encode(null);
        $json = json_encode($data, $encodingOptions);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(sprintf(
                'Unable to encode data to JSON in %s: %s',
                __CLASS__,
                json_last_error_msg()
            ));
        }

        return $json;
    }
}
