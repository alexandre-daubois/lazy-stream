<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream\Tests;

use LazyStream\Exception\LazyStreamOpenException;
use LazyStream\MultiLazyStreamWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultiLazyStreamWriter::class)]
class MultiLazyStreamWriterTest extends TestCase
{
    public function testStreamAreLazilyOpened(): void
    {
        $multiStream = new MultiLazyStreamWriter([
            'php://memory',
            'php://temp'
        ], (static fn(): \Generator => yield null)());

        foreach ($multiStream->getHandles() as $handle) {
            $this->assertNull($handle);
        }
    }

    public function testDestructClosesAllStreams(): void
    {
        $multiStream = new MultiLazyStreamWriter([
            'php://memory',
            'php://temp'
        ], (static fn(): \Generator => yield null)(), autoClose: false);

        $multiStream->trigger();
        $handles = $multiStream->getHandles();

        unset($multiStream);

        foreach ($handles as $handle) {
            $this->assertIsClosedResource($handle);
        }
    }

    public function testAutoClose(): void
    {
        $multiStream = new MultiLazyStreamWriter([
            'php://memory',
            'php://temp'
        ], (static fn(): \Generator => yield null)(), autoClose: true);

        $multiStream->trigger();

        $this->assertEmpty($multiStream->getHandles());
    }

    public function testTrigger(): void
    {
        $multiStream = new MultiLazyStreamWriter([
            'php://memory',
            'php://temp',
        ], (static fn(): \Generator => yield 'chunk')(), autoClose: false);

        $multiStream->trigger();

        foreach ($multiStream->getHandles() as $handle) {
            \rewind($handle);
            $this->assertSame('chunk', \stream_get_contents($handle));
        }
    }

    public function testInvalidStreamUri(): void
    {
        $multiStream = new MultiLazyStreamWriter(['invalid://memory'], (static fn(): \Generator => yield 'chunk')());

        $this->expectException(LazyStreamOpenException::class);
        $multiStream->trigger();
    }

    public function testInvalidStreamUriOnSecondPosition(): void
    {
        $multiStream = new MultiLazyStreamWriter(['php://memory', 'invalid://memory'], (static fn(): \Generator => yield 'chunk')());

        $this->expectException(LazyStreamOpenException::class);
        $multiStream->trigger();
    }

    public function testGetMetadata(): void
    {
        $multiStream = new MultiLazyStreamWriter([
            'php://memory',
            'php://temp',
        ], (static fn(): \Generator => yield 'chunk')());

        $metadata = $multiStream->getMetadata();
        $this->assertArrayHasKey('php://memory', $metadata);
        $this->assertSame('MEMORY', $metadata['php://memory']['stream_type']);

        $this->assertArrayHasKey('php://temp', $metadata);
        $this->assertSame('TEMP', $metadata['php://temp']['stream_type']);
    }
}
