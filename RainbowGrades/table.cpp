#include <cmath>
#include <cassert>

#include "table.h"
#include "constants_and_globals.h"

bool GLOBAL_instructor_output = false;

bool global_details = false;

TableCell::TableCell(const std::string& c, const std::string& d, const std::string& n, int ldu,
                     CELL_CONTENTS_STATUS v, const std::string& a, int s, int r) { 
  assert (c.size() == 6);
  color=c; 
  data=d; 
  note=n; 
  late_days_used=ldu,
  visible=v;
  align=a;
  span=s; 
  rotate=r;
}

TableCell::TableCell(const std::string& c, int d, const std::string& n, int ldu,
                     CELL_CONTENTS_STATUS v, const std::string& a, int s, int r) { 
  assert (c.size() == 6);
  color=c; 
  data=std::to_string(d); 
  note=n; 
  late_days_used=ldu,
  visible=v;
  align=a;
  span=s; 
  rotate=r;
}

TableCell::TableCell(const std::string& c, float d, int precision, const std::string& n, int ldu,
                     CELL_CONTENTS_STATUS v, const std::string& a, int s, int r) { 
  assert (c.size() == 6);
  assert (precision >= 0);
  color=c; 
  if (fabs(d) > 0.0001) {
    std::stringstream ss;
    ss << std::setprecision(precision) << std::fixed << d;
    data=ss.str(); span=s; 
  } else {
    data = "";
  }
  note=n;
  late_days_used=ldu,
  visible=v;
  align=a;
  span=s; 
  rotate = 0;
}

std::ostream& operator<<(std::ostream &ostr, const TableCell &c) {
  assert (c.color.size() == 6);
  //  ostr << "<td bgcolor=\"" << c.color << "\" align=\"" << c.align << "\">";
  ostr << "<td style=\"border:1px solid #aaaaaa; background-color:#" << c.color << ";\" align=\"" << c.align << "\">";
  if (0) { //rotate == 90) {
    ostr << "<div style=\"position:relative\"><p class=\"rotate\">";
  }
  ostr << "<font size=-1>";
  

  if ((c.data == "" && c.note=="") 
      || c.visible==CELL_CONTENTS_HIDDEN
      || (c.visible==CELL_CONTENTS_VISIBLE_INSTRUCTOR && GLOBAL_instructor_output == false) 
      || (c.visible==CELL_CONTENTS_VISIBLE_STUDENT    && GLOBAL_instructor_output == true)) {


    ostr << "<div></div>";
  } else {
    ostr << c.data; 
    if (c.late_days_used > 0) {
      if (c.late_days_used > 3) { ostr << " (" << std::to_string(c.late_days_used) << "*)"; }
      else { ostr << " " << std::string(c.late_days_used,'*'); }
    }
    if (c.note.length() > 0 &&
        c.note != " " && 
        (global_details 
         /*
        || 
        c.visible==CELL_CONTENTS_HIDDEN
         */)
        ) {
      ostr << "<br><em>" << c.note << "</em>";
    }
  }
  ostr << "</font>";
  if (0) { //rotate == 90) {
    ostr << "</p></div>";
  }
  ostr << "</td>";
  return ostr;
}



void Table::output(std::ostream& ostr,
                   std::vector<int> which_students,
                   std::vector<int> which_data,
                   bool transpose,
                   bool show_details,
                   std::string last_update) const {

  global_details = show_details;

  ostr << "<style>\n";
  ostr << ".rotate {\n";
  ostr << "             filter:  progid:DXImageTransform.Microsoft.BasicImage(rotation=0.083);  /* IE6,IE7 */\n";
  ostr << "         -ms-filter: \"progid:DXImageTransform.Microsoft.BasicImage(rotation=0.083)\"; /* IE8 */\n";
  ostr << "     -moz-transform: rotate(-90.0deg);  /* FF3.5+ */\n";
  ostr << "      -ms-transform: rotate(-90.0deg);  /* IE9+ */\n";
  ostr << "       -o-transform: rotate(-90.0deg);  /* Opera 10.5 */\n";
  ostr << "  -webkit-transform: rotate(-90.0deg);  /* Safari 3.1+, Chrome */\n";
  ostr << "          transform: rotate(-90.0deg);  /* Standard */\n";
  ostr << " display:block;\n";
  ostr << " position:absolute;\n";
  ostr << " right:-50%;\n";
  ostr << "}\n";
  ostr << "</style>\n";


  // -------------------------------------------------------------------------------
  // PRINT INSTRUCTOR SUPPLIED MESSAGES
  for (unsigned int i = 0; i < MESSAGES.size(); i++) {
    ostr << "" << MESSAGES[i] << "<br>\n";
  }
  if (last_update != "") {
    ostr << "<em>Information last updated: " << last_update << "</em><br>\n";
  }
  ostr << "&nbsp;<br>\n";


  ostr << "<table style=\"border:1px solid #aaaaaa; background-color:#aaaaaa;\">\n";
  //  ostr << "<table border=0 cellpadding=3 cellspacing=2 style=\"background-color:#aaaaaa\">\n";
  
  if (transpose) {
    for (std::vector<int>::iterator c = which_data.begin(); c != which_data.end(); c++) {
      ostr << "<tr>\n";
      for (std::vector<int>::iterator r = which_students.begin(); r != which_students.end(); r++) {
        ostr << cells[*r][*c] << "\n";
      }
      ostr << "</tr>\n";
    }
  } else {
    for (std::vector<int>::iterator r = which_students.begin(); r != which_students.end(); r++) {
      ostr << "<tr>\n";
      for (std::vector<int>::iterator c = which_data.begin(); c != which_data.end(); c++) {
        ostr << cells[*r][*c] << "\n";
      }
      ostr << "</tr>\n";
    }
  } 
   
  ostr << "</table>" << std::endl;
}
