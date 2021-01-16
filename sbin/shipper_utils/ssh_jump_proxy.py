import os
import sys
import json
import paramiko


def ssh_connection_allowing_jump_proxy(target_user, target_host):

    # load and parse the ssh config
    my_key_filename = None
    intermediate_host = None
    intermediate_user = None
    config_file = os.path.expanduser("~")+"/.ssh/config"
    if (os.path.exists(config_file)):
        conf = paramiko.SSHConfig()
        conf.parse(open(config_file))
        o = conf.lookup(target_host)
        if 'proxyjump' in o:
            (intermediate_user,intermediate_host) = str(o['proxyjump']).split('@')
        if 'identityfile' in o:
            my_key_filename = o['identityfile'][0]

    try:
        # if this connection has a jump proxy, open that connection first
        my_sock = None
        intermediate_connection = None
        if intermediate_host:
            intermediate_connection = paramiko.SSHClient()
            intermediate_connection.get_host_keys()
            intermediate_connection.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            intermediate_connection.connect(hostname = intermediate_host, username = intermediate_user, timeout=60, key_filename=my_key_filename)
            my_sock = intermediate_connection.get_transport().open_channel(
                'direct-tcpip', (target_host, 22), ('', 0)
            )
        # open the actual connection
        target_connection = paramiko.SSHClient()
        target_connection.get_host_keys()
        target_connection.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        target_connection.connect(hostname = target_host, username = target_user, timeout=60, sock=my_sock, key_filename=my_key_filename)
        # return both connections (so they can be cleaned up)
        return (target_connection,intermediate_connection)

    except Exception as e:
        print("ERROR: could not open a connection to the target host: "+str(e))
        raise e
