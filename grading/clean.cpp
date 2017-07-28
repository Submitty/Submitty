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
