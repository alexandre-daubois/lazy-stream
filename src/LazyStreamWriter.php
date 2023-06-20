<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream;

use LazyStream\Exception\LazyStreamWriterOpenException;
use LazyStream\Exception\LazyStreamWriterTriggerException;

/**
 * A class to write to a stream lazily. The stream is only opened when
 * the `trigger()` method is called. Data to write are provided by a
 * generator, allowing data to be generated on the fly if possible.
 */
class LazyStreamWriter implements LazyStreamWriterInterface
{
    /**
     * @param resource|null $handle
     */
    private $handle;

    private ?array $metadata = null;

    /**
     * @param string $uri A valid stream URI.
     * @param \Iterator $dataProvider The data provider that will be written to the stream.
     * @param string $openingMode A valid writing mode listed in https://www.php.net/manual/fr/function.fopen.php.
     * @param bool $autoClose Whether the stream should be closed once the `trigger` method is done.
     */
    public function __construct(
        private string $uri,
        private \Iterator $dataProvider,
        private string $openingMode = 'w',
        private bool $autoClose = true,
    ) {
    }

    public function __destruct()
    {
        $this->closeStream();
    }

    public function trigger(): void
    {
        $this->openStream();

        try {
            while ($this->dataProvider->valid()) {
                $data = $this->dataProvider->current();

                \fwrite($this->handle, $data, \strlen($data));

                $this->dataProvider->next();
            }
        } catch (\Throwable $throwable) {
            throw new LazyStreamWriterTriggerException(previous: $throwable);
        } finally {
            if ($this->autoClose) {
                $this->closeStream();
            }
        }
    }

    /**
     * @return resource|null
     */
    public function getStreamHandle()
    {
        return $this->handle;
    }

    public function unlink(): bool
    {
        if (!\is_resource($this->handle)) {
            return true;
        }

        $this->closeStream();

        return \unlink($this->uri);
    }

    /**
     * @return array Stream meta-data array indexed by keys given in https://www.php.net/manual/en/function.stream-get-meta-data.php.
     */
    public function getMetadata(): array
    {
        if ($this->metadata === null) {
            // If metadata is null, then we never opened the stream yet
            $this->openStream();
            $this->closeStream();
        }

        return $this->metadata;
    }

    public function equals(self $other): bool
    {
        return $this->dataProvider === $other->dataProvider && $this->uri === $other->uri;
    }

    public function isAutoClose(): bool
    {
        return $this->autoClose;
    }

    public function setAutoClose(bool $autoClose): void
    {
        $this->autoClose = $autoClose;
    }

    private function openStream(): void
    {
        if (!\is_resource($this->handle)) {
            $this->handle = @\fopen($this->uri, $this->openingMode);

            if ($this->handle === false) {
                throw new LazyStreamWriterOpenException($this->uri, $this->openingMode);
            }
        }

        $this->metadata = \stream_get_meta_data($this->handle);
    }

    private function closeStream(): void
    {
        if (\is_resource($this->handle)) {
            \fflush($this->handle);
            \fclose($this->handle);

            $this->handle = null;
        }
    }
}
