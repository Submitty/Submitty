DATABASE_JSON = os.path.join(CONFIG_INSTALL_DIR, 'database.json')
SUBMITTY_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty.json')
SUBMITTY_USERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty_users.json')
WORKERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_workers.json')
CONTAINERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_containers.json')
SECRETS_PHP_JSON = os.path.join(CONFIG_INSTALL_DIR, 'secrets_submitty_php.json')

if not args.worker:
    for file in [WORKERS_JSON, CONTAINERS_JSON]:
        os.chmod(file, 0o660)
    shutil.chown(WORKERS_JSON, PHP_USER, DAEMON_GID)
    shutil.chown(CONTAINERS_JSON, group=DAEMONPHP_GROUP)

os.chmod(SUBMITTY_JSON, 0o444)

os.chmod(SUBMITTY_USERS_JSON, 0o440)
shutil.chown(SUBMITTY_USERS_JSON, 'root', DAEMON_GROUP if args.worker else DAEMONPHP_GROUP)


if not args.worker:
    config = OrderedDict()
    characters = string.ascii_letters + string.digits
    config['session'] = ''.join(secrets.choice(characters) for _ in range(64))
    with open(SECRETS_PHP_JSON, 'w') as json_file:
        json.dump(config, json_file, indent=2)
shutil.chown(SECRETS_PHP_JSON, 'root', PHP_GROUP)
os.chmod(SECRETS_PHP_JSON, 0o440)

if not args.worker:
    shutil.chown(DATABASE_JSON, 'root', DAEMONPHP_GROUP)
    os.chmod(DATABASE_JSON, 0o440)

    shutil.chown(AUTHENTICATION_JSON, 'root', DAEMONPHP_GROUP)
    os.chmod(AUTHENTICATION_JSON, 0o440)

    shutil.chown(SUBMITTY_ADMIN_JSON, 'root', DAEMON_GROUP)
    os.chmod(SUBMITTY_ADMIN_JSON, 0o440)

    shutil.chown(EMAIL_JSON, 'root', DAEMONPHP_GROUP)
    os.chmod(EMAIL_JSON, 0o440)