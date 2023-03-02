#ifndef _cat_h_
#define _cat_h_

#include <string>

class Cat
{
	public:
		Cat();
        // pass string by reference to avoid copy
		Cat(const std::string &n, float av_size, float av_lifeSpan,const std::string &interesting);

		//get functions
        // change return type to be const for more security, reference to avoid copy
		const std::string &getBreed() const { return  name; }
		float getAverageSize() const { return average_size; }
		float getAverageLifeSpan() const { return average_lifeSpan; }
		const std::string &getInterestingFact() const { return interestingFact; }

	private:
		std::string name;
		float average_size;
		float average_lifeSpan;
		std::string interestingFact;
};

bool sortCats(const Cat& c1, const Cat& c2);

#endif