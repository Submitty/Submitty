When loading a sample configuration into the vagrant box for a course, it will try and create some number of fake
submissions with the files taken from the (and in the tutorial repo) folders for the given sample assignment. It
determines what to submit by following these steps:

Is there a directory that gradeable in sample_submissions, is there files in that folder, and is there a submissions.yml
file?

If the answer the first two parts of that question is "No", then no fake submissions will be made for that gradeable.
Otherwise, then the submissions.yml file comes into play. However, if the submisisons.yml file exists, and is empty,
then that gradeable will be skipped for fake submissions.
  
a) No, there is no submissions.yml file  

Every invididual file and directory in the sample_submissions folder for a given gradeable is considered a valid 
submission. This is best when all files are standalone and there is no junk files in a directory.


b) Yes, there is a submissions.yml file

The submissions.yml file can be used to choose how the submissions folder is organized and what to submit for a given
submission attempt. This file at its simplest is just a list of elements where each element is either a string or
another list, which then only contains strings. These strings would be either a file name or a directory name. If a
directory is specified, then all files in that directory are zipped and submitted as one zip, where as the specified
file is submitted as is.

For the first case, an example submissions.yml would be:
```yaml
- file1.py
- file2.py
- dir1
```

This tells us that each of these is a valid submission attempt. This allows us to also place a README.md in that
directory and the system won't consider using that file for a submission. However, sometimes you have multiple files
that would make a submission attempt (like in C++ for .cpp and .h files), which is where the list of lists comes in.
An example for this would be:
```yaml
- - file1.cpp
  - file2.h
- file3.txt
- dir1
```

This now says that a submission attempt would contain the two files `file1.cpp` and `file2.h`, and then the other
attempts would be just `file3.txt` and just `dir1`. In this fashion, you can specify exactly what files are to be
included in the attempt, even if the attempt has multiple files.

Finally, the final type of gradeable is for multi-parted, which is handled where you must have a submissions.yml file
which contains a dictionary where each key is `part#` for each part in the gradeable. For example, for something with
three parts:
```yaml
part1:
  - part1_file1.py
  - part1_file2.py
part2:
  - part2_file1.py
  - part2_file2.py
part3:
  - - part3_file1_1.py
    - part3_file1_2.py
  - part3_file2.py
```
