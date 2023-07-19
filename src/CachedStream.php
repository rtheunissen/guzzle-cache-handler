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
    public function rewind(): void
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function detach(): void
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
    public function getSize(): ?int
    {
        return strlen($this->content);
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function write($string): int
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function read($length): string
    {
        return substr($this->content, 0, $length);
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
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
