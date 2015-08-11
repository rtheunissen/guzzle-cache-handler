<?php

namespace Concat\Http\Handler;

use BadMethodCallException;
use GuzzleHttp\Stream\StreamInterface;

/**
 * A stream that wraps around a string value.
 */
class CachedStream implements StreamInterface
{
    /**
     * @var string $content
     */
    private $content;

    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * Attempts to seek to the beginning of the stream and reads all data into
     * a string until the end of the stream is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->content;
    }

    /**
     * Closes the stream and any underlying resources.
     */
    public function close()
    {
        throw new BadMethodCallException();
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the underlying resource has been detached, the stream object is in
     * an unusable state. If you wish to use a Stream object as a PHP stream
     * but keep the Stream object in a consistent state, use
     * {@see GuzzleHttp\Stream\GuzzleStreamWrapper::getResource}.
     *
     * @return resource|null Returns the underlying PHP stream resource or null
     *                       if the Stream object did not utilize an underlying
     *                       stream resource.
     */
    public function detach()
    {
        throw new BadMethodCallException();
    }

    /**
     * Replaces the underlying stream resource with the provided stream.
     *
     * Use this method to replace the underlying stream with another; as an
     * example, in server-side code, if you decide to return a file, you
     * would replace the original content-oriented stream with the file
     * stream.
     *
     * Any internal state such as caching of cursor position should be reset
     * when attach() is called, as the stream has changed.
     *
     * @param resource $stream
     *
     * @return void
     */
    public function attach($stream)
    {
        throw new BadMethodCallException();
    }

    /**
     * Get the size of the stream if known
     *
     * @return int|null Returns the size in bytes if known, or null if unknown
     */
    public function getSize()
    {
        return strlen($this->content);
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int|bool Returns the position of the file pointer or false on error
     */
    public function tell()
    {
        throw new BadMethodCallException();
    }
    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof()
    {
        throw new BadMethodCallException();
    }
    /**
     * Returns whether or not the stream is seekable
     *
     * @return bool
     */
    public function isSeekable()
    {
        return false;
    }
    /**
     * Seek to a position in the stream
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *                    based on the seek offset. Valid values are identical
     *                    to the built-in PHP $whence values for `fseek()`.
     *                    SEEK_SET: Set position equal to offset bytes
     *                    SEEK_CUR: Set position to current location plus offset
     *                    SEEK_END: Set position to end-of-stream plus offset
     *
     * @return bool Returns true on success or false on failure
     * @link   http://www.php.net/manual/en/function.fseek.php
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new BadMethodCallException();
    }
    /**
     * Returns whether or not the stream is writable
     *
     * @return bool
     */
    public function isWritable()
    {
        return false;
    }
    /**
     * Write data to the stream
     *
     * @param string $string The string that is to be written.
     *
     * @return int|bool Returns the number of bytes written to the stream on
     *                  success returns false on failure (e.g., broken pipe,
     *                  writer needs to slow down, buffer is full, etc.)
     */
    public function write($string)
    {
        throw new BadMethodCallException();
    }
    /**
     * Returns whether or not the stream is readable
     *
     * @return bool
     */
    public function isReadable()
    {
        return true;
    }
    /**
     * Read data from the stream
     *
     * @param int $length Read up to $length bytes from the object and return
     *                    them. Fewer than $length bytes may be returned if
     *                    underlying stream call returns fewer bytes.
     *
     * @return string     Returns the data read from the stream.
     */
    public function read($length)
    {
        return substr($this->content, 0, $length);
    }
    /**
     * Returns the remaining contents of the stream as a string.
     *
     * Note: this could potentially load a large amount of data into memory.
     *
     * @return string
     */
    public function getContents()
    {
        return $this->content;
    }
    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @param string $key Specific metadata to retrieve.
     *
     * @return array|mixed|null Returns an associative array if no key is
     *                          no key is provided. Returns a specific key
     *                          value if a key is provided and the value is
     *                          found, or null if the key is not found.
     * @see http://php.net/manual/en/function.stream-get-meta-data.php
     */
    public function getMetadata($key = null)
    {
        throw new BadMethodCallException();
    }
}
