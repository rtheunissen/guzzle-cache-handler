<?php

namespace Concat\Http\Handler;

use BadMethodCallException;
use Psr\Http\Message\StreamInterface;

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
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function close()
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function detach()
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function attach($stream)
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function getSize()
    {
        return strlen($this->content);
    }

    /**
     * @inheritDoc
     */
    public function tell()
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function eof()
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function write($string)
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function isReadable()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function read($length)
    {
        return substr($this->content, 0, $length);
    }

    /**
     * @inheritDoc
     */
    public function getContents()
    {
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata($key = null)
    {
        throw new BadMethodCallException();
    }
}
