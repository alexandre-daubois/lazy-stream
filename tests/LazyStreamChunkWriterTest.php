<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


use LazyStream\Exception\LazyStreamOpenException;
use LazyStream\Exception\LazyStreamWriterTriggerException;
use LazyStream\LazyStreamChunkWriter;
use LazyStream\LazyStreamWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LazyStreamChunkWriter::class)]
class LazyStreamChunkWriterTest extends TestCase
{
    public function testEqualsDifferentStreams(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory');
        $other = new LazyStreamChunkWriter('php://input');

        $this->assertFalse($lazyStream->equals($other));
    }

    public function testEqualsSameUri(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory');
        $other = new LazyStreamChunkWriter('php://memory');

        $this->assertTrue($lazyStream->equals($other));
    }

    public function testStreamIsLazilyOpened(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory');

        $this->assertNull($lazyStream->getStreamHandle());
    }

    public function testTriggerStreamsThrows(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You must provide data to write to the stream by calling "LazyStream\LazyStreamChunkWriter::send()".');
        $lazyStream->trigger();
    }

    public function testInvalidStreamThrowsAtSend(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://invalid');

        $this->expectException(LazyStreamOpenException::class);
        $this->expectExceptionMessage('Unable to open "php://invalid" with mode "a".');
        $lazyStream->send('test');
    }

    public function testGetType(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory');

        $this->assertSame('MEMORY', $lazyStream->getMetadata()['stream_type']);
        $this->assertNull($lazyStream->getStreamHandle());
    }

    public function testGetTypeOnTriggeredStreamWithAutoclose(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory', autoClose: true);

        $this->assertSame('MEMORY', $lazyStream->getMetadata()['stream_type']);
        $this->assertNull($lazyStream->getStreamHandle());
    }

    public function testGetTypeOnTriggeredStreamWithoutAutoclose(): void
    {
        $lazyStream = new LazyStreamChunkWriter('php://memory');

        $lazyStream->send('test');
        $this->assertNotNull($lazyStream->getStreamHandle());

        $this->assertSame('MEMORY', $lazyStream->getMetadata()['stream_type']);
        $this->assertNotNull($lazyStream->getStreamHandle());
    }

    public function testSend(): void
    {
        $lazyStream = new LazyStreamChunkWriter(__DIR__.\DIRECTORY_SEPARATOR.__METHOD__);

        try {
            $lazyStream->send('test');
            $lazyStream->send('test2');
            $this->assertSame('testtest2', file_get_contents(__DIR__.\DIRECTORY_SEPARATOR.__METHOD__));
        } finally {
            \unlink(__DIR__.\DIRECTORY_SEPARATOR.__METHOD__);
        }
    }

    public function testSendWithAutoclose(): void
    {
        $lazyStream = new LazyStreamChunkWriter(__DIR__.\DIRECTORY_SEPARATOR.__METHOD__, true);

        try {
            $lazyStream->send('test');
            $lazyStream->send('test2');
            $this->assertSame('testtest2', file_get_contents(__DIR__.\DIRECTORY_SEPARATOR.__METHOD__));
        } finally {
            \unlink(__DIR__.\DIRECTORY_SEPARATOR.__METHOD__);
        }
    }
}
