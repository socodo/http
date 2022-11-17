<?php

namespace Socodo\Http\CookieJars;

use Psr\Http\Message\StreamInterface;
use Socodo\Http\Interfaces\CookieJarInterface;

class StreamCookieJar extends JsonCookieJar implements CookieJarInterface
{
    /** @var StreamInterface Stream. */
    protected StreamInterface $stream;

    /**
     * Constructor.
     *
     * @param StreamInterface $stream
     */
    public function __construct (StreamInterface $stream)
    {
        $this->stream = $stream;

        $content = $stream->getContents();
        parent::__construct($content);
    }

    /**
     * Save to stream.
     * 
     * @return void
     */
    public function save (): void
    {
        parent::save();

        $this->stream->rewind();
        $this->stream->write($this->json);
    }
}