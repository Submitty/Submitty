#include <fstream>
#include <iostream>
#include <string>
#include <vector>
#include <cassert>
#include <cstdlib>


// a helper function to print a line of words in flush_left, flush_right, 
// or full_justify format and with vertical bars on the left and right sides
void print_line(std::vector<std::string> &line, std::ofstream &out_str, 
                int text_width, const std::string &justify_mode) {
  assert (line.size() > 0);

  // the left vertical bar
  out_str << "| ";

  // count the number of characters on the line
  int count = 0;
  for (unsigned int i = 0; i < line.size(); i++) {
    // add a space to the end of each words, except the last word
    if (i != line.size()-1) 
      line[i].push_back(' ');
    count += line[i].size();
  }

  int extra_spaces = text_width - count;

  // for full justify, just add these extra spaces between the words
  if (justify_mode == std::string("full_justify")) {
    int tmp = 0;
    while (extra_spaces > 0) {
      line[tmp] += ' ';
      tmp++;
      extra_spaces--;
      if (tmp > int(line.size())-2) 
	tmp = 0;
    }
  }

  // for flush right, put them all at the beginning of the line
  if (justify_mode == std::string("flush_right")) {
    for (int j = 0; j < extra_spaces; j++) {
      out_str << " ";
    }
  }

  // print out the words
  for (unsigned int i = 0; i < line.size(); i++) {
    out_str << line[i];
  }

  // for flush left, put them all at the end of the line
  if (justify_mode == std::string("flush_left")) {
    for (int j = 0; j < extra_spaces; j++) {
      out_str << " ";
    }
  }

  // the right vertical bar and newline
  out_str << " |" << std::endl;
}


// chop a long word into pieces that (including ending hyphen) are
// equal in length to the text_width
void break_into_pieces(const std::string &word, int text_width, 
                       std::vector<std::string> &pieces) {
  assert (int(word.size()) > text_width);
  // walk through all the letters of the word
  for (unsigned int i = 0; i < word.size(); i++) {
    // if the rest of the word does not fit on the line
    if (i != word.size()-1 && i % (text_width-1) == 0) {
      // add the hyphen at the end of the current line
      // (except the first time)
      if (i != 0) pieces[pieces.size()-1].push_back('-');
      // then start a new line 
      pieces.push_back(std::string(""));
    }
    // push back the letters of the word one at at time
    pieces[pieces.size()-1].push_back(word[i]);
  }
}


// this function processes all the words in the file, greedily packing
// the words into lines
void process_words(std::ifstream &in_str, std::ofstream &out_str, 
                   int text_width, const std::string &justify_mode) {

  // read the file into a vector of strings
  std::string tmp;
  std::vector<std::string> all_strings;
  while (in_str >> tmp) {
    all_strings.push_back(tmp);
  }

  // an index into the vector of all words
  unsigned int current_word = 0;  

  // keep track of the current line and the number of characters so far
  std::vector<std::string> line;
  int current_width = 0;

  // go through all the words
  while (current_word < all_strings.size()) {

    if (current_width == 0 && int(all_strings[current_word].size()) > text_width) {
      // if the next word is longer than the total line width, we need
      // to break up that word
      std::vector<std::string> pieces;
      break_into_pieces(all_strings[current_word],text_width,pieces);
      assert (pieces.size() >= 2);
      // each piece (except the last piece) is a line by itself
      for (unsigned int i = 0; i < pieces.size()-1; i++) {
	line.push_back(pieces[i]);
	current_width = text_width;
	print_line(line,out_str,text_width,justify_mode);
	line.clear();
      }
      // the last piece might fit with other words
      line.push_back(pieces[pieces.size()-1]);
      current_width = pieces[pieces.size()-1].size();
    } else if (current_width == 0) {
      // add the first word to the line (no space!)
      line.push_back(all_strings[current_word]);
      current_width += all_strings[current_word].size();
    } else if (current_width + 1 + int(all_strings[current_word].size()) <= text_width) {
      // add a word, not the first, so we do need a space
      line.push_back(all_strings[current_word]);
      current_width += all_strings[current_word].size() + 1;
    } else {
      // otherwise, this word doesn't fit on the current line
      assert (line.size() > 0);
      print_line(line,out_str,text_width,justify_mode);
      line.clear();
      current_width = 0;
      continue; // don't increment the current_word
    }
    current_word++;
  }

  // print out the final line
  if (current_width != 0) {
    if (justify_mode == std::string("full_justify"))
      // flush left the last line of text that has been full justified
      print_line(line,out_str,text_width,std::string("flush_left"));
    else
      print_line(line,out_str,text_width,justify_mode);
  }
}


// handle the args & open the file streams
int main( int argc, char* argv[] ) {

  // check the number of arguments
  if ( argc != 5 ) {
    std::cerr << "Usage:\n  " << argv[0] << " infile outfile text_width justify_mode\n";
    return 1;
  }

  // open the two files
  std::ifstream in_str(argv[1]);
  if (!in_str.good()) {
    std::cerr << "Could not open " << argv[1] << " to read\n";
    return 1;
  }

  std::ofstream out_str(argv[2]);
  if (!out_str.good()) {
    std::cerr << "Could not open " << argv[2] << " to write\n";
    return 1;
  }

  // Check that the remaining arguments are valid
  int text_width = atoi(argv[3]);
  if (text_width < 2) {
    std::cerr << "ERROR: text_width < 2\n";
    return 1;
  }

  std::string justify_mode = argv[4];
  if (justify_mode != std::string("flush_left") &&
      justify_mode != std::string("flush_right") &&
      justify_mode != std::string("full_justify")) {
    std::cerr << "ERROR: unknown justify_mode: " << justify_mode << std::endl;
    return 1;
  }

  // print out the first line
  out_str << std::string(text_width+4,'-') << std::endl;
  // helper function to do all the middle lines
  process_words(in_str,out_str,text_width,justify_mode);
  // print out the last line
  out_str << std::string(text_width+4,'-') << std::endl;
  
  return 0;
}
