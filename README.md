# LazyStream

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg?style=flat-square)](https://php.net/)
![CI](https://github.com/alexandre-daubois/lazy-stream/actions/workflows/php.yml/badge.svg)
[![Latest Unstable Version](http://poser.pugx.org/alexandre-daubois/lazy-stream/v/unstable)](https://packagist.org/packages/alexandre-daubois/lazy-stream)
[![License](http://poser.pugx.org/alexandre-daubois/lazy-stream/license)](https://packagist.org/packages/alexandre-daubois/lazy-stream)

LazyStream is a library that provides a convenient way to write lazily to streams using generators. It allows you to write data incrementally to a stream, reducing memory usage and improving performance when dealing with large amounts of data.

## Features

- **Lazy writing**: The library uses PHP generators to write data lazily to a stream. This means that data is written in small chunks, reducing memory consumption and allowing for efficient handling of large datasets.
- **Multi stream writing** (lazily of course): export large datasets to multiple files or network locations concurrently, like backups and logs.
- **Lazy opening**: your stream will never be opened before you actually need it.
- **Stream compatibility**: The library is compatible with various stream types, including file streams, network streams, and custom streams. You can easily integrate it with your existing code that relies on stream operations. It actually works with any type of stream, as long as it is registered thanks to `stream_wrapper_register()`. Follow this link for more information: https://www.php.net/manual/en/function.stream-wrapper-register.php
- **Stream modes**: the library only supported stream reading at this time. It is planned to support lazy stream reading and a convenient way to do so in the near future.

## Installation

You can install the LazyStream using Composer. Run the following command in your project directory:

```shell
composer require alexandre-daubois/lazy-stream
```

## Usage

### Writing lazily to a stream with `LazyStreamWriter`

#### Why using this writer?

The class allows you to write data to a stream incrementally, in small chunks, rather than loading the entire dataset into memory at once. This is especially beneficial when dealing with large amounts of data, as it reduces memory consumption. It also offers:

- **Stream compatibility and integration with third-party libraries**: the class is compatible with various stream types, including file streams, network streams, and custom streams. This flexibility allows you to seamlessly integrate it with your existing code that relies on stream operations, and allows you to leverage the benefits of lazy writing in conjunction with other libraries.
- **Auto-closing option**: the `LazyStreamWriter` provides an auto-closing option, which automatically flushes and closes the stream after the writing process is complete. This helps ensure proper resource management and simplifies your code by handling the stream cleanup automatically.
- **Customizable opening mode**: you can specify the opening mode for the stream, allowing you to define how the stream should be opened for writing. This flexibility enables you to choose the appropriate mode based on your specific requirements.
- **Exception handling**: the class handles exceptions related to stream opening and writing, providing informative error messages and facilitating proper error handling in your code.
- **Supports generator-based data providers**: the `LazyStreamWriter` accepts data providers implemented as generators, allowing you to generate or retrieve data on the fly while writing to the stream. This provides flexibility and versatility in handling dynamic data sources.

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

## The `MultiLazyStreamWriter` class

The MultiLazyStreamWriter is a core class in the LazyStream library. This class empowers you to write to multiple streams concurrently from any iterator, like a generator. This is particularly beneficial when you need to write large amounts of data to different locations in a memory-efficient manner.

This class is extremely helpful in situations where large amounts of data need to be written to multiple destinations:

- Logging systems: Write log data to multiple destinations like local files, network sockets, etc.
- Data export: Export large datasets to multiple files or network locations concurrently.
- Backup systems: Write backup data to multiple storage locations.

Here is a usage example:

```php
use LazyStream\MultiLazyStreamWriter;

class BackupProvider
{
    public function provideData(): \Generator
    {
        // Yield backup data
    }
}

// Write your backups in many locations at once
$stream = new MultiLazyStreamWriter([
        'https://user:pass@example.com/backup.json',
        'gs://backup_path/backup.json',
        's3://backup_path/backup.json',
    ],
    (new BackupProvider())->provideData()
);

$stream->trigger();
```

## Reading lazily a stream with `LazyStreamReader`

Files are already read lazily by default: when you call `fread()`, you only fetch the number of bytes you asked for, not more.
`LazyStreamReader` does the same thing, but it also allows you to keep the stream open or not between reading operations.

#### Why using this reader?

The autoclose feature of the `LazyStreamReader` class offers several concrete use cases where it can be useful:

- **Automatic resource management**: When working with stream resources, such as files or network connections, it's important to properly close them to free up system resources. By enabling autoclose, you ensure that the stream is automatically closed after each read operation, avoiding potential resource leaks.
- **Iterative reading**: If you want to read data from a stream iteratively, performing sequential read operations, autoclose can simplify your code. After each read, the stream is automatically closed, and in the next read operation, it is reopened at the same position, allowing seamless iteration over the stream's data.
- **Asynchronous processing**: When working with asynchronous read operations or parallel tasks, autoclose can come in handy. After each read, the stream is closed, allowing other tasks to access the stream if needed. When the task is ready to perform the next read operation, the stream is automatically reopened.
- **Fine-grained memory management**: If you're dealing with large or long-lasting streams, it can be beneficial to close the stream after each read to release memory used by the read buffer. Autoclose enables fine-grained memory management by closing the stream immediately after each read operation.

By using the autoclose feature, you simplify resource management, facilitate iterative or asynchronous operations, and have better control over memory management when reading data from a stream.

By setting the `autoClose` option to `true` when creating a new `LazyStreamReader` object, you ask to close the stream after each reading operation and open it again when the next reading operation is triggered. You'll be resumed at the same position you were in the stream before closing it.

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

## Usage of third-party libraries with LazyStream

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
