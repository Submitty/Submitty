#include <iostream>
#include <vector>
#include <sstream>

using namespace std;

vector<int> days_in_month = {0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31};

bool is_leap_year(int year) {
    return year % 4 == 0 && (year % 100 != 0 || year % 400 == 0);
}

int julian_day(int month, int day, int year) {
    int jday = 0;
    for (int m = 1; m < month; ++m) {
        jday += days_in_month[m];
        if (m == 2 && is_leap_year(year)) {
            jday += 1;
        }
    }
    jday += day;
    return jday;
}

int main() {
    std::cout << "Please enter three integers (a month, a day and a year): ";
    string line;
    getline(cin, line);
    istringstream iss(line);
    int month, day, year;
    iss >> month >> day >> year;

    if (month < 1 || month > 12) {
        cerr << "ERROR bad month: " << month << endl;
        return 1;
    }

    if (year < 1) {
        cerr << "ERROR bad year: " << year << endl;
        return 1;
    }

    int days_this_month = days_in_month[month];
    if (month == 2 && is_leap_year(year)) {
        days_this_month += 1;
    }

    if (day < 1 || day > days_this_month) {
        cerr << "ERROR bad day: " << day << endl;
        return 1;
    }

    cout << julian_day(month, day, year) << endl;
    return 0;
}
