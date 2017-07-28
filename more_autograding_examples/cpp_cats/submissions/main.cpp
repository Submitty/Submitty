#include <iostream>
#include <fstream>
#include <sstream>
#include <algorithm>
#include <vector>
#include <string>

#include "cat.h"

float average(std::string s);
void readin(std::ifstream &in, std::vector<Cat> &cats);
void print(std::ofstream &out, std::vector<Cat> &cats);
void print_extraLines(std::ofstream &out, std::vector<Cat> &cats);
void print_extraSpaces(std::ofstream &out, std::vector<Cat> &cats);
void print_lineOrder(std::ofstream &out, const std::vector<Cat> &cats);
void print_frontSpacing(std::ofstream &out, std::vector<Cat> &cats);
void print_columnSpacing(std::ofstream &out, std::vector<Cat> &cats);

//========================================================================================================

int main(int argc, char* argv[])
{
	//check command line arguments
	if(argc != 3)
	{
		std::cerr << "Wrong number of command line arguments" << std::endl;
		exit(1);
	}

	//open input stream
	std::ifstream in(argv[1]);
	if(!in.good())
	{
		std::cerr << "Error opening input file" << std::endl;
		exit(1);
	}

	//open output stream
	std::ofstream out(argv[2]);
	if(!out.good())
	{
		std::cerr << "Error opening output file" << std::endl;
	}

	std::vector<Cat> cats;

	readin(in, cats);

	//call a print function
	
	//print(out, cats);
	//print_extraLines(out, cats);
	//print_extraSpaces(out, cats);
	//print_lineOrder(out, cats);
	//print_frontSpacing(out, cats);
	print_columnSpacing(out, cats);

	return 0;
}

//============================================================================================================

//extracts the lower and upper of the range in the string and averages them
float average(std::string s)
{
	//get lower value of the range
	std::size_t dash = s.find('-');
	std::size_t start = 0;
	std::string lowerValue = s.substr(start, dash);

	//get upper value of the range
	start = dash+1;
	std::string upperValue = s.substr(start);

	//convert strings to ints
	int lower;
	int upper;
	std::istringstream(lowerValue) >> lower;
	std::istringstream(upperValue) >> upper;

	return (float)(upper + lower) / 2;
}

//read in from the input file
void readin(std::ifstream &in, std::vector<Cat> &cats)
{
	std::string breed, lifeSpan, weight;
	std::string interesting = "";
	std::string s;
	std::string classifier;
	std::string unit;
	
	in >> classifier;

	while(!in.eof())
	{
		//take in the breed, life span as a string, weight as a string
		in >> breed;
		in >> classifier >> lifeSpan >> unit;
		in >> classifier >> weight >> unit;
		in >> classifier >> s;

		//take in  the interesting fact about the cat breed
		while(!in.eof() && s != "BREED:")
		{
			interesting += s + " ";
			in >> s;
		}

		//get the average from the range of values
		float weightAverage = average(weight);
		float lifeSpanAverage = average(lifeSpan);

		cats.push_back(Cat(breed, lifeSpanAverage, weightAverage, interesting));

		interesting = "";
	}
}

//---------------------------------------------------------------------------------------------------------------------------
//functions to print

//print the output as expected
void print(std::ofstream &out, std::vector<Cat> &cats)
{
	std::sort(cats.begin(), cats.end(), sortCats);
	std::string spacing = "     ";
	for(unsigned int i = 0; i < cats.size(); i++)
	{
		out << cats[i].getBreed() << std::endl;
		out << spacing << "Average Lifespan: " << cats[i].getAverageLifeSpan() << std::endl;
		out << spacing << "Average Weight:   " << cats[i].getAverageSize() << std::endl;
		out << spacing << "Intersting Fact:  " << cats[i].getInterestingFact() << std::endl;
	}
}

//print the output with extra newlines at the end of every line
void print_extraLines(std::ofstream &out, std::vector<Cat> &cats)
{
	std::sort(cats.begin(), cats.end(), sortCats);
	std::string spacing = "     ";
	for(unsigned int i = 0; i < cats.size(); i++)
	{
		out << cats[i].getBreed() << std::endl << std::endl;
		out << spacing << "Average Lifespan: " << cats[i].getAverageLifeSpan() 
		    << std::endl << std::endl;
		out << spacing << "Average Weight:   " << cats[i].getAverageSize() 
		    << std::endl << std::endl;
		out << spacing << "Intersting Fact:  " << cats[i].getInterestingFact() 
		    << std::endl << std::endl;
	}
}

//print the output with extra spaces at the end of every line
void print_extraSpaces(std::ofstream &out, std::vector<Cat> &cats)
{
	std::sort(cats.begin(), cats.end(), sortCats);
	std::string spacing = "     ";
	for(unsigned int i = 0; i < cats.size(); i++)
	{
		out << cats[i].getBreed() << " " << std::endl;
		out << spacing << "Average Lifespan: " << cats[i].getAverageLifeSpan() 
		    << " " << std::endl;
		out << spacing << "Average Weight:   " << cats[i].getAverageSize() 
		    << " " << std::endl;
		out << spacing << "Intersting Fact:  " << cats[i].getInterestingFact() 
		    << " " << std::endl;
	}
}

//print the output with the lines in the wrong order
void print_lineOrder(std::ofstream &out, const std::vector<Cat> &cats)
{
	std::string spacing = "     ";
	for(unsigned int i = 0; i < cats.size(); i++)
	{
		out << cats[i].getBreed() << std::endl;
		out << spacing << "Average Lifespan: " << cats[i].getAverageLifeSpan() << std::endl;
		out << spacing << "Average Weight:   " << cats[i].getAverageSize() << std::endl;
		out << spacing << "Intersting Fact:  " << cats[i].getInterestingFact() << std::endl;
	}
}

//print the output with the spacing in front off (expected: 5 spaces)
void print_frontSpacing(std::ofstream &out, std::vector<Cat> &cats)
{
	std::sort(cats.begin(), cats.end(), sortCats);
	std::string spacing = "   ";
	for(unsigned int i = 0; i < cats.size(); i++)
	{
		out << cats[i].getBreed() << std::endl;
		out << spacing << "Average Lifespan: " << cats[i].getAverageLifeSpan() << std::endl;
		out << spacing << "Average Weight:   " << cats[i].getAverageSize() << std::endl;
		out << spacing << "Intersting Fact:  " << cats[i].getInterestingFact() << std::endl;
	}
}

//print the output with the column spacing off
//(expected: 1 space between 1st column and 2nd, 1st column the length of the longest attribute)
void print_columnSpacing(std::ofstream &out, std::vector<Cat> &cats)
{
	std::sort(cats.begin(), cats.end(), sortCats);
	std::string spacing = "     ";
	for(unsigned int i = 0; i < cats.size(); i++)
	{
		out << cats[i].getBreed() << std::endl;
		out << spacing << "Average Lifespan: " << cats[i].getAverageLifeSpan() << std::endl;
		out << spacing << "Average Weight: " << cats[i].getAverageSize() << std::endl;
		out << spacing << "Intersting Fact: " << cats[i].getInterestingFact() << std::endl;
	}
}