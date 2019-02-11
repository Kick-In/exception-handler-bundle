# Upgrade notes

# 2.0

The 2.0 release marks the usage of autowiring for the configuration class. You don't need to change anything if you use autowiring, but you can remove the `kickin.exceptionhandler.configuration.class` parameter from you configuration.

If you don't use autowiring, check the installion guide on what you need to register for the bundle to work.

# 1.1

The 1.1 release marks support for Symfony 4. It shouldn't require any changes
for you to work correctly.
