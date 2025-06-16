#!/usr/bin/env python3
"""
Script to trigger the generate grade summaries.

Usage:
./generate_grade_summaries.py <semester> <course>
"""

import argparse
import requests
import os
import json
from sys import stderr
from bs4 import BeautifulSoup

# Get path to current file directory
current_dir = os.path.dirname(__file__)

# Collect submission url
submitty_json_config = os.path.join(current_dir, '..', 'config', 'submitty.json')

if not os.path.exists(submitty_json_config):
    raise Exception('Unable to locate submitty.json configuration file')

with open(submitty_json_config, 'r') as file:
    data = json.load(file)
    base_url = data['submission_url'].rstrip('/')
    install_dir = data['submitty_install_dir']
    data_dir = data['submitty_data_dir']

# Collect submitty admin token
submitty_creds_file = os.path.join(install_dir, 'config', 'submitty_admin.json')

if not os.path.exists(submitty_creds_file):
    raise Exception('Unable to locate submitty_admin.json credentials file')

# Load credentials out of admin file
with open(submitty_creds_file, 'r') as file:
    creds = json.load(file)

if 'token' not in creds or not creds['token']:
    raise Exception('Unable to read credentials from submitty_admin.json')

# Rainbow Grades GUI Customization Parsing Functions (see /site/public/js/rainbow-customization.js)
def get_display(soup):
    """Collects the values of checked 'display' checkboxes."""
    return [element['value'] for element in soup.select('input[name="display"]:checked')]

def get_display_benchmark(soup):
    """Collects the values of checked 'display_benchmarks' checkboxes."""
    return [element['value'] for element in soup.select('input[name="display_benchmarks"]:checked')]

def get_selected_curve_benchmarks(soup):
    """Determines which curve-related benchmarks are selected."""
    all_selected = get_display_benchmark(soup)
    benchmarks_with_input_fields = ['lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d']

    return [elem for elem in all_selected if elem in benchmarks_with_input_fields]

def get_benchmark_percent(soup):
    """Collects benchmark percents for the selected curve benchmarks."""
    benchmark_percent = {}
    selected_benchmarks = get_selected_curve_benchmarks(soup)

    for element in soup.select('.benchmark_percent_input'):
        benchmark = element['data-benchmark']

        if benchmark in selected_benchmarks:
            if not element.get('value'):
                raise ValueError("All benchmark percents must have a value before saving.")

            try:
                benchmark_percent[benchmark] = float(element['value'])
            except ValueError:
                raise ValueError("Benchmark percent input must be a floating point number.")

    return benchmark_percent

def get_final_cutoff_percent(soup):
    """Collects the final grade cutoff percentages."""
    final_cutoff = {}
    allowed_grades_excluding_f = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D']

    for element in soup.select('.final_cutoff_input'):
        letter_grade = element['data-benchmark']

        if letter_grade in allowed_grades_excluding_f:
            if not element.get('value'):
                raise ValueError("All final cutoffs must have a value before saving.")

            try:
                final_cutoff[letter_grade] = float(element['value'])
            except ValueError:
                raise ValueError("Final cutoff input must be a floating point number.")

    return final_cutoff


def get_section(soup):
    """Collects section labels."""
    sections = {}

    for element in soup.select('.sections_and_labels'):
        section = element['data-section']
        label = element['value']
        sections[section] = label

    return sections

def get_gradeable_buckets(soup):
    """Collects data for all visible gradeable buckets."""
    gradeables = []

    for bucket_div in soup.select('.bucket_detail_div'):
        bucket = {}

        # Extract bucket type from the h3 tag
        type_header = bucket_div.find('h3')
        if not type_header:
            continue

        bucket_type = type_header.text.lower()
        bucket['type'] = bucket_type

        # Extract count, remove_lowest, and percent
        bucket['count'] = int(soup.select_one(f'#config-count-{bucket_type}')['value'])
        bucket['remove_lowest'] = int(soup.select_one(f'#config-remove_lowest-{bucket_type}')['value'])
        bucket['percent'] = float(soup.select_one(f'#percent-{bucket_type}')['value']) / 100.0

        # Extract details for each gradeable within the bucket
        ids = []
        for li in bucket_div.select(f'#gradeables-list-{bucket_type} .gradeable-li'):
            gradeable = {}
            max_score_input = li.select_one('.max-score')
            percent_input = li.select_one('div[id^="gradeable-percents-div-"] input')

            gradeable['id'] = li.select_one('.gradeable-id').text.strip()
            gradeable['max'] = float(max_score_input['data-max-score'])
            gradeable['release_date'] = max_score_input['data-grade-release-date']

            # Only include percent if the input is visible
            if 'style' in percent_input.attrs:
                gradeable['percent'] = float(percent_input['value']) / 100.0

            ids.append(gradeable)
            print(gradeable)

        bucket['ids'] = ids
        gradeables.append(bucket)

    return gradeables

