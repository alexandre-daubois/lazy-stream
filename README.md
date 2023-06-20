# LazyStream

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg?style=flat-square)](https://php.net/)
![CI](https://github.com/alexandre-daubois/lazy-stream/actions/workflows/php.yml/badge.svg)
[![Latest Unstable Version](http://poser.pugx.org/alexandre-daubois/lazy-stream/v/unstable)](https://packagist.org/packages/alexandre-daubois/lazy-stream)
[![License](http://poser.pugx.org/alexandre-daubois/lazy-stream/license)](https://packagist.org/packages/alexandre-daubois/lazy-stream)

LazyStream is a library that provides a convenient way to write lazily to streams using generators. It allows you to write data incrementally to a stream, reducing memory usage and improving performance when dealing with large amounts of data.

## Features

- Lazy writing: The library uses PHP generators to write data lazily to a stream. This means that data is written in small chunks, reducing memory consumption and allowing for efficient handling of large datasets.
- Lazy opening: your stream will never be opened before you actually need it.
- Stream compatibility: The library is compatible with various stream types, including file streams, network streams, and custom streams. You can easily integrate it with your existing code that relies on stream operations. It actually works with any type of stream, as long as it is registered thanks to `stream_wrapper_register()`. Follow this link for more information: https://www.php.net/manual/en/function.stream-wrapper-register.php
- Stream modes: the library only supported stream reading at this time. It is planned to support lazy stream reading and a convenient way to do so in the near future.

## Installation

You can install the LazyStream using Composer. Run the following command in your project directory:

```shell
composer require alexandre-daubois/lazy-stream
```

## Usage

### Writing lazily to a stream with `LazyStreamWriter`

```php
function provideJsonData(): \Generator
{
    yield '[';

    while (/** ... */) {
        $data = fetchSomewhere();

        yield sprintf('{"id": %d}', $data['id']);
    }

    return true;
}

// The stream is not opened yet, in case you never need it
$stream = new \LazyStream\LazyStreamWriter(
    'https://user:pass@example.com/my-file.json',
    provideJsonData()
);

// Trigger the stream to *actually* initiate connection
// and unwrap the iterator
$stream->trigger();

// Fetch stream's metadata, which will also be done lazily. It is
// *not* required to call `trigger()` to get those data.
$metadata = $stream->getMetadata();
```

### Configuring `LazyStreamWriter` behavior

A few options are available to configure how à lazy stream should behave:

- Opening mode: this allows to define the mode that will be used to open the stream. Any writing mode [listed here](https://www.php.net/manual/en/function.fopen.php) can be used.
- Auto-closing: whether the stream should be automatically flushed and closed at the end of the `trigger()` method. If set to false, the stream will be flushed and closed in any case when the `LazyStreamWriter` object is destroyed.

## Usage with third-party libraries

This library also works well with third-party libraries. For example, you can combine it with the [google/cloud-storage](https://packagist.org/packages/google/cloud-storage) package to write big files to your buckets without having to worry about memory problems (among other things).

Indeed, Google Cloud Storage package includes a way to register a Google Storage stream wrapper and use it with the `gs://` protocol. Here is an example with this library:

```php
use Google\Cloud\Storage\StorageClient;

class GoogleCloudStorageLazyStreamFactory
{
    private StorageClient $storageClient;

    public function __construct(string $serviceAccountPath, string $projectId)
    {
        // Pass your service account file and other needed information
        $this->storageClient = new StorageClient([
            'keyFilePath' => $serviceAccountPath,
            'projectId' => $projectId,
        ]);

        // This is the key to register the new `gs://` protocol
        $this->storageClient->registerStreamWrapper();
    }

    public function __destruct()
    {
        // Optionally, unregister wrapper once the factory is destroyed
        $this->storageClient->unregisterStreamWrapper();
    }

    public function createLazyStream(string $bucket, string $path, \Generator $generator): LazyStream
    {
        // You can then create a new LazyStream with this protocol
        // and stream big files to your bucket
        return new LazyStream(sprintf('gs://%s/%s', $bucket, $path), $generator);
    }
}
```

### Reading lazily a stream with `LazyStreamReader`

Files are already read lazily by default: when you call `fread()`, you only fetch the number of bytes you asked for, not more.
`LazyStreamReader` does the same thing, but it also allows you to keep the stream open or not between reading operations.

For example, you may want to read a file 1MB by 1MB, and do some processing that may take some time each time. By setting the `autoClose` option to `true` when creating a new `LazyStreamReader` object, you ask to close the stream after each reading operation and open it again when the next reading operation is triggered. You'll be resumed at the same position you were in the stream before closing it.

```php
// The stream is not opened yet, in case you never need it
$stream = new \LazyStream\LazyStreamReader('https://user:pass@example.com/my-file.png', chunkSize: 1024, autoClose: true, binary: true);

// Use the stream directly in the loop
foreach ($stream as $str) {
    // With auto-closing, the stream is already closed here. You can
    // do any long operation, and the stream will be opened again when
    // you get in the next loop iteration
}
```
