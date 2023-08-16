import os
import subprocess

def up(config):
    DAEMON_USER = config.submitty_users['daemon_user']
    RAINBOWGRADES_REPOSITORY = os.path.join(config.submitty['submitty_install_dir'], 'GIT_CHECKOUT','RainbowGrades')

    gitconfig_path = f"/home/{DAEMON_USER}/.gitconfig"

    gitconfig_content = """[safe]
    directory = {}""".format(RAINBOWGRADES_REPOSITORY)

    try:
        with open(gitconfig_path, "w") as gitconfig_file:
            gitconfig_file.write(gitconfig_content)
        subprocess.run(["sudo", "chown", f"{DAEMON_USER}:{DAEMON_USER}", gitconfig_path], check=True)

    except Exception as e:
        print("Error:", e)
        print("Failed to write .gitconfig entry or change ownership.")

def down(config):
    pass
