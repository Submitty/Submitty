GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
VERSION=$(lsb_release -sr | tr '[:upper:]' '[:lower:]')
mkdir -p ${GIT_PATH}/.vagrant/logs
bash ${GIT_PATH}/.setup/vagrant/setup_vagrant.sh 2>&1 | tee ${GIT_PATH}/.vagrant/logs/submitty-main.log