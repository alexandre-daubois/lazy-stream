<?php

/*
 * (c) Alexandre Daubois <alex.daubois@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyStream;

interface LazyStreamWriterInterface
{
    public function trigger(): void;

    /**
     * @return bool True if the stream has been unlinked, false otherwise.
     */
    public function unlink(): bool;
}
