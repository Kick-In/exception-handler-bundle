# Upgrade notes

# 2.0

The 2.0 release marks the usage of autowiring for the configuration class. If you set the `container.autowiring.strict_mode` parameter to true (default for Symfony >=4.0), you need to alias the `Kickin\ExceptionHandlerBundle\Configuration\ConfigurationInterface` class to your configuration class. You can remove the `kickin.exceptionhandler.configuration.class` parameter from your configuration.

If you don't use autowiring, check the installation guide on what you need to register for the bundle to work.

# 1.1

The 1.1 release marks support for Symfony 4. It shouldn't require any changes
for you to work correctly.
