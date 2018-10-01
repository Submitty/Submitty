import os
import sys

if __name__ == "__main__":
  if len(sys.argv) < 3:
    print("Please specify a mode (read or write) and a file taget")
    sys.exit(1)

  mode = sys.argv[1]
  target = sys.argv[2]

  if mode == 'read':
    with open(target, 'r') as file:
       content = file.readlines()
    for line in content:
      print(line, end='')
  elif mode == 'write':
    target = os.path.join("dir", target)
    if not os.path.exists("dir"):
      os.makedirs("dir")
    with open(target, 'w') as file:
      file.write("Hi there!\n")
      file.write("This is a message\n")
      file.write("From test 1 to test 2!\n")
      file.flush()
    print("Wrote to the file!")
  else:
    print("ERROR: invalid mode!")
    sys.exit(1)