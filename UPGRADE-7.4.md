UPGRADE FROM 7.3 to 7.4
=======================

Symfony 7.4 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.4/setup/upgrade_minor.html).

If you're upgrading from a version below 7.3, follow the [7.3 upgrade guide](UPGRADE-7.3.md) first.

Console
-------

 * Deprecate `Symfony\Component\Console\Application::add()` in favor of `Symfony\Component\Console\Application::addCommand()`

DependencyInjection
-------------------

 * Add argument `$target` to `ContainerBuilder::registerAliasForArgument()`

FrameworkBundle
---------------

 * Deprecate `Symfony\Bundle\FrameworkBundle\Console\Application::add()` in favor of `Symfony\Bundle\FrameworkBundle\Console\Application::addCommand()`

HttpClient
----------

 * Deprecate using amphp/http-client < 5

HttpFoundation
--------------

 * Deprecate using `Request::sendHeaders()` after headers have already been sent; use a `StreamedResponse` instead

Security
--------

 * Deprecate callable firewall listeners, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Deprecate `AbstractListener::__invoke`
 * Deprecate `LazyFirewallContext::__invoke()`
