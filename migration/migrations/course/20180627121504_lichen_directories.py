import os
import grp

def up(conn, semester, course):

    course_dir          = os.path.join("/var/local/submitty/courses",semester,course)
    lichen_dir          = os.path.join(course_dir,"lichen")
    lichen_config_dir   = os.path.join(lichen_dir,"config")
    lichen_provided_dir = os.path.join(lichen_dir,"provided_code")

    # create the directories
    os.makedirs(lichen_dir, exist_ok=True)
    os.makedirs(lichen_config_dir, exist_ok=True)
    os.makedirs(lichen_provided_dir, exist_ok=True)

    # get course group
    stat_info = os.stat(course_dir)
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R hwphp:"+course_group+" "+lichen_dir)
    os.system("chmod -R u+rwx  "+lichen_dir)
    os.system("chmod -R g+rwxs "+lichen_dir)
    os.system("chmod -R o-rwx  "+lichen_dir)

    pass


def down(conn, semester, course):
    pass
