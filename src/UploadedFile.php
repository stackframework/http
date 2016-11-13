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

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedFile
 * @package Stack\Http
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * @var string
     */
    private $clientFilename;

    /**
     * @var string
     */
    private $clientMediaType;

    /**
     * @var int
     */
    private $errorCode;

    /**
     * @var string
     */
    private $file;

    /**
     * @var bool
     */
    private $isMoved = false;

    /**
     * @var int
     */
    private $size;

    /**
     * @var StreamInterface|resource|string
     */
    private $stream;

    /**
     * UploadedFile constructor.
     * @param $streamOrFile
     * @param $size
     * @param $errorStatus
     * @param null $clientFilename
     * @param null $clientMediaType
     */
    public function __construct($streamOrFile, $size, $errorStatus, $clientFilename = null, $clientMediaType = null)
    {
        if ($errorStatus === UPLOAD_ERR_OK) {
            if (is_string($streamOrFile)) {
                $this->file = $streamOrFile;
            }

            if (is_resource($streamOrFile)) {
                $this->stream = new Stream($streamOrFile);
            }

            if (!$this->file && !$this->stream) {
                if (!$streamOrFile instanceof StreamInterface) {
                    throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
                }
                $this->stream = $streamOrFile;
            }
        }

        if (!is_int($size)) {
            throw new \InvalidArgumentException('Invalid size provided for UploadedFile; must be an int');
        }

        $this->size = $size;
        if (!is_int($errorStatus)
            || 0 > $errorStatus
            || 8 < $errorStatus
        ) {
            throw new \InvalidArgumentException(
                'Invalid error status for UploadedFile; must be an UPLOAD_ERR_* constant'
            );
        }

        $this->errorCode = $errorStatus;
        if (null !== $clientFilename && !is_string($clientFilename)) {
            throw new \InvalidArgumentException(
                'Invalid client filename provided for UploadedFile; must be null or a string'
            );
        }

        $this->clientFilename = $clientFilename;
        if (null !== $clientMediaType && !is_string($clientMediaType)) {
            throw new \InvalidArgumentException(
                'Invalid client media type provided for UploadedFile; must be null or a string'
            );
        }

        $this->clientMediaType = $clientMediaType;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Use this method as an alternative to move_uploaded_file(). This method is
     * guaranteed to work in both SAPI and non-SAPI environments.
     * Implementations must determine which environment they are in, and use the
     * appropriate method (move_uploaded_file(), rename(), or a stream
     * operation) to perform the operation.
     *
     * $targetPath may be an absolute path, or a relative path. If it is a
     * relative path, resolution should be the same as used by PHP's rename()
     * function.
     *
     * The original file or stream MUST be removed on completion.
     *
     * If this method is called more than once, any subsequent calls MUST raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \InvalidArgumentException if the $path specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     */
    public function moveTo($targetPath)
    {
        if ($this->errorCode !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }
        if (!is_string($targetPath)) {
            throw new \InvalidArgumentException(
                'Invalid path provided for move operation; must be a string'
            );
        }
        if (empty($targetPath)) {
            throw new \InvalidArgumentException(
                'Invalid path provided for move operation; must be a non-empty string'
            );
        }
        if ($this->isMoved) {
            throw new \RuntimeException('Cannot move file; already moved!');
        }
        $sapi = PHP_SAPI;
        switch (true) {
            case (empty($sapi) || 0 === strpos($sapi, 'cli') || !$this->file):
                // Non-SAPI environment, or no filename present
                $this->writeFile($targetPath);
                break;
            default:
                // SAPI environment, with file present
                if (false === move_uploaded_file($this->file, $targetPath)) {
                    throw new \RuntimeException('Error occurred while moving uploaded file');
                }
                break;
        }

        $this->isMoved = true;
    }

    /**
     * @param $path
     */
    private function writeFile($path)
    {
        $handle = fopen($path, 'wb+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to write to designated path');
        }

        $stream = $this->getStream();
        $stream->rewind();
        while (!$stream->eof()) {
            fwrite($handle, $stream->read(4096));
        }

        fclose($handle);
    }

    /**
     * Retrieve a stream representing the uploaded file.
     *
     * This method MUST return a StreamInterface instance, representing the
     * uploaded file. The purpose of this method is to allow utilizing native PHP
     * stream functionality to manipulate the file upload, such as
     * stream_copy_to_stream() (though the result will need to be decorated in a
     * native PHP stream wrapper to work with such functions).
     *
     * If the moveTo() method has been called previously, this method MUST raise
     * an exception.
     *
     * @return StreamInterface Stream representation of the uploaded file.
     * @throws \RuntimeException in cases when no stream is available or can be
     *     created.
     */
    public function getStream(): StreamInterface
    {
        if ($this->errorCode !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->isMoved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        $this->stream = new Stream($this->file);

        return $this->stream;
    }

    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, this method MUST return
     * UPLOAD_ERR_OK.
     *
     * Implementations SHOULD return the value stored in the "error" key of
     * the file in the $_FILES array.
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError(): int
    {
        return $this->errorCode;
    }

    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "name" key of
     * the file in the $_FILES array.
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "type" key of
     * the file in the $_FILES array.
     *
     * @return string|null The media type sent by the client or null if none
     *     was provided.
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }
}