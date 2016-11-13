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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class Request
 * @package Stack\Http
 */
class Request extends AbstractMessage implements RequestInterface
{
    /**
     * @var string
     */
    protected $method = '';

    /**
     * @var
     */
    private $requestTarget;

    /**
     * @var \Stack\Http\Uri
     */
    private $uri;

    /**
     * Request constructor.
     * @param null $uri
     * @param null $method
     * @param string $body
     * @param array $headers
     */
    public function __construct($uri = null, $method = null, $body = 'php://temp', array $headers = [])
    {
        if (!$uri instanceof UriInterface && !is_string($uri) && $uri !== null) {
            throw new \InvalidArgumentException(
                'Invalid URI provided; must be null, a string, or a Psr\Http\Message\UriInterface instance'
            );
        }

        self::assertValidMethod($method);

        if (!is_string($body) && !is_resource($body) && !$body instanceof StreamInterface) {
            throw new \InvalidArgumentException(
                'Body must be a string stream resource identifier, '
                . 'an actual stream resource, '
                . 'or a Psr\Http\Message\StreamInterface implementation'
            );
        }

        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        $this->method = $method ?: '';
        $this->uri = $uri ?: new Uri();
        $this->stream = ($body instanceof StreamInterface) ? $body : new Stream($body, 'wb+');
        $this->headers = new Header($this->uri, $headers);
    }

    /**
     * @param $name
     */
    protected static function assertValidMethod($name)
    {
        if ($name === null) {
            return;
        }

        if (!is_string($name)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported HTTP method; must be a string, received %s',
                (is_object($name) ? get_class($name) : gettype($name))
            ));
        }

        $method = strtoupper($name);
        if (!isset(HttpSecurity::$validMethods[$method])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported HTTP method "%s" provided',
                $method
            ));
        }
    }

    /**
     * @return array|mixed
     */
    public function getHeaders()
    {
        $headers = $this->headers->getHeaders();
        if (!$this->hasHeader('host')
            && ($this->uri && $this->uri->getHost())
        ) {
            $headers['Host'] = [$this->getHostFromUri()];
        }
        return $headers;
    }

    /**
     * @return string
     */
    private function getHostFromUri()
    {
        $host = $this->uri->getHost();
        $host .= $this->uri->getPort() ? ':' . $this->uri->getPort() : '';
        return $host;
    }

    /**
     * @param string $name
     * @return array|mixed
     */
    public function getHeader($name)
    {
        $host = $this->getHostFromUri();
        return $this->headers->getHeader($name, $host);
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        if (!$this->uri) {
            return "/";
        }

        $requestTarget = $this->uri->getPath();
        if ($this->uri->getQuery()) {
            $requestTarget .= "?" . $this->uri->getQuery();
        }

        if (empty($requestTarget)) {
            $requestTarget = "/";
        }

        return $requestTarget;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-2.7 (for the various
     *     request-target forms allowed in request messages)
     * @param mixed $requestTarget
     * @return self
     */
    public function withRequestTarget($requestTarget)
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException(
                'Invalid request target provided; cannot contain whitespace'
            );
        }

        $self = clone $this;
        $self->requestTarget = $requestTarget;

        return $self;
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-sensitive method.
     * @return self
     * @throws \InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method)
    {
        self::assertValidMethod($method);

        $self = clone $this;
        $self->method = $method;

        return $self;
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return self
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $self = clone $this;
        $self->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            return $self;
        }

        if (!$uri->getHost()) {
            return $self;
        }

        $host = $uri->getHost();
        if ($uri->getPort()) {
            $host .= ":" . $uri->getPort();
        }

        $self->headers->setHeaderName('host', 'Host');

        $headers = $self->headers->getHeaders();
        foreach (array_keys($headers) as $header) {
            if (strtolower($header) === 'host') {
                unset($headers[$header]);
                $self->headers->setHeaders($headers);
            }
        }

        $self->headers->setHost($host);

        return $self;
    }
}