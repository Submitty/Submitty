#ifndef __PACMAN_GRADER_H__
#define __PACMAN_GRADER_H__

#include "TestCase.h"

TestResults* PacmanGrader_doit (const TestCase &tc, const nlohmann::json& j);

#endif
