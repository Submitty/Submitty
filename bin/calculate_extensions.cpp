#include <fstream>
#include <iostream>
#include <map>
#include <string>
#include <vector>
#include <sstream>
#include <cassert>
#include <cstdlib>
#include <iomanip>


//  g++ calculate_extensions.cpp -lboost_system -lboost_filesystem -std=c++11 -Wall -g


//  ./a.out /var/local/submitty/courses/f16/csci1200/submissions/hw03/  2016 9 28  8   5 6 7  > foo.txt 2> hw03_late.txt


#include "boost/filesystem/operations.hpp"
#include "boost/filesystem/path.hpp"
#include "boost/timer/timer.hpp"

#include "boost/date_time/gregorian/gregorian.hpp"


// ------------------------------------------------------

void usage(const std::string& program) {

  std::cerr << "Usage " << program << " <path> <year> <month> <day> <cutoff> <testcase#> <testcase#> ... <testcase#>" << std::endl;
  exit(0);

}


// ------------------------------------------------------

int parse_results_grade(std::ifstream &istr, std::vector<int>& testcases, int cutoff) {
  int answer = 0;
  //  std::cout << "parse_results_grade " << std::endl;
  std::string line;
  while (std::getline(istr,line)) {
    std::stringstream ss(line);
    std::string token, token2;
    ss >> token;
    if (token == "Testcase") {
      int num;
      ss >> num;
      bool found = false;
      for (unsigned int i = 0; i < testcases.size(); i++) {
	//std::cout << " arg " << testcases[i] << std::endl;
	if (num == testcases[i]) {
	  found = true;
	}
      }
      if (!found) continue;
      //std::cout << "line: " << line << std::endl;
      ss >> token;
      assert (token == ":");
      ss >> token2;
      int value = 0;
      while (ss.good()) {
	if (token2 == "/") {
	  value = stoi(token);
	  break;
	}
	token = token2;
	ss >> token2;
      }
      //std::cout << "testcases " << num << " VALUE " << value << std::endl;
      answer += value;
    }
  }
  if (answer >= cutoff) {
    //std::cout << "QUALIFIES" << std::endl;
  } else {
    //std::cout << "not" << std::endl;
  }
  return answer;
}

// ------------------------------------------------------

int parse_timestamp(std::ifstream &istr, int y, int m, int d) {
  //std::cout << "parse_timestamp " << std::endl;
  std::string line;
  int year, month, day;
  while (std::getline(istr,line)) {
    //std::cout << "line: " << line << std::endl;
    char c;
    std::stringstream ss(line);
    ss >> year >> c;
    assert (c == '-');
    ss >> month >> c;
    assert (c == '-');
    ss >> day;
  }
  boost::gregorian::date cutoff(y,m,d);
  boost::gregorian::date timestamp(year,month,day);
  int diff = (cutoff-timestamp).days();
  //std::cout << "dates " << cutoff << " " << timestamp << " " << diff << std::endl;
  if (diff >= 0) {
    //std::cout << "AVAILABLE" << std::endl;
  } else {
    //std::cout << "later" << std::endl;
  }
  return diff;
}

// ------------------------------------------------------

