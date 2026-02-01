"""Migration for the Submitty system."""

import os
import subprocess


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    # Get list of currently installed PHP packages
    result = subprocess.run(
        ["dpkg", "-l"],
        capture_output=True,
        text=True
    )

    installed_php_extensions = []
    installed_php_packages = []
    for line in result.stdout.split('\n'):
        if line.startswith('ii  php'):
            # Extract package name (remove version, architecture info)
            package_name = line.split()[1].split(':')[0]
            if package_name.startswith('php8.'):
                installed_php_packages.append(package_name)
                # Extract module name after php- or phpX.Y-
                if '-' in package_name:
                    module = package_name.split('-', 1)[1]  # Remove 'phpX.Y-'
                else:
                    continue
                installed_php_extensions.append(module)

    # Remove existing PHP packages
    env = os.environ.copy()
    env['DEBIAN_FRONTEND'] = 'noninteractive'
    subprocess.run(
        ["apt-get", "remove", "-qqy", "php8.1"] + installed_php_packages,
        env=env
    )

    # Install PHP 8.2 versions of the previously installed packages
    packages_to_install = [f"php8.2-{module}" for module in installed_php_extensions]
    if packages_to_install:
        subprocess.run(
            ["apt-get", "install"] + packages_to_install,
            env=env
        )

    conf_php_script = os.path.join(config.submitty['submitty_repository'], '.setup', 'configure_php.sh')
    subprocess.run(["bash", conf_php_script])
    os.system("cp -r /etc/php/8.1/fpm/pool.d/submitty.conf /etc/php/8.2/fpm/pool.d")


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    # Get list of currently installed PHP 8.2 packages
    result = subprocess.run(
        ["dpkg", "-l"],
        capture_output=True,
        text=True
    )

    installed_modules = []
    for line in result.stdout.split('\n'):
        if line.startswith('ii  php8.2-'):
            package_name = line.split()[1].split(':')[0]
            module = package_name[7:]  # Remove 'php8.2-'
            installed_modules.append(module)

    # Remove PHP 8.2 packages
    env = os.environ.copy()
    env['DEBIAN_FRONTEND'] = 'noninteractive'
    subprocess.run(["apt-get", "purge", "-qqy", "php8.2*"], env=env)

    # Reinstall PHP 8.1
    packages_to_install = [f"php8.1-{module}" for module in installed_modules]
    subprocess.run(
        ["apt-get", "install", "-qqy", "php8.1"] + packages_to_install,
        env=env
    )
