# import requests
# from requests.auth import HTTPBasicAuth
from verify import parse_assigned_zones, get_actual_zone_dict

actual_dict = get_actual_zone_dict()
assigned_dict = parse_assigned_zones()

output_list = []
file_list = ['TestExam_1']
for file in file_list:
    with open('%s.csv' % (file, ), 'r') as zones:
        # Get header row contents
        header_str = zones.readline()

        line_list = zones.readlines()

        # line_list = line_list[:1]
        for index, line in enumerate(line_list):
            line = line.strip()
            if len(line) > 0 and 'Missing' not in line:
                temp_array = []
                record = line.split(',')
                student_rcs = record[1]
                student_name = record[0]
                student_tmp = student_name.rsplit(' ', 1)
                student_first_name = student_tmp[0]
                student_last_name = student_tmp[1]
                #student_assigned_zone = assigned_dict[student_rcs]
                student_question_grade = float(record[8])
                student_grade = float(record[4])
                #student_actual_zone = actual_dict[student_rcs]
                student_actual_zone = "A"
                student_assigned_zone = record[3]

                if student_assigned_zone == 'Z':
                    student_assigned_zone = 'EXTRA'
                if student_actual_zone == 'Z':
                    student_actual_zone = 'EXTRA'

                if student_assigned_zone == 'AA':
                    student_assigned_zone = 'UNASSIGNED'
                if student_actual_zone == 'AA':
                    student_actual_zone = 'UNASSIGNED'

                temp_array.append('%s' % (student_rcs))
                temp_array.append('%s' % (student_first_name))
                temp_array.append('%s' % (student_last_name))
                x = 10
                while x < len(record):
                    student_question_grade = float(record[x])
                    temp_array.append('%s' % (record[x]))
                    x+=1
                temp_array.append('%s' % (student_grade))
                temp_array.append('%s' % (student_assigned_zone))
                temp_str = ','.join(temp_array)
                output_list.append('%s' % (temp_str))

with open('TestExam_1_Upload.csv', 'w') as upload:
    output_list.sort()
    output_str = '\n'.join(output_list)
    upload.write(output_str)
