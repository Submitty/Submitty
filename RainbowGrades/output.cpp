#include <iostream>
#include <cassert>
#include <fstream>
#include <sstream>
#include <string>
#include <vector>
#include <sstream>
#include <iomanip>
#include <map>
#include <algorithm>
#include <ctime>
#include <cmath>

#include "student.h"
#include "iclicker.h"
#include "grade.h"
#include "table.h"
#include "benchmark.h"

#include "json.hpp"

#define grey_divider "aaaaaa"

#include "constants_and_globals.h"

extern std::string OUTPUT_FILE;
extern std::string ALL_STUDENTS_OUTPUT_DIRECTORY;

extern Student* AVERAGE_STUDENT_POINTER;
extern Student* STDDEV_STUDENT_POINTER;

extern std::string GLOBAL_sort_order;

extern int GLOBAL_ACTIVE_TEST_ZONE;

// ==========================================================

std::string HEX(int h) {
  std::stringstream ss;
  ss << std::hex << std::setw(2) << std::setfill('0') << h;
  return ss.str();
}

int UNHEX(std::string s) {
  assert (s.size() == 2);
  int h;
  std::stringstream ss(s);
  ss >> std::hex >> h;
  assert (h >= 0 && h <= 255);
  return h;
}


// colors for grades
const std::string GradeColor(const std::string &grade) {
  if      (grade == "A" ) return HEX(200)+HEX(200)+HEX(255); 
  else if (grade == "A-") return HEX(200)+HEX(235)+HEX(255); 
  else if (grade == "B+") return HEX(219)+HEX(255)+HEX(200); 
  else if (grade == "B" ) return HEX(237)+HEX(255)+HEX(200); 
  else if (grade == "B-") return HEX(255)+HEX(255)+HEX(200); 
  else if (grade == "C+") return HEX(255)+HEX(237)+HEX(200); 
  else if (grade == "C" ) return HEX(255)+HEX(219)+HEX(200); 
  else if (grade == "C-") return HEX(255)+HEX(200)+HEX(200); 
  else if (grade == "D+") return HEX(255)+HEX(100)+HEX(100); 
  else if (grade == "D" ) return HEX(255)+HEX(  0)+HEX(  0); 
  else if (grade == "F" ) return HEX(200)+HEX(  0)+HEX(  0); 
  else return "ffffff";
}

// ==========================================================

float compute_average(const std::vector<float> &vals) {
  assert (vals.size() > 0);
  float total = 0;
  for (std::size_t i = 0; i < vals.size(); i++) {
    total += vals[i];
  }
  return total / float (vals.size());
}


float compute_stddev(const std::vector<float> &vals, float average) {
  assert (vals.size() > 0);
  float total = 0;
  for (std::size_t i = 0; i < vals.size(); i++) {
    total += (vals[i]-average)*(vals[i]-average);
  }
  return sqrt(total / float (vals.size()) );
}

// ==========================================================

int convertYear(const std::string &major) {
  if (major == "FR") return 1;
  if (major == "SO") return 2;
  if (major == "JR") return 3;
  if (major == "SR") return 4;
  if (major == "FY") return 5;
  if (major == "GR") return 6;
  else return 10;
}

int convertMajor(const std::string &major) {
  if (major == "CSCI") return 20;
  if (major == "ITWS" || major == "ITEC") return 19;
  if (major == "CSYS") return 18;
  if (major == "GSAS") return 17;
  if (major == "MATH") return 16;
  if (major == "COGS" || major == "PSYC") return 15;
  if (major == "ELEC") return 14;
  if (major == "PHYS" || major == "APHY") return 13;
  if (major == "BMGT" || 
      major == "ISCI" || 
      major == "ENGR" || 
      major == "USCI" ||
      major == "DSIS" ||
      major == "ECON" ||
      major == "EART" ||
      major == "CHEG" || 
      major == "MECL" ||
      major == "MGTE" ||
      major == "UNGS" ||
      major == "BMED" ||
      major == "MECL" ||
      major == "BFMB" ||
      major == "ARCH" ||
      major == "FERA" ||
      major == "CHEM" ||
      major == "MGMT" ||
      major == "NUCL" ||
      major == "MATL" ||
      major == "") return 0;
  else return 10;
}

// ==========================================================

class Color {
public:
  Color(int r_=0, int g_=0, int b_=0) : r(r_),g(g_),b(b_) {}
  Color(const std::string& s) {
    r = UNHEX(s.substr(0,2));
    g = UNHEX(s.substr(2,2));
    b = UNHEX(s.substr(4,2));
  }
  int r,g,b;
};

std::string coloritcolor(float val,
                         float perfect,
                         float a,
                         float b,
                         float c,
                         float d) {

  //check for nan
  if (val != val) return "ffffff";
  if (std::isinf(val)) return "00ff00";

  //std::cout << "coloritcolor " << val << " " << perfect << " " << a << " " << b << " " << c << " " << d << std::endl;
  assert (perfect >= a &&
          a >= b &&
          b >= c &&
          c >= d &&
          d >= 0);

  if (val < 0.00001) return "ffffff";
  else if (val > perfect) return GetBenchmarkColor("extracredit");
  else {
    float alpha;
    Color c1,c2;

    static Color perfect_color(GetBenchmarkColor("perfect"));
    static Color a_color(GetBenchmarkColor("lowest_a-"));
    static Color b_color(GetBenchmarkColor("lowest_b-"));
    static Color c_color(GetBenchmarkColor("lowest_c-"));
    static Color d_color(GetBenchmarkColor("lowest_d"));

    if (val >= a) {
      if (fabs(perfect-a) < 0.0001) alpha = 0;
      else alpha = (perfect-val)/float(perfect-a);
      c1 = perfect_color;
      c2 = a_color;
    }
    else if (val >= b) {
      if (fabs(a-b) < 0.0001) alpha = 0;
      else alpha = (a-val)/float(a-b);
      c1 = a_color;
      c2 = b_color;
    }
    else if (val >= c) {
      if (fabs(b-c) < 0.0001) alpha = 0;
      else alpha = (b-val)/float(b-c);
      c1 = b_color;
      c2 = c_color;
    }
    else if (val >= d) {
      if (fabs(c-d) < 0.0001) alpha = 0;
      else alpha = (c-val)/float(c-d);
      c1 = c_color;
      c2 = d_color;
    }
    else {
      return GetBenchmarkColor("failing");
    }

    float red   = (1-alpha) * c1.r + (alpha) * c2.r;
    float green = (1-alpha) * c1.g + (alpha) * c2.g;
    float blue  = (1-alpha) * c1.b + (alpha) * c2.b;

    return HEX(red) + HEX(green) + HEX(blue);

  }
}

