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
 * to multiple streams at once with the same data provider.
 */
class MultiLazyStreamWriter implements LazyStreamWriterInterface
{
    /**
     * @var resource[]
     */
    private array $handles = [];

    /**
     * @var array<string, array<string, string>>|null Array of all metadata of streams, indexed by URI.
     */
    private ?array $metadata = null;

    /**
     * @param array $uris Valid stream URIs.
     * @param \Iterator $dataProvider The data provider that will be written to the streams.
     * @param string $openingMode A valid writing mode listed in https://www.php.net/manual/fr/function.fopen.php.
     * @param bool $autoClose Whether the stream should be closed once the `trigger` method is done.
     */
    public function __construct(
        private array $uris,
        private \Iterator $dataProvider,
        private string $openingMode = 'w',
        private bool $autoClose = false,
    ) {
        foreach (\array_unique($this->uris) as $uri) {
            $this->handles[$uri] = null;
        }
    }

    public function __destruct()
    {
        $this->closeAllStreams();
    }

    public function trigger(): void
    {
        $this->openAllStreams();

        try {
            while ($this->dataProvider->valid()) {
                $data = $this->dataProvider->current();

                foreach ($this->handles as $uri => $handle) {
                    if (false === \fwrite($handle, $data)) {
                        throw new LazyStreamWriterTriggerException(sprintf('Unable to write to stream with URI "%s".', $uri));
                    }
                }

                $this->dataProvider->next();
            }
        } catch (\Throwable $throwable) {
            throw new LazyStreamWriterTriggerException(previous: $throwable);
        } finally {
            if ($this->autoClose) {
                $this->closeAllStreams();
            }
        }
    }


    /**
     * @return array Stream meta-data array indexed by URIs, then
     * by keys given in https://www.php.net/manual/en/function.stream-get-meta-data.php.
     */
    public function getMetadata(): array
    {
        if ($this->metadata === null) {
            // If metadata is null, then we never opened the stream yet
            $this->openAllStreams();
            $this->closeAllStreams();
        }

        return $this->metadata;
    }

    public function getHandles(): array
    {
        return $this->handles;
    }

    protected function openAllStreams(): void
    {
        if (!empty($this->handles)) {
            $this->closeAllStreams();
        }

        foreach ($this->uris as $uri) {
            $handle = @\fopen($uri, $this->openingMode);

            if ($handle === false) {
                throw new LazyStreamOpenException($uri, $this->openingMode);
            }

            $this->handles[$uri] = $handle;
            $this->metadata[$uri] = \stream_get_meta_data($handle);
        }
    }

    protected function closeAllStreams(): void
    {
        foreach ($this->handles as $handle) {
            if (\is_resource($handle)) {
                \fflush($handle);
                \fclose($handle);
            }
        }

        $this->handles = [];
    }
}
