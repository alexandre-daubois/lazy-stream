<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream\Tests;

use LazyStream\LazyStreamWriter;
use PHPUnit\Framework\TestCase;

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
        })());

        $lazyStream->trigger();
        $handle = $lazyStream->getStreamHandle();

        $this->assertNotNull($handle);
        \rewind($handle);
        $this->assertSame('chunkchunk', stream_get_contents($handle));
        $this->assertSame('return_value', $lazyStream->getProviderReturn());

        // Generator should be closed
        $this->assertFalse($generator->valid());
    }
}
