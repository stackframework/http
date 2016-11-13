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

use Psr\Http\Message\UriInterface;
use Stack\Http\Response;

/**
 * Class RedirectResponse
 * @package Stack\Http\Response
 */
class RedirectResponse extends Response
{
    /**
     * RedirectResponse constructor.
     * @param string $uri
     * @param int $status
     * @param array $headers
     */
    public function __construct($uri, $status = 302, array $headers = [])
    {
        if (!is_string($uri) && !$uri instanceof UriInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Uri provided to %s MUST be a string or Psr\Http\Message\UriInterface instance; received "%s"',
                __CLASS__,
                (is_object($uri) ? get_class($uri) : gettype($uri))
            ));
        }
        $headers['location'] = [(string)$uri];
        parent::__construct('php://temp', $status, $headers);
    }
}
