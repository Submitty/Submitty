#!/usr/bin/python

import sys


def main():
    if len(sys.argv) == 1:
        raise SystemError("Please provide relevant requirements.txt file path")
    file_paths = sys.argv[1:]
    dependencies = dict()
    for path in file_paths:
        check_dependencies_for_path(path, dependencies)
    pass


def check_dependencies_for_path(path, dependency_dict):
    with open(path) as file:
        for line in file:
            result = check_dependency(line.strip(), dependency_dict)
            if result is not None:
                name, version = result
                print(f"Mismatch dependency for {name} at file {path}, "
                      f"{version} is not {dependency_dict[name]}")
                exit(-1)
    pass


def check_dependency(dependency, dependency_dict):
    """
    >>> check_dependency("ABC==EFG", {})

    >>> check_dependency("ABC==EFG", {"XYZ": "==XYZ"})

    >>> check_dependency("ABC==EFG", {"ABC": "==EFG"})

    >>> check_dependency("ABC>=EFG", {"ABC": "==EFG"})
    ('ABC', '>=EFG')

    >>> check_dependency("ABC>=EFG", {"ABC": "==XYZ"})
    ('ABC', '>=EFG')
    """
    name, version = parse_dependency_line(dependency)
    if name not in dependency_dict:
        dependency_dict[name] = version
    else:
        if not version == dependency_dict[name]:
            return name, version
    return None


def parse_dependency_line(line):
    """
    >>> parse_dependency_line("ABC==EFG")
    ('ABC', '==EFG')

    >>> parse_dependency_line("ABC>=EFG")
    ('ABC', '>=EFG')

    >>> parse_dependency_line("ABC<=EFG")
    ('ABC', '<=EFG')

    >>> parse_dependency_line("ABC~=EFG")
    ('ABC', '~=EFG')

    >>> parse_dependency_line("ABC")
    ('ABC', '')
    """
    first_version_descriptor \
        = next(filter(lambda ch: ch in ['=', '<', '>', '~'], line), None)
    if first_version_descriptor is not None:
        separator = line.find(first_version_descriptor)
        return line[:separator], line[separator:]
    else:
        return line, ""


if __name__ == "__main__":
    main()
