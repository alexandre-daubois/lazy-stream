<?php

namespace LazyStream;

use LazyStream\Exception\LazyStreamOpenException;

abstract class AbstractLazyStream
{
    /**
     * @param resource|null $handle
     */
    protected $handle;

    protected ?array $metadata = null;

    public function __construct(
        protected string $uri,
        protected string $openingMode,
    ) {
    }

    public function __destruct()
    {
        $this->closeStream();
    }

    protected function openStream(): void
    {
        if (!\is_resource($this->handle)) {
            $this->handle = @\fopen($this->uri, $this->openingMode);

            if ($this->handle === false) {
                throw new LazyStreamOpenException($this->uri, $this->openingMode);
            }

            $this->metadata = \stream_get_meta_data($this->handle);
        }
    }

    protected function closeStream(): void
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }

        $this->handle = null;
    }

    /**
     * @return resource|null
     */
    public function getStreamHandle()
    {
        return $this->handle;
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
}
