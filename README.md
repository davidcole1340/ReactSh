# ReactSh

ReactPHP event loop REPL. Inspired by PsySH but doesn't block any event loop.

## Usage

```php
$shell = new Shell();

$shell->run(get_defined_vars());
```

or

```php
$loop = ...;
$yourOtherProcess = new OtherProcess($loop);
$shell = new Shell($loop);

$yourOtherProcess->on('ready', function () use ($shell) {
    $shell->setContext(get_defined_vars());
});

$loop->run();
```

## License

GNU GPLv3. See [LICENSE](LICENSE).

## Credits

- [David Cole](mailto:david.cole1340@gmail.com)