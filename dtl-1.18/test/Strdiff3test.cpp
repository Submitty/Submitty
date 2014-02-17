#include "dtl_test_common.hpp"
#include "comparators.hpp"

class Strdiff3test : public ::testing::Test
{
protected :
    dtl_test_typedefs(char, string)
    typedef struct case_t {
        sequence S;
        bool     is_merge_success;
        sequence merged_seq;
    } case_t;
    typedef vector< case_t > caseVec;
    
    caseVec merge_cases;
    caseVec detect_cases;
    caseVec custom_cases;
    
    template < typename comparator >
    case_t createCase (sequence a, sequence b, sequence c, sequence s) {
        Diff3< elem, sequence, comparator > diff3(a, b, c);
        case_t ct;

        diff3.compose();

        ct.S                = s;
        ct.is_merge_success = diff3.merge();
        ct.merged_seq       = diff3.getMergedSequence();
        return ct;
    }
    
    void SetUp() {
        // merge test
        merge_cases.push_back(createCase< Compare < elem > >("ab",            "b",             "bc",           "abc"));              // 0
        merge_cases.push_back(createCase< Compare < elem > >("bc",            "b",             "ab",           "abc"));              // 1
        merge_cases.push_back(createCase< Compare < elem > >("qqqabc",        "abc",           "abcdef",       "qqqabcdef"));        // 2
        merge_cases.push_back(createCase< Compare < elem > >("abcdef",        "abc",           "qqqabc",       "qqqabcdef"));        // 3
        merge_cases.push_back(createCase< Compare < elem > >("aaacccbbb",     "aaabbb",        "aaabbbqqq",    "aaacccbbbqqq"));     // 4
        merge_cases.push_back(createCase< Compare < elem > >("aaabbbqqq",     "aaabbb",        "aaacccbbb",    "aaacccbbbqqq"));     // 5
        merge_cases.push_back(createCase< Compare < elem > >("aeaacccbbb",    "aaabbb",        "aaabbbqqq",    "aeaacccbbbqqq"));    // 6
        merge_cases.push_back(createCase< Compare < elem > >("aaabbbqqq",     "aaabbb",        "aeaacccbbb",   "aeaacccbbbqqq"));    // 7
        merge_cases.push_back(createCase< Compare < elem > >("aeaacccbbb",    "aaabbb",        "aaabebbqqq",   "aeaacccbebbqqq"));   // 8
        merge_cases.push_back(createCase< Compare < elem > >("aaabebbqqq",    "aaabbb",        "aeaacccbbb",   "aeaacccbebbqqq"));   // 9
        merge_cases.push_back(createCase< Compare < elem > >("aaacccbbb",     "aaabbb",        "aeaabbbqqq",   "aeaacccbbbqqq"));    // 10
        merge_cases.push_back(createCase< Compare < elem > >("aeaabbbqqq",    "aaabbb",        "aaacccbbb",    "aeaacccbbbqqq"));    // 11
        merge_cases.push_back(createCase< Compare < elem > >("aaacccbbb",     "aaabbb",        "aaabeebbeeqqq","aaacccbeebbeeqqq")); // 12
        merge_cases.push_back(createCase< Compare < elem > >("aaabeebbeeqqq", "aaabbb",        "aaacccbbb",    "aaacccbeebbeeqqq")); // 13
        merge_cases.push_back(createCase< Compare < elem > >("aiueo",         "aeo",           "aeKokaki",     "aiueKokaki"));       // 14
        merge_cases.push_back(createCase< Compare < elem > >("aeKokaki",      "aeo",           "aiueo",        "aiueKokaki"));       // 15
        merge_cases.push_back(createCase< Compare < elem > >("1234567390",    "1234567890",    "1239567890",   "1239567390"));       // 16
        merge_cases.push_back(createCase< Compare < elem > >("1239567890",    "1234567890",    "1234567390",   "1239567390"));       // 17
        merge_cases.push_back(createCase< Compare < elem > >("qabcdef",       "abcdef",        "ab",           "qab"));              // 18
        merge_cases.push_back(createCase< Compare < elem > >("ab",            "abcdef",        "qabcdef",      "qab"));              // 19
        merge_cases.push_back(createCase< Compare < elem > >("abcdf",         "abcdef",        "acdef",        "acdf"));             // 20
        merge_cases.push_back(createCase< Compare < elem > >("acdef",         "abcdef",        "abcdf",        "acdf"));             // 21
        merge_cases.push_back(createCase< Compare < elem > >("acdef",         "abcdef",        "abcdfaa",      "acdfaa"));           // 22
        merge_cases.push_back(createCase< Compare < elem > >("abcdfaa",       "abcdef",        "acdef",        "acdfaa"));           // 23
        
        // detect confliction test
        detect_cases.push_back(createCase< Compare < elem > >("adc",           "abc",          "aec",          ""));                 // 0
        detect_cases.push_back(createCase< Compare < elem > >("abqdcf",        "abcdef",       "abqqef",       ""));                 // 1
        
        // use custom comparator
        custom_cases.push_back(createCase< CaseInsensitive >("abc", "abc", "abC", "abc"));
    }
    
    void TearDown () {}
    
};

/**
 * Strdiff3test
 * check list is following
 * - merge function
 * - detect confliction
 */
TEST_F (Strdiff3test, merge_test0) {
    ASSERT_TRUE(merge_cases[0].is_merge_success);
    ASSERT_EQ(merge_cases[0].S, merge_cases[0].merged_seq);
}

