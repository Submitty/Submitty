#include "table.h"

std::ostream& operator<<(std::ostream &ostr, const TableCell &c) {
  
  ostr << "<td bgcolor=\"" << c.color << "\" align=\"" << c.align << "\"><font size=-1>";

  if (c.data == "") {
    ostr << "<div></div>";
  } else {
    ostr << c.data; 
    if (c.note.length() > 0 &&
        c.note != " ") {
      ostr << "<br><em>" << c.note << "</em>";
    }
  }
  ostr << "</font></td>";
  return ostr;
}

void Table::output(std::ostream& ostr,
                   std::vector<int> which_students,
                   std::vector<int> which_data,
                   bool transpose,
                   bool show_details) const {

  ostr << "<table border=0 cellpadding=3 cellspacing=2 style=\"background-color:#aaaaaa\">\n";
  
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
