/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <fstream>
#include "modules.h"

using std::cout; using std::endl; using std::cin; using std::string; using std::vector;
int main(int argc, const char * argv[])
{
    /*
    std::ofstream file_out;
    std::string file_name("out.txt");
    if (!file_out.good()) {
        std::cerr << "Can't open " << file_name << " to write.\n";
        file_out.close();
    }

    std::string one= ("is Tom in a button factory");
    std::string two= ("is Charlie in a mill shop");
    //std::string* one= new string("Hiellpo");
    //std::string* two= new string("Helvldko");
    
    //  Difference<std::string> text_diff=ses(&one, &two);
    
    vector<string> one_vector;
    one_vector.push_back("Why");
    one_vector.push_back("is");
    one_vector.push_back("Tom");
    one_vector.push_back("in");
    one_vector.push_back("a");
    one_vector.push_back("button");
    one_vector.push_back("factory");

    vector<string> two_vector;
   // two_vector.push_back("Dad,");
    two_vector.push_back("Why");
    two_vector.push_back("is");
    two_vector.push_back("Thomas");
    two_vector.push_back("in");
    two_vector.push_back("a");
    two_vector.push_back("mill");
    two_vector.push_back("shop");
    two_vector.push_back("factory");
     Difference< vector <string> > text_diff_2=ses(&one_vector, &two_vector, true);
    
    vector<string> three_vector;
    three_vector.push_back("Hello");
    three_vector.push_back("my");
    three_vector.push_back("name");
    three_vector.push_back("is");
    three_vector.push_back("charlie");
    three_vector.push_back("and");
    three_vector.push_back("I");
    three_vector.push_back("bit");
    three_vector.push_back("his");
    three_vector.push_back("finger");
    
    vector<string> four_vector;
    four_vector.push_back("Why");
    four_vector.push_back("did");
    four_vector.push_back("you");
    four_vector.push_back("do");
    four_vector.push_back("that,");
    four_vector.push_back("Charlie?");
    
    
    vector< vector <string> > vec_a;
    vec_a.push_back(one_vector);
    vec_a.push_back(two_vector);
    vec_a.push_back(one_vector);
    vec_a.push_back(three_vector);
    vec_a.push_back(four_vector);
    
    vector< vector <string> > vec_b;
    vec_b.push_back(one_vector);
    vec_b.push_back(two_vector);
    vec_b.push_back(two_vector);
    vec_b.push_back(three_vector);
    vec_b.push_back(four_vector);

    if (vec_a[1]==vec_b[2]) {
        //     cout<<"yes!"<<endl;
    }
    else{
        // cout<<"NO :("<<endl;
    }
    
    Difference<vector< vector <string> > > vec_diff=ses(&vec_a, &vec_b, true);

    printJSON(vec_diff, file_out);
    for (int a = 0; a<text_diff_2.changes.size(); a++) {
        for (int b=0; b<text_diff_2.changes[a].a_changes.size(); b++) {
            cout<<one_vector[text_diff_2.changes[a].a_changes[b]]<<endl;
        }
        cout<<"-------------------"<<endl;
        for (int b=0; b<text_diff_2.changes[a].b_changes.size(); b++) {
            cout<<two_vector[text_diff_2.changes[a].b_changes[b]]<<endl;
        }
        cout<<endl<<"||||||||||||||||||||"<<endl<<endl;

    }
    file_out.close();
    
    int a =0;
     
    // Errors if bad arguments /////////////////////////////////////////////////////////////////
    
     */
     // input1.txt
    /*
    Difference object;
    Change c2;
    c2.a_changes.push_back(2);
    c2.a_characters.push_back(std::vector<int>());
    c2.a_changes.push_back(4);
    std::vector<int> c2_int;
    c2_int.push_back(2);
    c2_int.push_back(4);
    c2_int.push_back(8);
    c2_int.push_back(16);
    c2.a_characters.push_back(std::vector<int>(c2_int));
    c2.a_changes.push_back(6);
    c2.a_characters.push_back(std::vector<int>());
    c2.a_changes.push_back(8);
    std::vector<int> c2_int2;
    c2_int2.push_back(32);
    c2_int2.push_back(64);
    c2_int2.push_back(128);
    c2_int2.push_back(256);
    c2.a_characters.push_back(std::vector<int>(c2_int2));

    Change c;
    c.a_changes.push_back(10);
    std::vector<int> c_int;
    c_int.push_back(20);
    c_int.push_back(40);
    c_int.push_back(60);
    c_int.push_back(80);
    c.a_characters.push_back(std::vector<int>(c_int));
    c.a_characters.push_back(std::vector<int>());
    c.a_changes.push_back(12);
    c.a_changes.push_back(14);
    c.a_characters.push_back(std::vector<int>());
    c.a_changes.push_back(16);
    std::vector<int> c_int2;
    c_int2.push_back(100);
    c_int2.push_back(120);
    c_int2.push_back(140);
    c_int2.push_back(160);
    c.a_characters.push_back(std::vector<int>(c_int2));

    c2.b_changes.push_back(1);
    c2.b_characters.push_back(std::vector<int>());
    c2.b_changes.push_back(3);
    std::vector<int> c2_intb;
    c2_intb.push_back(10);
    c2_intb.push_back(30);
    c2_intb.push_back(50);
    c2_intb.push_back(70);
    c2.b_characters.push_back(std::vector<int>(c2_intb));
    c2.b_changes.push_back(5);
    c2.b_characters.push_back(std::vector<int>());
    c2.b_changes.push_back(7);
    std::vector<int> c2_int2b;
    c2_int2b.push_back(90);
    c2_int2b.push_back(110);
    c2_int2b.push_back(130);
    c2_int2b.push_back(150);
    c2.b_characters.push_back(std::vector<int>(c2_int2b));

    c.b_changes.push_back(9);
    c.b_characters.push_back(std::vector<int>());
    c.b_changes.push_back(11);
    c.b_changes.push_back(13);
    c.b_characters.push_back(std::vector<int>());
    std::vector<int> c_intb;
    c_intb.push_back(170);
    c_intb.push_back(190);
    c_intb.push_back(210);
    c_intb.push_back(230);
    c.b_characters.push_back(std::vector<int>(c_intb));
    c.b_changes.push_back(15);
    std::vector<int> c_int2b;
    c_int2b.push_back(250);
    c_int2b.push_back(270);
    c_int2b.push_back(290);
    c_int2b.push_back(310);
    c.b_characters.push_back(std::vector<int>(c_int2b));


    std::ofstream file_out;
    file_out.open(("out.json")); //edit the name to add _out
    if (!file_out.good()) {
        std::cerr << "Can't open to write.\n";
        file_out.close();
    }
    object.changes.push_back(c);
    object.changes.push_back(c2);

    object.printJSON(file_out, 3);
    */

    std::vector <std::string> text;
    text.push_back("hello I am");
    text.push_back("going to say");
    text.push_back("hello thanks");
    text.push_back("because why not say it");

    for (int a=0; a<text.size(); a++) {
        std::cout<<text[a]<<std::endl;
    }
    std::cout<<std::endl;
    std::vector <std::string> output=includelines("hello", text);
    for (int a=0; a<output.size(); a++) {
        std::cout<<output[a]<<std::endl;
    }
    std::cout<<std::endl;
    output=includelines("\"hello\"\n\"am\"", text);
    for (int a=0; a<output.size(); a++) {
        std::cout<<output[a]<<std::endl;
    }

    std::cout<<std::endl;
    output=excludelines("hello", text);
    for (int a=0; a<output.size(); a++) {
        std::cout<<output[a]<<std::endl;
    }
    std::cout<<std::endl;
    output=excludelines("\"hello\"\n\"am\"", text);
    for (int a=0; a<output.size(); a++) {
        std::cout<<output[a]<<std::endl;
    }
    std::cout<<std::endl;





//    if (argc != 2) {
//        std::cerr << "Usage: " << argv[0] << " input file name"<<std::endl<<std::endl;
//        return 1;
//    }
//    runFiles(string(argv[1]));

    return 0;
}