int main(int argc, char* argv[]) {

  // SOME ARGUMENT CHECKS
  if (argc < 7) {
    usage(argv[0]);
  }
  std::vector<int> testcases;
  for (int i = 6; i < argc; i++) {
    testcases.push_back(atoi(argv[i]));
  }
  boost::filesystem::path submission_path = boost::filesystem::system_complete(argv[1]);
  if (!boost::filesystem::exists(submission_path) ||
      !boost::filesystem::is_directory( submission_path )) {
    std::cerr << "ERROR with directory " << submission_path << std::endl;
    usage(argv[0]); 
  }


  //boost::filesystem::path p2;
  boost::filesystem::path::iterator it = submission_path.end();
  --it;
  --it;
  std::string GRADEABLE = it->string();

  int year = atoi(argv[2]);
  int month = atoi(argv[3]);
  int day = atoi(argv[4]);
  if (year < 2014 || month < 1 || month > 12 || day < 1 || day > 31) {
    usage(argv[0]);
  }

  std::cout << "Calculating extension earned by this date: " << year << " " << month << " " << day <<std::endl;

  int cutoff = atoi(argv[5]);
  assert (cutoff > 0 && cutoff < 100);


  std::cout << "Cutoff is: " << cutoff << std::endl;

  std::cout << "Testcases: ";
  for (unsigned int i = 0; i < testcases.size(); i++) { std::cout << " " << testcases[i]; }
  std::cout << std::endl;


  std::map<std::string,std::map<int,std::pair<int,int> > > data;


  // LOOP OVER THE USERNAMES
  boost::filesystem::directory_iterator end_iter;
  for (boost::filesystem::directory_iterator dir_itr( submission_path ); dir_itr != end_iter; ++dir_itr) {

    boost::filesystem::path user_path = dir_itr->path();
    if (!is_directory(user_path)) {
      //std::cout << "NOT A DIRECTORY " << user_path.string() << std::endl;
      continue;
    }

    std::string username = dir_itr->path().filename().string();
    //std::cout << "username " << username << std::endl;

    // LOOP OVER THE SUBMISSION VERSIONS
    for (boost::filesystem::directory_iterator user_itr( user_path ); user_itr != end_iter; ++user_itr) {

      std::string version = user_itr->path().filename().string();
      //std::cout << "version " << version << std::endl;


      boost::filesystem::path version_path = user_itr->path();
      if (!is_directory(version_path)) {
	//std::cout << "NOT A DIRECTORY " << version_path.string() << std::endl;
	continue;
      }

      std::string timestamp_file = submission_path.string() + username + "/" + version + "/.submit.timestamp";
      int pos = submission_path.string().find("submissions");
      std::string results_path = submission_path.string();
      results_path = results_path.replace(pos,std::string("submissions").length(),"results");

      std::string results_file = results_path + username + "/" + version + "/grade.txt";
      //std::cout << "timestamp_file " << timestamp_file << std::endl;
      //std::cout << "results_file " << results_file << std::endl;

      std::ifstream res_istr(results_file);
      int points = 0;
      if (res_istr.good()) {
	//std::cout << "user " << username << " " << version << " " << version << std::endl;
	points = parse_results_grade(res_istr,testcases,cutoff);
	//std::cout << "points = " << points << std::endl;
      }

      std::ifstream time_istr(timestamp_file);
      int days_diff = 0;
      if (time_istr.good()) {
	days_diff = parse_timestamp(time_istr,year,month,day);
	//std::cout << "days_diff = " << days_diff << std::endl;
      } else {
	//std::cout << "timestamp file error!" << std::endl;
	exit(0);
      }

      int version_int = atoi(version.c_str());
      assert (version_int >= 1);
      data[username][version_int] = std::make_pair(days_diff,points);
      //std::cout << "thing "<< username << " " << days_diff << " " << points << std::endl;
    }
  }


  for (std::map<std::string,std::map<int,std::pair<int,int> > >::const_iterator itr = data.begin();
       itr != data.end(); itr++) {
    std::cout << "user: " << itr->first << std::endl;
    bool earned = false;
    bool attempt = false;
    for (std::map<int,std::pair<int,int> >::const_iterator itr2 = itr->second.begin();
	 itr2 != itr->second.end(); itr2++) {
      std::cout << "   version: " << itr2->first << ", days=" << itr2->second.first << " points=" << itr2->second.second << std::endl;
      if (itr2->second.first >= 0 && itr2->second.second >= cutoff) earned = true;
      if (itr2->second.first >= 0) attempt = true;
    }
    if (earned) { 
      std::cout << "LATE DAY EARNED FOR " << itr->first << std::endl;
      std::cerr << itr->first << "," << GRADEABLE << "," << 1 << std::endl;
    } else if (attempt) { std::cout << "ATTEMPT by " << itr->first << std::endl; }
    else { std::cout << "ONLY ON TIME " << itr->first << std::endl; }
  }

}





