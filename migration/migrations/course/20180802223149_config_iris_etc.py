import os
from pathlib import Path
import configparser

def up(config, conn, semester, course):

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    config_file = Path(course_dir, 'config', 'config.ini')

    if config_file.is_file():

        # should have done this replacement a while ago...
        os.system("sed -i 's/iris/rainbow/' "+str(config_file))
        
        config = configparser.ConfigParser()
        config.read(str(config_file))
        print ("thing",str(config_file))
        print ("my_config",config)

        if not config.has_section('course_details'):
            print ("ARCH")

        print (config.sections())
        
        if not config.has_option('course_details','forum_enabled'):
            config.set('course_details','forum_enabled','false')
        if not config.has_option('course_details','regrade_enabled'):
            config.set('course_details','regrade_enabled','false')
        if not config.has_option('course_details','regrade_message'):
            config.set('course_details','regrade_message',"Frivolous regrade requests may result in a grade deduction or loss of late days")
        if not config.has_option('course_details','private_repository'):
            config.set('course_details','private_repository','""')
        if not config.has_option('course_details','room_seating_gradeable_id'):
            config.set('course_details','room_seating_gradeable_id','""')
        
        with open(str(config_file),'w') as configfile:
            config.write(configfile)
        
    pass


def down(config, conn, semester, course):
    pass
