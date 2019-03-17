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

2. Enable the bundle in your `AppKernel.php`
```
$bundles = [
....
  new Kickin\ExceptionHandlerBundle\KickinExceptionHandlerBundle(),
];
```

3. Create the `exception_mailer` swiftmailer instance. For example:
```yml
swiftmailer:
    default_mailer: default
    mailers:
      default:
        transport: "%mailer_transport%"
        host:      "%mailer_host%"
        username:  "%mailer_user%"
        password:  "%mailer_password%"
        spool: { type: memory }
      exception_mailer:
        transport: "%mailer_transport%"
        host:      "%mailer_host%"
        username:  "%mailer_user%"
        password:  "%mailer_password%"
        spool: { type: memory }
```

4. Implement your custom configuration service. This should implement the `Configuration\ConfigurationInterface`. It will
 then be autowired to the `ExceptionHandler` if you have set `container.autowiring.strict_mode` to false. Otherwise (default in Symfony >=4.0), alias the `Kickin\ExceptionHandlerBundle\Configuration\ConfigurationInterface` service to your custom configuration service. You can check an example implementation [here](Resources/doc/configuration-example.md).

6. (Optional) If you don't use autowiring, or need manual configuration for your configuration service, configure your
configuration service in the regular way. Include the new class in your own `services.yml`, and configure the service as you like:
```yml
services:
    <your_class_here>:
        arguments:
          $cacheDir: '%kernel.cache_dir%'
          <argument>: '@someservice'
```

That should be it, happy exception mailing!

## Contributors

The original functionality has been created by WendoB, while BobV splitted the codebase into a separate bundle making
it configurable for more users.


## Problems

If you experience any problems, do not hesitate to create an issue (or PR if you're able to fix it)!
