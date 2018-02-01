#include <fstream>
#include <map>
#include <iomanip>


#include "student.h"
#include "constants_and_globals.h"

//==========================================================================

bool is_lefty_seat(const std::string& s) {
  return s.back() == 'L';
}

class ZoneInfo {
public:
  ZoneInfo() { max = 0; count = 0; }
  std::string building;
  std::string room;
  std::string zone;
  std::string image_url;
  int max;
  int count;
  bool take_seat(std::string r, std::string s) {
    assert (count < max);
    if (r == "N/A" && s == "N/A") {
      assert (num_available_seats() == 0);
      count++;
      return true;
    }
    std::vector<std::pair<std::string,std::string> >* available_seats;
    if (is_lefty_seat(s)) {
      available_seats = &available_lefty_seats;
    } else {
      available_seats = &available_nonlefty_seats;
    }
    for (std::vector<std::pair<std::string,std::string> >::iterator itr = available_seats->begin();
         itr != available_seats->end(); itr++) {
      if (itr->first == r && itr->second == s) {
        available_seats->erase(itr);
        count++;
        return true;
      }
    }
    return false;
  }
  void assign_seat(std::string &r, std::string &s, bool is_lefty) {
    if (num_available_seats() == 0) {
      r = "N/A";
      s = "N/A";
      count++;
      return;
    }

    std::vector<std::pair<std::string,std::string> >* available_seats;
    if (is_lefty) {
      available_seats = &available_lefty_seats;
    } else {
      available_seats = &available_nonlefty_seats;
    }
    assert (available_seats->size() > 0);
    assert (count < max);

    int random_seat = std::rand()%available_seats->size();
    std::vector<std::pair<std::string,std::string> >::iterator itr = available_seats->begin() + random_seat;
    assert (itr != available_seats->end());
    r = itr->first;
    s = itr->second;
    available_seats->erase(itr);
    count++;
  }
  void clear_seats() {
    available_lefty_seats.clear();
    available_nonlefty_seats.clear();
  }
  int num_available_seats() {
    return available_lefty_seats.size() + available_nonlefty_seats.size();
  }
  int num_lefty_seats() {
    return available_lefty_seats.size();
  }
  void add_seat(std::string &r, std::string &s) {
    if (is_lefty_seat(s)) {
      available_lefty_seats.push_back(std::make_pair(r,s));
    } else {
      available_nonlefty_seats.push_back(std::make_pair(r,s));
    }
  }
private:
  std::vector<std::pair<std::string,std::string> > available_lefty_seats;
  std::vector<std::pair<std::string,std::string> > available_nonlefty_seats;
};


// random generator function:
int myrandomzone (int i) { return std::rand()%i;}

//==========================================================================

// More intuitive sort function to organize zones first by length (#
// of characters), then alphabetical.
class ZoneSorter {
public:
  bool operator()(const std::string& a, const std::string& b) const {
    return (a.size() < b.size() ||
            (a.size() == b.size() && a < b));
  }
};


int CountLefties(std::vector<Student*> &students,const std::string& lefty_file, float min_for_assignment) {
  if (lefty_file == "") return 0;
  std::ifstream istr(lefty_file.c_str());
  assert (istr.good());

  int count = 0;
  std::string user,handedness;
  while (istr >> user >> handedness) {
    Student *s = GetStudent(students,user);    
    assert (s != NULL);
    if (handedness == "left") {
      //std::cout << "LEFTY " << user << std::endl;
      s->setLefty();
      if (s->getExamRoom() != "") {
        std::cout << "ALREADY ASSIGNED ROOM " << user << std::endl;
      } else if (s->overall() < GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT) {
        std::cout << "NOT ASSIGNING LEFTY " << user << std::endl; 
      } else {
        std::cout << "NEED SEAT FOR LEFTY " << user << std::endl;
        count++;
      }
    }
  }
  return count;
}

