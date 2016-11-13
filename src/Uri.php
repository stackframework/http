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
 * Class Uri
 * @package Stack\Http
 */
class Uri implements UriInterface
{
    /**
     * @var string
     */
    private $scheme = '';

    /**
     * @var string
     */
    private $userInfo = '';

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $query = '';

    /**
     * @var string
     */
    private $fragment = '';

    /**
     * @var string
     */
    private $uriString;

    /**
     * Uri constructor.
     * @param string $uri
     */
    public function __construct($uri = '')
    {
        if (!is_string($uri)) {
            throw new \InvalidArgumentException(sprintf(
                'URI passed to constructor must be a string; received "%s"',
                (is_object($uri) ? get_class($uri) : gettype($uri))
            ));
        }

        if (!empty($uri)) {
            $this->parseUri($uri);
        }
    }

    /**
     * @param $uri
     */
    private function parseUri($uri)
    {
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new \InvalidArgumentException(
                'The source URI string appears to be malformed'
            );
        }

        $this->scheme = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $parts['user'] : '';
        $this->host = isset($parts['host']) ? $parts['host'] : '';
        $this->port = isset($parts['port']) ? $parts['port'] : null;
        $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->filterQuery($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filterFragment($parts['fragment']) : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * @param $scheme
     * @return string
     */
    private function filterScheme($scheme): string
    {
        $scheme = strtolower($scheme);
        $scheme = preg_replace('#:(//)?$#', '', $scheme);

        if (empty($scheme)) {
            return '';
        }

        if (!array_key_exists($scheme, HttpSecurity::$allowedSchemes)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported scheme "%s"; must be any empty string or in the set (%s)',
                $scheme,
                implode(', ', array_keys(HttpSecurity::$allowedSchemes))
            ));
        }

