#include <fstream>

#include "cat.h"

//constructor that takes in arguments
Cat::Cat(const std::string &n, float av_size, float av_lifeSpan, const std::string &interesting)
:name(n),average_size(av_lifeSpan),average_lifeSpan(av_lifeSpan)
{
	interestingFact = interesting.substr(0, interesting.size()-1);
}

//sort cats by breed name
bool sortCats(const Cat& c1, const Cat& c2)
{
	return c1.getBreed() < c2.getBreed();
}