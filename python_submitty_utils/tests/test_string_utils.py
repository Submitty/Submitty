import unittest

from submitty_utils import string_utils


class TestUser(unittest.TestCase):
    def test_negative_length(self):
        self.assertEqual(string_utils.generate_random_string(-1), "")

    def test_zero_length(self):
        self.assertEqual(string_utils.generate_random_string(0), "")

    def test_positive_length(self):
        self.assertEqual(len(string_utils.generate_random_string(1)), 1)

    def test_random(self):
        # Very low chance of generating the same string twice.
        for _ in range(10):
            self.assertNotEqual(
                string_utils.generate_random_string(10),
                string_utils.generate_random_string(10),
            )
            self.assertNotEqual(
                string_utils.generate_random_string(100),
                string_utils.generate_random_string(100),
            )
            self.assertNotEqual(
                string_utils.generate_random_string(1000),
                string_utils.generate_random_string(1000),
            )


if __name__ == "__main__":
    unittest.main()
