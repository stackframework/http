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

namespace Stack\Http\Stream;

use Stack\Http\Stream;

/**
 * Class PhpInputStream
 * @package Stack\Http\Stream
 */
class PhpInputStream extends Stream
{
    /**
     * @var string
     */
    private $cache = '';

    /**
     * @var bool
     */
    private $isEof = false;

    /**
     * PhpInputStream constructor.
     */
    public function __construct($stream = 'php://input', $mode = 'r')
    {
        parent::__construct($stream, $mode);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if ($this->isEof) {
            return $this->cache;
        }

        $this->getContents();

        return $this->cache;
    }

    /**
     * @param int $maxLength
     * @return string
     */
    public function getContents($maxLength = -1): string
    {
        if ($this->isEof) {
            return $this->cache;
        }

        $contents = stream_get_contents($this->resource, $maxLength);
        $this->cache .= $contents;

        if ($maxLength === -1 || $this->eof()) {
            $this->isEof = true;
        }

        return $contents;
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @param int $length
     * @return string
     */
    public function read($length): string
    {
        $content = parent::read($length);

        if ($content && !$this->isEof) {
            $this->cache .= $content;
        }

        if ($this->eof()) {
            $this->isEof = true;
        }

        return $content;
    }
}
