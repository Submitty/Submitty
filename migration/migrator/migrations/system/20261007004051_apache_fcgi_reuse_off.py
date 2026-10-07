"""Turn off FastCGI connection reuse in the installed Apache site config.

With enablereuse=on, each Apache child process keeps idle connections to
PHP-FPM, and each one holds a PHP-FPM worker. During a burst of requests,
new requests can wait on workers that idle connections tie up.

INSTALL_SUBMITTY restarts Apache after the migrations run, which applies the change.
"""

from pathlib import Path
import re

APACHE_CONF = Path('/etc/apache2/sites-available/submitty.conf')
FCGI_REUSE_ON = re.compile(r'(<Proxy\s+"fcgi://localhost/"[^>]*\benablereuse=)on\b', re.IGNORECASE)


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    # Worker machines have no Apache config
    if not APACHE_CONF.exists():
        return
    text = APACHE_CONF.read_text()
    new_text = FCGI_REUSE_ON.sub(r'\1off', text)
    if new_text != text:
        APACHE_CONF.write_text(new_text)
