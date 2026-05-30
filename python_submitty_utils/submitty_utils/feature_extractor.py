# pylint: disable=line-too-long,invalid-name
import ast


def get_expr_depth(node):
    """Recursively calculates the maximum depth of an AST expression node."""
    if not isinstance(node, ast.AST):
        return 0
    max_child_depth = 0
    for child in ast.iter_child_nodes(node):
        max_child_depth = max(max_child_depth, get_expr_depth(child))
    return 1 + max_child_depth


class CodeFeatureExtractor:
    """
    Extracts Layer 1 (Structural), Layer 2 (Behavioral), and Layer 3 (Metrics)
    features from Python source code for clustering analysis.
    """

    def __init__(self):
        self.features = {}
        self.reset_features()

    def reset_features(self):
        # We define them in exact order so list(dict.values()) is stable and reproducible.
        self.features = {
            # Layer 1: Control Flow Patterns
            'for_loop_count': 0,
            'while_loop_count': 0,
            'has_recursion': False,
            'recursion_count': 0,
            'max_recursion_depth': 0,
            'recursion_style': 0,  # 0: None, 1: Linear, 2: Tree, 3: Mutual
            'iteration_pattern': 0,
            'if_statement_count': 0,
            'nested_if_depth': 0,
            'switch_statement_count': 0,  # Match statements
            'ternary_operator_count': 0,
            'loop_total_count': 0,

            # Layer 1: Boundary & Defensive
            'boundary_check_count': 0,
            'defensive_programming_level': 0.0,
            'error_handling_strategy': 0,  # 0: None, 1: Exception, 2: Assertion
            'error_handling_count': 0,

            # Layer 1: Modularity
            'function_count': 0,
            'helper_function_count': 0,
            'modularity_score': 0.0,
            'average_function_length': 0.0,

            # Layer 1: Data Structure & Memory
            'array_access_count': 0,
            'pointer_dereference_count': 0,  # Mapped to unpacking operators in Python
            'memory_allocation_count': 0,
            'memory_deallocation_count': 0,
            'memory_leak_risk': 0.0,
            'struct_access_count': 0,
            'string_operations_count': 0,

            # Layer 2: Behavioral (From test results)
            'test_pass_rate': 0.0,
            'total_tests_passed': 0,
            'total_tests_failed': 0,
            'total_tests_run': 0,
            'consecutive_passes': 0,
            'consecutive_failures': 0,

            # Layer 3: Size Metrics
            'total_lines': 0,
            'code_lines': 0,
            'comment_lines': 0,
            'blank_lines': 0,
            'code_density': 0.0,

            # Layer 3: Complexity
            'cyclomatic_complexity': 1,
            'max_nesting_depth': 0,
            'average_nesting_depth': 0.0,
            'statement_simplicity_score': 0.0,
            'max_expression_depth': 0,
            'average_expression_depth': 0.0,

            # Layer 3: Style
            'comment_ratio': 0.0,
            'identifier_quality': 0.0,
            'code_maintainability_score': 0.0,
            'variable_reuse_ratio': 0.0
        }

    def extract_features(self, source_code: str, test_results: dict = None) -> dict:
        """Main entry point. Parses AST and extracts all features."""
        self.reset_features()

        # 1. Extract Layer 3 text-based metrics first
        self._extract_text_metrics(source_code)

        # 2. Extract AST based features (Layer 1 & 3)
        try:
            tree = ast.parse(source_code)
            self._extract_ast_features(tree)
        except SyntaxError:
            # If code doesn't parse, we return empty/default features but keep text metrics
            pass

        # 3. Extract Layer 2 features from test_results
        if test_results:
            self._extract_behavioral_features(test_results)

        return self.features

    def _extract_text_metrics(self, source_code: str):
        lines = source_code.split('\n')
        self.features['total_lines'] = len(lines)

        for line in lines:
            stripped = line.strip()
            if not stripped:
                self.features['blank_lines'] += 1
            elif stripped.startswith('#'):
                self.features['comment_lines'] += 1
            else:
                self.features['code_lines'] += 1

        if self.features['total_lines'] > 0:
            self.features['comment_ratio'] = self.features['comment_lines'] / self.features['total_lines']

    def _extract_ast_features(self, tree):
        # We will use an AST NodeVisitor to walk the tree
        visitor = FeatureNodeVisitor(self.features)
        visitor.visit(tree)

        # Post-process some aggregated metrics
        self.features['loop_total_count'] = self.features['for_loop_count'] + self.features['while_loop_count']

        if self.features['function_count'] > 0:
            self.features['average_function_length'] = self.features['code_lines'] / self.features['function_count']
            self.features['modularity_score'] = self.features['function_count'] / \
                max(1, self.features['cyclomatic_complexity'])

        # Calculate code density properly (statements per code line)
        if self.features['code_lines'] > 0:
            self.features['code_density'] = visitor.statement_count / self.features['code_lines']

        if visitor.expr_depths:
            self.features['average_expression_depth'] = sum(visitor.expr_depths) / len(visitor.expr_depths)

        if visitor.nesting_depths:
            self.features['average_nesting_depth'] = sum(visitor.nesting_depths) / len(visitor.nesting_depths)

        self.features['defensive_programming_level'] = (self.features['error_handling_count'] * 2.0) + self.features['boundary_check_count']

        # Statement simplicity score
        self.features['statement_simplicity_score'] = max(0.0, 10.0 - self.features['average_expression_depth'])

        # Identifier quality
        if visitor.all_variables:
            good_names = 0
            for var in visitor.all_variables:
                if len(var) > 1 or var in ['i', 'j', 'k', 'x', 'y', 'z', '_']:
                    good_names += 1
            self.features['identifier_quality'] = good_names / len(visitor.all_variables)

            unique_vars = len(set(visitor.all_variables))
            if visitor.assignments_count > 0:
                self.features['variable_reuse_ratio'] = (visitor.assignments_count - unique_vars) / visitor.assignments_count

        # Maintainability
        self.features['code_maintainability_score'] = max(0.0, 100.0 - (self.features['cyclomatic_complexity'] * 1.5) - (self.features['average_nesting_depth'] * 5.0) + (self.features['comment_ratio'] * 10.0))

    def _extract_behavioral_features(self, test_results: dict):
        # test_results is expected to look like:
        # { 'total_tests': 10, 'passed': 8, 'failed': 2, 'results': [True, True, False, ...] }
        self.features['total_tests_run'] = test_results.get('total_tests', 0)
        self.features['total_tests_passed'] = test_results.get('passed', 0)
        self.features['total_tests_failed'] = test_results.get('failed', 0)

        if self.features['total_tests_run'] > 0:
            self.features['test_pass_rate'] = self.features['total_tests_passed'] / self.features['total_tests_run']

        results = test_results.get('results', [])
        max_consecutive_passes = 0
        max_consecutive_fails = 0
        current_passes = 0
        current_fails = 0

        for r in results:
            if r:
                current_passes += 1
                current_fails = 0
                max_consecutive_passes = max(max_consecutive_passes, current_passes)
            else:
                current_fails += 1
                current_passes = 0
                max_consecutive_fails = max(max_consecutive_fails, current_fails)

        self.features['consecutive_passes'] = max_consecutive_passes
        self.features['consecutive_failures'] = max_consecutive_fails


