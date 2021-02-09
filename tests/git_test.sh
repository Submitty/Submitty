SEMESTER=$(python3 -c 'from datetime import datetime; today = datetime.today(); semester = ("s" if today.month < 7 else "f") + str(today.year)[-2:]; print(semester)')

test_git() {
    random_string=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 8 | head -n 1)
    echo http://${1}:${1}@localhost/git/${SEMESTER}/sample/open_homework/$2 open_homework
    git clone --progress --verbose http://${1}:${1}@localhost/git/${SEMESTER}/sample/open_homework/$2 open_homework
    cd open_homework
    echo "foo" >> test.txt
    git add .
    git commit -m "First commit"
    git push
    echo "bar" >> test2.txt
    git add .
    git commit -m "Second commit"
    git push
    git reset --hard HEAD~1
    git pull
    cd ..
    rm -rf open_homework
}

cleanup() {
    rm -rf /tmp/submitty_git
}

err_message() {
    cleanup
    popd
}

# Display our error message if something fails below
trap 'err_message' ERR

set -ev

mkdir /tmp/submitty_git
pushd /tmp/submitty_git


test_git instructor instructor
test_git instructor student
test_git student student

EXIT_CODE=0
git clone http://student:student@localhost/git/${SEMESTER}/sample/open_homework/instructor > /tmp/submitty_git/git_stdout 2> /tmp/submitty_git/git_stderr || EXIT_CODE=$?
test ${EXIT_CODE} -ne 0
test "$(echo -e "Cloning into 'instructor'...\nfatal: Authentication failed for 'http://localhost/git/${SEMESTER}/sample/open_homework/instructor/'")" = "$(</tmp/submitty_git/git_stderr)"

cleanup
popd
