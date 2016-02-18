#include <vector>
#include <string>
#include <iostream>
#include <sstream>
#include <iomanip>

class TableCell {
public:
  TableCell() { color="ffcccc"; data =""; span =1; align="left";}
  TableCell(std::string c, std::string d="", int s=1) { color=c; data=d; span=s; align="left";}
  TableCell(std::string c, int d, int s=1) { color=c; data=std::to_string(d); span=s; align="left";}
  TableCell(std::string c, float d, int s=1) { 
    color=c; 
    if (d > 0.0001) {
      std::stringstream ss;
      ss << std::setprecision(1) << std::fixed << d;
      data=ss.str(); span=s; 
    } else {
      data = "";
    }
    align="right";
  }
  std::string color;
  std::string data;
  int span;
  std::string align;
  friend std::ostream& operator<<(std::ostream &ostr, const TableCell &c);
};



class Table {

public:

  Table() {
    cells = std::vector<std::vector<TableCell>>(1,std::vector<TableCell>(1));
  }

  void set(int r, int c, TableCell cell) {
    while(r >= numRows()) {
      cells.push_back(std::vector<TableCell>(numCols()));
    }
    while (c >= numCols()) {
      for (int x = 0; x < numRows(); x++) {
        cells[x].push_back(TableCell());
      }
    }
    cells[r][c] = cell;
  }

  void output(std::ostream& ostr,
              std::vector<int> which_students,
              std::vector<int> which_data,
              bool transpose=false) const;
  

  int numRows() const { return cells.size(); }
  int numCols() const { return cells[0].size(); }

private:
  std::vector<std::vector<TableCell>> cells;
};
