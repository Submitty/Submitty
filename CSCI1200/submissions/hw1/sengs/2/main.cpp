//
//  main.cpp
//  hw1
//
//  Created by Kiana on 1/24/14.
//  Copyright (c) 2014 Kiana. All rights reserved.
//

#include <iostream>
#include <string>
#include <iomanip>
#include <cmath>
#include <vector>
#include <cstdlib>
//#include <cstdio>
#include <cctype>
#include <fstream>


void parse(const int, std::vector<std::string> &, std::vector<std::vector<std::string> > &);
/*
void right(const int, const std::vector<std::string> &, std::ofstream &);
void left(const int, const std::vector<std::string> &, std::ofstream &);
void full(const int, const std::vector<std::string> &, std::ofstream &);
*/

void right2(const int, std::ofstream &, const std::vector<std::vector<std::string> > &);
void left2( const int, std::ofstream &, const std::vector<std::vector<std::string> > &);
void full2( const int, std::ofstream &, const std::vector<std::vector<std::string> > &);


int main(int argc, const char * argv[])
{
    int len;
    std::string align;
    //ifstream file_in
    //ofstream file_out
    std::vector<std::string> text;
    //    std::cout<<argc<<std::endl;
   /*
    for (int a=0; a<argc; a++)
    {
        std::cout<<"->"<<argv[a]<<"<--"<<std::endl;
    }
    */
    
    if (argc != 5) {
        std::cerr << "Usage: " << argv[0] << "input file name, output file name, length of line, justification type\n";
        return 1;
    }
    std::string temp(argv[3]);
    for (int a=0; a<(temp.length()); a++)
    {
        if (!isdigit(temp[a])) {//if whitespace, x
            std::cerr << "Length of line \"" << temp << "\" must be integer\n";
            exit(1);
        }
    }
    
    len=atoi(argv[3]);
    
    if (!(len>1)) {
        std::cerr << "Length of line \"" << argv[3] << "\" must be greater than 1\n";
        exit(1);
    }
    
    align=argv[4];
    for (int a=0; a<align.length(); a++) {
        align[a]=std::tolower(align[a]);
    }
    
    if (align!="flush_left" && align!="flush_right"&&align!="full_justify") {
        std::cerr << "Justification type: " << argv[4] << " must be either 'flush_left', 'flush_right', or 'full_justify' \n";
        exit(1);
    }
    
    std::ifstream file_in(argv[1]);
    if (!file_in.good()) {
        std::cerr << "Can't open " << argv[1] << " to read.\n";
        exit(1);
    }
    
    std::ofstream file_out;
    file_out.open(argv[2]);
    if (!file_out.good()) {
        std::cerr << "Can't open " << argv[2] << " to write.\n";
        exit(1);
    }

    
    std::string word;
    while (file_in >> word) {
        if (word!="/n" && word!="/r/n"&& word!="/r"&& word!="/n/r"&& word!=" ") {
            text.push_back(word);
        }
    }
    std::vector<std::vector<std::string> > block;
    
    parse(len, text, block);
    
    if (align=="flush_left") {
        left2(len, file_out, block);
    }
    else if (align=="flush_right") {
        right2(len, file_out, block);
    }
    else if (align=="full_justify") {
        full2(len, file_out, block);
    }
    
    file_in.close();
    file_out.close();
    
}

void parse(const int  len , std::vector<std::string> & text, std::vector<std::vector<std::string> > & block)
{
    int word=0;
    int letter=0;
    std::vector<std::string> seg;
    
    while (word<text.size()) {
        //Basic case- the word is small enough to fit in the line without a hyphen
        if (text[word].length()<(len-letter+1)) {
            seg.push_back(text[word]);
            letter+=text[word].length();
            
            if (letter<len) {
                letter++;
            }
            word++;
        }
        else if (letter==0)
            //Second case- the word is too large to fit in the line without a hyphen,
            //    it starts on a new line and continues untill 1 character before end, and adds the hyphen.
            //    It then continues on as many new lines as neccesary, and resumes normal operation afterward
        {
            std::string frag;
            frag=text[word].substr(0,len-1);
            letter+=len-1;
            
            int b=(len-1);
            while ( b<text[word].length() ) {
                
                frag+='-';
                seg.push_back(frag);
                frag.clear();
                block.push_back(seg);
                seg.clear();
                letter=0;
                
                frag=text[word].substr(b,len-1);
                letter+=frag.length();
                b+=frag.length();
            }
            
            seg.push_back(frag);
            frag.clear();
            
            if (letter<len) {
                letter++;
            }
            word++;
            
        }
        else
            //The line can't fit the next word, start a new line
        {
            //std::cout<<"3"<<std::endl;
            block.push_back(seg);
            letter=0;
            seg.clear();
        }
    }
    block.push_back(seg);
    
}

