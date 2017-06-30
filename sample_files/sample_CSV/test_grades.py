# import requests
# from requests.auth import HTTPBasicAuth
import csv
from verify import parse_assigned_zones, get_actual_zone_dict

actual_dict = get_actual_zone_dict()
assigned_dict = parse_assigned_zones()

output_list = []
file_list = ['TestExam_1']
for file in file_list:
    with open('%s.csv' % (file, ), 'r') as zones:
        
        csv_reader = csv.reader(zones)
        # Skip header row contents
        next(csv_reader, None) 
        for line in csv_reader:
            if len(line) > 0 and 'Missing' not in line:
                temp_array = []
                student_rcs = line[1]
                student_name = line[0]
                student_tmp = student_name.rsplit(' ', 1)
                student_first_name = student_tmp[0]
                student_last_name = student_tmp[1]
                #obtain the zones from verify.py, not used right now
                #student_assigned_zone = assigned_dict[student_rcs]
                student_grade = float(line[4])
                #student_actual_zone = actual_dict[student_rcs]
                student_actual_zone = "A"
                student_assigned_zone = line[3]

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
                #10 is the index in which the csv starts listing grades
                temp_array += [line[i + 10].strip() for i in range(len(line) - 10)]
                temp_array.append('%s' % (student_grade))
                temp_array.append('%s' % (student_assigned_zone))
                temp_str = ','.join(temp_array)
                output_list.append('%s' % (temp_str))

with open('TestExam_1_Upload.csv', 'w') as upload:
    output_list.sort()
    output_str = '\n'.join(output_list)
    upload.write(output_str)
