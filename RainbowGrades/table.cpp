#include "table.h"

std::ostream& operator<<(std::ostream &ostr, const TableCell &c) {
  ostr << "<td bgcolor=\"" << c.color << "\" align=\"" << c.align << "\">" << c.data << "</td>";
  return ostr;
}

void Table::output(std::ostream& ostr,
                   std::vector<int> which_students,
                   std::vector<int> which_data,
                   bool transpose) const {

  ostr << "<table border=1 cellpadding=5 cellspacing=0 style=\"background-color:#ddffdd\">\n";
  
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