def get_table_data(soup, table_name):
    """Extracts data from the plagiarism, manual grade, or performance warnings tables."""
    data = []
    table_map = {
        'plagiarism': 'plagiarism-table-body',
        'manualGrade': 'manual-grading-table-body',
        'performanceWarnings': 'performance-warnings-table-body'
    }

    if table_name not in table_map:
        return []

    table_body = soup.select_one(f'#{table_map[table_name]}')
    if not table_body:
        return []

    for row in table_body.find_all('tr'):
        cells = row.find_all('td')
        first_input = cells[0].text.strip()
        second_input = cells[1].text.strip()
        third_input = cells[2].text.strip()

        if table_name == 'plagiarism':
            data.append({
                'user': first_input,
                'gradeable': second_input,
                'penalty': float(third_input)
            })
        elif table_name == 'manualGrade':
             data.append({
                'user': first_input,
                'grade': second_input,
                'note': third_input
            })
        elif table_name == 'performanceWarnings':
            data.append({
                'msg': first_input,
                'ids': [g.strip() for g in second_input.split(',')],
                'value': float(third_input)
            })

    return data

def get_messages(soup):
    """Extracts custom messages from the textarea."""
    textarea = soup.select_one('#cust_messages_textarea')

    return [textarea.text.strip()] if textarea and textarea.text.strip() else []

def build_json(html_string):
    """
    Constructs a JSON representation of the form input from an HTML string. This function
    mimics the behavior of the original JavaScript `buildJSON` function.
    """
    soup = BeautifulSoup(html_string, 'html.parser')
    data = {
        'display': get_display(soup),
        'display_benchmark': get_display_benchmark(soup),
        'benchmark_percent': get_benchmark_percent(soup),
        'final_cutoff': get_final_cutoff_percent(soup),
        'section': get_section(soup),
        'gradeables': get_gradeable_buckets(soup),
        'messages': get_messages(soup),
        'plagiarism': get_table_data(soup, 'plagiarism'),
        'manual_grade': get_table_data(soup, 'manualGrade'),
        'warning': get_table_data(soup, 'performanceWarnings')
    }

    return json.dumps(data, indent=4)

def main():
    """Automatically call Generate Grade Summaries API."""
    parser = argparse.ArgumentParser(
        description='Automatically call API endpoints to save/load GUI customizations and generate Grade Summaries.'
    )
    parser.add_argument('semester')
    parser.add_argument('course')
    args = parser.parse_args()

    semester = args.semester
    course = args.course
    token = creds['token']

    """Automatically call Generate Grade Summaries API"""
    try:
        grade_generation_response = requests.post(
            '{}/api/courses/{}/{}/reports/summaries'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token}
        )
    except Exception as grade_generation_exception:
        print("ERROR: Failed to generate grade summaries for {}.{} - {}".format(
            semester, course, grade_generation_exception
        ), file=stderr)
        exit(-1)

    if grade_generation_response.status_code == 200:
        grade_generation_response = grade_generation_response.json()
        if grade_generation_response["status"] == 'success':
            print("Successfully generated grade summaries for {}.{}".format(
                semester, course
            ))
        else:
            print("ERROR: Failed to generate grade summaries for {}.{} - {}".format(
                semester, course, grade_generation_response["message"]
            ), file=stderr)
    else:
        print("ERROR: Submitty Service Unavailable.", file=stderr)

    """Automatically call Save & Load GUI Customization API endpoints"""
    try:
        customization_file = os.path.join(data_dir, 'courses', semester, course, 'rainbow_grades', 'customization.json')
        if not os.path.exists(customization_file):
            raise Exception('Unable to locate customization.json file')
        with open(customization_file, 'r') as file:
            customization_data = json.load(file)

        # Load the GUI customization page via server-side rendering to trigger customization updates
        load_response = requests.post(
            '{}/api/courses/{}/{}/reports/rainbow_grades_customization'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token},
        )
        print(load_response.text[:200])
        print(build_json(load_response.text))

        save_response = requests.post(
            '{}/api/courses/{}/{}/reports/rainbow_grades_customization_save'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token},
            data={"json_string": json.dumps(customization_data)}
        )
        print(save_response.text)
    except Exception as save_load_exception:
        print("ERROR: Failed to save or load Rainbow Grades GUI customization for {}.{} - {}".format(
            semester, course, save_load_exception
        ), file=stderr)
        exit(-1)

    if load_response.status_code == 200:
        save_response = save_response.json()
        load_response = load_response.text.strip()

        if save_response["status"] == 'success':
            print("Successfully saved Rainbow Grades GUI customization for {}.{}".format(
                semester, course
            ))
        else:
            print("ERROR: Failed to save Rainbow Grades GUI customization for {}.{} - {}".format(
                semester, course, save_response["message"]
            ), file=stderr)

        if "rg_web_ui" in load_response:
            print("Successfully loaded Rainbow Grades GUI customization for {}.{}".format(
                semester, course
            ))
        else:
            print("ERROR: Failed to load Rainbow Grades GUI customization for {}.{}. Response - {}".format(
                semester, course, load_response
            ), file=stderr)
    else:
        print("ERROR: Submitty Service Unavailable.", file=stderr)


if __name__ == "__main__":
    main()