        return $scheme;
    }

    /**
     * @param $path
     * @return string
     */
    private function filterPath($path): string
    {
        $path = preg_replace_callback(
            '/(?:[^' . HttpSecurity::CHAR_UNRESERVED . ':@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'urlEncodeChar'],
            $path
        );

        if (empty($path)) {
            return $path;
        }

        if ($path[0] !== '/') {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @param $query
     * @return string
     */
    private function filterQuery($query): string
    {
        if (!empty($query) && strpos($query, '?') === 0) {
            $query = substr($query, 1);
        }

        $parts = explode('&', $query);
        foreach ($parts as $index => $part) {
            list($key, $value) = $this->splitQueryValue($part);

            $key = $this->filterQueryOrFragment($key);
            if ($value === null) {
                $parts[$index] = $key;
                continue;
            }

            $parts[$index] = sprintf(
                '%s=%s',
                $key,
                $this->filterQueryOrFragment($value)
            );
        }

        return implode('&', $parts);
    }

    /**
     * @param $value
     * @return array
     */
    private function splitQueryValue($value): array
    {
        $data = explode('=', $value, 2);
        if (count($data) === 1) {
            $data[] = null;
        }

        return $data;
    }

    /**
     * @param $value
     * @return string
     */
    private function filterQueryOrFragment($value): string
    {
        return preg_replace_callback(
            '/(?:[^' . HttpSecurity::CHAR_UNRESERVED . HttpSecurity::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'urlEncodeChar'],
            $value
        );
    }

    /**
     * @param $fragment
     * @return string
     */
    private function filterFragment($fragment): string
    {
        if (!empty($fragment) && strpos($fragment, '#') === 0) {
            $fragment = '%23' . substr($fragment, 1);
        }

        return $this->filterQueryOrFragment($fragment);
    }

    /**
     * Retrieve the scheme component of the URI.
     *
     * If no scheme is present, this method MUST return an empty string.
     *
     * The value returned MUST be normalized to lowercase, per RFC 3986
     * Section 3.1.
     *
     * The trailing ":" character is not part of the scheme and MUST NOT be
     * added.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.1
     * @return string The URI scheme.
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Retrieve the user information component of the URI.
     *
     * If no user information is present, this method MUST return an empty
     * string.
     *
     * If a user is present in the URI, this will return that value;
     * additionally, if the password is also present, it will be appended to the
     * user value, with a colon (":") separating the values.
     *
     * The trailing "@" character is not part of the user information and MUST
     * NOT be added.
     *
     * @return string The URI user information, in "username[:password]" format.
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * Retrieve the host component of the URI.
     *
     * If no host is present, this method MUST return an empty string.
     *
     * The value returned MUST be normalized to lowercase, per RFC 3986
     * Section 3.2.2.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
     * @return string The URI host.
     */
    public function getHost(): string
    {
        if ($this->scheme === 'file' && !$this->host) {
            return 'localhost';
        }

        return $this->host;
    }

    /**
     * Retrieve the port component of the URI.
     *
     * If a port is present, and it is non-standard for the current scheme,
     * this method MUST return it as an integer. If the port is the standard port
     * used with the current scheme, this method SHOULD return null.
     *
     * If no port is present, and no scheme is present, this method MUST return
     * a null value.
     *
     * If no port is present, but a scheme is present, this method MAY return
     * the standard port for that scheme, but SHOULD return null.
     *
     * @return null|int The URI port.
     */
    public function getPort()
    {
        return $this->isNonStandardPort($this->scheme, $this->host, $this->port)
            ? $this->port
            : null;
    }

    /**
     * @param $scheme
     * @param $host
     * @param $port
     * @return bool
     */
    private function isNonStandardPort($scheme, $host, $port): bool
    {
        if (!$scheme) {
            return true;
        }
        if (!$host || !$port) {
            return false;
        }

        return !isset(HttpSecurity::$allowedSchemes[$scheme]) || $port !== HttpSecurity::$allowedSchemes[$scheme];
    }

    /**
     * Retrieve the query string of the URI.
     *
     * If no query string is present, this method MUST return an empty string.
     *
     * The leading "?" character is not part of the query and MUST NOT be
     * added.
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode
     * any characters. To determine what characters to encode, please refer to
     * RFC 3986, Sections 2 and 3.4.
     *
     * As an example, if a value in a key/value pair of the query string should
     * include an ampersand ("&") not intended as a delimiter between values,
     * that value MUST be passed in encoded form (e.g., "%26") to the instance.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.4
     * @return string The URI query string.
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Retrieve the fragment component of the URI.
     *
     * If no fragment is present, this method MUST return an empty string.
     *
     * The leading "#" character is not part of the fragment and MUST NOT be
     * added.
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode
     * any characters. To determine what characters to encode, please refer to
     * RFC 3986, Sections 2 and 3.5.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.5
     * @return string The URI fragment.
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * Return an instance with the specified scheme.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified scheme.
     *
     * Implementations MUST support the schemes "http" and "https" case
     * insensitively, and MAY accommodate other schemes if required.
     *
     * An empty scheme is equivalent to removing the scheme.
     *
     * @param string $scheme The scheme to use with the new instance.
     * @return self A new instance with the specified scheme.
     * @throws \InvalidArgumentException for invalid or unsupported schemes.
     */
    public function withScheme($scheme): self
    {
        if (!is_string($scheme)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string argument; received %s',
                __METHOD__,
                (is_object($scheme) ? get_class($scheme) : gettype($scheme))
            ));
        }

        $scheme = $this->filterScheme($scheme);
        if ($scheme === $this->scheme) {
            return clone $this;
        }

        $self = clone $this;
        $self->scheme = $scheme;

        return $self;
    }

    /**
     * Return an instance with the specified user information.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified user information.
     *
     * Password is optional, but the user information MUST include the
     * user; an empty string for the user is equivalent to removing user
     * information.
     *
     * @param string $user The user name to use for authority.
     * @param null|string $password The password associated with $user.
     * @return self A new instance with the specified user information.
     */
    public function withUserInfo($user, $password = null): self
    {
        if (!is_string($user)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string user argument; received %s',
                __METHOD__,
                (is_object($user) ? get_class($user) : gettype($user))
            ));
        }

        if ($password !== null && !is_string($password)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string password argument; received %s',
                __METHOD__,
                (is_object($password) ? get_class($password) : gettype($password))
            ));
        }

        $userInfo = $user;
        if ($password) {
            $userInfo .= ':' . $password;
        }

        if ($userInfo === $this->userInfo) {
            return clone $this;
        }

        $self = clone $this;
        $self->userInfo = $userInfo;

        return $self;
    }

    /**
     * Return an instance with the specified host.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified host.
     *
     * An empty host value is equivalent to removing the host.
     *
     * @param string $host The hostname to use with the new instance.
     * @return self A new instance with the specified host.
     * @throws \InvalidArgumentException for invalid hostnames.
     */
    public function withHost($host): self
    {
        if (!is_string($host)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string argument; received %s',
                __METHOD__,
                (is_object($host) ? get_class($host) : gettype($host))
            ));
        }

        if ($host === $this->host) {
            return clone $this;
        }

        $self = clone $this;
        $self->host = $host;

        return $self;
    }

    /**
     * Return an instance with the specified port.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified port.
     *
     * Implementations MUST raise an exception for ports outside the
     * established TCP and UDP port ranges.
     *
     * A null value provided for the port is equivalent to removing the port
     * information.
     *
     * @param null|int $port The port to use with the new instance; a null value
     *     removes the port information.
     * @return self A new instance with the specified port.
     * @throws \InvalidArgumentException for invalid ports.
     */
    public function withPort($port): self
    {
        if (!is_numeric($port) && $port !== null) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid port "%s" specified; must be an integer, an integer string, or null',
                (is_object($port) ? get_class($port) : gettype($port))
            ));
        }

        if ($port !== null) {
            $port = (int)$port;
            if ($port < 1 || $port > 65535) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid port "%d" specified; must be a valid TCP/UDP port',
                    $port
                ));
            }
        }

        if ($port === $this->port) {
            return clone $this;
        }

        $self = clone $this;
        $self->port = $port;

        return $self;
    }

    /**
     * Return an instance with the specified path.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified path.
     *
     * The path can either be empty or absolute (starting with a slash) or
     * rootless (not starting with a slash). Implementations MUST support all
     * three syntaxes.
     *
     * If the path is intended to be domain-relative rather than path relative then
     * it must begin with a slash ("/"). Paths not starting with a slash ("/")
     * are assumed to be relative to some base path known to the application or
     * consumer.
     *
     * Users can provide both encoded and decoded path characters.
     * Implementations ensure the correct encoding as outlined in getPath().
     *
     * @param string $path The path to use with the new instance.
     * @return self A new instance with the specified path.
     * @throws \InvalidArgumentException for invalid paths.
     */
    public function withPath($path): self
    {
        if (!is_string($path)) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must be a string'
            );
        }

        if (strpos($path, '?') !== false) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must not contain a query string'
            );
        }

        if (strpos($path, '#') !== false) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must not contain a URI fragment'
            );
        }

        $path = $this->filterPath($path);
        if ($path === $this->path) {
            return clone $this;
        }

        $self = clone $this;
        $self->path = $path;

        return $self;
    }

    /**
     * Return an instance with the specified query string.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified query string.
     *
     * Users can provide both encoded and decoded query characters.
     * Implementations ensure the correct encoding as outlined in getQuery().
     *
     * An empty query string value is equivalent to removing the query string.
     *
     * @param string $query The query string to use with the new instance.
     * @return self A new instance with the specified query string.
     * @throws \InvalidArgumentException for invalid query strings.
     */
    public function withQuery($query): self
    {
        if (!is_string($query)) {
            throw new \InvalidArgumentException(
                'Query string must be a string'
            );
        }

        if (strpos($query, '#') !== false) {
            throw new \InvalidArgumentException(
                'Query string must not include a URI fragment'
            );
        }

        $query = $this->filterQuery($query);
        if ($query === $this->query) {
            return clone $this;
        }

        $self = clone $this;
        $self->query = $query;

        return $self;
    }

    /**
     * Return an instance with the specified URI fragment.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified URI fragment.
     *
     * Users can provide both encoded and decoded fragment characters.
     * Implementations ensure the correct encoding as outlined in getFragment().
     *
     * An empty fragment value is equivalent to removing the fragment.
     *
     * @param string $fragment The fragment to use with the new instance.
     * @return self A new instance with the specified fragment.
     */
    public function withFragment($fragment): self
    {
        if (!is_string($fragment)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string argument; received %s',
                __METHOD__,
                (is_object($fragment) ? get_class($fragment) : gettype($fragment))
            ));
        }

        $fragment = $this->filterFragment($fragment);
        if ($fragment === $this->fragment) {
            return clone $this;
        }

        $self = clone $this;
        $self->fragment = $fragment;

        return $self;
    }

    /**
     * Return the string representation as a URI reference.
     *
     * Depending on which components of the URI are present, the resulting
     * string is either a full URI or relative reference according to RFC 3986,
     * Section 4.1. The method concatenates the various components of the URI,
     * using the appropriate delimiters:
     *
     * - If a scheme is present, it MUST be suffixed by ":".
     * - If an authority is present, it MUST be prefixed by "//".
     * - The path can be concatenated without delimiters. But there are two
     *   cases where the path has to be adjusted to make the URI reference
     *   valid as PHP does not allow to throw an exception in __toString():
     *     - If the path is rootless and an authority is present, the path MUST
     *       be prefixed by "/".
     *     - If the path is starting with more than one "/" and no authority is
     *       present, the starting slashes MUST be reduced to one.
     * - If a query is present, it MUST be prefixed by "?".
     * - If a fragment is present, it MUST be prefixed by "#".
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.1
     * @return string
     */
    public function __toString(): string
    {
        if ($this->uriString !== null) {
            return $this->uriString;
        }

        $this->uriString = static::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->getPath(),
            $this->query,
            $this->fragment
        );

        return $this->uriString;
    }

    /**
     * @param $scheme
     * @param $authority
     * @param $path
     * @param $query
     * @param $fragment
     * @return string
     */
    private static function createUriString($scheme, $authority, $path, $query, $fragment): string
    {
        $uri = '';

        if (!empty($scheme)) {
            $uri .= $scheme . ':';
        }

        if (!empty($authority)) {
            $uri .= '//';
            if ('file' === $scheme && 0 === strpos($authority, 'localhost')) {
                $authority = substr($authority, 9);
            }
            $uri .= $authority;
        }

        if ($path) {
            if (empty($path) || '/' !== substr($path, 0, 1)) {
                $path = '/' . $path;
            }
            $uri .= $path;
        }

        if ($query) {
            $uri .= sprintf('?%s', $query);
        }

        if ($fragment) {
            $uri .= sprintf('#%s', $fragment);
        }

        return $uri;
    }

    /**
     * Retrieve the authority component of the URI.
     *
     * If no authority information is present, this method MUST return an empty
     * string.
     *
     * The authority syntax of the URI is:
     *
     * <pre>
     * [user-info@]host[:port]
     * </pre>
     *
     * If the port component is not set or is the standard port for the current
     * scheme, it SHOULD NOT be included.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2
     * @return string The URI authority, in "[user-info@]host[:port]" format.
     */
    public function getAuthority(): string
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;
        if (!empty($this->userInfo)) {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->isNonStandardPort($this->scheme, $this->host, $this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * Retrieve the path component of the URI.
     *
     * The path can either be empty or absolute (starting with a slash) or
     * rootless (not starting with a slash). Implementations MUST support all
     * three syntaxes.
     *
     * Normally, the empty path "" and absolute path "/" are considered equal as
     * defined in RFC 7230 Section 2.7.3. But this method MUST NOT automatically
     * do this normalization because in contexts with a trimmed base path, e.g.
     * the front controller, this difference becomes significant. It's the task
     * of the user to handle both "" and "/".
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode
     * any characters. To determine what characters to encode, please refer to
     * RFC 3986, Sections 2 and 3.3.
     *
     * As an example, if the value should include a slash ("/") not intended as
     * delimiter between path segments, that value MUST be passed in encoded
     * form (e.g., "%2F") to the instance.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.3
     * @return string The URI path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     *
     */
    public function __clone()
    {
        $this->uriString = null;
    }

    /**
     * @param array $matches
     * @return string
     */
    private function urlEncodeChar(array $matches): string
    {
        return rawurlencode($matches[0]);
    }
}
