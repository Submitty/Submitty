#include "difference.h"
#include "clean.h"
#include "myersDiff.h"

// In Change (see change.h), a_* fields are student/actual output and b_* are expected/reference.
Difference::Difference() :
  TestResults(), output_length_a(0), output_length_b(0), edit_distance(0),
  type(OtherType), extraStudentOutputOk(false), only_whitespace_changes(false) {

  line_added = -1;
  line_deleted = -1;
  total_line = -1;
  char_added = -1;
  char_deleted = -1;
  total_char = -1;
}


// Emits JSON: { "differences": [ { "actual": {...}, "expected": {...} }, ... ] }
// Each block has start index and per-level entries ("line"/"line_number", "char"/"char_number").
void Difference::printJSON(std::ostream & file_out) {
  // JSON key names for the two diff levels (line-level and character-level)
  const std::string line_level_key = "line";
  const std::string char_level_key = "char";

  nlohmann::json whole_file;
  whole_file["differences"] = nlohmann::json::array();

  for (unsigned int block_idx = 0; block_idx < changes.size(); block_idx++) {
    const Change& change_block = changes[block_idx];
    nlohmann::json block_json;
    nlohmann::json student_json;
    nlohmann::json expected_json;

    // Student/actual side (a_* in Change)
    student_json["start"] = change_block.a_start;
    for (unsigned int line = 0; line < change_block.a_changes.size(); line++) {
      nlohmann::json line_entry;
      line_entry[line_level_key + "_number"] = change_block.a_changes[line];
      if (change_block.a_characters.size() > line &&
          change_block.a_characters[line].size() > 0) {
        nlohmann::json char_positions;
        for (unsigned int character = 0; character < change_block.a_characters[line].size(); character++) {
          char_positions.push_back(change_block.a_characters[line][character]);
        }
        line_entry[char_level_key + "_number"] = char_positions;
      }
      student_json[line_level_key].push_back(line_entry);
    }

    // Expected/reference side (b_* in Change)
    expected_json["start"] = change_block.b_start;
    for (unsigned int line = 0; line < change_block.b_changes.size(); line++) {
      nlohmann::json line_entry;
      line_entry[line_level_key + "_number"] = change_block.b_changes[line];
      if (change_block.b_characters.size() > line &&
          change_block.b_characters[line].size() > 0) {
        nlohmann::json char_positions;
        for (unsigned int character = 0; character < change_block.b_characters[line].size(); character++) {
          char_positions.push_back(change_block.b_characters[line][character]);
        }
        line_entry[char_level_key + "_number"] = char_positions;
      }
      expected_json[line_level_key].push_back(line_entry);
    }

    block_json["actual"] = student_json;
    block_json["expected"] = expected_json;
    whole_file["differences"].push_back(block_json);
  }

  file_out << whole_file.dump(4) << std::endl;
  return;
}