void coloritcolor(std::ostream &ostr,
                  float val,
                  float perfect,
                  float a,
                  float b,
                  float c,
                  float d) {
  ostr << coloritcolor(val,perfect,a,b,c,d);
}

void colorit_year(std::ostream &ostr, const std::string& s) {
  if (s == "FR") {
    ostr << "<td align=center bgcolor=ffffff>" << s << "</td>";
  } else if (s == "SO") {
    ostr << "<td align=center bgcolor=dddddd>" << s << "</td>";
  } else if (s == "JR") {
    ostr << "<td align=center bgcolor=bbbbbb>" << s << "</td>";
  } else if (s == "SR") {
    ostr << "<td align=center bgcolor=999999>" << s << "</td>";
  } else if (s == "GR") {
    ostr << "<td align=center bgcolor=777777>" << s << "</td>";
  } else if (s == "FY") {
    ostr << "<td align=center bgcolor=555555>" << s << "</td>";
  } else if (s == "") {
    ostr << "<td align=center bgcolor=ffffff>&nbsp;</td>";
  } else {
    std::cout << "EXIT " << s << std::endl;
    exit(0);
  }
}



void colorit_major(std::ostream &ostr, const std::string& s) {
  int m = convertMajor(s);
  ostr << "<td align=center bgcolor=";
  coloritcolor(ostr,m,19.5,17,15,11,10);
  ostr << ">" << s << "</td>";
}
   


void colorit_section(std::ostream &ostr,
                     int section, bool for_instructor, const std::string &color) {

  std::string section_name;

  if (validSection(section)) 
    section_name = sectionNames[section];
  std::string section_color = sectionColors[section_name];

  if (section == 0) {
    section_color=color;
  }

  if (for_instructor) {
    if (section != 0) {
      ostr << "<td align=center bgcolor=" << section_color << ">" << section << "&nbsp;(" << section_name << ")</td>";
    } else {
      ostr << "<td align=center bgcolor=" << section_color << ">&nbsp;</td>" << std::endl;
    }
  } else {
    if (section != 0) {
      ostr << "<td align=center>" << section << "</td>";
    } else {
      ostr << "<td align=center bgcolor=" << section_color << ">&nbsp;</td>" << std::endl;
    }
  }

}

void colorit_section2(int section, std::string &color, std::string &label) {
  std::string section_name;
  if (validSection(section)) {
    section_name = sectionNames[section];
    color = sectionColors[section_name];
    std::stringstream ss;
    ss << section << "&nbsp;(" << sectionNames[section] << ")";
    label = ss.str();
  }
}



void colorit(std::ostream &ostr,
             float val,
             float perfect,
             float a,
             float b,
             float c,
             float d,
             int precision=1,
             bool centered=false,
             std::string bonus_text="") {
  if (centered)
    ostr << "<td align=center bgcolor=";
  else
    ostr << "<td align=right bgcolor=";
  coloritcolor(ostr,val,perfect,a,b,c,d);
  ostr << ">";
  if (val < 0.0000001) {
    ostr << "&nbsp;";
  } else if (precision == 1) {  
    ostr << std::dec << val << "&nbsp;" << bonus_text;
  } else {
    assert (precision == 0);
    ostr << std::dec << (int)val;
  }
  ostr << "</td>"; 
}

// ==========================================================