void left2(const int  len , std::ofstream & file_out, const std::vector<std::vector<std::string> > & block)
{
    int letter=0;
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
    for (int a=0; a<(block.size()); a++) {
        std::vector<char> line(len, ' ');
        letter=0;
        if (block[a].size()==1) {
            for (int b=0; b<block[a][0].length(); b++) {
                line[letter]=block[a][0][b];
                letter++;
            }
        }
        else{
            
            for (int b=0; b<block[a].size(); b++) {
                for (int c=0; c<block[a][b].length(); c++) {
                    line[letter]=block[a][b][c];
                    letter++;
                }
                if (letter==len) {
                    
                }
                else {
                    line[letter]=' ';
                    letter++;
                }
            }
        }
        
        file_out<<"| ";
        for (int b=0; b<len; b++) {
            file_out<<line[b];
        }
        file_out<<" |";
        file_out<<std::endl;
    }
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
}

void right2(const int  len , std::ofstream & file_out, const std::vector<std::vector<std::string> > & block)
{
    int letter=0;
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
    for (int a=0; a<(block.size()); a++) {
        std::vector<char> line(len, ' ');
        letter=0;
        if (block[a].size()==1) {
            int size=0;
            size=int(block[a][0].length());
            
            int spaces=((len-size)-(int(block[a].size()-1)));
            letter=spaces;

            for (int b=0; b<block[a][0].length(); b++) {
                line[letter]=block[a][0][b];
                letter++;
            }
        }
        else{
            int size=0;
            for (int b=0; b<block[a].size(); b++) {
                size+=block[a][b].length();
            }
            int spaces=((len-size)-(int(block[a].size()-1)));
            letter=spaces;
            for (int b=0; b<block[a].size(); b++) {
                for (int c=0; c<block[a][b].length(); c++) {
                    line[letter]=block[a][b][c];
                    letter++;
                }
                if (letter==len) {
                    
                }
                else {
                    line[letter]=' ';
                    letter++;
                }
            }
        }
        
        file_out<<"| ";
        for (int b=0; b<len; b++) {
            file_out<<line[b];
        }
        file_out<<" |";
        file_out<<std::endl;
    }
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
}

void full2(const int  len , std::ofstream & file_out, const std::vector<std::vector<std::string> > & block)
{
    int letter=0;
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
    for (int a=0; a<(block.size()-1); a++) {
        std::vector<char> line(len, ' ');
        letter=0;
        if (block[a].size()==1) {
            for (int b=0; b<block[a][0].length(); b++) {
                line[letter]=block[a][0][b];
                letter++;
            }
        }
        else{
            
            int size=0;
            for (int b=0; b<block[a].size(); b++) {
                size+=block[a][b].length();
            }
            int spaces=len-size;
            int each=spaces/(block[a].size()-1);
            int extra=spaces%(block[a].size()-1);
            //  std::cout<<spaces<<" "<<each<<" "<<extra<<std::endl;
            for (int b=0; b<block[a].size(); b++) {
                for (int c=0; c<block[a][b].length(); c++) {
                    line[letter]=block[a][b][c];
                    letter++;
                }
                if (letter==len) {
                    
                }
                else if ((b)<extra) {
                    for (int d=0; d<each+1; d++) {
                        line[letter]=' ';
                        letter++;
                    }
                }
                else
                {
                    for (int d=0; d<each; d++) {
                        line[letter]=' ';
                        letter++;
                    }
                }
            }
        }
        
        file_out<<"| ";
        for (int b=0; b<len; b++) {
            file_out<<line[b];
        }
        file_out<<" |";
        file_out<<std::endl;
    }
    file_out<<"| ";
    letter=0;
    for (int b=0; b<block[block.size()-1].size(); b++) {
        file_out<<block[block.size()-1][b];
        file_out<<' ';
        letter+=block[block.size()-1][b].length()+1;
        
    }
    for (int b=letter; b<len; b++) {
        file_out<<' ';
        letter++;
    }
    
    file_out<<" |";
    file_out<<std::endl;
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
}

