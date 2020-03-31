# Upgrade notes

# 3.0
The 3.0 adds support for the Symfony mailer component, but applications using SwiftMailer are still supported.

For the Symfony mailer a separate mailer instance is not supported (as the mailer component also doesn't), but it does simplify the configuration.

To upgrade to this release, make sure to update to the new interface (when keeping SwiftMailer as mail backend): `ConfigurationInterface` must be replaced with `SwiftMailerConfigurationInterface` in both your service alias (if any) and your configuration class implementation.

# 2.1
In the 2.1 release support for Twig versions smaller than 2.7 was removed in order to add support for Twig 3

# 2.0

The 2.0 release marks the usage of autowiring for the configuration class. If you set the `container.autowiring.strict_mode` parameter to true (default for Symfony >=4.0), you need to alias the `Kickin\ExceptionHandlerBundle\Configuration\ConfigurationInterface` class to your configuration class. You can remove the `kickin.exceptionhandler.configuration.class` parameter from your configuration.

If you don't use autowiring, check the installation guide on what you need to register for the bundle to work.

# 1.1

The 1.1 release marks support for Symfony 4. It shouldn't require any changes
for you to work correctly.