class FeatureNodeVisitor(ast.NodeVisitor):
    """AST Visitor that extracts structural features from Python code."""
    def __init__(self, features):
        self.features = features
        self.current_nesting = 0
        self.current_if_nesting = 0
        self.current_function = None
        self.functions_defined = set()
        self.function_calls = []
        
        self.expr_depths = []
        self.nesting_depths = []
        self.all_variables = []
        self.assignments_count = 0
        self.statement_count = 0

    def generic_visit(self, node):
        if isinstance(node, ast.stmt):
            self.statement_count += 1
            if isinstance(node, ast.Expr):
                depth = get_expr_depth(node.value)
                self.expr_depths.append(depth)
                self.features['max_expression_depth'] = max(self.features['max_expression_depth'], depth)
            elif isinstance(node, (ast.Assign, ast.AnnAssign, ast.AugAssign)):
                depth = get_expr_depth(node)
                self.expr_depths.append(depth)
                self.features['max_expression_depth'] = max(self.features['max_expression_depth'], depth)
        super().generic_visit(node)

    def visit_For(self, node):
        self.features['for_loop_count'] += 1
        self.features['cyclomatic_complexity'] += 1
        
        if isinstance(node.iter, ast.Call) and getattr(node.iter.func, 'id', '') == 'range':
            self.features['iteration_pattern'] = max(self.features['iteration_pattern'], 1)
        else:
            self.features['iteration_pattern'] = 2
            
        self._handle_nesting(node)

    def visit_While(self, node):
        self.features['while_loop_count'] += 1
        self.features['cyclomatic_complexity'] += 1
        self._handle_nesting(node)

    def visit_If(self, node):
        self.features['if_statement_count'] += 1
        self.features['cyclomatic_complexity'] += 1
        
        self.current_if_nesting += 1
        self.features['nested_if_depth'] = max(self.features['nested_if_depth'], self.current_if_nesting)
        
        if isinstance(node.test, ast.Compare):
            if any(isinstance(comp, ast.Call) and getattr(comp.func, 'id', '') == 'len' for comp in [node.test.left] + node.test.comparators):
                self.features['boundary_check_count'] += 1
                
        self._handle_nesting(node)
        self.current_if_nesting -= 1

    def visit_IfExp(self, node):
        # Ternary operator: a if condition else b
        self.features['ternary_operator_count'] += 1
        self.features['cyclomatic_complexity'] += 1
        self.generic_visit(node)

    def visit_Match(self, node):  # Python 3.10+
        self.features['switch_statement_count'] += 1
        self.features['cyclomatic_complexity'] += len(node.cases)
        self.generic_visit(node)

    def visit_Try(self, node):
        self.features['error_handling_count'] += 1
        self.features['error_handling_strategy'] = max(self.features['error_handling_strategy'], 1)
        self.features['cyclomatic_complexity'] += len(node.handlers)
        self.generic_visit(node)

    def visit_Assert(self, node):
        self.features['error_handling_count'] += 1
        self.features['error_handling_strategy'] = max(self.features['error_handling_strategy'], 2)
        self.generic_visit(node)

    def visit_FunctionDef(self, node):
        self.features['function_count'] += 1
        self.functions_defined.add(node.name)
        if node.name.startswith('_'):
            self.features['helper_function_count'] += 1

        prev_function = self.current_function
        self.current_function = node.name
        self.generic_visit(node)
        self.current_function = prev_function

    def visit_Name(self, node):
        if isinstance(node.ctx, ast.Store):
            self.all_variables.append(node.id)
            self.assignments_count += 1
        self.generic_visit(node)

    def visit_arg(self, node):
        self.all_variables.append(node.arg)
        self.generic_visit(node)

    def visit_Call(self, node):
        if isinstance(node.func, ast.Name):
            func_name = node.func.id
            self.function_calls.append(func_name)

            # Simple recursion check
            if self.current_function == func_name:
                self.features['has_recursion'] = True
                self.features['recursion_count'] += 1
                self.features['recursion_style'] = 1  # Linear (simplified check)

            # String ops heuristic (len, str, print)
            if func_name in ['len', 'str', 'print', 'format']:
                self.features['string_operations_count'] += 1
            elif func_name == 'open':
                self.features['memory_leak_risk'] += 0.5

        elif isinstance(node.func, ast.Attribute):
            # Object method calls, often string ops like .join(), .split()
            self.features['string_operations_count'] += 1
            
        for arg in node.args:
            if isinstance(arg, ast.Starred):
                self.features['pointer_dereference_count'] += 1
        for kw in node.keywords:
            if kw.arg is None:
                self.features['pointer_dereference_count'] += 1

        self.generic_visit(node)
        
    def visit_List(self, node):
        self.features['memory_allocation_count'] += 1
        self.generic_visit(node)
        
    def visit_Dict(self, node):
        self.features['memory_allocation_count'] += 1
        self.generic_visit(node)
        
    def visit_Set(self, node):
        self.features['memory_allocation_count'] += 1
        self.generic_visit(node)
        
    def visit_ListComp(self, node):
        self.features['memory_allocation_count'] += 1
        self.generic_visit(node)
        
    def visit_Delete(self, node):
        self.features['memory_deallocation_count'] += 1
        self.generic_visit(node)

    def visit_With(self, node):
        self.features['memory_leak_risk'] = max(0.0, self.features['memory_leak_risk'] - 0.5)
        self.generic_visit(node)

    def visit_Subscript(self, node):
        self.features['array_access_count'] += 1
        self.generic_visit(node)

    def visit_Attribute(self, node):
        self.features['struct_access_count'] += 1
        self.generic_visit(node)

    def _handle_nesting(self, node):
        self.current_nesting += 1
        self.nesting_depths.append(self.current_nesting)
        self.features['max_nesting_depth'] = max(self.features['max_nesting_depth'], self.current_nesting)
        self.generic_visit(node)
        self.current_nesting -= 1
