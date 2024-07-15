/* FILENAME: clean.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 * The clean.h module is used for formatting raw output from students and
 * converting the format for various other modules. This module is a
 * dependency for a majority of the modules in this library and will be
 * required for creating custom grading modules.
 */

#include <iostream>
#include "clean.h"

/* METHOD: clean
 * ARGS: content: the body of text that needs cleaning
 * RETURN: void
 * PURPOSE: Removes all instances of \r\n and replaces with \n
 */
 void clean(std::string & content) {
  int pos = (int) content.find('\r');
  while (pos != std::string::npos) {
    if (content[pos + 1] == '\n') {
      content.erase(pos, 1);
    } else if (content[pos - 1] == '\n') {
      content.erase(pos, 1);
    } else {
      content[pos] = '\n';
    }
    pos = (int) content.find('\r');
  }
  return;
}

/* METHOD: stringToWords
 * ARGS: text: the body of text that needs cleaning
 * RETURN: vectorOfWords: a vector of vector of strings
 * PURPOSE: the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 */
vectorOfWords stringToWords(std::string text) {
  vectorOfWords contents;
  std::stringstream input(text);
  
  std::string word;
  while (getline(input, word)) {
    std::vector<std::string> text;
    std::stringstream line;
    line << word;
    while (line >> word) {
      text.push_back(word);
    }
    contents.push_back(text);
  }
  return contents;
}

std::string LimitLineLength(std::string word) {
  if (word.length() < 30) { return word; }
  for (int i = 0; i < word.size(); i++) {
    if (word[i] != '-') { return word; }
  }
  return std::string(30,'-');
}


vectorOfWords stringToWordsLimitLineLength(std::string text) {
  vectorOfWords contents;
  std::stringstream input(text);
  
  std::string word;
  while (getline(input, word)) {
    std::vector<std::string> text;
    std::stringstream line;
    line << word;
    while (line >> word) {
         text.push_back(LimitLineLength(word));
    }
    contents.push_back(text);
  }
  return contents;
}


int removeDOSnewlines(std::string& line) {
  int count = 0;
  std::string answer;
  for (int i = 0; i < line.size(); i++) {
    if (line[i] != '\r') {
      answer.push_back(line[i]);
    } else {
      count++;
    }
  }
  line = answer;
  return count;
}


/* METHOD: stringToLines
 * ARGS: text: the body of text that needs cleaning
 * RETURN: vectorOfLines: a vector of strings 
 * PURPOSE: eachstring is a line of text from the input
 */
vectorOfLines stringToLines(std::string text, const nlohmann::json &j) {
  vectorOfLines contents;
  std::stringstream input(text);

  bool has_DOS_newline = false;
  int DOS_newline_count = 0;

  bool ignore_line_endings = j.value("ignore_line_endings",false);

  std::string line;
  while (getline(input, line)) {
    if (line.find('\r') != std::string::npos) {
      has_DOS_newline = true;
      if (ignore_line_endings) {
        DOS_newline_count += removeDOSnewlines(line);
      }
    }
    contents.push_back(line);
  }
  if (has_DOS_newline && DOS_newline_count == 0) {
    std::cout << "WARNING:  This file has DOS newlines." << std::endl;
  } else if (DOS_newline_count > 0) {
    std::cout << "NOTE:  Removed " << DOS_newline_count << " DOS newlines" << std::endl;
  }
  return contents;
}

/* METHOD: linesToString
 * ARGS: text: the body of text that needs cleaning
 * in the form of a vector of strings where each string
 * is a line of text
 * RETURN: string: the string body 
 * PURPOSE: string converted from the vector input
 */
std::string linesToString(vectorOfLines text) {
  std::string contents;
  
  for (int a = 0; a < text.size(); a++) {
    contents += text[a] + '\n';
  }
  return contents;
}

/* METHOD: linesToWords
 * ARGS: text: the body of text that needs cleaning
 * in the form of a vector of strings where each string
 * is a line of text
 * RETURN: vectorOfWords: a vector of vector of strings
 * PURPOSE: the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 */
vectorOfWords linesToWords(vectorOfLines text) {
  vectorOfWords contents;
  for (int a = 0; a < text.size(); a++) {
    std::string word;
    std::stringstream line(text[a]);
    std::vector<std::string> temp;
    while (line >> word) {
      temp.push_back(word);
    }
    contents.push_back(temp);
  }
  return contents;
}

/* METHOD: wordsToString
 * ARGS: text: the body of text that needs cleaning
 * in the form of a vector of vector of strings
 * where the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 * RETURN: string: the body of text in raw form
 * PURPOSE: convert text back to original raw form
 */
