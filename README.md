# ExceptionHandlerBundle
This bundles integrates an simple Exception Handler in your Symfony Application, which is capable of mailing the exact problem. 

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

4. Implement your custom configuration class. This should implement the `Configuration\ConfigurationInterface`. For
an implementation example, have a look at `Configuration\EmptyConfiguration`. Note that the custom file is required:
it is checked whether the `EmptyConfiguration` is still loaded, which will make this bundle throw an exception if that
is the case.

5. Register your configuration class in the kernel parameters (either `parameters.yml` or `config.yml`):
```yml
parameters:
    kickin.exceptionhandler.configuration.class: <your_class_here>
```

6. (Optional) By default, the default configuration doesn't include access to other services in the `Configuration` class.
Should you require these, include the new class in your own `services.yml` to decorate the default service. If you use autowiring, you're done.
Otherwise, configure the arguments of your class here as well.
```yml
services:
    <your_class_here>:
        decorates: kickin.exceptionhandler.configuration
```

That should be it, happy exception mailing!

## Contributors

The original functionality has been created by WendoB, while BobV splitted the codebase into a separate bundle making
it configurable for more users.


## Problems

If you experience any problems, do not hesitate to create an issue (or PR if you're able to fix it)!
