# ExceptionHandlerBundle
This bundles integrates an simple Exception Handler in your Symfony Application, which is capable of mailing the exact problem.

## Upgrading

See the [upgrade notes](UPGRADE.md).

## Installation

In order for this bundle to work, you are required to follow the following steps:

1. Install the bundle:
```
php composer.phar require kick-in/exception-handler-bundle
```

1. Enable the bundle in your `bundles.php` (if not done automatically)
```
$bundles = [
....
  Kickin\ExceptionHandlerBundle\KickinExceptionHandlerBundle::class => ['all' => true],
];
```

1. Implement your custom configuration service. This should implement the `Configuration\ConfigurationInterface`. It will
 then be autowired to the `ExceptionHandler` if you have set `container.autowiring.strict_mode` to false. Otherwise (default in Symfony >=4.0), alias the `Kickin\ExceptionHandlerBundle\Configuration\ConfigurationInterface` service to your custom configuration service. You can check an example implementation [here](Resources/doc/configuration-example.md).

That should be it, happy exception mailing!

## Contributors

The original functionality has been created by WendoB, while BobV splitted the codebase into a separate bundle making
it configurable for more users.


## Problems

If you experience any problems, do not hesitate to create an issue (or PR if you're able to fix it)!
