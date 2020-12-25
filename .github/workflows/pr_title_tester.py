import re


# paste this from pr_title.yml
exp = '^(\[SYSADMIN ACTION\])?(\[(Bugfix|Feature|Refactor|Testing|Documentation|VPAT|UI\/UX):(Submission|Autograding|Forum|Notifications|TAGrading|InstructorUI|RainbowGrades|System|Developer|API)\] .{2,40}$|\[(DevDependency|Dependency)\] .{2,70})$'


# should pass
assert re.search(exp,"[Refactor:Autograding] xxxx") != None
assert re.search(exp,"[Bugfix:Submission] xxxx") != None
assert re.search(exp,"[VPAT:InstructorUI] xxxx") != None
assert re.search(exp,"[UI/UX:API] xxxx") != None
assert re.search(exp,"[SYSADMIN ACTION][Refactor:Autograding] xxxx") != None
assert re.search(exp,"[DevDependency] xxxx") != None
assert re.search(exp,"[Dependency] xxxx") != None
assert re.search(exp,"[Bugfix:Submission] 0123456789012345678901234567890123456789") != None
assert re.search(exp,"[Dependency] 0123456789012345678901234567890123456789012345678901234567890123456789") != None

# should fail
assert re.search(exp,"[UI//UX:API] xxxx") == None
assert re.search(exp,"[UI\/UX:API] xxxx") == None
assert re.search(exp,"[Refactor:RainbowGrades]") == None
assert re.search(exp,"[BugFix:TAGrading] xxxx") == None
assert re.search(exp,"[Dependency:Autograding] xxxx") == None
assert re.search(exp,"[SYSADMINACTION][Refactor:Autograding] xxxx") == None
assert re.search(exp,"[DevDependency:Autograding] xxxx") == None
assert re.search(exp,"[Dependency] xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx") != None
assert re.search(exp,"[Bugfix:Submission] 01234567890123456789012345678901234567890") == None
assert re.search(exp,"[Dependency] 01234567890123456789012345678901234567890123456789012345678901234567890") == None

