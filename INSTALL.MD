# Installing Valet+

1. Install [Homebrew](https://brew.sh/) or update to the latest version using `brew update`.

> If you're upgrading van Valet+ 2.3 or lower use the extra following steps.
> - Remove all `valet-php@` versions with `brew remove valet-php@...`.
> - Untap henkrehorst/php with `brew untap henkrehorst/php`.

2. Add the Homebrew PHP tap with `brew tap shivammathur/php`.
3. Install PHP 7.4 using homebrew with `brew install shivammathur/php/php@7.4`.
4. Link the installed PHP version using `brew link php@7.4 --force --overwrite`.

> Sometimes you need to restart your terminal for the link to have effect.

5. Install Composer using anyway you like, suggested is using `brew install composer`.
6. Install Valet+ with Composer using `composer global require weprovde/valet-plus:2.4.0-beta2`.
7. Add `export PATH="$PATH:$HOME/.composer/vendor/bin"` to `.bash_profile` (for bash) or `.zshrc` (for zsh) depending on
your shell (`echo $SHELL`).
8. Run the `valet install` command. Optionally add `--with-mariadb` to use MariaDB instead of MySQL This will configure and install Valet+ and DnsMasq, and register Valet's daemon to launch when your system starts.

> Sometimes `php` (latest version) gets installed too, which throws an "Unable to determine linked PHP" error. In this case just re-link PHP 7.4.

> After install you can validate that it's working by pinging any `.test` domain you like, for example `ping -c1 foobar.test`.

> Sometimes you to need restart your Mac in order for all installed services to work properly.

9. Create any directory on your disk to your liking where you run your sites from and park valet there with `valet park`.
10. Restart Valet+ if necessary with `valet restart`.
