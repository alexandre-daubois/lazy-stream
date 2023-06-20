<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream\Tests;

use LazyStream\Exception\LazyStreamWriterOpenException;
use LazyStream\Exception\LazyStreamWriterTriggerException;
use LazyStream\LazyStreamWriter;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * @covers \LazyStream\LazyStreamWriter
 */
class LazyStreamWriterTest extends TestCase
{
    public function testEqualsDifferentGenerators(): void
    {
        $lazyStream = new LazyStreamWriter('php://memory', (static fn (): \Generator => yield null)());
        $other = new LazyStreamWriter('php://memory', (static fn (): \Generator => yield null)());

        $this->assertFalse($lazyStream->equals($other));
    }

    public function testEqualsDifferentUri(): void
    {
        $generator = (static fn (): \Generator => yield null)();

        $lazyStream = new LazyStreamWriter('php://memory', $generator);
        $other = new LazyStreamWriter('php://temp', $generator);

        $this->assertFalse($lazyStream->equals($other));
    }

    public function testEquals(): void
    {
        $generator = (static fn (): \Generator => yield null)();

        $lazyStream = new LazyStreamWriter('php://memory', $generator);
        $other = new LazyStreamWriter('php://memory', $generator);

        $this->assertTrue($lazyStream->equals($other));
    }

    public function testStreamIsLazilyOpened(): void
    {
        $lazyStream = new LazyStreamWriter('php://memory', (static fn (): \Generator => yield null)());

        $this->assertNull($lazyStream->getStreamHandle());
    }

    public function testTriggerStreams(): void
    {
        $lazyStream = new LazyStreamWriter('php://memory', $generator = (static function (): \Generator {
            yield 'chunk';

            yield 'chunk';

            return 'return_value';
        })(), autoClose: false);

        $lazyStream->trigger();
        $handle = $lazyStream->getStreamHandle();

        $this->assertNotNull($handle);
        \rewind($handle);
        $this->assertSame('chunkchunk', stream_get_contents($handle));

        // Generator should be closed
        $this->assertFalse($generator->valid());
    }

    public function testInvalidStream(): void
    {
        $lazyStream = new LazyStreamWriter('php://invalid', new \ArrayIterator([]));

        $this->expectException(LazyStreamWriterOpenException::class);
        $this->expectExceptionMessage('Unable to open "php://invalid" with mode "w".');
        $lazyStream->trigger();
    }

    public function testTriggersThrowsOnUnwrappingWithAutoClose(): void
    {
        $expectedException = new \Exception();

        $lazyStream = new LazyStreamWriter('php://memory', (static function () use ($expectedException): \Generator {
            yield 'data';

            throw $expectedException;
        })());

        $this->expectExceptionObject(new LazyStreamWriterTriggerException(previous: $expectedException));
        try {
            $lazyStream->trigger();
        } catch (\Exception $exception) {
            $this->assertNull($lazyStream->getStreamHandle());

            throw $exception;
        }
    }

    public function testTriggersThrowsOnUnwrappingWithoutAutoClose(): void
    {
        $expectedException = new \Exception();

        $lazyStream = new LazyStreamWriter('php://memory', (static function () use ($expectedException): \Generator {
            yield 'data';

            throw $expectedException;
        })(), autoClose: false);

        $this->expectExceptionObject(new LazyStreamWriterTriggerException(previous: $expectedException));
        try {
            $lazyStream->trigger();
        } catch (\Exception $exception) {
            $this->assertNotNull($lazyStream->getStreamHandle());

            throw $exception;
        }
    }

    public function testGetType(): void
    {
        $lazyStream = new LazyStreamWriter('php://memory', new \ArrayIterator([]));

        $this->assertSame('MEMORY', $lazyStream->getMetadata()['stream_type']);
        $this->assertNull($lazyStream->getStreamHandle());
    }

    public function testGetTypeOnTriggeredStreamWithoutAutoclose(): void
    {
        $lazyStream = new LazyStreamWriter('php://memory', new \ArrayIterator([]), autoClose: false);

        $lazyStream->trigger();

        $this->assertSame('MEMORY', $lazyStream->getMetadata()['stream_type']);
        $this->assertNotNull($lazyStream->getStreamHandle());
    }

    public function testGetTypeOnTriggeredStreamWithAutoclose(): void
    {
        $lazyStream = new LazyStreamWriter('php://memory', new \ArrayIterator([]), autoClose: true);

        $lazyStream->trigger();
        $this->assertNull($lazyStream->getStreamHandle());

        $this->assertSame('MEMORY', $lazyStream->getMetadata()['stream_type']);
        $this->assertNull($lazyStream->getStreamHandle());
    }
}
