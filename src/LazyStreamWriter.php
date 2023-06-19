<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream;

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

    public function __construct(
        private string $uri,
        private \Iterator $dataProvider
    ) {
    }

    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            \fflush($this->handle);

            \fclose($this->handle);
        }
    }

    public function trigger(): void
    {
        if (!\is_resource($this->handle)) {
            $this->handle = \fopen($this->uri, 'w');
        }

        while ($this->dataProvider->valid()) {
            $data = $this->dataProvider->current();

            \fwrite($this->handle, $data, \strlen($data));

            $this->dataProvider->next();
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
}