void PrintExamRoomAndZoneTable(std::ofstream &ostr, Student *s, const nlohmann::json &special_message) {

  if (special_message.size() > 0) {
    
    ostr << "<table border=1 cellpadding=5 cellspacing=0 style=\"background-color:#ddffdd; width:auto;\">\n";
    ostr << "<tr><td>\n";
    ostr << "<table border=0 cellpadding=5 cellspacing=0>\n";

    assert (special_message.find("title") != special_message.end());
    std::string title = special_message.value("title","MISSING TITLE");

    assert (special_message.find("description") != special_message.end());
    std::string description = special_message.value("description","provided_files.zip");

    ostr << "<h3>" << title << "</h3>" << std::endl;

    assert (special_message.find("files") != special_message.end());
    nlohmann::json files = *(special_message.find("files"));
    int num_files = files.size();
    assert (num_files >= 1);

    std::string username = s->getUserName();
    int A = 54059; /* a prime */
    int B = 76963; /* another prime */
    int FIRSTH = 37; /* also prime */

    unsigned int tmp = FIRSTH;
    for (std::size_t i = 0; i < username.size(); i++) {
      tmp = (tmp * A) ^ (username[i] * B);
      s++;
    }

    int which = (tmp % num_files)+1;
    std::string filename = files.value(std::to_string(which),"");
    assert (filename != "");

    ostr << "  <tr><td><a href=\"" << filename << "\" download=\"provided_files.zip\">" << description << "</a></td></tr>\n";
    ostr << "</table>\n";
    ostr << "</tr></td>\n";
    ostr << "</table>\n";
  }

  // ==============================================================


  if ( DISPLAY_EXAM_SEATING == false) return;

  std::string room = GLOBAL_EXAM_DEFAULT_ROOM;
  std::string zone = "SEE INSTRUCTOR";
  std::string time = GLOBAL_EXAM_TIME;
  std::string row = "";
  std::string seat = "";
  if (s->getSection() == 0) {
    //room = "";
    //zone = "";
    time = "";
  }
  if (s->getExamRoom() == "") {
    //std::cout << "NO ROOM FOR " << s->getUserName() << std::endl;
  } else {
    room = s->getExamRoom();
    zone = s->getExamZone();
    row = s->getExamRow();
    seat = s->getExamSeat();
    if (s->getExamTime() != "") {
      time = s->getExamTime();
    }
  }
  if (zone == "SEE_INSTRUCTOR") {
    zone = "SEE INSTRUCTOR";
  }


#if 1

  ostr << "<table style=\"border:1px solid yellowgreen; background-color:#ddffdd; width:auto;\" >\n";
  //  ostr << "<table border=\"1\" cellpadding=5 cellspacing=0 style=\"border:1px solid yellowgreen; background-color:#ddffdd;\">\n";
  ostr << "<tr><td>\n";
  ostr << "<table border=0 cellpadding=5 cellspacing=0>\n";
  ostr << "  <tr><td colspan=2>" << GLOBAL_EXAM_TITLE << "</td></tr>\n";
  ostr << "  <tr><td>" << GLOBAL_EXAM_DATE << "</td><td align=center>" << time << "</td></tr>\n";
  ostr << "  <tr><td>Your room assignment: </td><td align=center>" << room << "</td></tr>\n";
  ostr << "  <tr><td>Your zone assignment: </td><td align=center>" << zone << "</td></tr>\n";
  ostr << "  <tr><td>Your row assignment: </td><td align=center>" << row << "</td></tr>\n";
  ostr << "  <tr><td>Your seat assignment: </td><td align=center>" << seat << "</td></tr>\n";
  ostr << "</table>\n";
  ostr << "</tr></td>\n";

  if (s->getExamZoneImage() != "") {
    ostr << "<tr><td style=\"background-color:#ffffff;\"><img src=\"zone_images/" + s->getExamZoneImage() + "\"></td></tr>\n";
  }


  ostr << "</table>\n";

#else


  ostr << "<table border=1 cellpadding=5 cellspacing=0 style=\"background-color:#ddffdd; width:auto;\">\n";
  ostr << "<tr><td>\n";
  ostr << "<table border=0 cellpadding=5 cellspacing=0>\n";
  ostr << "  <tr><td colspan=2>" << GLOBAL_EXAM_TITLE << "</td></tr>\n";
  //  ostr << "  <tr><td>" << GLOBAL_EXAM_DATE << "</td><td align=center>" << time << "</td></tr>\n";
  //ostr << "  <tr><td>Your room assignment: </td><td align=center>" << room << "</td></tr>\n";

  


  if (zone == "SEE INSTRUCTOR") {
    zone = "10";
  }

  std::string foo = "http://www.cs.rpi.edu/academics/courses/fall15/csci1200/hw/10_pokemon/";

  ostr << "  <tr><td>Your list assignment:                </td><td align=left><a target=_top href=\"" << foo << "List"                         << zone << ".txt\">List"                         << zone << ".txt</a></td></tr>\n";
  ostr << "  <tr><td>Small Input:                         </td><td align=left><a target=_top href=\"" << foo << "PokedexSmall"                 << zone << ".txt\">PokedexSmall"                 << zone << ".txt</a></td></tr>\n";
  ostr << "  <tr><td>Small Input Obfuscate:               </td><td align=left><a target=_top href=\"" << foo << "PokedexSmallObfuscate"        << zone << ".txt\">PokedexSmallObfuscate"        << zone << ".txt</a></td></tr>\n";
  ostr << "  <tr><td>Small Output Obfuscate:              </td><td align=left><a target=_top href=\"" << foo << "OutputSmallObfuscate"         << zone << ".txt\">OutputSmallObfuscate"         << zone << ".txt</a></td></tr>\n";
  ostr << "  <tr><td>Small Output Obfuscate w/ Breeding:  </td><td align=left><a target=_top href=\"" << foo << "OutputSmallObfuscateBreeding" << zone << ".txt\">OutputSmallObfuscateBreeding" << zone << ".txt</a></td></tr>\n";

  ostr << "</table>\n";
  ostr << "</tr></td>\n";
  ostr << "</table>\n";



#endif


  std::string x1 = s->getExamZone();
  std::string x2 = s->getZone(GLOBAL_ACTIVE_TEST_ZONE);

  if (x2.size() > 0) {
    assert (x1.size() > 0);
    if (x1.size() > 1 || std::toupper(x1[0]) != std::toupper(x2[0]) || x2.find('?') != std::string::npos || x2.size() == 1) {
      std::cout << "WRONG ZONE" << s->getUserName() << " " << x1 << " " <<x2  << std::endl;
    }
  }
}


// ====================================================================================================
// ====================================================================================================
// ====================================================================================================

void end_table(std::ofstream &ostr,  bool for_instructor, Student *s);

void start_table_open_file(bool for_instructor,
                 const std::vector<Student*> &students, int rank, int month, int day, int year,
                 enum GRADEABLE_ENUM which_gradeable_enum) {

  /*
  ostr.exceptions ( std::ofstream::failbit | std::ofstream::badbit );
  try {
    ostr.open(filename.c_str());
  }
  catch (std::ofstream::failure e) {
    std::cout << "FAILED TO OPEN " << filename << std::endl;
    std::cerr << "Exception opening/reading file";
    exit(0);
  }
  */
}


void SelectBenchmarks(std::vector<int> &select_students, const std::vector<Student*> &students,
                      Student *sp, Student *sa, Student *sb, Student *sc, Student *sd) {
  int myrow = 1;

  int offset = select_students.size();
  select_students.resize(select_students.size()+NumVisibleBenchmarks());

  for (unsigned int stu= 0; stu < students.size(); stu++) {
    std::string default_color="ffffff";
    Student *this_student = students[stu];
    myrow++;

    int which;

    if (this_student->getLastName() == "") {
      if (this_student == sp) {
        which = WhichVisibleBenchmark("perfect");
        if (which >= 0) select_students[offset+which]=myrow;
      } else if (this_student == AVERAGE_STUDENT_POINTER) {
        which = WhichVisibleBenchmark("average");
        if (which >= 0) select_students[offset+which]=myrow;
      } else if (this_student == STDDEV_STUDENT_POINTER) {
        which = WhichVisibleBenchmark("stddev");
        if (which >= 0) select_students[offset+which]=myrow;
      } else if (this_student == sa) {
        which = WhichVisibleBenchmark("lowest_a-");
        if (which >= 0) select_students[offset+which]=myrow;
      } else if (this_student == sb) {
        which = WhichVisibleBenchmark("lowest_b-");
        if (which >= 0) select_students[offset+which]=myrow;
      } else if (this_student == sc) {
        which = WhichVisibleBenchmark("lowest_c-");
        if (which >= 0) select_students[offset+which]=myrow;
      } else if (this_student == sd) {
        which = WhichVisibleBenchmark("lowest_d");
        if (which >= 0) select_students[offset+which]=myrow;
      }
    }
  }
}