// Grading: (1) If config has max_char_changes, grade by character-change count;
// (2) else if extraStudentOutputOk, only missing (expected) lines deduct;
// (3) else grade by edit distance over max of student/expected length.
void Difference::PrepareGrade(const nlohmann::json& j) {
  std::cout << "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%" << std::endl;
  std::cout << "PREPARE GRADE" << std::endl;


  // --------------------------------------------------------
  // Branch 1: Grade by character-change bounds (min/max_char_changes).
  //std::cout << "json " << j.dump(4) << std::endl;
  if (j.find("max_char_changes") != j.end()) {
    std::cout << "MAX CHAR CHANGES" << std::endl;

    int max_char_changes = j.value("max_char_changes", -1);
    assert (max_char_changes > 0);
    int min_char_changes = j.value("min_char_changes",0);
    assert (min_char_changes >= 0);
    assert (min_char_changes < max_char_changes);

    assert (total_char > 0);
    if (max_char_changes > total_char) {
      std::cout << "WARNING!  max_char_changes > total_char)" << std::endl;
      max_char_changes = total_char;
      if (min_char_changes > max_char_changes) {
        min_char_changes = max_char_changes-1;
      }
      assert (min_char_changes >= 0);
    }
    assert (max_char_changes <= total_char);

    int char_changes = char_added + char_deleted;

    std::cout << "char_changes=" << char_changes << " min=" << min_char_changes << " max=" << max_char_changes << std::endl;

    int min_max_diff = max_char_changes-min_char_changes;
    int lower_bar = std::max(0,min_char_changes-min_max_diff);
    int upper_bar = max_char_changes + min_max_diff;

    assert (0 <= lower_bar &&
      lower_bar <= min_char_changes &&
      min_char_changes <= max_char_changes &&
      max_char_changes <= upper_bar);

    float grade;
    if (char_changes < lower_bar) {
      std::cout << "too few char changes (zero credit)" << std::endl;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Significantly fewer character changes than allowed."));
    } else if (char_changes < min_char_changes) {
      std::cout << "less than min char changes (partial credit)" << std::endl;
      float numer = min_char_changes - char_changes;
      float denom = min_max_diff;
      std::cout << "numer " << numer << " denom= " << denom << std::endl;
      assert (denom > 0);
      grade = 1 - numer/denom;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Fewer character changes than allowed."));
    } else if (char_changes < max_char_changes) {
      messages.push_back(std::make_pair(MESSAGE_SUCCESS,"Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Character changes within allowed range."));
      std::cout << "between min and max char changes (full credit)" << std::endl;
      grade = 1.0;
    } else if (char_changes < upper_bar) {
      std::cout << "more than max char changes (partial credit)" << std::endl;
      float numer = char_changes - max_char_changes;
      float denom = min_max_diff;
      assert (denom > 0);
      grade = 1 - numer/denom;
      std::cout << "numer " << numer << " denom= " << denom << std::endl;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  More character changes than allowed."));
    } else {
      std::cout << "too many char changes (zero credit)" << std::endl;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Significantly more character changes than allowed."));
      grade = 0.0;
    }
    std::cout << "grade " << grade << std::endl;
    assert (grade >= -0.00001 & grade <= 1.00001);
    this->setGrade(grade);
  }

  // --------------------------------------------------------
  // Branch 2: Extra student output allowed; only missing expected lines deduct.
  else if (this->extraStudentOutputOk) {
    int count_of_missing_lines = 0;
    for (int x = 0; x < this->changes.size(); x++) {
      int num_b_lines = this->changes[x].b_changes.size();
      if (num_b_lines > 0) {
        count_of_missing_lines += num_b_lines;
      }
    }
    int output_length = this->output_length_b;
    std::cout << "COMPARE outputlength=" << output_length << " missinglines=" << count_of_missing_lines << std::endl;
    assert (count_of_missing_lines <= output_length);
    float grade = 1.0;
    if (output_length > 0) {
      //std::cout << "SES [ESOO] calculating grade " << this->distance << "/" << output_length << std::endl;
      //grade -= (this->distance / (float) output_length );
      grade -= count_of_missing_lines / float(output_length);
      std::cout <<
        "grade:  missing_lines [ " << count_of_missing_lines <<
        "] / output_length " << output_length << "]\n";
      //std::cout << "SES [ESOO] calculated grade = " << std::setprecision(1) << std::fixed << std::setw(5) << grade << " " << std::setw(5) << (int)floor(5*grade) << std::endl;
      if (grade < 1.0 && this->only_whitespace_changes) {
        std::cout << "ONLY WHITESPACE DIFFERENCES! adjusting grade: " << grade << " -> ";
        // FIXME:  Ugly, but with rounding, this will be only a -1 point grade for this test case
        grade = std::max(grade,0.99f);
        std::cout << grade << std::endl;
      } else {
        std::cout << "MORE THAN JUST WHITESPACE DIFFERENCES! " << std::endl;
      }
    } else {
      assert (output_length == 0);
      std::cout << "NO OUTPUT, GRADE IS ZERO" << std::endl;
      grade = 0;
    }

    std::cout << "this test grade = " << grade << std::endl;
    this->setGrade(grade);
  }

  // --------------------------------------------------------
  // Branch 3: Strict comparison; both missing and extra lines reduce grade.
  else {
    int max_output_length = std::max(this->output_length_a, this->output_length_b);
    float grade = 1.0;
    if (max_output_length == 0) {
      grade = 0;
    } else {
      //std::cout << "SES  calculating grade " << this->distance << "/" << max_output_length << std::endl;
      grade -= (this->distance / (float) max_output_length );
      std::cout <<
        "grade:  this->distance [ " << this->distance <<
        "] / max_output_length " << max_output_length << "]\n";
      //std::cout << "SES calculated grade = " << grade << std::endl;
    }
    this->setGrade(grade);
  }
  // ===================================================
  std::cout << "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%" << std::endl;
}