TEST_F (Strdiff3test, merge_test1) {
    ASSERT_TRUE(merge_cases[1].is_merge_success);
    ASSERT_EQ(merge_cases[1].S, merge_cases[1].merged_seq);
}

TEST_F (Strdiff3test, merge_test2) {
    ASSERT_TRUE(merge_cases[2].is_merge_success);
    ASSERT_EQ(merge_cases[2].S, merge_cases[2].merged_seq);
}

TEST_F (Strdiff3test, merge_test3) {
    ASSERT_TRUE(merge_cases[3].is_merge_success);
    ASSERT_EQ(merge_cases[3].S, merge_cases[3].merged_seq);
}

TEST_F (Strdiff3test, merge_test4) {
    ASSERT_TRUE(merge_cases[4].is_merge_success);
    ASSERT_EQ(merge_cases[4].S, merge_cases[4].merged_seq);
}

TEST_F (Strdiff3test, merge_test5) {
    ASSERT_TRUE(merge_cases[5].is_merge_success);
    ASSERT_EQ(merge_cases[5].S, merge_cases[5].merged_seq);
}

TEST_F (Strdiff3test, merge_test6) {
    ASSERT_TRUE(merge_cases[6].is_merge_success);
    ASSERT_EQ(merge_cases[6].S, merge_cases[6].merged_seq);
}

TEST_F (Strdiff3test, merge_test7) {
    ASSERT_TRUE(merge_cases[7].is_merge_success);
    ASSERT_EQ(merge_cases[7].S, merge_cases[7].merged_seq);
}

TEST_F (Strdiff3test, merge_test8) {
    ASSERT_TRUE(merge_cases[8].is_merge_success);
    ASSERT_EQ(merge_cases[8].S, merge_cases[8].merged_seq);
}

TEST_F (Strdiff3test, merge_test9) {
    ASSERT_TRUE(merge_cases[9].is_merge_success);
    ASSERT_EQ(merge_cases[9].S, merge_cases[9].merged_seq);
}

TEST_F (Strdiff3test, merge_test10) {
    ASSERT_TRUE(merge_cases[10].is_merge_success);
    ASSERT_EQ(merge_cases[10].S, merge_cases[10].merged_seq);
}

TEST_F (Strdiff3test, merge_test11) {
    ASSERT_TRUE(merge_cases[11].is_merge_success);
    ASSERT_EQ(merge_cases[11].S, merge_cases[11].merged_seq);
}

TEST_F (Strdiff3test, merge_test12) {
    ASSERT_TRUE(merge_cases[12].is_merge_success);
    ASSERT_EQ(merge_cases[12].S, merge_cases[12].merged_seq);
}

TEST_F (Strdiff3test, merge_test13) {
    ASSERT_TRUE(merge_cases[13].is_merge_success);
    ASSERT_EQ(merge_cases[13].S, merge_cases[13].merged_seq);
}

TEST_F (Strdiff3test, merge_test14) {
    ASSERT_TRUE(merge_cases[14].is_merge_success);
    ASSERT_EQ(merge_cases[14].S, merge_cases[14].merged_seq);
}

TEST_F (Strdiff3test, merge_test15) {
    ASSERT_TRUE(merge_cases[15].is_merge_success);
    ASSERT_EQ(merge_cases[15].S, merge_cases[15].merged_seq);
}

TEST_F (Strdiff3test, merge_test16) {
    ASSERT_TRUE(merge_cases[16].is_merge_success);
    ASSERT_EQ(merge_cases[16].S, merge_cases[16].merged_seq);
}

TEST_F (Strdiff3test, merge_test17) {
    ASSERT_TRUE(merge_cases[17].is_merge_success);
    ASSERT_EQ(merge_cases[17].S, merge_cases[17].merged_seq);
}

TEST_F (Strdiff3test, merge_test18) {
    ASSERT_TRUE(merge_cases[18].is_merge_success);
    ASSERT_EQ(merge_cases[18].S, merge_cases[18].merged_seq);
}

TEST_F (Strdiff3test, merge_test19) {
    ASSERT_TRUE(merge_cases[19].is_merge_success);
    ASSERT_EQ(merge_cases[19].S, merge_cases[19].merged_seq);
}

TEST_F (Strdiff3test, merge_test20) {
    ASSERT_TRUE(merge_cases[20].is_merge_success);
    ASSERT_EQ(merge_cases[20].S, merge_cases[20].merged_seq);
}

TEST_F (Strdiff3test, merge_test21) {
    ASSERT_TRUE(merge_cases[21].is_merge_success);
    ASSERT_EQ( merge_cases[21].S, merge_cases[21].merged_seq);
}

TEST_F (Strdiff3test, merge_test22) {
    ASSERT_TRUE(merge_cases[22].is_merge_success);
    ASSERT_EQ( merge_cases[22].S, merge_cases[22].merged_seq);
}

TEST_F (Strdiff3test, merge_test23) {
    ASSERT_TRUE(merge_cases[23].is_merge_success);
    ASSERT_EQ(merge_cases[23].S, merge_cases[23].merged_seq);
}

TEST_F (Strdiff3test, detect_confliction_test0) {
    ASSERT_FALSE(detect_cases[0].is_merge_success);
}

TEST_F (Strdiff3test, detect_confliction_test1) {
    ASSERT_FALSE(detect_cases[1].is_merge_success);
}

TEST_F (Strdiff3test, custom_comparator_test0) {
    ASSERT_TRUE(custom_cases[0].is_merge_success);
    ASSERT_EQ(custom_cases[0].S, custom_cases[0].merged_seq);
}
