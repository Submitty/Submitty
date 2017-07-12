

def parse_assigned_zones():
    allowed_str = 'ABCDEFGHJKLMNPUZ'

    assigned_zone_dict = {}
    with open('exam1_seating.txt', 'r') as assigned:
        for line in assigned:
            line = line.strip()
            line_list = line.split(' ')
            line_list = [ line_.strip() for line_ in line_list ]
            line_list = [ line_ for line_ in line_list if len(line_) > 0 ]

            if len(line_list) == 3:
                assigned_zone = 'U'
            elif len(line_list) == 6:
                assigned_zone = line_list[-1]
            else:
                assigned_zone = line_list[-2]

            if assigned_zone == 'UNASSIGNED':
                assigned_zone = None

            assert assigned_zone is None or assigned_zone in allowed_str

            student_rcs = line_list[2]
            assigned_zone_dict[student_rcs] = assigned_zone
    return assigned_zone_dict


def get_actual_zone_dict():
    actual_dict = {}
    showed_dict = {}

    assigned_zone_dict = parse_assigned_zones()

    direct_list = ['CSCI_1100_Exam_1']
    for direct in direct_list:
        with open('%s/9_Zone_Assignment.csv' % (direct, ), 'r') as zones:
            # Get header row contents
            header_str = zones.readline()
            header_list = header_str.strip().split(',')[6: -3]

            line_list = zones.readlines()
            # Trim last three rows
            line_list = line_list[:-3]

            for index, line in enumerate(line_list):
                line = line.strip()
                if len(line) > 0:
                    record = line.split(',')
                    student_name = record[1]
                    student_rcs = record[2]
                    assigned_zone = assigned_zone_dict[student_rcs]
                    actual_list = record[6: -3]
                    actual_index = actual_list.index('true')
                    actual_zone = header_list[actual_index]
                    actual_dict[student_rcs] = actual_zone
                    if assigned_zone == actual_zone:
                        if assigned_zone not in showed_dict:
                            showed_dict[assigned_zone] = 0
                        showed_dict[assigned_zone] += 1
                    else:
                        print('%s (%s)' % (student_name, student_rcs, ))
                        print('\tAssigned: %s' % (assigned_zone, ))
                        print('\tActual:   %s' % (actual_zone, ))

    for key in sorted(showed_dict.keys()):
        print('Zone % 2s: %d' % (key, showed_dict[key]))

    return actual_dict

if __name__ == '__main__':
    get_actual_zone_dict()
