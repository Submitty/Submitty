SEMESTER=$(python3 -c 'from datetime import datetime; today = datetime.today(); semester = ("s" if today.month < 7 else "f") + str(today.year)[-2:]; print(semester)')

test_git() {
    git clone http://${1}:${1}@localhost/git/${SEMESTER}/sample/open_homework/instructor instructor_open_homework
    cd instructor_open_homework
    touch test.txt
    git add .
    git commit -m "First commit"
    git push
    touch test2.txt
    git add .
    git commit -m "Second commit"
    git push
    git reset --hard HEAD~1
    git pull
    cd ..
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


test_git instructor
test_git student


EXIT_CODE=0
git clone http://student:student@localhost/git/${SEMESTER}/sample/open_homework/instructor 2> /tmp/submitty_git/git_log || EXIT_CODE=$?
test ${EXIT_CODE} -ne 0
cat /tmp/submitty_git/git_log
test "fatal: Authentication failed for 'http://localhost/git/s20/sample/open_homework/instructor/'" = "${</tmp/submitty_git/git_log}"

cleanup
popd