std::string wordsToString(vectorOfWords text) {
  std::string contents;
  for (int a = 0; a < text.size(); a++) {
    std::string line;
    if (a > 0) {
      contents += "\n";
    }
    for (int b = 0; b < text[a].size(); b++) {
      if (b > 0) {
        line += " ";
      }
      line += text[a][b];
    }
    contents += line;
  }
  return contents;
}

/* METHOD: wordsToLines
 * ARGS: text: a vector of vector of strings
 * where the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 * RETURN: vectorOfLines: the body of text
 * PURPOSE: converts into the form of a vector of strings where each string
 * is a line of text
 */
vectorOfLines wordsToLines(vectorOfWords text) {
  vectorOfLines contents;
  for (int a = 0; a < text.size(); a++) {
    std::string line;
    for (int b = 0; b < text[a].size(); b++) {
      if (b > 0) {
        line += " ";
      }
      line += text[a][b];
    }
    contents.push_back(line);
  }
  return contents;
}

/* METHOD: stringToWordsAndSpaceList
 * ARGS: text: string
 * ARGS: vectorOfWords
 * ARGS: spaceVector: Reference to a vectorOfSpaces
 * PURPOSE: converts a string into a vector whose length is number of lines in the string
 * and each element in the vector is a vector containing the words  in each line.
 * Sets the spaceVector to a vector containing number of spaces between two words in line
 */
void stringToWordsAndSpaceList(std::string const &text, vectorOfWords &contents, vectorOfSpaces &spaceVector) {
  assert (contents.size() == 0);
  assert (spaceVector.size() == 0);
  std::stringstream input(text);
  int lineNum = 0;
  std::string line;
  while (getline(input, line)) {
    std::vector<std::string> words;
    std::vector<int> gaps;
    int count = 0;
    std::string word;
    bool gap = true;
    for (char chr : line) {
      if (std::isspace(chr)) {
        if (!gap) {
          words.push_back(word);
          word.clear();
          gap = true;
        }
        count += 1;
      } else {
        if (gap) {
          gaps.push_back(count);
          count=0;
          gap = false;
        }
        word.push_back(chr);
      }
    }
    if (!gap) {
      words.push_back(word);
    }
    gaps.push_back(count);
    contents.push_back(words);
    spaceVector.push_back(gaps);
    assert (gaps.size() == words.size()+1);
    lineNum += 1;
  }
}

std::string recreateStudentFile(vectorOfWords studentFileWords, vectorOfSpaces studentSpaces) {
  assert (studentFileWords.size() == studentSpaces.size());
  int num_lines = studentFileWords.size();
  std::vector<std::string> updatedLines;
  for (size_t i = 0; i < num_lines; i++) {
    assert (studentFileWords[i].size()+1 == studentSpaces[i].size());
    size_t num_words = studentFileWords[i].size();
    std::string line = "";
    for (size_t j = 0; j < num_words; j++) {
      line += std::string(studentSpaces[i][j],' ');
      line += studentFileWords[i][j];
    }
    line += std::string(studentSpaces[i][num_words],' ');
    updatedLines.push_back(line);
  }
  const char* const delim = "\n";
  std::ostringstream imploded;
  std::copy(updatedLines.begin(), updatedLines.end(),
            std::ostream_iterator<std::string>(imploded, delim));
  return imploded.str();
}

bool isNumber(const std::string &str) {
  std::string stripped_str = isolateAlphanumAndNumberPunctuation(str);
  bool atLeastOneDigit = false;
  bool dotFound = false;
  for (char const &c : stripped_str) {
    if (std::isdigit(c)) {
      atLeastOneDigit = true;
    }
    else if (c == '.') {
      if (dotFound) {
        return false;
      }
      dotFound = true;
    }
    else {
      return false;
    }
  }
  return atLeastOneDigit;
}

/* METHOD: isolateAlphanumAndNumberPunctuation
 * ARGS: str: string
 * RETURN: string
 * PURPOSE: remove non-alphanum, non-dot characters from around a string
 */
std::string isolateAlphanumAndNumberPunctuation(const std::string &str) {
  if (str.empty())
  {
    return str;
  }
  std::string::const_iterator begin = str.begin();
  while (!isalnum(*begin) && !(*begin == '.')) {
    begin++;
    if (begin == str.end())
    {
      return "";
    }
  }
  std::string::const_reverse_iterator end = str.rbegin();
  while (!isalnum(*end) && !(*end == '.')) {
    end++;
  }
  return std::string(begin, end.base());
}

bool whiteSpaceListsEqual(const std::vector<int> &expectedSpaces, const std::vector<int> &studentSpaces) {
  int len = std::min(studentSpaces.size(), expectedSpaces.size());
  for (size_t i = 0; i < len; i++)
  {
    if (studentSpaces[i] != expectedSpaces[i]) {
      return false;
    }
  }
  return true;
}
