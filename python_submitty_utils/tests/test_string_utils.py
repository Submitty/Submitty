import unittest

from submitty_utils import string_utils


class TestUser(unittest.TestCase):
    def testNegativeLength(self):
        self.assertEqual(string_utils.generate_random_string(-1), '')

    def testZeroLength(self):
        self.assertEqual(string_utils.generate_random_string(0), '')

    def testPositiveLength(self):
        self.assertEqual(len(string_utils.generate_random_string(1)), 1)

    def testRandom(self):
        # Very low chance of generating the same string twice.
        for _ in range(10):
            self.assertNotEqual(string_utils.generate_random_string(10), string_utils.generate_random_string(10))
            self.assertNotEqual(string_utils.generate_random_string(100), string_utils.generate_random_string(100))
            self.assertNotEqual(string_utils.generate_random_string(1000), string_utils.generate_random_string(1000))

if __name__ == '__main__':
    unittest.main()
