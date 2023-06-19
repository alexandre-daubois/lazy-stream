<?php

namespace LazyStream\Exception;

class LazyStreamWriterOpenException extends AbstractLazyStreamWriterException
{
    public function __construct(string $uri, string $mode)
    {
        parent::__construct(sprintf('Unable to open "%s" with mode "%s".', $uri, $mode));
    }
}
