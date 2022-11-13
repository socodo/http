<?php

namespace Socodo\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Socodo\Http\Exceptions\UnreachableUploadedFileException;
use TypeError;

class UploadedFile implements UploadedFileInterface
{
    /** @var StreamInterface|null File stream. */
    protected ?StreamInterface $stream;

    /** @var string|null File path. */
    protected ?string $filePath;

    /** @var string Original name of uploaded file. */
    protected string $clientFilename;

    /** @var string Given file media type from client. */
    protected string $clientMediaType;

    /** @var int File size. */
    protected int $size;

    /** @var int File uploading error code. */
    protected int $error;

    /** @var bool Is the file moved. */
    protected bool $moved = false;

    /**
     * Get file stream.
     *
     * @return StreamInterface
     */
    public function getStream (): StreamInterface
    {
        if ($this->error !== UPLOAD_ERR_OK)
        {
            throw $this->createUnreachableUploadedFileException('getStream', 'cannot retrieve stream due to upload error.', $this->error);
        }

        if ($this->moved)
        {
            throw $this->createUnreachableUploadedFileException('getStream', 'cannot retrieve stream after it has already been moved.');
        }

        if ($this->stream !== null)
        {
            return $this->stream;
        }

        $resource = fopen($this->filePath, 'r');
        $this->stream = new Stream($resource);
        return $this->stream;
    }

    /**
     * Move uploaded file to target path.
     *
     * @param $targetPath
     * @return void
     */
    public function moveTo ($targetPath): void
    {
        if ($this->error !== UPLOAD_ERR_OK)
        {
            throw $this->createUnreachableUploadedFileException('moveTo', 'cannot be moved due to upload error.', $this->error);
        }

        if ($this->moved)
        {
            throw $this->createUnreachableUploadedFileException('moveTo', 'cannot be moved after it has already been moved.');
        }

        if (!is_string($targetPath))
        {
            throw new TypeError('Socodo\\Http\\UploadedFile::moveTo() Argument #1 ($targetPath) must be of type string, ' . gettype($targetPath) . ' given.');
        }

        if ($targetPath === '')
        {
            throw new InvalidArgumentException('Socodo\\Http\\UploadedFile::moveTo() Argument #1 ($targetPath) must be a non-empty string, "' . $targetPath . '" given.');
        }

        if ($this->filePath !== null)
        {
            $this->moved = PHP_SAPI === 'cli' ? rename($this->filePath, $targetPath) : move_uploaded_file($this->filePath, $targetPath);
        }
        else
        {
            $stream = $this->stream;
            if ($stream->isSeekable())
            {
                $stream->rewind();
            }

            $resource = fopen($targetPath, 'w');
            $dest = new Stream($resource);

            while (!$stream->eof())
            {
                if (!$dest->write($stream->read(1048576)))
                {
                    break;
                }
            }

            $this->moved = true;
        }
    }

    /**
     * Get original file name from client request.
     *
     * @return string
     */
    public function getClientFilename (): string
    {
        return $this->clientFilename;
    }

    /**
     * Get file media type from client request.
     *
     * @return string
     */
    public function getClientMediaType (): string
    {
        return $this->clientMediaType;
    }

    /**
     * Get file size.
     *
     * @return int
     */
    public function getSize (): int
    {
        return $this->size;
    }

    /**
     * Get uploading error code.
     *
     * @return int
     */
    public function getError (): int
    {
        return $this->error;
    }

    /**
     * Create an unreachable uploaded file exception.
     *
     * @param string $method
     * @param string $message
     * @param int|null $code
     * @return UnreachableUploadedFileException
     */
    private function createUnreachableUploadedFileException (string $method, string $message, ?int $code = null): UnreachableUploadedFileException
    {
        return new UnreachableUploadedFileException('Socodo\\Http\\UploadedFile::' . $method . '() The file "' . $this->filePath . '" ' . $message, $code);
    }
}