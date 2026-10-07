"""Forward only the websocket token cookie from nginx to the socket server.

The socket server's HTTP parser (Ratchet) closes handshakes over 4096 bytes
with a 413. Browsers send every cookie for the domain with the handshake,
including cookies that other sites set, which can push it past that limit.

INSTALL_SUBMITTY restarts nginx after the migrations run, which applies the change.
"""

from pathlib import Path
import re

NGINX_CONF = Path('/etc/nginx/sites-available/submitty.conf')
COOKIE_LINE = 'proxy_set_header Cookie "submitty_websocket_token=$cookie_submitty_websocket_token";'
# The Host header line in the /ws location block. The cookie line goes right after it.
WS_HOST_LINE = re.compile(
    r'(location\s+/ws\s*\{[^}]*?^([ \t]*)proxy_set_header\s+Host\s+\$host;[ \t]*\n)',
    re.MULTILINE
)


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    # Worker machines have no nginx config
    if not NGINX_CONF.exists():
        return
    text = NGINX_CONF.read_text()
    if re.search(r'^\s*proxy_set_header\s+Cookie\b', text, re.MULTILINE):
        return

    new_text, count = WS_HOST_LINE.subn(
        lambda m: m.group(1) + m.group(2) + COOKIE_LINE + '\n',
        text,
        count=1
    )
    if count == 0:
        print(f"Could not find the /ws block in {NGINX_CONF}. Add this line to it by hand, then reload nginx:")
        print(f"    {COOKIE_LINE}")
        return
    NGINX_CONF.write_text(new_text)
