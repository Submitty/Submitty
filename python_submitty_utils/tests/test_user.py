import subprocess
import unittest

from submitty_utils import user


class TestUser(unittest.TestCase):
    def testGetPhpPassword(self):
        password = user.get_php_db_password('password')
        self.assertTrue(len(password) > 0)
        proc = subprocess.Popen(
            ["php", "-r", "print(password_verify('{0}', password_hash('{0}', PASSWORD_DEFAULT)));".format(password)],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        (out, _) = proc.communicate()
        self.assertEqual('1', out.decode('utf-8'))


if __name__ == '__main__':
    unittest.main()
