# ExceptionHandlerBundle
This bundles integrates an simple Exception Handler in your Symfony Application, 
which is capable of mailing the exact problem.

## Upgrading

See the [upgrade notes](UPGRADE.md).

## Installation

In order for this bundle to work, you are required to follow the following steps:

1. Install the bundle:
```
php composer.phar require kick-in/exception-handler-bundle
```

1. Enable the bundle in your `bundles.php` (if not done automatically)
```php
$bundles = [
  Kickin\ExceptionHandlerBundle\KickinExceptionHandlerBundle::class => ['all' => true],
];
```

1. Choose the mail backend in the configuration. For example, for SwiftMailer:
```yaml
kickin_exception_handler:
    mail_backend: 'swift' # One of "swift"; "swift_mailer"; "symfony"; "symfony_mailer"
```

1. Implement your custom configuration service. This should implement either 
   [`Configuration\SwiftMailerConfigurationInterface`](https://github.com/Kick-In/exception-handler-bundle/blob/master/Configuration/SwiftMailerConfigurationInterface.php)
   or  
   [`Configuration\SymfonyMailerConfigurationInterface`](https://github.com/Kick-In/exception-handler-bundle/blob/master/Configuration/SymfonyMailerConfigurationInterface.php)
   , depending on you mail backend choice.
   
   You can check a custom example implementation [here](Resources/doc/configuration-example.md).
    
1. Your configuration will be autowired to the correct ExceptionHandler if you have set `container.autowiring.strict_mode` to false.
   Otherwise, (default in Symfony >=4.0), alias the `Kickin\ExceptionHandlerBundle\Configuration\(Swift|Symfony)MailerConfigurationInterface` service to your custom configuration class.
   For example:
```yaml
 Kickin\ExceptionHandlerBundle\Configuration\SymfonyMailerConfigurationInterface:
   alias: 'App\ExceptionHandler\ExceptionHandlerConfiguration'
```

1. [SwiftMailer only] Create the `exception_mailer` SwiftMailer instance. For example:
```yaml
swiftmailer:
    default_mailer: default
    mailers:
      default:
        transport: "%mailer_transport%"
      exception_mailer:
        transport: "%mailer_transport%"
        spool: { type: memory }
```

That should be it, happy exception mailing!

## Contributors

The original functionality has been created by WendoB, while BobV splitted the codebase into a separate bundle making
it configurable for more users.

## Problems

If you experience any problems, do not hesitate to create an issue (or PR if you're able to fix it)!
