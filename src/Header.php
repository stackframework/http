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

namespace Stack\Http;

use Psr\Http\Message\UriInterface;

/**
 * Class Header
 * @package Stack\Http
 */
class Header
{
    /**
     * @var array|mixed
     */
    private $headers = [];

    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var array
     */
    private $headerNames = [];

    /**
     * Header constructor.
     * @param array $headers
     * @param UriInterface $uri
     */
    public function __construct(UriInterface $uri, array $headers = [])
    {
        list($this->headerNames, $headers) = self::filterHeaders($headers);
        $this->assertHeaders($headers);
        $this->headers = $headers;
        $this->uri = $uri;
    }

    /**
     * @param array $originalHeaders
     * @return array
     */
    private static function filterHeaders(array $originalHeaders)
    {
        $headerNames = $headers = [];
        foreach ($originalHeaders as $header => $value) {
            if (!is_string($header)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid header name; expected non-empty string, received %s',
                    gettype($header)
                ));
            }

            if (!is_array($value) && !is_string($value) && !is_numeric($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid header value type; expected number, string, or array; received %s',
                    (is_object($value) ? get_class($value) : gettype($value))
                ));
            }

            if (is_array($value)) {
                array_walk($value, function ($item) {
                    if (!is_string($item) && !is_numeric($item)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Invalid header value type; expected number, string, or array; received %s',
                            (is_object($item) ? get_class($item) : gettype($item))
                        ));
                    }
                });
            }

            if (!is_array($value)) {
                $value = [$value];
            }

            $headerNames[self::normalizeHeader($header)] = $header;
            $headers[$header] = $value;
        }

        return [$headerNames, $headers];
    }

    /**
     * @param $name
     * @return string
     */
    public static function normalizeHeader($name)
    {
        return strtolower($name);
    }

    /**
     * @param array $headers
     */
    private function assertHeaders(array $headers)
    {
        foreach ($headers as $name => $headerValues) {
            HttpSecurity::assertValidName($name);
            array_walk($headerValues, __NAMESPACE__ . '\HttpSecurity::assertValid');
        }
    }

    /**
     * @param $contentType
     * @param array $headers
     * @return array
     */
    public static function injectContentType($contentType, array $headers)
    {
        $hasContentType = array_reduce(array_keys($headers), function ($carry, $item) {
            return $carry ?: (strtolower($item) === 'content-type');
        }, false);

        if (!$hasContentType) {
            $headers['content-type'] = [$contentType];
        }

        return $headers;
    }

    /**
     * @param $name
     * @param $value
     * @return array
     */
    public static function assertValidHeader($name, $value)
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value) || !self::arrayContainsOnlyStrings($value)) {
            throw new \InvalidArgumentException(
                'Invalid header value; must be a string or array of strings'
            );
        }

        HttpSecurity::assertValidName($name);
        self::assertValidHeaderValue($value);

        return [$name, $value];

    }

    /**
     * @param array $array
     * @return mixed
     */
    private static function arrayContainsOnlyStrings(array $array)
    {
        return array_reduce($array, [__CLASS__, 'filterStringValue'], true);
    }

    /**
     * @param array $values
     */
    private static function assertValidHeaderValue(array $values)
    {
        array_walk($values, __NAMESPACE__ . '\HttpSecurity::assertValid');
    }

    /**
     * @param $carry
     * @param $item
     * @return bool
     */
    private static function filterStringValue($carry, $item)
    {
        if (!is_string($item)) {
            return false;
        }

        return $carry;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function addHeader($name, $value)
    {
        $normalizedHeader = self::normalizeHeader($name);
        $header = $this->headerNames[$normalizedHeader];

        $this->headers[$header] = array_merge((array)$this->headers[$header], $value);

        return $this;
    }

    /**
     * @param $name
     * @param string $host
     * @return array|mixed
     */
    public function getHeader($name, $host = '')
    {
        if (!$this->hasHeader($name)) {
            if (self::normalizeHeader($name) === 'host'
                && ($this->uri && $this->uri->getHost())
            ) {
                return [$host];
            }
            return [];
        }

        $headerName = $this->headerNames[self::normalizeHeader($name)];
        $header = $this->headers[$headerName];
        $header = is_array($header) ? $header : [$header];

        return $header;
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasHeader($name)
    {
        return array_key_exists(self::normalizeHeader($name), $this->headerNames);
    }

    /**
     * @return array|mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function setHeader($name, $value)
    {
        $normalizedHeader = self::normalizeHeader($name);

        if ($this->hasHeader($name)) {
            unset($this->headers[$this->headerNames[$normalizedHeader]]);
        }
        $this->headerNames[$normalizedHeader] = $name;
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param $name
     * @param $value
     */
    public function setHeaderName($name, $value)
    {
        $this->headerNames[$name] = $value;
    }

    /**
     * @param array $headerNames
     */
    public function setHeaderNames(array $headerNames)
    {
        $this->headerNames = $headerNames;
    }

    /**
     * @param $value
     */
    public function setHost($value)
    {
        $this->headers['Host'] = [$value];
    }

    /**
     * @param UriInterface $uri
     */
    public function setUri(UriInterface $uri)
    {
        $this->uri = $uri;
    }

    /**
     * @param $name
     * @return $this
     */
    public function removeHeader($name)
    {
        $normalizedHeader = self::normalizeHeader($name);
        $header = $this->headerNames[$normalizedHeader];
        unset($this->headers[$header], $this->headerNames[$normalizedHeader]);

        return $this;
    }
}
