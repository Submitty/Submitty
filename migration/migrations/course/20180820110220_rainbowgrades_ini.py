# import os
# import configparser
# from pathlib import Path
#
#
# def up(config, conn, semester, course):
#     course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
#     config_file = Path(course_dir, 'config', 'config.ini')
#
#     if config_file.is_file():
#         config = configparser.ConfigParser()
#         config.read(str(config_file))
#         if not config.has_option('course_details','auto_rainbow_grades'):
#             config.set('course_details','auto_rainbow_grades','false')
#
#         # write out the file
#         with open(str(config_file),'w') as configfile:
#             config.write(configfile)
#
#     pass
#
#
# def down(config, conn, semester, course):
#     pass
