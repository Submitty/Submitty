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


void find_spot(const std::vector<std::string> &adata,
               int num_rows,
               int start_row,
               int offset,
               int &which_line,
               int &which_character) {
  which_line = start_row;
  which_character = offset;
  while (1) {
    if (adata[which_line].size()+1 > which_character) {
      break;
    }
    which_character -= (adata[which_line].size())+1;
    which_line++;
    if (which_line >= adata.size() -1) break;
  }
}


void IMPROVE(Change &c,
             const std::vector<std::string> &adata,
             const std::vector<std::string> &bdata,
             std::string &added,
             std::string &deleted,
             int &tmp_line_added,
             int &tmp_line_deleted,
             int &tmp_char_added,
             int &tmp_char_deleted) {

  //  std::cout << "SOMETHING BETTER NEEDED HERE" << std::endl;
  nlohmann::json j;
  vectorOfLines a;
  vectorOfLines b;
  a.push_back(added);
  b.push_back(deleted);
  Difference *HERE = ses(j, &a, &b, true);
  assert (HERE != NULL);
  //std::cout << "EXTRA " << HERE->char_added << " " << HERE->char_deleted << std::endl;
  tmp_char_added += HERE->char_added;
  tmp_char_deleted += HERE->char_deleted;

  assert (HERE->changes.size() == 1);
  Change &change = HERE->changes[0];
  assert (change.a_start == 0);
  assert (change.b_start == 0);
  assert (change.a_changes.size() == 1);
  assert (change.b_changes.size() == 1);
  assert (change.a_changes[0] == 0);
  assert (change.b_changes[0] == 0);


  int num_a_rows = c.a_changes.size();
  int num_b_rows = c.b_changes.size();
  int a_start = c.a_changes[0];
  int b_start = c.b_changes[0];

  c.a_characters.resize(num_a_rows);
  c.b_characters.resize(num_b_rows);

  for (int i = 0; i < change.a_characters[0].size(); i++) {
    int offset = change.a_characters[0][i];
    int which_line;
    int which_character;
    find_spot(adata,num_a_rows,a_start,offset,which_line,which_character);
    c.a_characters[which_line-a_start].push_back(which_character);
    //std::cout << "a: " << offset << " " << which_line << " " << which_character << std::endl;
  }
  for (int i = 0; i < change.b_characters[0].size(); i++) {
    int offset = change.b_characters[0][i];
    int which_line;
    int which_character;
    find_spot(bdata,num_b_rows,b_start,offset,which_line,which_character);
    c.b_characters[which_line-b_start].push_back(which_character);
    //std::cout << "b: " << offset << " " << which_line << " " << which_character << std::endl;
  }
  //std::cout << "DONE SOMETHING BETTER NEEDED HERE" << std::endl;
}



void INSPECT_IMPROVE_CHANGES(std::ostream& ostr, Change &c,
                             const std::vector<std::string> &adata,
                             const std::vector<std::string> &bdata,
                             const nlohmann::json& j,
                             bool &only_whitespace,
                             bool extra_student_output_ok,
                             int &line_added,
                             int &line_deleted,
                             int &char_added,
                             int &char_deleted) {

  //  std::cout << "IN INSPECT IMPROVE CHANGES" << std::endl;

  std::string added;
  std::string deleted;
  int tmp_line_added = 0;
  int tmp_line_deleted = 0;
  int tmp_char_added = 0;
  int tmp_char_deleted = 0;
  //std::cout << "before" << std::endl;
  bool ignore_line_endings = false;
  if (j != nlohmann::json()) {
    j.value("ignore_line_endings",false);
  }
  //std::cout << "after" << std::endl;
  bool further_check = false;

  if (c.a_changes.size() != 0 && c.b_changes.size() != 0 &&
      c.a_changes.size() != c.b_changes.size()) {
    further_check = true;
  }

  for (int i = 0; i < c.a_changes.size(); i++) {
    int line = c.a_changes[i];
    tmp_line_added++;
    assert (line >= 0 && line < adata.size());
    added+=adata[line] + '\n';
  }
  if (!further_check && c.a_characters.size()==0) {
    c.a_characters.resize(c.a_changes.size());
    for (int i = 0; i < c.a_changes.size(); i++) {
      int line = c.a_changes[i];
      // highlight rows!
      for (int ch = 0; ch < adata[line].size(); ch++) {
        c.a_characters[i].push_back(ch);
      }
    }
  }
  for (int i = 0; i < c.b_changes.size(); i++) {
    int line = c.b_changes[i];
    tmp_line_deleted++;
    assert (line >= 0 && line < bdata.size());
    deleted+=bdata[line] + '\n';
  }
  if (!further_check && c.b_characters.size()==0) {
    c.b_characters.resize(c.b_changes.size());
    for (int i = 0; i < c.b_changes.size(); i++) {
      int line = c.b_changes[i];
      // highlight rows!
      for (int ch = 0; ch < bdata[line].size(); ch++) {
        c.b_characters[i].push_back(ch);
      }
    }
  }

  // if there are more lines in b (expected)
  if (c.a_changes.size() < c.b_changes.size()) only_whitespace = false;

  // if there are more lines in a (student), that might be ok...
  if (c.a_changes.size() != c.b_changes.size()) {
    // but if extra student output is not ok
    if (!extra_student_output_ok || c.b_changes.size() != 0)
      only_whitespace = false;
  }

  if (!further_check) {
    for (int i = 0; i < c.a_characters.size(); i++) {
      for (int j = 0; j < c.a_characters[i].size(); j++) {
        int row = c.a_changes[i];
        int col = c.a_characters[i][j];
        if (adata[row][col] != ' ') only_whitespace = false;
        tmp_char_added++;
        added.push_back(adata[row][col]);
      }
    }
    for (int i = 0; i < c.b_characters.size(); i++) {
      for (int j = 0; j < c.b_characters[i].size(); j++) {
        int row = c.b_changes[i];
        int col = c.b_characters[i][j];
        if (bdata[row][col] != ' ') only_whitespace = false;
        tmp_char_deleted++;
        deleted.push_back(bdata[row][col]);
      }
    }
  }

#if 0
  std::cout << "-----------" << std::endl;
  std::cout << "added   '" << added << "'" << std::endl;
  std::cout << "deleted '" << deleted << "'" << std::endl;
#endif

  if (further_check) {
    IMPROVE(c,adata,bdata,added,deleted,
            tmp_line_added,tmp_line_deleted,tmp_char_added,tmp_char_deleted);
  }

  line_added += tmp_line_added;
  line_deleted += tmp_line_deleted;
  char_added += tmp_char_added;
  char_deleted += tmp_char_deleted;

#if 0
  if (j != nlohmann::json()) {
    std::cout << "line_added=" << std::setw(6) << tmp_line_added << " " << " line_deleted=" << std::setw(6) << tmp_line_deleted
              << " char_added=" << std::setw(6) << tmp_char_added << " " << " char_deleted=" << std::setw(6) << tmp_char_deleted << "  | cumm:  ";
    std::cout << "line_added=" << std::setw(6) << line_added << " " << " line_deleted=" << std::setw(6) << line_deleted
              << " char_added=" << std::setw(6) << char_added << " " << " char_deleted=" << std::setw(6) << char_deleted << std::endl;
  }
#endif

  //  std::cout << "LEAVING INSPECT IMPROVE CHANGES" << std::endl;

}
