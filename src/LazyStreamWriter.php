<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream;

use LazyStream\Exception\LazyStreamOpenException;
use LazyStream\Exception\LazyStreamWriterTriggerException;

/**
 * A class to write to a stream lazily. The stream is only opened when
 * the `trigger()` method is called. Data to write are provided by a
 * generator, allowing data to be generated on the fly if possible.
 */
class LazyStreamWriter extends AbstractLazyStream implements LazyStreamWriterInterface
{
    /**
     * @param string $uri A valid stream URI.
     * @param \Iterator $dataProvider The data provider that will be written to the stream.
     * @param string $openingMode A valid writing mode listed in https://www.php.net/manual/fr/function.fopen.php.
     * @param bool $autoClose Whether the stream should be closed once the `trigger` method is done.
     */
    public function __construct(
        string $uri,
        private \Iterator $dataProvider,
        string $openingMode = 'w',
        private bool $autoClose = true,
    ) {
        parent::__construct($uri, $openingMode);
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

    public function unlink(): bool
    {
        if (!\is_resource($this->handle)) {
            return true;
        }

        $this->closeStream();

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

    protected function closeStream(): void
    {
        if (\is_resource($this->handle)) {
            \fflush($this->handle);
        }

        parent::closeStream();
    }
}
