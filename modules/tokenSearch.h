/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.

Knuth–Morris–Pratt algorithm used for single token search
Rabin-Karp algorithm used for multiple token search
*/

#ifndef __TOKEN__
#define __TOKEN__

#include <stdlib.h>
#include <string>
#include <algorithm>
#include "STRutil.h"
#include "difference.h"
#include "clean.h"
int RabinKarpSingle(std::string token, std::string searchstring);
void buildTable( int* V, const std::string& keyword);
Difference searchToken(const std::string& student, const std::string& token);
Tokens searchMultipleTokens(const std::string& student,
                            const std::string& tokens);
std::vector<std::string> splitTokens(const std::string& tokens);

/*A helper function that is used to construct a table for the keyword
in linear time with respect to the keyword given. This helper function
is used in the Knuth–Morris–Pratt token searching algorithm for single
tokens in order to eliminate redundant comparisons in the student string.
The expected arguments are an integer buffer the same size as the string
keyword and a keyword that accepts any ASCII character. The behavior
for the function with a buffer less than the size of the keyword is
not predictable and should not be used.*/
void buildTable( int* V, const std::string& keyword){
	int j = 0;

	//Table initialization
	V[0] = -1; V[1] = 0;
	for(unsigned int i = 2; i < keyword.size(); i++){
		if( keyword[i - 1] == keyword[j] ){
			j++;
			V[i] = j;
		} else if( j > 0 ){
			j = V[j];
			i--;
		} else {
			V[i] = 0;
		}
	}
}
/*searchToken looks for a token specified in the second argument in the
student output. The algorithm runs in linear time with respect to the 
length of the student output and preprocessing for the algorithm is
linear with respect to the token. Overall, the algorithm runs in O(N + M)
time where N is the length of the student and M is the length of the token.*/
Difference searchToken(const std::string& student, const std::string& token){
	
	//Build a table to use for the search
	Difference diff;
	diff.distance = 0;
	int V[token.size()];
	buildTable( V, token);

	int m = 0;
	int i = 0;
	while( m + i < student.size() ){
		if( student[i + m] == token[i] ){
			if( i == token.size() - 1 )
				return diff;
			i++;
		} else {
			m += i - V[i];
			if( V[i] == -1 )
				i = 0;
			else
				i = V[i];
		}
	}

	Change tmp;
	tmp.b_changes.push_back(0);
	tmp.a_start = tmp.b_start = 0;
	diff.changes.push_back(tmp);
	diff.distance = 1;
	return diff;
}
/*searchMultipleTokens looks for tokens delimited by newline characters in the 
student output. The algorithm runs in linear time with respect to the 
length of the student output and preprocessing for the algorithm is
linear with respect to the token. Overall, the algorithm runs in O(N + M)
time where N is the length of the student and M is the length of the token.*/
Tokens searchMultipleTokens(const std::string& student,
										 		const std::string& tokens){
	Tokens difference;
	std::vector<std::string> tokenlist;
	tokenlist=splitTokens(tokens);
	for (unsigned int i = 0; i<tokenlist.size(); i++)
	{
		difference.tokens.push_back(RabinKarpSingle(tokenlist[i], student));
	}
	for (unsigned int i = 0; i<difference.tokens.size(); i++)
	{
		if (difference.tokens[i]==-1)
		{
			difference.alltokensfound = false;
		}
	}
	return difference;
}

/*	Looks for a single token in a string using the Rabin-Karp rolling hash
	method.  Returns starting index if found, -1 if not.					*/
int RabinKarpSingle(std::string token, std::string searchstring)
{
	long hash = 0;
	long goalhash = 0;
	unsigned int tlen = (unsigned int)token.size();
	if (searchstring.size()<token.size())
	{
		return -1;
	}
	for (int i= 0; i<tlen; i++)		// Set up goal hash
	{
		goalhash += token[i];
	}
	for (int i = 0; i<tlen; i++)	// Set up first hash
	{
		hash+=searchstring[i];
	}
	for (int i = 0; i<=searchstring.size()-tlen; i++)
	{
		// Check if hashes then strings are equal, and if so return index
		if (hash==goalhash && searchstring.substr(i,tlen)==token)
		{
			return i;
		}
		hash+=searchstring[i+tlen];
		hash-=searchstring[i];
	}
	return -1;
}

std::vector<std::string> splitTokens(const std::string& tokens){
    std::vector<std::string> tokenlist;
	std::string tmpstr;                 // Create empty token variable

    // Start at 1 to avoid first double quote
    for (int i = 1;i<tokens.size(); i++){
        // If we're at a delimiter...
		if (tokens[i]=='\"' && tokens[i+1]=='\n' && tokens[i+2]=='\"')			{
			if (tmpstr!="")
			{
				tokenlist.push_back(tmpstr);
			}
			tmpstr.clear();
			i=i+2;						// Skip to end of said delimiter
		}
        else if ((tokens.size()-i==2) && tokens[i]=='\"' && tokens[i+1]=='\n'){
            if (tmpstr!="")
			{
				tokenlist.push_back(tmpstr);
			}
			tmpstr.clear();
            i=i+1;
        }
        else if ((tokens.size()-i==1) && tokens[i]=='\"'){
            if (tmpstr!="")
			{
				tokenlist.push_back(tmpstr);
			}
			tmpstr.clear();
        }

		else
		{
			tmpstr+=tokens[i];
		}
	}
    if (tmpstr!="")
    {
        tokenlist.push_back(tmpstr);
    }
    return tokenlist;
}


#endif //__TOKEN__
