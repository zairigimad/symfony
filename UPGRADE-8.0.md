UPGRADE FROM 7.4 to 8.0
=======================

Symfony 7.4 and Symfony 8.0 are released simultaneously at the end of November 2025. According to the Symfony
release process, both versions have the same features, but Symfony 8.0 doesn't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.0/setup/upgrade_major.html).

Console
-------

 * Remove methods `Command::getDefaultName()` and `Command::getDefaultDescription()` in favor of the `#[AsCommand]` attribute

   *Before*
   ```php
   use Symfony\Component\Console\Command\Command;

   class CreateUserCommand extends Command
   {
       public static function getDefaultName(): ?string
       {
           return 'app:create-user';
       }

       public static function getDefaultDescription(): ?string
       {
           return 'Creates users';
       }

       // ...
   }
   ```

   *After*
   ```php
   use Symfony\Component\Console\Attribute\AsCommand;
   use Symfony\Component\Console\Command\Command;

   #[AsCommand('app:create-user', 'Creates users')]
   class CreateUserCommand
   {
       // ...
   }
   ```

 * Ensure closures set via `Command::setCode()` method have proper parameter and return types

   *Before*
   ```php
   $command->setCode(function ($input, $output) {
       // ...
   });
   ```

   *After*
   ```php
   use Symfony\Component\Console\Input\InputInterface;
   use Symfony\Component\Console\Output\OutputInterface;

   $command->setCode(function (InputInterface $input, OutputInterface $output): int {
       // ...

       return 0;
   });
   ```

 * Add method `isSilent()` to `OutputInterface`

HttpClient
----------

 * Remove support for amphp/http-client < 5
 * Remove setLogger() methods on decorators; configure the logger on the wrapped client directly instead

TwigBridge
----------

 * Remove `text` format from the `debug:twig` command, use the `txt` format instead
