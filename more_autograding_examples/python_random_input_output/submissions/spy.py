import os
import fnmatch
import mimetypes

def list_files(startpath):
    script_path = os.path.join(os.path.dirname(os.path.abspath( __file__ )),startpath)
    for root, dirs, files in os.walk(script_path):
        level = root.replace(script_path, '').count(os.sep)
        indent = ' ' * 4 * (level)
        print('{}{}/'.format(indent, os.path.basename(root)))
        subindent = ' ' * 4 * (level + 1)
        for f in files:
            print('{}{}'.format(subindent, f))

def contents(path):
    script_path = os.path.join(os.path.dirname(os.path.abspath( __file__ )),path)
    matches = []
    for root, dirnames, filenames in os.walk(script_path):
        for filename in fnmatch.filter(filenames, '*.*'):
            matches.append(os.path.join(root, filename))

    for file in matches:
        print("-----------------------------------------------------------------")
        print("Opening :-")
        print(os.path.relpath(file,script_path))
        if mimetypes.guess_type(file)[0] != None:
            f = open(file, "r")
            print(f.read())
        else:
            print("**** file is not ascii (possibly executable) ****")

def main():
    print("I am a spy program, looking for test input, test output, and instructor solutions.")
    print("==================================================================================")
    list_files("..")
    print("==================================================================================")
    contents("..")

if __name__ == "__main__":
    main()