void start_table_output( bool for_instructor,
                         const std::vector<Student*> &students, int rank, int month, int day, int year,
                         Student *sp, Student *sa, Student *sb, Student *sc, Student *sd) {

  std::vector<int> all_students;
  std::vector<int> select_students;
  std::vector<int> instructor_data;
  std::vector<int> student_data;

  Table table;


  // =====================================================================================================
  // =====================================================================================================
  // DEFINE HEADER ROW
  int counter = 0;
  table.set(0,counter++,TableCell("ffffff","#"));
  table.set(0,counter++,TableCell("ffffff","SECTION"));
  //table.set(0,counter++,TableCell("ffffff","part."));
  //table.set(0,counter++,TableCell("ffffff","under."));
  if (DISPLAY_INSTRUCTOR_NOTES) {
    table.set(0,counter++,TableCell("ffffff","notes"));
  }
  student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","USERNAME"));
  int last_name_counter=counter; table.set(0,counter++,TableCell("ffffff","LAST"));

  if (DISPLAY_INSTRUCTOR_NOTES) {
    table.set(0,counter++,TableCell("ffffff","FIRST (LEGAL)"));
  }
  student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","FIRST"));
  student_data.push_back(last_name_counter);
  student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));

  if (DISPLAY_EXAM_SEATING) {
    student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","exam room"));
    student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","exam zone"));
    student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","exam row"));
    student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","exam seat"));
    student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","exam time"));
    student_data.push_back(counter); table.set(0,counter++,TableCell(grey_divider));
  }

  student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","OVERALL"));
  student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));

  if (DISPLAY_FINAL_GRADE) {
    std::cout << "DISPLAY FINAL GRADE" << std::endl;
    student_data.push_back(counter); table.set(0,counter++,TableCell("ffffff","FINAL GRADE"));
    student_data.push_back(counter); table.set(0,counter++,TableCell(grey_divider));
  } 

  // ----------------------------
  // % OF OVERALL AVERAGE FOR EACH GRADEABLE
  for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
    enum GRADEABLE_ENUM g = ALL_GRADEABLES[i];
    if (g == GRADEABLE_ENUM::NOTE) {
      assert (GRADEABLES[g].getPercent() < 0.01);
      continue;
    }
    student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff",gradeable_to_string(g)+" %"));
  }
  student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));

  // ----------------------------
  // DETAILS OF EACH GRADEABLE
  if (DISPLAY_GRADE_DETAILS) {
    for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
      GRADEABLE_ENUM g = ALL_GRADEABLES[i];
      for (int j = 0; j < GRADEABLES[g].getCount(); j++) {
        if (g != GRADEABLE_ENUM::NOTE) {
          student_data.push_back(counter);
        }
        std::string gradeable_id = GRADEABLES[g].getID(j);
        std::string gradeable_name = "";
        if (GRADEABLES[g].hasCorrespondence(gradeable_id)) {
          gradeable_name = GRADEABLES[g].getCorrespondence(gradeable_id).second;
        }
        table.set(0,counter++,TableCell("ffffff",gradeable_name));
      }
      if (g != GRADEABLE_ENUM::NOTE) {
        student_data.push_back(counter);
      }
      table.set(0,counter++,TableCell(grey_divider));

      if (g == GRADEABLE_ENUM::TEST && TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT) {
        for (int j = 0; j < GRADEABLES[g].getCount(); j++) {
          student_data.push_back(counter);
          std::string gradeable_id = GRADEABLES[g].getID(j);
          std::string gradeable_name = "";
          if (GRADEABLES[g].hasCorrespondence(gradeable_id)) {
            gradeable_name = "Adjusted " + GRADEABLES[g].getCorrespondence(gradeable_id).second;
          }
          table.set(0,counter++,TableCell("ffffff",gradeable_name));
        }
        student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));
      }
    }

    if (DISPLAY_LATE_DAYS) {
      student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","ALLOWED LATE DAYS"));
      student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","USED LATE DAYS"));
      student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));
    }
  }




  // ----------------------------
  // ICLICKER
  if (DISPLAY_ICLICKER && ICLICKER_QUESTION_NAMES.size() > 0) {

    student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","iclicker status"));
    student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));
    student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","ICLICKER TOTAL"));
    //student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff","ICLICKER RECENT"));
    student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));
    
    /*
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>" 
           << "<td align=center colspan=" << ICLICKER_QUESTION_NAMES.size() << ">ICLICKER QUESTIONS<br>CORRECT(green)=1.0, INCORRECT(red)=0.5, POLL(yellow)=1.0, NO ANSWER(white)=0.0<br>30.0 iClicker points = 3rd late day, 60.0 iClicker pts = 4th late day, 90.0 iClicker pts = 5th late day<br>&ge;8.0/12.0 most recent=Priority Help Queue (iClicker status highlighted in blue)</td>";
    */

    for (unsigned int i = 0; i < ICLICKER_QUESTION_NAMES.size(); i++) {
      student_data.push_back(counter);  table.set(0,counter++,TableCell("ffffff",ICLICKER_QUESTION_NAMES[i]));
    }
    student_data.push_back(counter);  table.set(0,counter++,TableCell(grey_divider));

  }

  // =====================================================================================================
  // =====================================================================================================
  // HORIZONTAL GRAY DIVIDER
  for (int i = 0; i < table.numCols(); i++) {
    table.set(1,i,TableCell(grey_divider));
  }


  // header row
  select_students.push_back(0);
  select_students.push_back(1);
  select_students.push_back(-1);  // replace this with the real student!
  select_students.push_back(1);


  std::map<int,std::string> student_correspondences;

  // =====================================================================================================
  // =====================================================================================================
  // ALL OF THE STUDENTS

  SelectBenchmarks(select_students,students,sp,sa,sb,sc,sd);


  int myrank = 1;
  int myrow = 1;
  int last_section = -1;
  for (unsigned int stu= 0; stu < students.size(); stu++) {

    Student *this_student = students[stu];

    std::string default_color="ffffff";

    myrow++;
    counter = 0;
    if (this_student->getLastName() == "") {
      if (this_student == sp) {
        default_color= coloritcolor(5,5,4,3,2,1);
      } else if (this_student == sa) {
        default_color= coloritcolor(4,5,4,3,2,1);
      } else if (this_student == sb) {
        default_color= coloritcolor(3,5,4,3,2,1);
      } else if (this_student == sc) {
        default_color= coloritcolor(2,5,4,3,2,1);
      } else if (this_student == sd) {
        default_color= coloritcolor(1,5,4,3,2,1);
      } 
      assert (default_color.size()==6);
      table.set(myrow,counter++,TableCell(default_color,""));
    } else {
      //std::cout << " WHO? " << this_student->getUserName() << std::endl;
      student_correspondences[myrow] = this_student->getUserName();
      if (GLOBAL_sort_order == "by_section" && this_student->getSection() != last_section) {
        myrank=1;
        last_section = this_student->getSection();
      }
      if (validSection(this_student->getSection())) {
        assert (default_color.size()==6);
        table.set(myrow,counter++,TableCell(default_color,std::to_string(myrank)));
        myrank++;
      } else {
        assert (default_color.size()==6);
        table.set(myrow,counter++,TableCell(default_color,""));
      }
    }

    
    std::string section_color = default_color;
    std::string section_label = "";
    colorit_section2(this_student->getSection(),section_color,section_label);
    assert (section_color.size()==6);
    table.set(myrow,counter++,TableCell(section_color,section_label));

    //table.set(myrow,counter++,TableCell(default_color,"part"));
    //table.set(myrow,counter++,TableCell(default_color,"under"));
    if (DISPLAY_INSTRUCTOR_NOTES) {
      std::string notes;
      std::vector<std::string> ews = this_student->getEarlyWarnings();
      for (std::size_t i = 0; i < ews.size(); i++) {
        notes += ews[i];
      }
      std::string other_note = this_student->getOtherNote();
      std::string recommendation = this_student->getRecommendation();
      std::string THING =
        "<font color=\"ff0000\">"+notes+"</font> " +
        "<font color=\"0000ff\">"+other_note+"</font> " +
        "<font color=\"00bb00\">"+recommendation+"</font>";
      assert (default_color.size()==6);
      table.set(myrow,counter++,TableCell(default_color,THING));
    }

    //counter+=3;
    assert (default_color.size()==6);
    table.set(myrow,counter++,TableCell(default_color,this_student->getUserName()));
    table.set(myrow,counter++,TableCell(default_color,this_student->getLastName()));
    if (DISPLAY_INSTRUCTOR_NOTES) {
      table.set(myrow,counter++,TableCell(default_color,this_student->getFirstName()));
    }
    table.set(myrow,counter++,TableCell(default_color,this_student->getPreferredName()));
    table.set(myrow,counter++,TableCell(grey_divider));


    if (DISPLAY_EXAM_SEATING) {

      std::string room = GLOBAL_EXAM_DEFAULT_ROOM;
      std::string zone = "SEE INSTRUCTOR";
      std::string row = "";
      std::string seat = "";
      std::string time = GLOBAL_EXAM_TIME;

      if (this_student->getSection() == 0) { //LastName() == "") {
        room = "";
        zone = "";
        time = "";
      }
      if (this_student->getExamRoom() == "") {
        //std::cout << "NO ROOM FOR " << this_student->getUserName() << std::endl;
      } else {
        room = this_student->getExamRoom();
        zone = this_student->getExamZone();
        row = this_student->getExamRow();
        seat = this_student->getExamSeat();
        if (this_student->getExamTime() != "") {
          time = this_student->getExamTime();
        }
      }
      if (zone == "SEE_INSTRUCTOR") {
        zone = "SEE INSTRUCTOR";
      }

      table.set(myrow,counter++,TableCell("ffffff",room));
      table.set(myrow,counter++,TableCell("ffffff",zone));
      table.set(myrow,counter++,TableCell("ffffff",row));
      table.set(myrow,counter++,TableCell("ffffff",seat));
      table.set(myrow,counter++,TableCell("ffffff",time));
      table.set(myrow,counter++,TableCell(grey_divider));
    }


    float grade;
    if (this_student->getUserName() == "AVERAGE" ||
        this_student->getUserName() == "STDDEV") {
      // Special case for overall average and standard deviation.
      // Mathematically, we can't simply add the std dev for the
      // different gradeables.  Note also: the average isn't a simple
      // addition either, since blank scores for a specific gradeable
      // are omitted from the average.
      std::vector<float> vals;
      for (unsigned int S = 0; S < students.size(); S++) {
        if (validSection(students[S]->getSection())) {
          vals.push_back(students[S]->overall());
        }
      }
      float tmp_average = compute_average(vals);
      if (this_student->getUserName() == "AVERAGE") {
        grade = tmp_average;
      } else {
        float tmp_std_dev = compute_stddev(vals,tmp_average);
        grade = tmp_std_dev;
      }
    } else {
      grade = this_student->overall();
    }

    std::string color = coloritcolor(grade,
                                     sp->overall(),
                                     sa->overall(),
                                     sb->overall(),
                                     sc->overall(),
                                     sd->overall());
    if (this_student == STDDEV_STUDENT_POINTER) color="ffffff";
    assert (color.size()==6);
    table.set(myrow,counter++,TableCell(color,grade,2));
    table.set(myrow,counter++,TableCell(grey_divider));


    if (DISPLAY_FINAL_GRADE) {
      std::string g = this_student->grade(false,sd);
      color = GradeColor(g);
      assert (color.size()==6);
      table.set(myrow,counter++,TableCell(color,g,"",0,CELL_CONTENTS_VISIBLE,"center"));
      table.set(myrow,counter++,TableCell(grey_divider));
    }

    // ----------------------------
    // % OF OVERALL AVERAGE FOR EACH GRADEABLE
    for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
      enum GRADEABLE_ENUM g = ALL_GRADEABLES[i];
      if (g == GRADEABLE_ENUM::NOTE) {
        assert (GRADEABLES[g].getPercent() < 0.01);
        continue;
      }

      float grade;
      if (this_student->getUserName() == "AVERAGE" ||
          this_student->getUserName() == "STDDEV") {
        // Special case for per gradeable average and standard deviation.
        // Mathematically, we can't simply add the std dev for the
        // different gradeables.  Note also: the average isn't a simple
        // addition either, since blank scores for a specific gradeable
        // are omitted from the average.
        std::vector<float> vals;
        for (unsigned int S = 0; S < students.size(); S++) {
          if (validSection(students[S]->getSection())) {
            vals.push_back(students[S]->GradeablePercent(g));
          }
        }
        float tmp_average = compute_average(vals);
        if (this_student->getUserName() == "AVERAGE") {
          grade = tmp_average;
        } else {
          float tmp_std_dev = compute_stddev(vals,tmp_average);
          grade = tmp_std_dev;
        }
      } else {
        grade = this_student->GradeablePercent(g);
      }
      std::string color = coloritcolor(grade,
                                       sp->GradeablePercent(g),
                                       sa->GradeablePercent(g),
                                       sb->GradeablePercent(g),
                                       sc->GradeablePercent(g),
                                       sd->GradeablePercent(g));
      if (this_student == STDDEV_STUDENT_POINTER) color="ffffff";
      assert (color.size()==6);
      table.set(myrow,counter++,TableCell(color,grade,2));
    }
    table.set(myrow,counter++,TableCell(grey_divider));
    
    // ----------------------------
    // DETAILS OF EACH GRADEABLE    
    if (DISPLAY_GRADE_DETAILS) {
      for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
        GRADEABLE_ENUM g = ALL_GRADEABLES[i];
        enum CELL_CONTENTS_STATUS visible = CELL_CONTENTS_VISIBLE;
        if (g == GRADEABLE_ENUM::TEST) {
          visible = CELL_CONTENTS_NO_DETAILS;
        }
        for (int j = 0; j < GRADEABLES[g].getCount(); j++) {
          float grade = this_student->getGradeableItemGrade(g,j).getValue();
          std::string color = coloritcolor(grade,
                                           sp->getGradeableItemGrade(g,j).getValue(),
                                           sa->getGradeableItemGrade(g,j).getValue(),
                                           sb->getGradeableItemGrade(g,j).getValue(),
                                           sc->getGradeableItemGrade(g,j).getValue(),
                                           sd->getGradeableItemGrade(g,j).getValue());
          if (this_student == STDDEV_STUDENT_POINTER) color="ffffff";
          std::string details;
          details = this_student->getGradeableItemGrade(g,j).getNote();
          std::string status = this_student->getGradeableItemGrade(g,j).getStatus();

          if (status.find("Bad") != std::string::npos) {
            details += " " + status;
          }
          int late_days_used = this_student->getGradeableItemGrade(g,j).getLateDaysUsed();
          assert (color.size()==6);
          table.set(myrow,counter++,TableCell(color,grade,1,details,late_days_used,visible));
        }
        table.set(myrow,counter++,TableCell(grey_divider));

        // FIXME
        if (g == GRADEABLE_ENUM::TEST && TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT) {
          for (int j = 0; j < GRADEABLES[g].getCount(); j++) {
            float grade = this_student->adjusted_test(j);
            std::string color = coloritcolor(this_student->adjusted_test(j),
                                             sp->adjusted_test(j),
                                             sa->adjusted_test(j),
                                             sb->adjusted_test(j),
                                             sc->adjusted_test(j),
                                             sd->adjusted_test(j));
            if (this_student == STDDEV_STUDENT_POINTER) color="ffffff";
            assert (color.size()==6);
            table.set(myrow,counter++,TableCell(color,grade,1,"",0,visible));
          }
          table.set(myrow,counter++,TableCell(grey_divider));
        }
      }

      if (DISPLAY_LATE_DAYS) {
        // LATE DAYS
        if (this_student->getLastName() != "") {
          int allowed = this_student->getAllowedLateDays(100);
          std::string color = coloritcolor(allowed,5,4,3,2,2);
          table.set(myrow,counter++,TableCell(color,allowed,"",0,CELL_CONTENTS_VISIBLE,"right"));
          int used = this_student->getUsedLateDays();
          color = coloritcolor(allowed-used+2, 5+2, 3+2, 2+2, 1+2, 0+2);
          table.set(myrow,counter++,TableCell(color,used,"",0,CELL_CONTENTS_VISIBLE,"right"));
        } else {
          color="ffffff"; // default_color;
          table.set(myrow,counter++,TableCell(color,""));
          table.set(myrow,counter++,TableCell(color,""));
        }
        table.set(myrow,counter++,TableCell(grey_divider));
      }
    }



    // ----------------------------
    // ICLICKER
    if (DISPLAY_ICLICKER && ICLICKER_QUESTION_NAMES.size() > 0) {

      if (this_student->getRemoteID() != "") { // && this_student->hasPriorityHelpStatus()) {
        table.set(myrow,counter++,TableCell("ccccff","registered"));
        //} else if (this_student->getRemoteID() != "") {
        //table.set(myrow,counter++,TableCell("ffffff","registered"));
      } else if (this_student->getLastName() == "") {
        table.set(myrow,counter++,TableCell("ffffff"/*default_color*/,""));
      } else {
        table.set(myrow,counter++,TableCell("ffcccc","no iclicker registration"));
      }
      table.set(myrow,counter++,TableCell(grey_divider));

      if (this_student->getLastName() != "" ||
          this_student->getUserName() == "PERFECT") {
        float grade = this_student->getIClickerTotalFromStart();
        std::string color = coloritcolor(grade,
                                         MAX_ICLICKER_TOTAL,
                                         0.90*MAX_ICLICKER_TOTAL,
                                         0.80*MAX_ICLICKER_TOTAL,
                                         0.60*MAX_ICLICKER_TOTAL,
                                         0.40*MAX_ICLICKER_TOTAL);
        table.set(myrow,counter++,TableCell(color,grade,1));
        /*
        grade = this_student->getIClickerRecent();
        color = coloritcolor(grade,
                             ICLICKER_RECENT,
                             0.90*ICLICKER_RECENT,
                             0.80*ICLICKER_RECENT,
                             0.60*ICLICKER_RECENT,
                             0.40*ICLICKER_RECENT);
        table.set(myrow,counter++,TableCell(color,grade,1));
        */
      } else {
        color="ffffff"; // default_color;
        table.set(myrow,counter++,TableCell(color,""));
        //table.set(myrow,counter++,TableCell(color,""));
      }

      table.set(myrow,counter++,TableCell(grey_divider));
      for (unsigned int i = 0; i < ICLICKER_QUESTION_NAMES.size(); i++) {
        std::pair<std::string,float> answer = this_student->getIClickerAnswer(ICLICKER_QUESTION_NAMES[i]);
        std::string thing = answer.first;
        std::string color = "ffffff"; //default_color;
        if (answer.second == ICLICKER_CORRECT) {
          color = "aaffaa"; 
        } else if (answer.second == ICLICKER_PARTICIPATED) {
          color = "ffffaa"; 
        } else if (answer.second == ICLICKER_INCORRECT) {
          color = "ffaaaa"; 
        } else {
          assert (answer.second == ICLICKER_NOANSWER);
        }
        table.set(myrow,counter++,TableCell(color,thing,"",0,CELL_CONTENTS_VISIBLE_INSTRUCTOR,"center"));
      }
      table.set(myrow,counter++,TableCell(grey_divider));
    }
  }


  
  for (int i = 0; i < table.numCols(); i++) {
    instructor_data.push_back(i);
  }
  // need to add 2, for the perfect & average student.
  for (unsigned int i = 0; i < students.size()+2; i++) {
    all_students.push_back(i);
  }

  std::cout << "WRITE ALL.html" << std::endl;
  std::ofstream ostr2(OUTPUT_FILE);

  GLOBAL_instructor_output = true;
  table.output(ostr2, all_students,instructor_data);

  end_table(ostr2,true,NULL);
  ostr2.close();
  
  std::stringstream ss;
  ss << ALL_STUDENTS_OUTPUT_DIRECTORY << "output_" << month << "_" << day << "_" << year << ".html";
   
  std::string command = "cp -f output.html " + ss.str();
  std::cout << "RUN COMMAND " << command << std::endl;
  system(command.c_str());
  

  for (std::map<int,std::string>::iterator itr = student_correspondences.begin();
       itr != student_correspondences.end(); itr++) {

    select_students[2] = itr->first;
    std::string filename = "individual_summary_html/" + itr->second + "_summary.html";
    std::ofstream ostr3(filename.c_str());
    assert (ostr3.good());

    Student *s = GetStudent(students,itr->second);
    std::string last_update;
    if (s != NULL) {
      last_update = s->getLastUpdate();
    }
    GLOBAL_instructor_output = false;

    table.output(ostr3, select_students,student_data,true,true,last_update);

    end_table(ostr3,false,s);
  }

  Student* s = NULL;
  if (rank != -1) {
    s = students[rank];
    assert (s != NULL);
  }



  //ostr << "<br>&nbsp;<br>\n";


  // -------------------------------------------------------------------------------
  // BEGIN THE TABLE
  //ostr << "<table border=2 cellpadding=5 cellspacing=0>\n";

  // open the title row
  //ostr << "<tr>";

  /*
  // -------------------------------------------------------------------------------
  // RANK & SECTION
  if (for_instructor) {
    ostr << "<td align=center>#</td>";   
  }
  ostr << "<td align=center>SECTION</td>";   
  
  // -------------------------------------------------------------------------------
  // INSTRUCTOR NOTES
  
  if (for_instructor && DISPLAY_INSTRUCTOR_NOTES) {
    //ostr << "<td align=center>part.</td>" 
    //   << "<td align=center>under.</td>";
    ostr << "<td align=center>notes</td>";
  }
  
  
  // -------------------------------------------------------------------------------  
  // NAME
  ostr << "<td align=center>USERNAME</td>";
  ostr << "<td align=center>LAST</td>" 
       << "<td align=center>FIRST</td>";
  
  // -------------------------------------------------------------------------------  
  // EXAM SEATING
  if (DISPLAY_EXAM_SEATING) {
    ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
    ostr << "<td align=center>exam room</td>";
    ostr << "<td align=center>exam zone</td>";
    ostr << "<td align=center>exam time</td>";
  }

  // -------------------------------------------------------------------------------  
  // ICLICKER REMOTE
  if (DISPLAY_ICLICKER && ICLICKER_QUESTION_NAMES.size() > 0) {
    ostr << "<td align=center>iclicker status</td>";
  }

  // -------------------------------------------------------------------------------  
  // GRADE SUMMARY
  if (DISPLAY_GRADE_SUMMARY && (for_instructor || g == GRADEABLE_ENUM::NONE)) {
    
    if (DISPLAY_FINAL_GRADE) {
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
      ostr << "<td align=center>GRADE</td>";

      if (for_instructor && DISPLAY_MOSS_DETAILS) {
        ostr << "<td align=center>GRADE BEFORE MOSS</td>";
      }

    }
    ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
    if (for_instructor && DISPLAY_MOSS_DETAILS) {
      ostr << "<td align=center>OVERALL AFTER PENALTY</td>";
    }
    ostr << "<td align=center>OVERALL</td>";
    ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
    for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
      ostr << "<td align=center>" << gradeable_to_string(ALL_GRADEABLES[i]) << " %</td>";
    }
  }



  // -------------------------------------------------------------------------------  
  // GRADE DETAILS
  if (DISPLAY_GRADE_DETAILS) {
    for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {

      if (!for_instructor && g != ALL_GRADEABLES[i]) {
        continue;
      }
        
      GRADEABLE_ENUM g = ALL_GRADEABLES[i];
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>"          
           << "<td align=center colspan=" << GRADEABLES[g].getCount() << ">" <<  gradeable_to_string(g)<< "S";
      if (g == GRADEABLE_ENUM::HOMEWORK) {
        ostr << "<br>* = 1 late day used";
      }
      ostr << "</td>";
      if (g == GRADEABLE_ENUM::TEST) {
        if (TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT) {
          ostr << "<td align=center bgcolor=888888>&nbsp;</td>" 
               << "<td align=center colspan=" << GRADEABLES[g].getCount() << ">ADJUSTED TESTS</td>";
        }
      }
    }
  }
   
  if (DISPLAY_ICLICKER) {
    // ICLICKER DETAILS
    if (ICLICKER_QUESTION_NAMES.size() > 0) {
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
      ostr << "<td align=center>ICLICKER TOTAL</td>";
      ostr << "<td align=center>ICLICKER RECENT</td>";
      ostr << "<td align=center>ALLOWED LATE DAYS</td>";
        ostr << "<td align=center>USED LATE DAYS</td>";
    }
    if (ICLICKER_QUESTION_NAMES.size() > 0) {
        ostr << "<td align=center bgcolor=888888>&nbsp;</td>" 
             << "<td align=center colspan=" << ICLICKER_QUESTION_NAMES.size() << ">ICLICKER QUESTIONS<br>CORRECT(green)=1.0, INCORRECT(red)=0.5, POLL(yellow)=1.0, NO ANSWER(white)=0.0<br>25.0 iClicker points = 3rd late day, 50.0 iClicker pts = 4th late day, 75.0 iClicker pts = 5th late day<br>&ge;8.0/12.0 most recent=Priority Help Queue (iClicker status highlighted in blue)</td>";
    }
  }

  // -------------------------------------------------------------------------------  
  ostr << "</td></tr>\n";    
  */  
}




