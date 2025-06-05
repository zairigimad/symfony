UPGRADE FROM 7.4 to 8.0
=======================

Symfony 7.4 and Symfony 8.0 are released simultaneously at the end of November 2025. According to the Symfony
release process, both versions have the same features, but Symfony 8.0 doesn't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.0/setup/upgrade_major.html).

HttpClient
----------

 * Remove support for amphp/http-client < 5
 * Remove setLogger() methods on decorators; configure the logger on the wrapped client directly instead

OptionsResolver
---------------

 * Remove support for nested options definition via `setDefault()`, use `setOptions()` instead

   *Before*
   ```php
   $resolver->setDefault('option', function (OptionsResolver $resolver) {
       // ...
   });
   ```

   *After*
   ```php
   $resolver->setOptions('option', function (OptionsResolver $resolver) {
       // ...
   });
   ```

TwigBridge
----------

 * Remove `text` format from the `debug:twig` command, use the `txt` format instead
