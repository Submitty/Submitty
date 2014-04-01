#include <string>
#include "runDiff.h"

int main(int argc, const char * argv[])
{
	if (argc != 2 )
	{
		std::cerr<<"Usage: "<<argv[0]<<" input file name"<<std::endl;
		return 1;
	}
	runFiles(string(argv[1]));
	return 0;
}