void end_table(std::ofstream &ostr,  bool for_instructor, Student *s) {


    ostr << "<p>* = 1 late day used</p>" << std::endl;

  if (GLOBAL_instructor_output == false &&
      DISPLAY_ICLICKER) {

    ostr << "<p><b>IClicker Legend:</b><br> &nbsp;&nbsp; CORRECT(green)=1.0 <br> &nbsp;&nbsp; INCORRECT(red)=0.5 <br>&nbsp;&nbsp; POLL(yellow)=1.0 <br> &nbsp;&nbsp; NO ANSWER(white)=0.0<br>" << std::endl;
    if (s != NULL) {
      ostr << "<b>Initial number of allowed late days: </b>" << s->getDefaultAllowedLateDays() <<  "<br>" << std::endl;
    }
    if(!GLOBAL_earned_late_days.empty()) {
      ostr << "<b>Extra late days earned after iclicker points:</b> ";
      for (std::size_t i = 0; i < GLOBAL_earned_late_days.size(); i++) {
        ostr << GLOBAL_earned_late_days[i];
        if (i < GLOBAL_earned_late_days.size() - 1) {
          ostr << ", ";
        }
      }
      ostr << "<br>" << std::endl;
    }
    ostr << "</p>" << std::endl;


    //ostr << GLOBAL_earned_late

    //25.0 iClicker points = 3rd late day, 50.0 iClicker pts = 4th late day, 75.0 iClicker pts = 5th late day<br>&ge;8.0/12.0 most recent=Priority Help Queue (iClicker status highlighted in blue)</td>";
  }

  ostr << "<p>&nbsp;<p>\n";



  bool print_moss_message = false;
  if (s != NULL && s->getMossPenalty() < -0.01) {
    print_moss_message = true;
  }

  if (print_moss_message) {
    ostr << "@ = final grade with Academic Integrity Violation penalty<p>&nbsp;<p>\n";
  }

  if (DISPLAY_FINAL_GRADE) { // && students.size() > 50) {

  int total_A = grade_counts[Grade("A")] + grade_counts[Grade("A-")];
  int total_B = grade_counts[Grade("B+")] + grade_counts[Grade("B")] + grade_counts[Grade("B-")]; 
  int total_C = grade_counts[Grade("C+")] + grade_counts[Grade("C")] + grade_counts[Grade("C-")];
  int total_D = grade_counts[Grade("D+")] + grade_counts[Grade("D")];
  int total_passed = total_A + total_B + total_C + total_D;
  int total_F = grade_counts[Grade("F")];
  int total_blank = grade_counts[Grade("")];
  assert (total_blank == 0);
  int total = total_passed + total_F + auditors + total_blank + dropped;

  ostr << "<p>\n";



  ostr << "<table style=\"border:1px solid yellowgreen; background-color:#ddffdd; width:auto;\">\n";
  //  ostr << "<table border=2 cellpadding=5 cellspacing=0>\n";
  ostr << "<tr>\n";
  ostr << "<td width=150>FINAL GRADE</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("A")<<" width=40>A</td><td align=center bgcolor="<<GradeColor("A-")<<" width=40>A-</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("B+")<<" width=40>B+</td><td align=center bgcolor="<<GradeColor("B")<<" width=40>B</td><td align=center bgcolor="<<GradeColor("B-")<<" width=40>B-</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("C+")<<" width=40>C+</td><td align=center bgcolor="<<GradeColor("C")<<" width=40>C</td><td align=center bgcolor="<<GradeColor("C-")<<" width=40>C-</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("D+")<<" width=40>D+</td><td align=center bgcolor="<<GradeColor("D")<<" width=40>D</td>\n";
  if (for_instructor) {
    ostr << "<td align=center bgcolor="<<GradeColor("F")<<"width=40>F</td>\n";
    //    ostr << "<td align=center width=40>dropped</td>\n";
    ostr << "<td align=center width=40>audit</td>\n";
    ostr << "<td align=center align=center width=40>took final</td>\n";
    ostr << "<td align=center align=center width=40>total passed</td>\n";
    ostr << "<td align=center align=center width=40>dropped</td>\n";
    ostr << "<td align=center align=center width=40>total</td>\n";
  }
  ostr << "</tr>\n";
  
  ostr << "<tr>\n";
  ostr << "<td width=150># of students</td>";
  ostr << "<td align=center width=40>"<<grade_counts[Grade("A")]<<"</td><td align=center width=40>"<<grade_counts[Grade("A-")]<<"</td>";
  ostr << "<td align=center width=40>"<<grade_counts[Grade("B+")]<<"</td><td align=center width=40>"<<grade_counts[Grade("B")]<<"</td><td align=center width=40>"<<grade_counts[Grade("B-")]<<"</td>";
  ostr << "<td align=center width=40>"<<grade_counts[Grade("C+")]<<"</td><td align=center width=40>"<<grade_counts[Grade("C")]<<"</td><td align=center width=40>"<<grade_counts[Grade("C-")]<<"</td>";
  ostr << "<td align=center width=40>"<<grade_counts[Grade("D+")]<<"</td><td align=center width=40>"<<grade_counts[Grade("D")]<<"</td>\n";
  
  if (for_instructor) {
    ostr << "<td align=center width=40>"<<grade_counts[Grade("F")]<<"</td>\n";
    //ostr << "<td align=center width=40>" << grade_counts[Grade("")]<<"</td>\n";
    ostr << "<td align=center width=40>"<<auditors<<"</td>\n";
    ostr << "<td align=center width=40>"<<took_final<<"</td>\n";
    ostr << "<td align=center width=40>"<<total_passed<<"</td>\n";
    ostr << "<td align=center width=40>"<<dropped<<"</td>\n";
    ostr << "<td align=center width=40>"<<total<<"</td>\n";
  }
  ostr << "</tr>\n";
  
  
  
  ostr << "<tr>\n";
  ostr << "<td width=150>average OVERALL<br>of students with<br>this FINAL GRADE</td>";
  ostr << "<td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("A")]<<"</td><td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("A-")]<<"</td>";
  ostr << "<td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("B+")]<<"</td><td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("B")]<<"</td><td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("B-")]<<"</td>";
  ostr << "<td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("C+")]<<"</td><td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("C")]<<"</td><td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("C-")]<<"</td>";

  if (for_instructor) {
    ostr << "<td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("D+")]<<"</td><td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("D")]<<"</td>\n";
  } else {
    ostr << "<td align=center width=40> &nbsp; </td><td align=center width=40> &nbsp; </td>\n";
  }

  if (for_instructor) {
    ostr << "<td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("F")]<<"</td>\n";
    //ostr << "<td align=center width=40>"<<std::setprecision(1)<<std::fixed<<grade_avg[Grade("")]<<"</td>\n";
    ostr << "<td align=center width=40>&nbsp;</td>\n";
    ostr << "<td align=center width=40>&nbsp;</td>\n";
    ostr << "<td align=center width=40>&nbsp;</td>\n";
    ostr << "<td align=center width=40>&nbsp;</td>\n";
    ostr << "<td align=center width=40>&nbsp;</td>\n";
  }
  ostr << "</tr>\n";
  
  
  
  ostr << "</table><p>\n";

  }

  ostr.close();
}
