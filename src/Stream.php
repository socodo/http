<?php

namespace Socodo\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Socodo\Http\Exceptions\UnreachableStreamException;
use TypeError;

class Stream implements StreamInterface
{
    /** @var resource|null Resource reference. */
    protected $stream;

    /** @var bool Is seekable. */
    protected bool $seekable;

    /** @var bool Is readable. */
    protected bool $readable;

    /** @var bool Is writable. */
    protected bool $writable;

    /** @var ?int Stream size. */
    protected ?int $size = null;

    /** @var mixed|null Stream URI. */
    protected mixed $uri;

    /** @var array<string> Hashes that allows to read. */
    private const READ_HASHES = [
        'r', 'w+' ,'r+', 'x+', 'c+',
        'rb', 'w+b', 'r+b', 'x+b', 'c+b',
        'rt', 'w+t', 'r+t', 'x+t', 'c+t',
        'a+'
    ];

    /** @var array<string> Hashes that allows to write. */
    private const WRITE_HASHES = [
        'w', 'w+', 'rw', 'r+', 'x+', 'c+',
        'wb', 'w+b', 'r+b', 'x+b', 'c+b',
        'w+t', 'r+t', 'x+t', 'c+t', 'a', 'a+'
    ];

    /**
     * Constructor.
     *
     * @param resource|string $stream
     */
    public function __construct(mixed $stream)
    {
        if (is_string($stream))
        {
            $resource = fopen('php://temp', 'rw+');
            fwrite($resource, $stream);
            rewind($resource);
            $stream = $resource;
        }

        if (!is_resource($stream))
        {
            throw $this->createTypeError('__construct', 1, 'stream', 'resource|string', gettype($stream));
        }

        $this->stream = $stream;

        $meta = stream_get_meta_data($stream);
        $this->seekable = $meta['seekable'];
        $this->readable = in_array($meta['mode'], self::READ_HASHES);
        $this->writable = in_array($meta['mode'], self::WRITE_HASHES);
        $this->uri = $meta['uri'] ?? false;
    }

    /**
     * Destructor.
     */
    public function __destruct ()
    {
        $this->close();
    }

    /**
     * Get as string.
     *
     * @return string
     */
    public function __toString ()
    {
        if ($this->seekable)
        {
            $this->rewind();
        }

        return $this->getContents();
    }

    /**
     * Close the stream.
     *
     * @return void
     */
    public function close (): void
    {
        if (isset($this->stream))
        {
            if(is_resource($this->stream))
            {
                fclose($this->stream);
            }

            $this->detach();
        }
    }

    /**
     * Detach resource from the stream.
     *
     * @return mixed
     */
    public function detach (): mixed
    {
        if (!isset($this->stream))
        {
            return null;
        }

        $result = $this->stream;

        unset($this->stream);
        $this->size = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    /**
     * Read the stream.
     *
     * @param $length
     * @return string
     */
    public function read ($length): string
    {
        if (!isset($this->stream))
        {
            throw new UnreachableStreamException('Unable to read from detached stream.');
        }

        if (!$this->readable)
        {
            throw new UnreachableStreamException('Unable to read from non-readable stream.');
        }

        if ($length < 0)
        {
            throw new InvalidArgumentException('Socodo\\Http\\Stream::read() Argument #1 ($length) must be positive int, ' . $length . ' given.');
        }

        if ($length == 0)
        {
            return '';
        }

        $content = fread($this->stream, $length);
        if ($content === false)
        {
            throw new UnreachableStreamException('Unable to read from stream.');
        }

        return $content;
    }

    /**
     * Get contents from the stream.
     *
     * @return string
     */
    public function getContents (): string
    {
        if (!isset($this->stream))
        {
            throw new UnreachableStreamException('Unable to read from detached stream.');
        }

        if (!$this->readable)
        {
            throw new UnreachableStreamException('Unable to read from non-readable stream.');
        }

        return stream_get_contents($this->stream);
    }

    /**
     * Write into stream.
     *
     * @param $string
     * @return int
     */
    public function write ($string): int
    {
        if (!isset($this->stream))
        {
            throw new UnreachableStreamException('Unable to write into detached stream.');
        }

        if (!$this->writable)
        {
            throw new UnreachableStreamException('Unable to write into non-writable stream.');
        }

        $this->size = null;
        $result = fwrite($this->stream, $string);

        if ($result === false)
        {
            throw new UnreachableStreamException('Unable to write into stream.');
        }

        return $result;
    }

    /**
     * Seek stream.
     *
     * @param $offset
     * @param int $whence
     * @return void
     */
    public function seek ($offset, $whence = SEEK_SET): void
    {
        if (!isset($this->stream))
        {
            throw new UnreachableStreamException('Unable to seek detached stream.');
        }

        if (!$this->seekable)
        {
            throw new UnreachableStreamException('Unable to seek non-seekable stream.');
        }

        if (!is_int($offset))
        {
            throw $this->createTypeError('seek', 1, 'offset', 'int', gettype($offset));
        }

        if (!is_int($whence))
        {
            throw $this->createTypeError('seek', 2, 'whence', 'int', gettype($whence));
        }

        if (fseek($this->stream, $offset, $whence) === -1)
        {
            throw new RuntimeException('Unable to seek stream position ' . $offset . ' with whence ' . var_export($whence, true));
        }
    }

    /**
     * Rewind stream.
     *
     * @return void
     */
    public function rewind (): void
    {
        $this->seek(0);
    }

    /**
     * Tell stream.
     *
     * @return int
     */
    public function tell (): int
    {
        if (!isset($this->stream))
        {
            throw new UnreachableStreamException('Unable to tell detached stream.');
        }

        $result = ftell($this->stream);
        if ($result === false)
        {
            throw new UnreachableStreamException('Unable to tell stream.');
        }

        return $result;
    }

    /**
     * Determine if the stream is eof.
     *
     * @return bool
     */
    public function eof (): bool
    {
        if (!isset($this->stream))
        {
            throw new UnreachableStreamException('Unable to determine eof from detached stream.');
        }

        return feof($this->stream);
    }

    /**
     * Determine if the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable (): bool
    {
        return $this->seekable;
    }

    /**
     * Determine if the stream is readable.
     *
     * @return bool
     */
    public function isReadable (): bool
    {
        return $this->readable;
    }

    /**
     * Determine if the stream is writable.
     *
     * @return bool
     */
    public function isWritable (): bool
    {
        return $this->writable;
    }

    /**
     * Get resource size from the stream.
     *
     * @return int|null
     */
    public function getSize (): ?int
    {
        if ($this->size !== null)
        {
            return $this->size;
        }

        if (!isset($this->stream))
        {
            return null;
        }

        if ($this->uri)
        {
            clearstatcache(true, $this->uri);
        }

        $stats = fstat($this->stream);
        if (is_array($stats) && isset($stats['size']))
        {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    /**
     * Get metadata.
     *
     * @param $key
     * @return array|mixed|null
     */
    public function getMetadata ($key = null): mixed
    {
        if (!isset($this->stream))
        {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->stream);
        if ($key === null)
        {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    /**
     * Create a type error.
     *
     * @param string $methodName
     * @param int $argumentPos
     * @param string $argumentName
     * @param string $expectedType
     * @param string $givenType
     * @return TypeError
     */
    private function createTypeError (string $methodName, int $argumentPos, string $argumentName, string $expectedType, string $givenType): TypeError
    {
        return new TypeError('Socodo\\Http\\Stream::' . $methodName . '() Argument #' . $argumentPos . ' ($' . $argumentName . ') must be of type ' . $expectedType . ', ' . $givenType . ' given.');
    }
}