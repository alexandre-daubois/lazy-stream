<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use LazyStream\Exception\LazyStreamOpenException;
use LazyStream\LazyStreamReader;
use LazyStream\LazyStreamWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LazyStreamReader::class)]
class LazyStreamReaderTest extends TestCase
{
    public function testStreamIsLazilyOpened(): void
    {
        $lazyStream = new LazyStreamReader('php://memory', 2);

        $this->assertNull($lazyStream->getStreamHandle());
    }

    public function testReadStreamWithoutAutoclose(): void
    {
        $handle = tmpfile();
        $uri = \stream_get_meta_data($handle)['uri'];
        \fwrite($handle, 'chunk');

        $lazyStream = new LazyStreamReader($uri, 2, autoClose: false);

        $finalStr = '';
        foreach ($lazyStream as $str) {
            $finalStr .= $str;

            $this->assertLessThanOrEqual(2, \strlen($str));
            $this->assertNotNull($lazyStream->getStreamHandle());
        }

        $this->assertSame('chunk', $finalStr);
        \unlink($uri);
    }

    public function testReadStreamWithAutoclose(): void
    {
        $handle = tmpfile();
        $uri = \stream_get_meta_data($handle)['uri'];
        \fwrite($handle, 'chunky');

        $lazyStream = new LazyStreamReader($uri, 2, autoClose: true);

        $finalStr = '';
        foreach ($lazyStream as $str) {
            $finalStr .= $str;

            $this->assertLessThanOrEqual(2, \strlen($str));
            $this->assertNull($lazyStream->getStreamHandle());
        }

        $this->assertSame('chunky', $finalStr);
        \unlink($uri);
    }

    public function testReadStreamAndGetPosition(): void
    {
        $handle = tmpfile();
        $uri = \stream_get_meta_data($handle)['uri'];
        \fwrite($handle, 'chunk');

        $lazyStream = new LazyStreamReader($uri, 3, autoClose: false);

        $this->assertSame('chu', $lazyStream->getIterator()->current());
        $this->assertSame(3, $lazyStream->getStreamPosition());
        $this->assertNotNull($lazyStream->getStreamHandle());

        \unlink($uri);
    }

    public function testInvalidStream(): void
    {
        $lazyStream = new LazyStreamWriter('php://invalid', new \ArrayIterator([]));

        $this->expectException(LazyStreamOpenException::class);
        $this->expectExceptionMessage('Unable to open "php://invalid" with mode "w".');
        $lazyStream->trigger();
    }
}
