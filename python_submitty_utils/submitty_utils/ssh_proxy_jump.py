import os
import paramiko


def ssh_connection_allowing_proxy_jump(target_user, target_host):
    """
    Uses paramiko package to connect to a remote machine via ssh either
    directly or through an intermediary machine specified using a proxyjump
    in the current user's ssh config file.
    """

    # load and parse the ssh config
    my_key_filename = None
    intermediate_host = None
    intermediate_user = None
    config_file = os.path.expanduser("~")+"/.ssh/config"
    if os.path.exists(config_file):
        conf = paramiko.SSHConfig()
        with open(config_file) as f:
            try:
                conf.parse(f)
                target_host_object = conf.lookup(target_host)
                if 'proxyjump' in target_host_object:
                    (intermediate_user,
                     intermediate_host) = str(target_host_object['proxyjump']).split('@')
                if 'identityfile' in target_host_object:
                    my_key_filename = target_host_object['identityfile'][0]
            except Exception as e:
                print("ERROR: unexpected syntax/formatting of ssh config: "+str(e))
                raise e
    try:
        # if this connection has a jump proxy, open that connection first
        my_sock = None
        intermediate_connection = None
        if intermediate_host:
            intermediate_connection = paramiko.SSHClient()
            intermediate_connection.get_host_keys()
            intermediate_connection.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            intermediate_connection.connect(hostname=intermediate_host,
                                            username=intermediate_user,
                                            timeout=60,
                                            key_filename=my_key_filename)
            my_sock = intermediate_connection.get_transport().open_channel(
                'direct-tcpip', (target_host, 22), ('', 0)
            )
        # open the actual connection
        target_connection = paramiko.SSHClient()
        target_connection.get_host_keys()
        target_connection.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        target_connection.connect(hostname=target_host,
                                  username=target_user,
                                  timeout=60,
                                  sock=my_sock,
                                  key_filename=my_key_filename)
        # return both connections (so they can be cleaned up)
        return (target_connection, intermediate_connection)

    except Exception as e:
        print("ERROR: could not open a connection to the target host: "+str(e))
        raise e