void LoadExamSeatingFile(const std::string &zone_counts_filename,
                         const std::string &zone_assignments_filename,
                         const std::string &seating_spacing,
                         const std::string &left_right_handedness,
                         std::vector<Student*> &students) {

  
  std::cout << "zone counts filename '" << zone_counts_filename << "'" << std::endl;
  std::cout << "zone assignments filename '" << zone_assignments_filename << "'" << std::endl;
  std::cout << "left_right_handedness '" << left_right_handedness << "'" << std::endl;
  std::cout << "min overall " << GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT << std::endl;
  
  assert (zone_counts_filename != "");
  assert (zone_assignments_filename != "");

  int num_lefties = CountLefties(students,left_right_handedness, GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT);

  std::cout << "NUM LEFTIES " << num_lefties << std::endl;
  
  // ============================================================
  // read in the desired zone counts

  std::map<std::string,ZoneInfo,ZoneSorter> zones;
  std::ifstream istr_zone_counts(zone_counts_filename.c_str());
  assert (istr_zone_counts.good());
  
  int total_seats = 0;
  ZoneInfo zi;

  std::string line;

  int lefty_desk_count = 0;
  
  std::string token;
  istr_zone_counts >> token;
  assert (token == "zone");
  while (1) {

    std::getline(istr_zone_counts,line);
    std::stringstream ss(line);

    if (!(ss >> zi.zone >> zi.building >> zi.room >> zi.max)) {
      std::cout << "MISFORMATTED ZONE COUNTS" << std::endl; exit(1); }
    ss >> zi.image_url;

    //std::cout << "ZONE IMAGE " << zi.image_url << std::endl;
    
    zi.count=0;
    zi.clear_seats();
    if (zones.find(zi.zone) != zones.end()) {
      std::cerr << "\nERROR: duplicate zone " << zi.zone << " in " << zone_counts_filename << std::endl;
      exit(0);
    }
    if (zi.max != -1) {
      assert (zi.max >= 0);
      total_seats += zi.max;
    }

    bool read_another_zone = false;
    while (istr_zone_counts >> token) {
      //std::cout << "======================================\nTOKEN " << token << std::endl;
      if (token[0] == '#') {
        std::getline(istr_zone_counts,line);
        //std::cout << "COMMENTED LINE " << line << std::endl;
        continue;
      } else if (token == "row") {
        std::getline(istr_zone_counts,line);
        std::stringstream ss(line);
        std::string row,seat;
        ss >> row >> token;
        assert (token == ":");
        std::vector<std::string> all_seats;
        while (ss >> seat) {
          all_seats.push_back(seat);
        }
        bool skip = false;
        for (int i = 0; i < all_seats.size(); i++) {
          seat = all_seats[i];
          //std::cout << "SEAT " << seat << std::endl;
          if (seat.back() == 'X') {
            //std::cout << "BROKEN DESK " << zi.zone << " "<< row << " " << seat << std::endl;
            continue;
          }
          if (skip) {
            skip = false;
            //std::cout << "SKIPPING " << zi.zone << " "<< row << " " << seat << std::endl;
            continue;
          }
          if (seat.back() == 'L') {
            //std::cout << "LEFTY DESK " << zi.zone << " " << row << " " << seat << std::endl;
            lefty_desk_count++;
          }
          skip = true;
          //std::cout << "SEAT " << seat << std::endl;
          zi.add_seat(row,all_seats[i]);
        }
      } else if (token == "zone") {
        read_another_zone = true;
        break;
      } else {
        assert (0);
      }
      if (token == "zone") {
        read_another_zone = true;
        break;
      }
    }
    if (zi.max == -1) {
      zi.max = zi.num_available_seats();
      total_seats += zi.max;
    }
    if (zi.num_available_seats() != 0 &&
        zi.max != zi.num_available_seats()) {
      std::cout << "AVAILABLE SEATS FOR ZONE " << zi.zone << " are incorrect " <<
        zi.max << " max    vs " << zi.num_available_seats() << " available" << std::endl;
      exit(1);
    }
    zones.insert(std::make_pair(zi.zone,zi));
    if (!read_another_zone) break;

  }
  std::cout << "TOTAL SEATS FOR EXAM " << total_seats << std::endl;
  std::cout << "LEFTY DESK COUNT " << lefty_desk_count << std::endl;

  // ============================================================
  // read in any existing assignments...

  std::cout << "READING " << zone_assignments_filename << std::endl;

  int existing_assignments = 0;
  {
    std::ifstream istr_zone_assignments(zone_assignments_filename.c_str());
    if (istr_zone_assignments.good()) {
      std::string line;
      while (getline(istr_zone_assignments,line)) {
        std::stringstream ss(line.c_str());
        std::string token,last,first,rcs,section,building,room,zone,row,seat,time;
        ss >> last >> first >> rcs >> section >> building >> room >> zone >> row >> seat >> time;

        while (ss >> token) { time += " " + token; }

        //std::cout << "FOUND " << rcs << std::endl;

        if (last == "") break;
        Student *s = GetStudent(students,rcs);
        if (s == NULL) {
          std::cout << "seating assignment...  couldn't find this userid " << rcs << std::endl;
        }
        assert (s != NULL);
        if (zone != "") {
          std::map<std::string,ZoneInfo>::iterator itr = zones.find(zone);
          if (itr == zones.end()) {
            std::cerr << "ERROR! this zone '" << zone << "' assigned to '" << s->getUserName() << "'does not exist!" << std::endl;
            exit(1);
          }
          if (itr->second.max <= itr->second.count) {
            std::cerr << "ERROR! this zone '" << zone << "' is full (max:" << itr->second.max << ")" << std::endl;
            exit(1);
          }
          assert (itr->second.building == building);
          assert (itr->second.room == room);
          bool success = itr->second.take_seat(row,seat);
          if (!success) {
            std::cout << "ERROR COULD NOT TAKE SEAT " << zone << " row:" << row
                      << " seat:" << seat << std::endl;
            exit(1);
          }
          
          existing_assignments++;
          s->setExamRoom(building+std::string(" ")+room);
          s->setExamZone(zone,row,seat);
          s->setExamZoneImage(itr->second.image_url);
          if (time != "") {
            s->setExamTime(time);
          } else {
            s->setExamTime(GLOBAL_EXAM_TIME);
          }
        }
      }
    }
  }
  std::cout << "EXISTING ASSIGNMENTS  " << existing_assignments << std::endl;

  // ============================================================
  // make a vector of available seats

  // FIXME: this belongs once, at start of program
  std::srand ( unsigned ( std::time(0) ) );
  
  std::vector<std::string> randomized_lefty_available;
  std::vector<std::string> randomized_nonlefty_available;
  for (std::map<std::string,ZoneInfo>::iterator itr = zones.begin(); itr != zones.end(); itr++) {
    assert (itr->second.count <= itr->second.max);
    int num_l = itr->second.num_lefty_seats();
    int num_nl = itr->second.num_available_seats() - num_l;
    for (int i = 0; i < num_l; i++) {
      randomized_lefty_available.push_back(itr->first);
    }
    for (int i = 0; i < num_nl; i++) {
      randomized_nonlefty_available.push_back(itr->first);
    }
  }
  std::cout << "AVAILABLE LEFTY SEATS " << randomized_lefty_available.size() << std::endl;
  std::cout << "AVAILABLE NONLEFTY SEATS " << randomized_nonlefty_available.size() << std::endl;
  std::random_shuffle ( randomized_lefty_available.begin(), randomized_lefty_available.end(), myrandomzone );
  std::random_shuffle ( randomized_nonlefty_available.begin(), randomized_nonlefty_available.end(), myrandomzone );

  // ============================================================
  // do the assignments!

  int not_reg = 0;
  int low_overall_grade = 0;
  int new_zone_assign = 0;
  int already_zoned = 0;
  int next_lefty_za = 0;
  int next_nonlefty_za = 0;

  for (unsigned int i = 0; i < students.size(); i++) {

    Student* &s = students[i];

    if (s->getExamRoom() != "") {
      already_zoned++;
    } else if (!validSection(s->getSection())) {
      not_reg++;
    } else if (s->overall() < GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT) {
      low_overall_grade++;
    } else {

      if (s->getLefty()) {
        if (next_lefty_za >= (int)randomized_lefty_available.size()) {
          std::cout << "OOPS!  we ran out of lefty exam seating" << std::endl;
        }
        assert (next_lefty_za < (int)randomized_lefty_available.size());
        ZoneInfo &next_zi = zones.find(randomized_lefty_available[next_lefty_za])->second;
        s->setExamRoom(next_zi.building+std::string(" ")+next_zi.room);
        std::string row,seat;
        //std::cout << "ASSIGN student " << s->getUserName() << std::endl;
        next_zi.assign_seat(row,seat,s->getLefty());
        s->setExamZone(next_zi.zone,row,seat);
        s->setExamZoneImage(next_zi.image_url);
        next_lefty_za++;
        new_zone_assign++;
      } else {
        if (next_nonlefty_za >= (int)randomized_nonlefty_available.size()) {
          std::cout << "OOPS!  we ran out of nonlefty exam seating" << std::endl;
        }
        assert (next_nonlefty_za < (int)randomized_nonlefty_available.size());
        ZoneInfo &next_zi = zones.find(randomized_nonlefty_available[next_nonlefty_za])->second;
        s->setExamRoom(next_zi.building+std::string(" ")+next_zi.room);
        std::string row,seat;
        //std::cout << "ASSIGN student " << s->getUserName() << std::endl;
        next_zi.assign_seat(row,seat,s->getLefty());
        s->setExamZone(next_zi.zone,row,seat);
        s->setExamZoneImage(next_zi.image_url);
        next_nonlefty_za++;
        new_zone_assign++;
      }
    }
  }
  
  std::cout << "new zone assignments             " << new_zone_assign << std::endl;
  std::cout << "low overall grade (not assigning a zone) " << low_overall_grade << std::endl;
  std::cout << "not registered in valid section  " << not_reg << std::endl;

  assert (new_zone_assign <=
          int(randomized_lefty_available.size()) +
          int(randomized_nonlefty_available.size()));


  // ============================================================
  // write out the assignments!

  if (1) { //new_zone_assign > 0) {

    std::ofstream ostr_zone_assignments(zone_assignments_filename.c_str());
    assert (ostr_zone_assignments.good());
    
    for (unsigned int i = 0; i < students.size(); i++) {
      
      Student* &s = students[i];
      
      if (s->getLastName() == "") continue;

      std::string f = s->getPreferredName();
      std::string l = s->getLastName();
      std::replace( f.begin(), f.end(), ' ', '_');
      std::replace( l.begin(), l.end(), ' ', '_');

      ostr_zone_assignments << std::setw(20) << std::left << l  << " ";
      ostr_zone_assignments << std::setw(15) << std::left << f << " ";
      ostr_zone_assignments << std::setw(12) << std::left << s->getUserName()  << " ";
      if (s->getSection())
        ostr_zone_assignments << std::setw(12) << std::left << s->getSection()  << " ";
      else
        ostr_zone_assignments << std::setw(12) << std::left << "" << " ";

      ostr_zone_assignments << std::setw(10) << std::left << s->getExamRoom()  << " ";
      ostr_zone_assignments << std::setw(10) << std::left << s->getExamZone()  << " ";
      ostr_zone_assignments << std::setw(10) << std::left << s->getExamRow()  << " ";
      ostr_zone_assignments << std::setw(10) << std::left << s->getExamSeat()  << " ";
      ostr_zone_assignments << std::setw(10) << std::left << s->getExamTime();
      
      ostr_zone_assignments << std::endl;

    }

  }

  // ============================================================
  // data for preparing exams


  int total_assignments = 0;
  for (std::map<std::string,ZoneInfo>::iterator itr = zones.begin();
       itr != zones.end(); itr++) {

    std::cout << "ZONE " << std::left  << std::setw(6)  << itr->first 
              << " "     << std::left  << std::setw(10) << itr->second.building << "  " 
              << " "     << std::left  << std::setw(4)  << itr->second.room << "  " 
              << " "     << std::right << std::setw(4)  << itr->second.count << "      (" << itr->second.max-itr->second.count << " seats remain)" << std::endl;

    total_assignments += itr->second.count;
  }
  std::cout << "TOTAL " << total_assignments << std::endl;


}
