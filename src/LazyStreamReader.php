<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream;

class LazyStreamReader extends AbstractLazyStream implements LazyStreamReaderInterface
{
    private int $position = 0;

    /**
     * @param string $uri A valid stream URI.
     * @param bool $autoClose Whether the stream should be closed between reading operations.
     */
    public function __construct(
        string $uri,
        private int $chunkSize,
        private bool $autoClose = true,
        private bool $binary = false,
    ) {
        parent::__construct($uri, $this->binary ? 'rb' : 'r');
    }

    public function getStreamPosition(): int
    {
        return $this->position;
    }

    public function isAutoClose(): bool
    {
        return $this->autoClose;
    }

    public function setAutoClose(bool $autoClose): void
    {
        $this->autoClose = $autoClose;
    }

    public function getIterator(): \Generator
    {
        yield from $this->read();
    }

    private function read(): \Generator
    {
        $this->openStream();

        while (($data = \fread($this->handle, $this->chunkSize)) !== false && \strlen($data) !== 0) {
            $this->position += $this->chunkSize;

            if ($this->autoClose) {
                $this->closeStream();
                yield $data;

                $this->openStream();
                \fseek($this->handle, $this->position);

                continue;
            }

            yield $data;
        }

        $this->closeStream();
    }
}
