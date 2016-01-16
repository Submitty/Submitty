#ifndef __CONSTANTS_H__
#define __CONSTANTS_H__

#define MAX_STRING_LENGTH 10000

#include "grade.h"

// ==========================================================
// What sections to display in the output table
extern bool DISPLAY_INSTRUCTOR_NOTES;
extern bool DISPLAY_EXAM_SEATING;
extern bool DISPLAY_MOSS_DETAILS;
extern bool DISPLAY_FINAL_GRADE;
extern bool DISPLAY_GRADE_SUMMARY;
extern bool DISPLAY_GRADE_DETAILS;
extern bool DISPLAY_ICLICKER;


// ==========================================================
// messages for zone assignment
extern char GLOBAL_EXAM_TITLE[MAX_STRING_LENGTH];
extern char GLOBAL_EXAM_DATE[MAX_STRING_LENGTH];
extern char GLOBAL_EXAM_TIME[MAX_STRING_LENGTH];
extern char GLOBAL_EXAM_DEFAULT_ROOM[MAX_STRING_LENGTH];

extern float GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT;

extern std::vector<std::string> MESSAGES;


// ==========================================================
extern Student* PERFECT_STUDENT_POINTER;

extern bool  TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT;
extern float LATE_DAY_PERCENTAGE_PENALTY;
extern bool  LOWEST_TEST_COUNTS_HALF;


// ==========================================================
extern std::map<int,std::string> sectionNames;
extern std::map<std::string,std::string> sectionColors;


extern std::map<std::string,float> CUTOFFS;
extern std::map<Grade,int> grade_counts;
extern std::map<Grade,float> grade_avg;
extern int took_final;
extern int dropped;
extern int auditors;
extern float LATE_DAY_PERCENTAGE_PENALTY;

// ==========================================================
#define ICLICKER_RECENT 12
#define ICLICKER_PRIORITY 0.666
extern float MAX_ICLICKER_TOTAL;



// ==========================================================
// PROTOTYPES 

bool validSection(int section);


#endif // __CONSTANTS_H__
