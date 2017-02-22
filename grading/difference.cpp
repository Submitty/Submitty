#include "difference.h"
#include "json.hpp"
#include "clean.h"
#include "myersDiff.h"

// FIXME: Thus function has terrible variable names (diff1, diff2, a, b) 
//   and the code is insufficiently commented for long term maintenance.


void Difference::printJSON(std::ostream & file_out) {
  std::string diff1_name;
  std::string diff2_name;
  
  switch (type) {
    // ByLineByChar;
    // ByWordByChar;
    // VectorVectorStringType;
    // ByLineByWord;
    // VectorOtherType;
    
  case ByLineByChar:
    diff1_name = "line";
    diff2_name = "char";
    break;
  case ByWordByChar:
    diff1_name = "word";
    diff2_name = "char";
    break;
  case ByLineByWord:
    diff1_name = "line";
    diff2_name = "word";
    break;
  default:
    diff1_name = "line";
    diff2_name = "char";
    break;
  }

  nlohmann::json whole_file;
  
  // always have a "differences" tag, even if it is an empty array
  whole_file["differences"] = nlohmann::json::array();

  for (unsigned int a = 0; a < changes.size(); a++) {
    nlohmann::json blob;
    nlohmann::json student;
    nlohmann::json expected;

    student["start"] = changes[a].a_start;
    for (unsigned int b = 0; b < changes[a].a_changes.size(); b++) {
      nlohmann::json d1;
      d1[diff1_name+"_number"] = changes[a].a_changes[b];
      if (changes[a].a_characters.size() >= b && 
          changes[a].a_characters.size() > 0 &&
          changes[a].a_characters[b].size() > 0) {
        nlohmann::json d2;
        for (unsigned int c=0; c< changes[a].a_characters[b].size(); c++) {
          d2.push_back(changes[a].a_characters[b][c]);
        }
        d1[diff2_name+"_number"] = d2;
      }
      student[diff1_name].push_back(d1);
    }

    expected["start"] = changes[a].b_start;
    for (unsigned int b = 0; b < changes[a].b_changes.size(); b++) {
      nlohmann::json d1;
      d1[diff1_name+"_number"] = changes[a].b_changes[b];
      if (changes[a].b_characters.size() >= b && 
          changes[a].b_characters.size() > 0 &&
          changes[a].b_characters[b].size() > 0) {
        nlohmann::json d2;
        for (unsigned int c=0; c< changes[a].b_characters[b].size(); c++) {
          d2.push_back(changes[a].b_characters[b][c]);
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



void INSPECT_CHANGES(std::ostream& ostr, const Change &c,
			    const std::vector<std::string> &adata,
			    const std::vector<std::string>  &bdata,
			    const nlohmann::json& j,
			    bool &only_whitespace,
			    bool extra_student_output_ok,
			    int &line_added,
			    int &line_deleted,
			    int &char_added,
			    int &char_deleted) {

  std::string added;
  std::string deleted;
  int tmp_line_added = 0;
  int tmp_line_deleted = 0;
  int tmp_char_added = 0;
  int tmp_char_deleted = 0;
  std::cout << "before" << std::endl;
  bool ignore_line_endings = false;
  if (j != nlohmann::json()) {
    j.value("ignore_line_endings",false);
  }
  std::cout << "after" << std::endl;
  bool further_check = false;

  if (c.a_changes.size() != 0 && c.b_changes.size() != 0 &&
      c.a_changes.size() != c.b_changes.size()) {
    further_check = true;
  }

  for (int i = 0; i < c.a_changes.size(); i++) {
    int line = c.a_changes[i];
    tmp_line_added++;
    assert (line >= 0 && line < adata.size());
    if (c.a_characters.size()==0) {
      if (!further_check) { tmp_char_added += adata[line].size(); }
      added+=adata[line] + '\n';
    }
  }
  for (int i = 0; i < c.b_changes.size(); i++) {
    int line = c.b_changes[i];
    tmp_line_deleted++;
    assert (line >= 0 && line < bdata.size());
    if (c.b_characters.size()==0) {
      if (!further_check) { tmp_char_deleted += bdata[line].size(); }
      deleted+=bdata[line] + '\n';
    }
  }

  // if there are more lines in b (expected)
  if (c.a_changes.size() < c.b_changes.size()) only_whitespace = false;

  // if there are more lines in a (student), that might be ok...
  if (c.a_changes.size() != c.b_changes.size()) {
    // but if extra student output is not ok
    if (!extra_student_output_ok
	||
	c.b_changes.size() != 0)
      only_whitespace = false;
  }

  for (int i = 0; i < c.a_characters.size(); i++) {
    for (int j = 0; j < c.a_characters[i].size(); j++) {
      int row = c.a_changes[i];
      int col = c.a_characters[i][j];
      if (adata[row][col] != ' ') only_whitespace = false;
      if (adata[row][col] == '\r') { std::cout << "line ending diff" << std::endl; }
      tmp_char_added++;
      added.push_back(adata[row][col]);
    }
  }

  for (int i = 0; i < c.b_characters.size(); i++) {
    for (int j = 0; j < c.b_characters[i].size(); j++) {
      int row = c.b_changes[i];
      int col = c.b_characters[i][j];
      if (bdata[row][col] != ' ') only_whitespace = false;
      if (bdata[row][col] == '\r') { std::cout << "line ending diff" << std::endl; }
      tmp_char_deleted++;
      deleted.push_back(bdata[row][col]);
    }
  }

#if 0
  std::cout << "-----------" << std::endl;
  //if (added.size() > 0) { assert (added.back() == '\n'); added.pop_back(); }
  std::cout << "added   '" << added << "'" << std::endl;
  //if (deleted.size() > 0) { assert (deleted.back() == '\n'); deleted.pop_back(); }
  std::cout << "deleted '" << deleted << "'" << std::endl;
#endif

  if (further_check) {
    //std::cout << "SOMETHING BETTER NEEDED HERE" << std::endl;
    nlohmann::json j;
    vectorOfLines a;
    vectorOfLines b;
    a.push_back(added);
    b.push_back(deleted);
    Difference *HERE = ses(j, &a, &b, true);
    assert (HERE != NULL);
    //std::cout << "EXTRA " << HERE->char_added << " " << HERE->char_deleted << std::endl;
    tmp_char_added += HERE->char_added;
    tmp_char_added += HERE->char_deleted;
    //std::cout << "DONE SOMETHING BETTER NEEDED HERE" << std::endl;
  }

  line_added += tmp_line_added;
  line_deleted += tmp_line_deleted;
  char_added += tmp_char_added;
  char_deleted += tmp_char_deleted;

#if 0
  std::cout << "line_added=" << std::setw(6) << tmp_line_added << " " << " line_deleted=" << std::setw(6) << tmp_line_deleted
	    << " char_added=" << std::setw(6) << tmp_char_added << " " << " char_deleted=" << std::setw(6) << tmp_char_deleted << "  | cumm:  ";
  std::cout << "line_added=" << std::setw(6) << line_added << " " << " line_deleted=" << std::setw(6) << line_deleted
	    << " char_added=" << std::setw(6) << char_added << " " << " char_deleted=" << std::setw(6) << char_deleted << std::endl;
#endif

}
