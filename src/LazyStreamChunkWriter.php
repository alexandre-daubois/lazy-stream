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
 * Implementation of the LazyStreamWriterInterface that allows writing data
 * to a stream by sending data when needed. The advantage of this class is that
 * data can be sent to the stream in a more "natural" order, where
 * {@see LazyStreamWriter} requires to provide a data provider iterator.
 */
class LazyStreamChunkWriter extends LazyStreamWriter
{
    private \Generator $dataProvider;
    protected string $openingMode = 'a';

    /**
     * @param string $uri A valid stream URI.
     * @param bool $autoClose Whether the stream should be closed once the `trigger` method is done.
     */
    public function __construct(
        protected string $uri,
        private bool $autoClose = false,
    ) {
        // no parent constructor call because we don't want to provide
        // an iterator as data provider

        $this->dataProvider = (function (): \Generator {
            while (true) {
                $data = yield;

                if (null === $this->handle) {
                    $this->openStream();
                }

                if (false === \fwrite($this->handle, $data)) {
                    throw new LazyStreamWriterTriggerException(sprintf('Unable to write to stream with URI "%s".', $this->metadata['uri']));
                }

                if ($this->autoClose) {
                    $this->closeStream();
                }
            }
        })();
    }

    /**
     * Sends data to the stream. If the stream is not open, it will be opened.
     */
    public function send(mixed $data): void
    {
        try {
            $this->dataProvider->send($data);
        } catch (LazyStreamOpenException $lazyStreamOpenException) {
            throw $lazyStreamOpenException;
        } catch (\Throwable $throwable) {
            throw new LazyStreamWriterTriggerException(previous: $throwable);
        } finally {
            if ($this->autoClose) {
                $this->closeStream();
            }
        }
    }

    /**
     * This method is not allowed to be called on this class. Use {@see self::send()} instead.
     */
    public function trigger(): never
    {
        throw new \LogicException(sprintf('You must provide data to write to the stream by calling "%s::send()".', __CLASS__));
    }

    public function equals(LazyStreamWriter $other): bool
    {
        return $this->uri === $other->uri;
    }
}