/*
void left(const int & len , const std::vector<std::string> & text, std::ofstream & file_out)
//Prints text in left justified rectangle of line length len to the file specified in file_out
{
    int word=0;
    int letter=0;
    std::vector<std::vector<char> > block;
    std::vector<char> line(len, ' ');
    
    while (word<text.size()) {
        //Basic case- the word is small enough to fit in the line without a hyphen
        if (text[word].length()<(len-letter+1)) {
            // std::cout<<"1"<<std::endl;
            for (int a=0; a<text[word].length(); a++) {
                line[letter]=text[word][a];
                letter++;
            }
            if (letter<len) {
                line[letter]=' ';
                letter++;
            }
            word++;
        }
        else if (letter==0)
        //Second case- the word is too large to fit in the line without a hyphen,
        //    it starts on a new line and continues untill 1 character before end, and adds the hyphen.
        //    It then continues on as many new lines as neccesary, and resumes normal operation afterward
        {
            //std::cout<<"2"<<std::endl;
            for (int a=0; a<(len-1); a++) {
                line[letter]=text[word][a];
                letter++;
            }
            for (int b=(len-1); b<text[word].length(); b++) {
                line[letter]=('-');
                block.push_back(line);
                letter=0;
                for (int i=0; i<line.size(); i++) {
                    line[i]=' ';
                }
                for (int a=0; a<(len-1) && b<text[word].length(); a++) {
                    line[letter]=(text[word][b]);
                    letter++;
                    b++;
                }
            }
            if (letter<len) {
                line[letter]=' ';
                letter++;
            }
            word++;

        }
        else
            //The line can't fit the next word, start a new line
        {
            //std::cout<<"3"<<std::endl;
            block.push_back(line);
            letter=0;
            for (int i=0; i<line.size(); i++) {
                line[i]=' ';
            }
        }
    }
    block.push_back(line);
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
    for (int a=0; a<block.size(); a++) {
        file_out<<"| ";
        for (int b=0; b<block[a].size(); b++) {
            file_out<<block[a][b];
        }
        file_out<<" |";
        file_out<<std::endl;
    }
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
}

void right(const int & len , const std::vector<std::string> & text, std::ofstream & file_out)
//Prints text in right justified rectangle of line length len to the file specified in file_out
{
    
        int word=0;
        int letter=0;
        std::vector<std::vector<char> > block;
        std::vector<char> line;
        
        while (word<text.size()) {
            //Basic case- the word is small enough to fit in the line without a hyphen
            if (text[word].length()<(len-letter)) {
                // std::cout<<"1"<<std::endl;
                if (letter<len && letter>0) {
                    line.push_back(' ');
                    letter++;
                }
                for (int a=0; a<text[word].length(); a++) {
                    line.push_back(text[word][a]);
                    letter++;
                }
                word++;
            }
            else if (letter==0)
            {
                //Second case- the word is too large to fit in the line without a hyphen,
                //    it starts on a new line and continues untill 1 character before end, and adds the hyphen.
                //    It then continues on as many new lines as neccesary, and resumes normal operation afterward
            
                //std::cout<<"2"<<std::endl;
                if (letter<len && letter>0) {
                    line.push_back(' ');
                    letter++;
                }
                for (int a=0; a<(len-1); a++) {
                    line.push_back(text[word][a]);
                    letter++;
                }
                for (int b=(len-1); b<text[word].length(); b++) {
                    line.push_back('-');
                    block.push_back(line);
                    letter=0;
                    line.clear();
                    for (int a=0; a<(len-1) && b<text[word].length(); a++) {
                        line.push_back(text[word][b]);
                        letter++;
                        b++;
                    }
                }
                
                word++;
                
            }
            else
            {
                //The line can't fit the next word, start a new line
            
                //std::cout<<"3"<<std::endl;
                block.push_back(line);
                letter=0;
                line.clear();
            }
        }
        block.push_back(line);
        for (int a=0; a<len+4; a++) {
            file_out<<'-';
        }
        file_out<<std::endl;
        for (int a=0; a<block.size(); a++) {
            file_out<<"| ";
            for (int b=int(block[a].size()); b<len; b++) {
                file_out<<' ';
            }
            for (int c=0; c<block[a].size(); c++) {
                file_out<<block[a][c];
            }
            file_out<<" |";
            file_out<<std::endl;
        }
        for (int a=0; a<len+4; a++) {
            file_out<<'-';
        }
        file_out<<std::endl;

}

void full(const int & len , const std::vector<std::string> & text, std::ofstream & file_out)
{
    int word=0;
    int letter=0;
    std::vector<std::vector<std::string> > block;
    std::vector<std::string> seg;
    
    while (word<text.size()) {
        //Basic case- the word is small enough to fit in the line without a hyphen
        if (text[word].length()<(len-letter+1)) {
                seg.push_back(text[word]);
                letter+=text[word].length();
            
            if (letter<len) {
                letter++;
            }
            word++;
        }
        else if (letter==0)
            //Second case- the word is too large to fit in the line without a hyphen,
            //    it starts on a new line and continues untill 1 character before end, and adds the hyphen.
            //    It then continues on as many new lines as neccesary, and resumes normal operation afterward
        {
            std::string frag;
            for (int a=0; a<(len-1); a++) {
                frag+=text[word][a];
                letter++;
            }
            for (int b=(len-1); b<text[word].length(); ) {
                frag+='-';
                seg.push_back(frag);
                frag.clear();
                block.push_back(seg);
                seg.clear();
                letter=0;
                
                for (int a=0; a<(len-1) && b<text[word].length(); a++) {
                    frag+=(text[word][b]);
                    letter++;
                    b++;
                }
            }

            seg.push_back(frag);
            frag.clear();
            
            if (letter<len) {
                letter++;
            }
            word++;
            
        }
        else
            //The line can't fit the next word, start a new line
        {
            //std::cout<<"3"<<std::endl;
            block.push_back(seg);
            letter=0;
            seg.clear();
        }
    }
    block.push_back(seg);
    
    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
    for (int a=0; a<(block.size()-1); a++) {
        std::vector<char> line(len, ' ');
        letter=0;
        if (block[a].size()==1) {
            for (int b=0; b<block[a][0].length(); b++) {
                line[letter]=block[a][0][b];
                letter++;
            }
        }
        else{

        int size=0;
        for (int b=0; b<block[a].size(); b++) {
            size+=block[a][b].length();
        }
        int spaces=len-size;
        int each=spaces/(block[a].size()-1);
        int extra=spaces%(block[a].size()-1);
            //  std::cout<<spaces<<" "<<each<<" "<<extra<<std::endl;
        for (int b=0; b<block[a].size(); b++) {
            for (int c=0; c<block[a][b].length(); c++) {
                line[letter]=block[a][b][c];
                letter++;
            }
            if (letter==len) {
                
            }
            else if ((b)<extra) {
                for (int d=0; d<each+1; d++) {
                    line[letter]=' ';
                    letter++;
                }
            }
            else
            {
                for (int d=0; d<each; d++) {
                    line[letter]=' ';
                    letter++;
                }
            }
        }
        }
        
        file_out<<"| ";
        for (int b=0; b<len; b++) {
            file_out<<line[b];
        }
        file_out<<" |";
        file_out<<std::endl;
    }
    file_out<<"| ";
    letter=0;
    for (int b=0; b<block[block.size()-1].size(); b++) {
        file_out<<block[block.size()-1][b];
        file_out<<' ';
        letter+=block[block.size()-1][b].length()+1;
        
    }
    for (int b=letter; b<len; b++) {
        file_out<<' ';
        letter++;
    }

    file_out<<" |";
    file_out<<std::endl;

    for (int a=0; a<len+4; a++) {
        file_out<<'-';
    }
    file_out<<std::endl;
}
*/





