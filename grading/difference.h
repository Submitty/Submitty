#ifndef __differences__difference__
#define __differences__difference__
#include <string>
#include <vector>

#include "change.h"

#include "testResults.h"

#include "json.hpp"

#define tab "    "
#define OtherType 0
#define ByLineByChar 1

class Difference: public TestResults {
public:
  Difference();
  std::vector<Change> changes;
  std::vector<int> diff_a; //student
  std::vector<int> diff_b; //expected
  void printJSON(std::ostream & file_out);
  int output_length_a;
  int output_length_b;
  int edit_distance;
  int type;
  bool extraStudentOutputOk;
  bool only_whitespace_changes;

  int line_added;
  int line_deleted;
  int total_line;
  int char_added;
  int char_deleted;
  int total_char;

  std::string message;

  void PrepareGrade(const nlohmann::json& j);
};

#endif /* defined(__differences__difference__) */
