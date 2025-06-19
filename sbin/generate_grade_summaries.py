#!/usr/bin/env python3
"""
Script to trigger the generate grade summaries.

Usage:
./generate_grade_summaries.py <semester> <course> <source>
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


# Rainbow Grades GUI Customization HTML parsing functions (see /site/public/js/rainbow-customization.js)
def get_display(soup):
    """Collects the values of checked 'display' checkboxes."""
    return [element['value'] for element in soup.select('input[name="display"]:checked')]


def get_display_benchmark(soup):
    """Collects the values of checked 'display_benchmarks' checkboxes."""
    return [element['value'] for element in soup.select('input[name="display_benchmarks"]:checked')]


def get_selected_curve_benchmarks(soup):
    """Determines which curve-related benchmarks are selected."""
    all_selected = get_display_benchmark(soup)
    benchmarks_with_input_fields = {'lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d'}

    return [elem for elem in all_selected if elem in benchmarks_with_input_fields]


def get_benchmark_percent(soup):
    """Collects benchmark percents for the selected curve benchmarks."""
    benchmark_percent = {}
    selected_benchmarks = get_selected_curve_benchmarks(soup)

    for element in soup.select('.benchmark_percent_input'):
        benchmark = str(element['data-benchmark'])

        if benchmark in selected_benchmarks:
            # Verify the percent is a valid number
            if not element.get('value'):
                raise ValueError("All benchmark percents must have a value before saving.")

            try:
                # Add to sections
                benchmark_percent[benchmark] = float(element['value'])
            except ValueError:
                raise ValueError("Benchmark percent input must be a floating point number.")

    return benchmark_percent


def get_final_cutoff_percent(soup):
    """Collects the final grade cutoff percentages."""
    # Default final grade cutoff percentages if final_grade is not checked
    if not soup.select_one('input[value="final_grade"]:checked'):
        return {
            'A': 93.0,
            'A-': 90.0,
            'B+': 87.0,
            'B': 83.0,
            'B-': 80.0,
            'C+': 77.0,
            'C': 73.0,
            'C-': 70.0,
            'D+': 67.0,
            'D': 60.0,
        }

    # Collect benchmark percents
    final_cutoff = {}
    allowed_grades_excluding_f = {'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D'}

    for element in soup.select('.final_cutoff_input'):
        letter_grade = str(element['data-benchmark'])

        if letter_grade in allowed_grades_excluding_f:
            # Verify the percent is a valid number
            if not element.get('value'):
                raise ValueError("All final cutoffs must have a value before saving.")

            try:
                # Add to sections
                final_cutoff[letter_grade] = float(element['value'])
            except ValueError:
                raise ValueError("Final cutoff input must be a floating point number.")

    return final_cutoff


def get_section(soup):
    """Collects section labels."""
    sections = {}

    for element in soup.select('.sections_and_labels'):
        section = str(element['data-section'])
        label = element['value']
        sections[section] = label

    return sections


def get_gradeable_buckets(soup):
    """Collects data for all visible gradeable buckets."""
    gradeables = []
    used_buckets = set()
    buckets_used_list = soup.select_one('#buckets_used_list')

    if not buckets_used_list:
        # No buckets are applied for the given course
        return []

    for li in buckets_used_list.select('li'):
        # Parse the bucket name from the inner text (i.e., '% Homework (29 items)')
        bucket_name = li.get_text(strip=True).split('(')[0].replace('%', '').strip().lower()
        used_buckets.add(bucket_name)

    for bucket_div in soup.select('.bucket_detail_div'):
        bucket = {}

        # Extract bucket type from the h3 tag
        type_header = bucket_div.find('h3')
        if not type_header:
            continue

        # Extract bucket type from the h3 tag
        bucket_type = type_header.text.lower()
        bucket['type'] = bucket_type

        if bucket_type not in used_buckets:
            # Ignore unassigned buckets
            continue

        # Extract count, remove_lowest, and percent
        bucket['count'] = int(soup.select_one(f'#config-count-{bucket_type}')['value'])
        bucket['remove_lowest'] = int(soup.select_one(f'#config-remove_lowest-{bucket_type}')['value'])
        bucket['percent'] = float(soup.select_one(f'#percent-{bucket_type}')['value']) / 100.0

        # Extract each independent gradeable in the bucket
        ids = []

        # Account for per-gradeable percents for the given bucket
        percents_checkbox = bucket_div.select_one(f'#per-gradeable-percents-checkbox-{bucket_type}')
        is_percent_enabled_for_this_bucket = percents_checkbox is not None and percents_checkbox.has_attr('checked')

        for li in bucket_div.select(f'#gradeables-list-{bucket_type} .gradeable-li'):
            gradeable = {}

            points_div = li.select_one('div[id^="gradeable-pts-div-"]')
            percent_div = li.select_one('div[id^="gradeable-percents-div-"]')

            if points_div and percent_div:
                max_score_input = points_div.select_one('.max-score')
                percent_input = percent_div.find('input')

                gradeable['max'] = float(max_score_input['data-max-score'])

                if is_percent_enabled_for_this_bucket:
                    # Extract the percent value from the input box with default value of ''
                    value = percent_input.get('value', '').strip()

                    try:
                        gradeable['percent'] = float(value) / 100.0
                    except (ValueError, TypeError):
                        # Ignore invalid percent values for simplicity
                        pass

                gradeable['release_date'] = max_score_input['data-grade-release-date']
                gradeable['id'] = li.select_one('.gradeable-id').text.strip()

            # Get per-gradeable curve data
            curve_points_selected = get_selected_curve_benchmarks(soup)
            for curve_input in li.select('.gradeable-li-curve input'):
                benchmark = str(curve_input['data-benchmark'])

                if benchmark in curve_points_selected and curve_input.get('value'):
                    if 'curve' not in gradeable:
                        gradeable['curve'] = []

                    gradeable['curve'].append(float(curve_input['value']))

            # Validate the set of per-gradeable curve value
            if 'curve' in gradeable:
                if len(gradeable['curve']) != len(curve_points_selected):
                    raise ValueError(f"To adjust the curve for gradeable {gradeable['id']} you must enter a value in each box")

                previous = gradeable['max']
                for elem in gradeable['curve']:
                    try:
                        elem = float(elem)
                    except ValueError:
                        raise ValueError(f"All curve inputs for gradeable {gradeable['id']} must be floating point values")

                    if elem < 0:
                        raise ValueError(f"All curve inputs for gradeable {gradeable['id']} must be greater than or equal to 0")

                    if elem > previous:
                        raise ValueError(f"All curve inputs for gradeable {gradeable['id']} must be less than or equal to the maximum points for the gradeable and also less than or equal to the previous input")

                    previous = elem

            ids.append(gradeable)

        # Add the gradeables to the bucket
        bucket['ids'] = ids
        gradeables.append(bucket)

    return gradeables


def get_table_data(soup, table_name):
    """Extracts data from the plagiarism, manual grade, or performance warnings tables."""
    tables = {'plagiarism', 'manualGrade', 'performanceWarnings'}
    if table_name not in tables:
        return []

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

    for row in table_body.select('tr'):
        cells = row.select('td')
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
                # Trim each gradeable ID to account for potential HTML whitespace
                'ids': [id.strip() for id in second_input.split(',')],
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


def get_error_message(response):
    """Extracts the error message from the response."""
    if 'application/json' in response.headers.get('Content-Type', ''):
        return response.json()['message']
    else:
        return response.text


def load_and_save_gui_customization(semester, course, token):
    """Loads and saves the GUI customization for the given course."""
    # Load the GUI customization page via server-side rendering for data parsing
    load_response = requests.post(
        '{}/api/courses/{}/{}/reports/rainbow_grades_customization'.format(
            base_url, semester, course
        ),
        headers={'Authorization': token},
    )

    if load_response.status_code == 200 and "rg_web_ui" in load_response.text:
        print("Successfully loaded Rainbow Grades GUI customization for {}.{}".format(
            semester, course
        ))
    else:
        print("ERROR: Failed to load Rainbow Grades GUI customization for {}.{} - {}".format(
            semester, course, get_error_message(load_response)
        ), file=stderr)
        exit(-1)

    # Save the most up-to-date GUI customization to the server
    json_string = build_json(load_response.text)
    save_response = requests.post(
        '{}/api/courses/{}/{}/reports/rainbow_grades_customization_save'.format(
            base_url, semester, course
        ),
        headers={'Authorization': token},
        data={"json_string": json_string}
    )

    if save_response.status_code == 200 and save_response.json()['status'] == 'success':
        print("Successfully saved Rainbow Grades GUI customization for {}.{}".format(
            semester, course
        ))
    else:
        print("ERROR: Failed to save Rainbow Grades GUI customization for {}.{} - {}".format(
            semester, course, get_error_message(save_response)
        ), file=stderr)
        exit(-1)

    # Finalize the build process by submitting the build_form API call
    build_response = requests.post(
        '{}/api/courses/{}/{}/reports/build_form'.format(
            base_url, semester, course
        ),
        headers={
            'Authorization': token,
            'source': 'submitty_daemon'
        }
    )

    if build_response.status_code == 200 and build_response.json()['status'] == 'success':
        print("Successfully submitted the Rainbow Grades build process for {}.{}".format(
            semester, course
        ))
    else:
        print("ERROR: Failed to submit the Rainbow Grades build process for {}.{} - {}".format(
            semester, course, get_error_message(build_response)
        ), file=stderr)
        exit(-1)

    # Remain blocked until the build process is complete
    requests.post(
        '{}/api/courses/{}/{}/reports/rainbow_grades_status'.format(
            base_url, semester, course
        ),
        headers={'Authorization': token}
    )
    print("Successfully completed the Rainbow Grades build process for {}.{}".format(
        semester, course
    ))


def save_and_build_rainbow_grades(semester, course, token):
    """Loads and saves the GUI customization for the given course."""
    try:
        # Try to select the GUI customization option to save changes based on the existing customization files
        select_response = requests.post(
            '{}/api/courses/{}/{}/reports/rainbow_grades_customization/manual_or_gui'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token},
            data={
                "selected_value": "gui",
                "source": "submitty_daemon"
            }
        )

        if select_response.status_code == 200:
            status = select_response.json()['status']

            if status == 'success':
                # Successful response implies the GUI customization is being applied for the given course
                load_and_save_gui_customization(semester, course, token)
            else:
                message = select_response.json()['message']

                if message == 'Manual customizations are currently applied.':
                    # Manual customizations are currently applied, so we can proceed with the grade summaries
                    pass
                else:
                    print("ERROR: Failed to select the GUI customization for {}.{} - {}".format(
                        semester, course, message
                    ), file=stderr)
                    exit(-1)
    except Exception as gui_exception:
        print("ERROR: Failed to load or save GUI customization for {}.{} - {}".format(
            semester, course, gui_exception
        ), file=stderr)
        exit(-1)


def generate_grade_summaries(semester, course, token):
    """Generates grade summaries for the given course."""
    try:
        grade_generation_response = requests.post(
            '{}/api/courses/{}/{}/reports/summaries'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token}
        )

        if grade_generation_response.status_code == 200:
            grade_generation_response = grade_generation_response.json()

            if grade_generation_response["status"] == 'success':
                print("Successfully generated grade summaries for {}.{}".format(
                    semester, course
                ))
            else:
                print("ERROR: Failed to generate grade summaries for {}.{} - {}".format(
                    semester, course, get_error_message(grade_generation_response)
                ), file=stderr)
        else:
            print("ERROR: Submitty Service Unavailable.", file=stderr)
    except Exception as grade_generation_exception:
        print("ERROR: Failed to generate grade summaries for {}.{} - {}".format(
            semester, course, grade_generation_exception
        ), file=stderr)
        exit(-1)


def main():
    parser = argparse.ArgumentParser(
        description='Automatically call API endpoints to save/load GUI customizations and generate Grade Summaries.'
    )
    parser.add_argument('semester')
    parser.add_argument('course')
    parser.add_argument('source')
    args = parser.parse_args()

    semester = args.semester
    course = args.course
    token = creds['token']
    source = args.source

    if source == 'submitty_daemon':
        # Only save and build Rainbow Grades if the source is submitty_daemon
        save_and_build_rainbow_grades(semester, course, token)

    # Always generate Rainbow Grades Grade Summaries
    generate_grade_summaries(semester, course, token)


if __name__ == "__main__":
    main()
