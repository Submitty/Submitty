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
#include <sstream>
#include <cmath>

#include "student.h"
#include "iclicker.h"
#include "grade.h"


#include "constants_and_globals.h"


// ==========================================================

std::string HEX(int h) {
  std::stringstream ss;
  ss << std::hex << std::setw(2) << std::setfill('0') << h;
  return ss.str();
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

std::string coloritcolor(float val,
                         float perfect,
                         float a,
                         float b,
                         float c,
                         float d) {
  
  if (val < 0.00001) return "ffffff";
  else if (val > perfect) return "aa88ff";
  else {
  float red,green,blue;

  if (val >= a) { // blue -> green
    red = 200;
    green = 200 + 55*(perfect-val)/(float(perfect-a));
    blue = 255 - 55*(perfect-val)/(float(perfect-a));
  } 

  else if (val >= b) {  // green -> yellow
    red = 200 + 55*(a-val)/(float(a-b));
    green = 255;
    blue = 200;
  } 

  else if (val >= c) { // yellow -> pink
    red = 255; 
    green = 255 - 55*(b-val)/(float(b-c));
    blue = 200;
  } 

  else if (val >= d) {  // pink -> red;
    red = 255;
    green = 200 - 200*(c-val)/(float(c-d));
    blue = 200 - 200*(c-val)/(float(c-d));
  } 

  else { // dark red
    red = 200;
    green = 0;
    blue = 0;
  }

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

void PrintExamRoomAndZoneTable(std::ofstream &ostr, Student *s) {

  if ( DISPLAY_EXAM_SEATING == false) return;

  std::string room = GLOBAL_EXAM_DEFAULT_ROOM;
  std::string zone = "SEE INSTRUCTOR";
  std::string time = GLOBAL_EXAM_TIME;
  if (s->getExamRoom() == "") {
    //std::cout << "NO ROOM FOR " << s->getUserName() << std::endl;
  } else {
    room = s->getExamRoom();
    zone = s->getExamZone();
    if (s->getExamTime() != "") {
      time = s->getExamTime();
    }
  }
  if (zone == "SEE_INSTRUCTOR") {
    zone = "SEE INSTRUCTOR";
  }


#if 1

  ostr << "<table border=1 cellpadding=5 cellspacing=0 style=\"background-color:#ddffdd\">\n";
  ostr << "<tr><td>\n";
  ostr << "<table border=0 cellpadding=5 cellspacing=0>\n";
  ostr << "  <tr><td colspan=2>" << GLOBAL_EXAM_TITLE << "</td></tr>\n";
  ostr << "  <tr><td>" << GLOBAL_EXAM_DATE << "</td><td align=center>" << time << "</td></tr>\n";
  ostr << "  <tr><td>Your room assignment: </td><td align=center>" << room << "</td></tr>\n";
  ostr << "  <tr><td>Your zone assignment: </td><td align=center>" << zone << "</td></tr>\n";
  ostr << "</table>\n";
  ostr << "</tr></td>\n";
  ostr << "</table>\n";

#else


  ostr << "<table border=1 cellpadding=5 cellspacing=0 style=\"background-color:#ddffdd\">\n";
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
  std::string x2 = s->getZone(1);

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


void start_table(std::ofstream &ostr, std::string &filename, bool for_instructor,
                 const std::vector<Student*> &students, int rank, int month, int day, int year) {
  
  ostr.exceptions ( std::ofstream::failbit | std::ofstream::badbit );
  try {
    ostr.open(filename.c_str());
  }
  catch (std::ofstream::failure e) {
    std::cout << "FAILED TO OPEN " << filename << std::endl;
    std::cerr << "Exception opening/reading file";
    exit(0);
  }

  Student* s = NULL;
  if (rank != -1) {
    s = students[rank];
    assert (s != NULL);
  }


  // -------------------------------------------------------------------------------
  // PRINT INSTRUCTOR SUPPLIED MESSAGES
  for (int i = 0; i < MESSAGES.size(); i++) {
    ostr << "" << MESSAGES[i] << "<br>\n";
  }
  // get todays date
  //time_t now = time(0);  
  //struct tm * now2 = localtime( & now );
  if (s != NULL) {
    ostr << "<em>Information last updated: " << s->getLastUpdate() << "</em><br>\n";
  }
  ostr << "<p>&nbsp;</p>\n";


  ostr << "<p>&nbsp;</p>\n";

  // -------------------------------------------------------------------------------
  // BEGIN THE TABLE
  ostr << "<table border=2 cellpadding=5 cellspacing=0>\n";

  // open the title row
  ostr << "<tr>";


  // -------------------------------------------------------------------------------
  // RANK & SECTION
  if (for_instructor) {
    ostr << "<td align=center>#</td>";   
  }
  ostr << "<td align=center>SECTION</td>";   
  
  // -------------------------------------------------------------------------------
  // INSTRUCTOR NOTES
  if (for_instructor && DISPLAY_INSTRUCTOR_NOTES) {
    ostr << "<td align=center>part.</td>" 
         << "<td align=center>under.</td>";
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
  if (DISPLAY_GRADE_SUMMARY) {
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
    for (int i = 0; i < ALL_GRADEABLES.size(); i++) {
      ostr << "<td align=center>" << gradeable_to_string(ALL_GRADEABLES[i]) << " %</td>";
    }
  }

  // -------------------------------------------------------------------------------  
  // GRADE DETAILS
  if (DISPLAY_GRADE_DETAILS) {
    for (int i = 0; i < ALL_GRADEABLES.size(); i++) {
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
             << "<td align=center colspan=" << ICLICKER_QUESTION_NAMES.size() << ">ICLICKER QUESTIONS<br>CORRECT(green)=1.0, INCORRECT(red)=0.5, POLL(yellow)=1.0, NO ANSWER(white)=0.0<br>30.0 iClicker points = 3rd late day, 60.0 iClicker pts = 4th late day, 90.0 iClicker pts = 5th late day<br>&ge;8.0/12.0 most recent=Priority Help Queue (iClicker status highlighted in blue)</td>";
    }
  }
  
  // -------------------------------------------------------------------------------  
  ostr << "</td></tr>\n";    
}




void output_line_helper(std::ofstream &ostr, GRADEABLE_ENUM g,
                        Student *this_student,
                        Student *sp, Student *sa, Student *sb, Student *sc, Student *sd) {
  for (int i = 0; i < GRADEABLES[g].getCount(); i++) {
    if (i == 0) ostr << "<td align=center bgcolor=888888>&nbsp;</td>\n"; 
 
    std::string bonus_text = "";
    // special case for homework
    if (g == GRADEABLE_ENUM::HOMEWORK) {
      int count = this_student->getUsedLateDays(i);
      if (count > 3) { bonus_text += "(" + std::to_string(count) + "*)"; }
      else { bonus_text += std::string(count,'*'); }
    }
    float grade = this_student->getGradeableValue(g,i);
    ostr << std::setprecision(2) << std::fixed;
    colorit(ostr,
            grade, 
            sp->getGradeableValue(g,i),
            sa->getGradeableValue(g,i),
            sb->getGradeableValue(g,i),
            sc->getGradeableValue(g,i),
            sd->getGradeableValue(g,i),1,false,bonus_text);
  }

  // special case for test
  if (g == GRADEABLE_ENUM::TEST) {
    if (TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT) {
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>\n"; 
      for (int i = 0; i < GRADEABLES[g].getCount(); i++) {
        colorit(ostr,this_student->adjusted_test(i),sp->adjusted_test(i),sa->adjusted_test(i),sb->adjusted_test(i),sc->adjusted_test(i),sd->adjusted_test(i));
      }
    }
  }

}


void output_line(std::ofstream &ostr, 
                 int part,
                 bool for_instructor,
                 Student *this_student,
                 int rank,
                 Student *sp, Student *sa, Student *sb, Student *sc, Student *sd) {
  
  assert (this_student != NULL);

  
  if (this_student->getParticipation() + this_student->getUnderstanding() >= 9) {
    //std::cout << "recommend " << this_student->getUserName() << " " << myTA(this_student->getSection()) << std::endl;
  }


#if 0
  if (this_student->overall() < 20 && validSection(this_student->getSection()) && this_student->getUserName() != "" &&
      for_instructor) {
    std::cout << "warning " << this_student->getUserName() << " failing(2)" << std::endl;
  }
#endif


  // open the row
  ostr << "<tr>";


  std::string my_color="ffffff";
  if (this_student->getSection() == 0 && this_student->getUserName() != "") {
    if (this_student->getUserName() == "PERFECT")        my_color = coloritcolor(1.0, 1.0,0.9,0.8,0.7,0.6);
    else if (this_student->getUserName() == "LOWEST A-") my_color = coloritcolor(0.9, 1.0,0.9,0.8,0.7,0.6);
    else if (this_student->getUserName() == "LOWEST B-") my_color = coloritcolor(0.8, 1.0,0.9,0.8,0.7,0.6);
    else if (this_student->getUserName() == "LOWEST C-") my_color = coloritcolor(0.7, 1.0,0.9,0.8,0.7,0.6);
    else if (this_student->getUserName() == "LOWEST D")  my_color = coloritcolor(0.6, 1.0,0.9,0.8,0.7,0.6);
    else my_color="ff0000";
  }

  // -------------------------------------------------------------------------------
  // RANK & SECTION
  if (for_instructor) {
    if (rank != -1) {
      ostr << "<td bgcolor=\"" << my_color << "\"align=center>" << rank << "</td>";   
    } else {
      ostr << "<td bgcolor=\"" << my_color << "\"align=center>&nbsp;</td>";   
    }
  }
  colorit_section(ostr,this_student->getSection(), for_instructor, my_color);


  // -------------------------------------------------------------------------------
  // INSTRUCTOR NOTES
  if (for_instructor && DISPLAY_INSTRUCTOR_NOTES) {
    colorit(ostr,this_student->getParticipation(),5,4,3,2,1,0);
    colorit(ostr,this_student->getUnderstanding(),5,4,3,2,1,0);
    ostr << "<td><font color=\"blue\">" << this_student->getTA_recommendation() << "</font>";
    ostr << "<font color=\"magenta\">" << this_student->getOtherNote() << "</font>";
    ostr << "&nbsp;<font color=\"red\">";
    std::vector<std::string> ews = this_student->getEarlyWarnings();
    for (int i = 0; i < ews.size(); i++) {
      ostr << ews[i] << "<br>";
    }
    ostr << "</font></td>";
  }


  // -------------------------------------------------------------------------------  
  // NAME
  ostr << "<td bgcolor=\"" << my_color << "\">" << this_student->getUserName() << "</td>";
  ostr << "<td bgcolor=\"" << my_color << "\">" << this_student->getLastName() << "</td>";
  ostr << "<td bgcolor=\"" << my_color << "\">" << this_student->getFirstName() << "</td>";

  // -------------------------------------------------------------------------------  
  // EXAM SEATING
  if (DISPLAY_EXAM_SEATING) {
    ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
    ostr << "<td bgcolor=\"" << my_color << "\"align=center>" << this_student->getExamRoom() << "</td>";   
    ostr << "<td bgcolor=\"" << my_color << "\"align=center>" << this_student->getExamZone() << "</td>";   
    ostr << "<td bgcolor=\"" << my_color << "\"align=center>" << this_student->getExamTime() << "</td>";   
  }

  // -------------------------------------------------------------------------------  
  // ICLICKER REMOTE
  if (DISPLAY_ICLICKER && ICLICKER_QUESTION_NAMES.size() > 0) {
    if (this_student->getRemoteID() != "" && this_student->hasPriorityHelpStatus()) {
      ostr << "<td bgcolor=ccccff>registered</td>";
      //ostr << "<td bgcolor=ccccff>" << this_student->getRemoteID() << "</td>";
    } else if (this_student->getRemoteID() != "") {
      ostr << "<td>registered</td>";
      //      ostr << "<td>" << this_student->getRemoteID() << "</td>";
    } else if (this_student->getLastName() == "") {
      ostr << "<td>&nbsp;</td>";
    } else {
      ostr << "<td bgcolor=ffcccc>no iclicker registration</td>";
    }
  }

  // -------------------------------------------------------------------------------  
  // GRADE SUMMARY
  if (DISPLAY_GRADE_SUMMARY) {
    if (DISPLAY_FINAL_GRADE) {
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
      if (!this_student->getAudit() &&
          !this_student->getWithdraw() &&
          validSection(this_student->getSection())) {
        //this_student->outputgrade(ostr,true,sd);
        this_student->outputgrade(ostr,false,sd);
      } else {
        ostr << "<td>&nbsp;</td>";
      }
    }
    if (DISPLAY_GRADE_DETAILS) {
      if (for_instructor && DISPLAY_MOSS_DETAILS) {
        if (this_student->getSection() != 0 && !this_student->getAudit() && this_student->getMossPenalty() < 0) {
          this_student->outputgrade(ostr,true,sd);
        } else { 
          ostr << "<td align=center bgcolor=ffffff>&nbsp;</td>";
        }
      }

      ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
    }
    ostr << std::setprecision(2) << std::fixed;

    if (for_instructor && DISPLAY_MOSS_DETAILS) {
      if (this_student->getUserName() != "LOWEST D" && this_student->getMossPenalty() < 0) {
        colorit(ostr,this_student->overall(),sp->overall(),sa->overall(),sb->overall(),sc->overall(),sd->overall());
      } else { 
        ostr << "<td align=center bgcolor=ffffff>&nbsp;</td>";
      }
    }

    if (this_student->getUserName() != "LOWEST D") {
      colorit(ostr,this_student->overall_b4_moss(),sp->overall(),sa->overall(),sb->overall(),sc->overall(),sd->overall());
    }
    else { 
      ostr << "<td align=center bgcolor=ffffff>&nbsp;</td>";
    }


    ostr << std::setprecision(2) << std::fixed;
    if (DISPLAY_GRADE_DETAILS) {
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>\n"; 
    }
    for (int i = 0; i < ALL_GRADEABLES.size(); i++) {
      GRADEABLE_ENUM g = ALL_GRADEABLES[i];
      colorit(ostr,this_student->GradeablePercent(g),
              sp->GradeablePercent(g),
              sa->GradeablePercent(g),
              sb->GradeablePercent(g),
              sc->GradeablePercent(g),
              sd->GradeablePercent(g));
    }
  }

  // -------------------------------------------------------------------------------  
  // GRADE DETAILS
  if (DISPLAY_GRADE_DETAILS) {
    for (int i = 0; i < ALL_GRADEABLES.size(); i++) {
      GRADEABLE_ENUM g = ALL_GRADEABLES[i];
      output_line_helper(ostr,g,this_student,sp,sa,sb,sc,sd);
    }
  }

  if (DISPLAY_ICLICKER) {
    if (ICLICKER_QUESTION_NAMES.size() > 0) {
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>";
      colorit(ostr,this_student->getIClickerTotalFromStart(),
              MAX_ICLICKER_TOTAL,
              0.90*MAX_ICLICKER_TOTAL,
              0.80*MAX_ICLICKER_TOTAL,
              0.60*MAX_ICLICKER_TOTAL,
              0.40*MAX_ICLICKER_TOTAL);
        //      /*
      colorit(ostr,this_student->getIClickerRecent(),
              ICLICKER_RECENT,
              0.90*ICLICKER_RECENT,
              0.80*ICLICKER_RECENT,
              0.60*ICLICKER_RECENT,
              0.40*ICLICKER_RECENT);
      int allowed = this_student->getAllowedLateDays(100);
      colorit(ostr,allowed,5,4,3,2,2,0,true);
      int used = this_student->getUsedLateDays();
      
      if (this_student->getSection() == 0) {
        ostr << "<td align=center bgcolor=ffffff>&nbsp;</td>";
      } else {
        ostr << "<td align=center bgcolor=";
        coloritcolor(ostr, allowed-used+2, 5+2, 3+2, 2+2, 1+2, 0+2);
        ostr << ">" << used<<"</td>\n"; 
        }
      //*/
      ostr << "<td align=center bgcolor=888888>&nbsp;</td>\n"; 
    }
    for (int i = 0; i < ICLICKER_QUESTION_NAMES.size(); i++) {
      
      std::pair<std::string,float> answer = this_student->getIClickerAnswer(ICLICKER_QUESTION_NAMES[i]);
      
      if (for_instructor && this_student->getUserName() == "PERFECT") {
        ostr << "<td align=center bgcolor=ffffff>" << ICLICKER_QUESTION_NAMES[i] << "</td>\n"; 
        
      } else {
        
        std::string thing = answer.first;
        if (!for_instructor) {
          thing = "&nbsp;";
        }
        
        if (answer.second == ICLICKER_CORRECT) {
          ostr << "<td align=center bgcolor=aaffaa>" << thing << "</td>\n"; 
        } else if (answer.second == ICLICKER_PARTICIPATED) {
          ostr << "<td align=center bgcolor=ffffaa>" << thing << "</td>\n"; 
        } else if (answer.second == ICLICKER_INCORRECT) {
          ostr << "<td align=center bgcolor=ffaaaa>" << thing << "</td>\n"; 
        } else {
          assert (answer.second == ICLICKER_NOANSWER);
          ostr << "<td align=center bgcolor=ffffff>&nbsp;</td>\n"; 
        }
      }
    }
  }
}



void end_table(std::ofstream &ostr,  bool for_instructor, const std::vector<Student*> &students, int rank) {


  bool print_moss_message = false;
  if (rank != -1 && students[rank]->getMossPenalty() < -0.01) {
    print_moss_message = true;
  }

  if (print_moss_message) {
    ostr << "@ = final grade with Academic Integrity Violation penalty<p>&nbsp;<p>\n";
  }

  if (DISPLAY_FINAL_GRADE && students.size() > 50) {

  int total_A = grade_counts[Grade("A")] + grade_counts[Grade("A-")];
  int total_B = grade_counts[Grade("B+")] + grade_counts[Grade("B")] + grade_counts[Grade("B-")]; 
  int total_C = grade_counts[Grade("C+")] + grade_counts[Grade("C")] + grade_counts[Grade("C-")];
  int total_D = grade_counts[Grade("D+")] + grade_counts[Grade("D")];
  int total_passed = total_A + total_B + total_C + total_D;
  int total_F = grade_counts[Grade("F")];
  int total_blank = grade_counts[Grade("")];
  int total = total_passed + total_F + auditors + total_blank;

  ostr << "<p>\n";



  ostr << "<table border=2 cellpadding=5 cellspacing=0>\n";
  ostr << "<tr>\n";
  ostr << "<td width=150>FINAL GRADE</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("A")<<" width=40>A</td><td align=center bgcolor="<<GradeColor("A-")<<" width=40>A-</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("B+")<<" width=40>B+</td><td align=center bgcolor="<<GradeColor("B")<<" width=40>B</td><td align=center bgcolor="<<GradeColor("B-")<<" width=40>B-</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("C+")<<" width=40>C+</td><td align=center bgcolor="<<GradeColor("C")<<" width=40>C</td><td align=center bgcolor="<<GradeColor("C-")<<" width=40>C-</td>";
  ostr << "<td align=center bgcolor="<<GradeColor("D+")<<" width=40>D+</td><td align=center bgcolor="<<GradeColor("D")<<" width=40>D</td>\n";
  if (for_instructor) {
    ostr << "<td align=center bgcolor="<<GradeColor("F")<<"width=40>F</td><td align=center width=40>dropped</td>\n";
    ostr << "<td align=center width=40>audit</td>\n";
    ostr << "<td align=center align=center width=40>took final</td>\n";
    ostr << "<td align=center align=center width=40>total passed</td>\n";
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
    ostr << "<td align=center width=40>"<<grade_counts[Grade("F")]<<"</td><td align=center width=40>"<<grade_counts[Grade("")]<<"</td>\n";
    ostr << "<td align=center width=40>"<<auditors<<"</td>\n";
    ostr << "<td align=center width=40>"<<took_final<<"</td>\n";
    ostr << "<td align=center width=40>"<<total_passed<<"</td>\n";
    ostr << "<td align=center width=40>"<<total<<"</td>\n";
  }
  ostr << "</tr>\n";
  
  
  
  ostr << "<tr>\n";
  ostr << "<td width=150>average OVERALL<br>of students with<br>this FINAL GRADE</td>";
  ostr << "<td align=center width=40>"<<grade_avg[Grade("A")]<<"</td><td align=center width=40>"<<grade_avg[Grade("A-")]<<"</td>";
  ostr << "<td align=center width=40>"<<grade_avg[Grade("B+")]<<"</td><td align=center width=40>"<<grade_avg[Grade("B")]<<"</td><td align=center width=40>"<<grade_avg[Grade("B-")]<<"</td>";
  ostr << "<td align=center width=40>"<<grade_avg[Grade("C+")]<<"</td><td align=center width=40>"<<grade_avg[Grade("C")]<<"</td><td align=center width=40>"<<grade_avg[Grade("C-")]<<"</td>";

  if (for_instructor) {
    ostr << "<td align=center width=40>"<<grade_avg[Grade("D+")]<<"</td><td align=center width=40>"<<grade_avg[Grade("D")]<<"</td>\n";
  } else {
    ostr << "<td align=center width=40> &nbsp; </td><td align=center width=40> &nbsp; </td>\n";
  }

  if (for_instructor) {
    ostr << "<td align=center width=40>"<<grade_avg[Grade("F")]<<"</td><td align=center width=40>"<<grade_avg[Grade("")]<<"</td>\n";
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
