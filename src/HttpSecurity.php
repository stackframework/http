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

/**
 * Class HttpSecurity
 * @package Stack\Http
 */
final class HttpSecurity
{
    const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    /**
     * @var array
     */
    public static $validMethods = [
        'CONNECT' => true,
        'DELETE' => true,
        'GET' => true,
        'HEAD' => true,
        'OPTIONS' => true,
        'PATCH' => true,
        'POST' => true,
        'PUT' => true,
        'TRACE' => true,
        'PROPFIND' => true,
        'PROPPATCH' => true,
        'MKCOL' => true,
        'COPY' => true,
        'MOVE' => true,
        'LOCK' => true,
        'UNLOCK' => true,
        '#!ALPHA-1234&%' => true,
    ];
    /**
     * @var array
     */
    public static $allowedSchemes = [
        'http' => 80,
        'https' => 443,
    ];
    /**
     * @var array
     */
    private static $acceptedProtocolVersions = [
        '1.0' => true,
        '1.1' => true,
        '1.2' => true,
        '2.0' => true
    ];

    /**
     * HttpSecurity private constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param $value
     */
    public static function assertValid($value)
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException('Invalid header value');
        }
    }

    /**
     * @param $value
     * @return bool
     */
    public static function isValid($value)
    {
        $value = (string)$value;

        if (preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $value)) {
            return false;
        }
        if (preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $value)) {
            return false;
        }
        return true;
    }

    /**
     * @param $name
     */
    public static function assertValidName($name)
    {
        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid header name');
        }
    }

    /**
     * @param $version
     */
    public static function assertValidProtocolVersion($version)
    {
        if (!isset(self::$acceptedProtocolVersions[$version])) {
            throw new \InvalidArgumentException('Invalid HTTP version. Must be one of: 1.0, 1.1, 1.2 or 2.0');
        }
    }
}
