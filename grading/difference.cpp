#include "difference.h"
#include "clean.h"
#include "myersDiff.h"

// FIXME: Thus function has terrible variable names (diff1, diff2, a, b)
//   and the code is insufficiently commented for long term maintenance.
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


void Difference::printJSON(std::ostream & file_out) {
  std::string diff1_name = "line";
  std::string diff2_name = "char";

  nlohmann::json whole_file;

  // always have a "differences" tag, even if it is an empty array
  whole_file["differences"] = nlohmann::json::array();

  for (unsigned int block = 0; block < changes.size(); block++) {
    nlohmann::json blob;
    nlohmann::json student;
    nlohmann::json expected;

    student["start"] = changes[block].a_start;
    for (unsigned int line = 0; line < changes[block].a_changes.size(); line++) {
      nlohmann::json d1;
      d1[diff1_name+"_number"] = changes[block].a_changes[line];
      if (changes[block].a_characters.size() > line &&
          changes[block].a_characters[line].size() > 0) {
        nlohmann::json d2;
        for (unsigned int character=0; character< changes[block].a_characters[line].size(); character++) {
          d2.push_back(changes[block].a_characters[line][character]);
        }
        d1[diff2_name+"_number"] = d2;
      }
      student[diff1_name].push_back(d1);
    }

    expected["start"] = changes[block].b_start;
    for (unsigned int line = 0; line < changes[block].b_changes.size(); line++) {
      nlohmann::json d1;
      d1[diff1_name+"_number"] = changes[block].b_changes[line];
      if (changes[block].b_characters.size() > line &&
          changes[block].b_characters[line].size() > 0) {
        nlohmann::json d2;
        for (unsigned int character=0; character< changes[block].b_characters[line].size(); character++) {
          d2.push_back(changes[block].b_characters[line][character]);
        }
        d1[diff2_name+"_number"] = d2;
      }
      expected[diff1_name].push_back(d1);
    }

    blob["actual"] = student;
    blob["expected"] = expected;
    whole_file["differences"].push_back(blob);
  }

  file_out << whole_file.dump(4) << std::endl;
  return;
}


void Difference::PrepareGrade(const nlohmann::json& j) {
  std::cout << "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%" << std::endl;
  std::cout << "PREPARE GRADE" << std::endl;


  // --------------------------------------------------------
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
  else if (this->extraStudentOutputOk) {
    // only missing lines (deletions) are a problem
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
  else {
    // both missing lines (deletions) and extra lines are a deduction
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
