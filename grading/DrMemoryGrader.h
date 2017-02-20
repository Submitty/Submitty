#ifndef __DRMEMORY_GRADER_H__
#define __DRMEMORY_GRADER_H__

#include "TestCase.h"

TestResults* DrMemoryGrader_doit (const TestCase &tc, const nlohmann::json& j);

#endif
