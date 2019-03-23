import time
import subprocess
import os 

if __name__ == '__main__':
  delay_in_seconds = 5
  print('Stopping the local shipper daemon...')
  subprocess.call(["python3", "systemctl_wrapper.py", "stop", "--daemon", "shipper", "--target", "primary"])

  print('Stopping all local worker daemon...')
  subprocess.call(["python3", "systemctl_wrapper.py", "stop", "--daemon", "worker", "--target", "primary"])
  
  print('Stopping all remote worker daemons...')
  cmd = 'python3 {0} stop --daemon worker --target perform_on_all_workers'.format(os.path.join(os.getcwd(), 'systemctl_wrapper.py'))
  subprocess.call(["su", "-", "submitty_daemon", "-c",  cmd])

  print('Delaying {0} seconds to allow the system to stabilize...'.format(delay_in_seconds))
  time.sleep(delay_in_seconds)

  print('Starting the local shipper daemon...')
  subprocess.call(["python3", "systemctl_wrapper.py", "start", "--daemon", "shipper", "--target", "primary"])


  print('Starting the local worker daemon...')
  subprocess.call(["python3", "systemctl_wrapper.py", "start", "--daemon", "worker", "--target", "primary"])

  print('Starting all worker daemons...')
  cmd = 'python3 {0} start --daemon worker --target perform_on_all_workers'.format(os.path.join(os.getcwd(), 'systemctl_wrapper.py'))
  subprocess.call(["su", "-", "submitty_daemon", "-c", cmd])

  print('Finished!')