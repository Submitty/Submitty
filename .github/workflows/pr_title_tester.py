import re


# paste this from pr_title.yml
exp = r"^(\[SYSADMIN ACTION\])?(\[(Bugfix|Feature|Refactor|Testing|Documentation|VPAT|UI\/UX):(Submission|Autograding|Forum|Notifications|TAGrading|InstructorUI|RainbowGrades|System|Developer|API)\] .{2,40}$|\[(DevDependency|Dependency)\] .{2,70})$"


# should pass
assert re.search(exp, "[Refactor:Autograding] xxxx")
assert re.search(exp, "[Bugfix:Submission] xxxx")
assert re.search(exp, "[VPAT:InstructorUI] xxxx")
assert re.search(exp, "[UI/UX:API] xxxx")
assert re.search(exp, "[SYSADMIN ACTION][Refactor:Autograding] xxxx")
assert re.search(exp, "[DevDependency] xxxx")
assert re.search(exp, "[Dependency] xxxx")
assert re.search(exp, "[Bugfix:Submission] 0123456789012345678901234567890123456789")
assert re.search(exp, "[Dependency] 012345678901234567890123456789" +
                 "0123456789012345678901234567890123456789")

# should fail
assert not re.search(exp, "[UI//UX:API] xxxx")
assert not re.search(exp, "[UI\/UX:API] xxxx")
assert not re.search(exp, "[Refactor:RainbowGrades]")
assert not re.search(exp, "[BugFix:TAGrading] xxxx")
assert not re.search(exp, "[Dependency:Autograding] xxxx")
assert not re.search(exp, "[SYSADMINACTION][Refactor:Autograding] xxxx")
assert not re.search(exp, "[DevDependency:Autograding] xxxx")
assert not re.search(exp, "[Bugfix:Submission] 01234567890123456789012345678901234567890")
assert not re.search(exp, "[Dependency] 012345678901234567890123456789" +
                     "01234567890123456789012345678901234567890")
