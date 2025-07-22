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
            if package_name.startswith('php-') or package_name.startswith('php8.'):
                installed_php_packages.append(package_name)
                # Extract module name after php- or phpX.Y-
                if package_name.startswith('php-'):
                    module = package_name[4:]  # Remove 'php-'
                elif '-' in package_name:
                    module = package_name.split('-', 1)[1]  # Remove 'phpX.Y-'
                else:
                    continue
                installed_php_extensions.append(module)

    # Remove existing PHP packages
    env = os.environ.copy()
    env['DEBIAN_FRONTEND'] = 'noninteractive'
    subprocess.run(
        ["apt-get", "remove", "-qqy", "php"] + installed_php_packages,
        env=env
    )

    # Add PHP PPA
    subprocess.run(
        ["add-apt-repository", "-y", "ppa:ondrej/php"],
        env=env
    )
    subprocess.run(["apt-get", "update", "-qq"], env=env)

    # Install PHP 8.1 versions of the previously installed packages
    if installed_php_extensions:
        packages_to_install = [f"php8.1-{module}" for module in installed_php_extensions]
        subprocess.run(
            ["apt-get", "install", "-qqy", "php8.1"] + packages_to_install,
            env=env
        )


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    # Get list of currently installed PHP 8.1 packages
    result = subprocess.run(
        ["dpkg", "-l"],
        capture_output=True,
        text=True
    )

    installed_modules = []
    for line in result.stdout.split('\n'):
        if line.startswith('ii  php8.1-'):
            package_name = line.split()[1].split(':')[0]
            module = package_name[7:]  # Remove 'php8.1-'
            installed_modules.append(module)

    # Remove PHP 8.1 packages
    env = os.environ.copy()
    env['DEBIAN_FRONTEND'] = 'noninteractive'
    subprocess.run(["apt-get", "remove", "-qqy", "php8.1*"], env=env)

    # Remove PHP PPA
    subprocess.run(
        ["add-apt-repository", "-r", "-y", "ppa:ondrej/php"],
        env=env
    )
    subprocess.run(["apt-get", "update", "-qq"], env=env)

    # Reinstall generic PHP packages
    if installed_modules:
        packages_to_install = [f"php-{module}" for module in installed_modules]
        subprocess.run(
            ["apt-get", "install", "-qqy"] + packages_to_install,
            env=env
        )
