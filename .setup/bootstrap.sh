# If script not run as root, attempt re-run script as root
if [ $EUID != 0 ]; then
    echo -e
"--- ERROR ---
Detected that you attempted to run the Submitty boostrap as a non-root user!
To allow Submitty to install properly (which involves install distro packages,
python packages, and creation of various folders and files), it requires
root access. If you don't feel comfortable with this, please feel free to
clone the repo and look through the source at https://github.com/Submitty/Submitty.

To continue the installation process, we will now request sudo acccess to re-run
this script.
"
    sudo "$0" "$@"
    exit $?
fi

echo -e "
Installing...
 _______  __   __  _______  __   __  ___   _______  _______  __   __
|       ||  | |  ||  _    ||  |_|  ||   | |       ||       ||  | |  |
|  _____||  | |  || |_|   ||       ||   | |_     _||_     _||  |_|  |
| |_____ |  |_|  ||       ||       ||   |   |   |    |   |  |       |
|_____  ||       ||  _   | |       ||   |   |   |    |   |  |_     _|
 _____| ||       || |_|   || ||_|| ||   |   |   |    |   |    |   |
|_______||_______||_______||_|   |_||___|   |___|    |___|    |___|
"

set -ev

mkdir -p /usr/local/submitty/GIT_CHECKOUT
git clone https://github.com/Submitty/Submitty /usr/local/submitty/GIT_CHECKOUT/Submitty

bash /usr/local/submitty/GIT_CHECKOUT/install_system.sh