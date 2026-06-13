import unittest
from submitty_utils.feature_extractor import CodeFeatureExtractor

class TestCodeFeatureExtractor(unittest.TestCase):
    """Tests for Feature Extractor."""
    def setUp(self):
        self.extractor = CodeFeatureExtractor()

    def test_basic_iterative(self):
        code = """
def sum_array(arr):
    total = 0
    for i in arr:
        total += i
    return total
"""
        features = self.extractor.extract_features(code)
        self.assertEqual(features['function_count'], 1)
        self.assertEqual(features['for_loop_count'], 1)
        self.assertEqual(features['while_loop_count'], 0)
        self.assertEqual(features['has_recursion'], False)
        self.assertEqual(features['max_nesting_depth'], 1)
        self.assertEqual(features['cyclomatic_complexity'], 2) # Base 1 + 1 for loop

    def test_recursive(self):
        code = """
def factorial(n):
    if n <= 1:
        return 1
    return n * factorial(n-1)
"""
        features = self.extractor.extract_features(code)
        self.assertEqual(features['function_count'], 1)
        self.assertEqual(features['if_statement_count'], 1)
        self.assertEqual(features['has_recursion'], True)
        self.assertEqual(features['recursion_count'], 1)
        self.assertEqual(features['cyclomatic_complexity'], 2) # Base 1 + 1 if

    def test_behavioral_features(self):
        code = "print('hello')"
        test_results = {
            'total_tests': 5,
            'passed': 3,
            'failed': 2,
            'results': [True, True, False, True, False]
        }
        features = self.extractor.extract_features(code, test_results)
        self.assertEqual(features['total_tests_run'], 5)
        self.assertEqual(features['test_pass_rate'], 0.6)
        self.assertEqual(features['consecutive_passes'], 2)
        self.assertEqual(features['consecutive_failures'], 1)

    def test_error_handling(self):
        code = """
def safe_divide(a, b):
    try:
        assert b != 0
        return a / b
    except Exception:
        return 0
"""
        features = self.extractor.extract_features(code)
        self.assertEqual(features['error_handling_count'], 2) # Try and Assert
        self.assertEqual(features['error_handling_strategy'], 2) # 2 = Assertion (max strategy)

if __name__ == '__main__':
    unittest.main()
