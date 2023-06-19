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

    /**
     * @param string $uri A valid stream URI.
     * @param \Iterator $dataProvider The data provider that will be written to the stream.
     * @param string $openMode A valid writing mode listed in https://www.php.net/manual/fr/function.fopen.php.
     * @param bool $autoClose Whether the stream should be closed once the `trigger` method is done.
     */
    public function __construct(
        private string $uri,
        private \Iterator $dataProvider,
        private string $openMode = 'w',
        private bool $autoClose = true,
    ) {
    }

    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }
    }

    public function trigger(): void
    {
        if (!\is_resource($this->handle)) {
            $this->handle = @\fopen($this->uri, $this->openMode);

            if ($this->handle === false) {
                throw new LazyStreamWriterOpenException($this->uri, $this->openMode);
            }
        }

        try {
            while ($this->dataProvider->valid()) {
                $data = $this->dataProvider->current();

                \fwrite($this->handle, $data, \strlen($data));

                $this->dataProvider->next();
            }
        } catch (\Throwable $throwable) {
            throw new LazyStreamWriterTriggerException(previous: $throwable);
        } finally {
            if (\is_resource($this->handle) && $this->autoClose) {
                \fclose($this->handle);

                $this->handle = null;
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

        $this->handle = null;

        return \unlink($this->uri);
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
}
