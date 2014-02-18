#include "dtl_test_common.hpp"
#include "comparators.hpp"

class Strdifftest : public ::testing::Test
{
protected :
    dtl_test_typedefs(char, string)
    typedef struct case_t {
        sequence   A;
        sequence   B;
        size_t     editdis;
        elemVec    lcs_v;
        sequence   lcs_s;
        sesElemVec ses_seq;
        uniHunkVec hunk_v;
        size_t     editdis_ses;
        size_t     editdis_uni;
        string     path_rses;
        string     path_rhunks;
    } case_t;
    typedef vector< case_t > caseVec;
    
    caseVec diff_cases;
    caseVec only_editdis_cases;
    caseVec custom_cases;
    
    template < typename comparator >
    case_t createCase (const sequence a, const sequence b, string test_name, bool onlyEditdis = false) {
        case_t  c;
        elemVec lcs_v;
        string  diff_name("strdiff");

        Diff< elem, sequence, comparator > diff(a, b);
        if (onlyEditdis) {
            diff.onOnlyEditDistance();
        }

        diff.compose();
        diff.composeUnifiedHunks();
        lcs_v = diff.getLcsVec();
        
        if (test_name != "") {
            string path_lses   = create_path(test_name, diff_name, TYPE_DIFF_SES);
            string path_rses   = create_path(test_name, diff_name, TYPE_DIFF_SES, true);
            string path_lhunks = create_path(test_name, diff_name, TYPE_DIFF_UNI);
            string path_rhunks = create_path(test_name, diff_name, TYPE_DIFF_UNI, true);
            diff_resultset_exist_check(path_lses);
            diff_resultset_exist_check(path_lhunks);
            
            create_file< elem, sequence, comparator >(path_rses,   diff, TYPE_DIFF_SES);
            create_file< elem, sequence, comparator >(path_rhunks, diff, TYPE_DIFF_UNI);
            
            c.editdis_ses = cal_diff_uni(path_lses,   path_rses);
            c.editdis_uni = cal_diff_uni(path_lhunks, path_rhunks);
            c.path_rses   = path_rses;
            c.path_rhunks = path_rhunks;
        }

        c.A       = a;
        c.B       = b;
        c.editdis = diff.getEditDistance();
        c.lcs_s   = sequence(lcs_v.begin(), lcs_v.end());
        c.ses_seq = diff.getSes().getSequence();
        c.hunk_v  = diff.getUniHunks();
        
        return c;
    }
    
    void SetUp(void) {
        diff_cases.push_back(createCase< Compare< elem > >("abc",               "abd",                 "diff_test0"));
        diff_cases.push_back(createCase< Compare< elem > >("acbdeacbed",        "acebdabbabed",        "diff_test1"));
        diff_cases.push_back(createCase< Compare< elem > >("abcdef",            "dacfea",              "diff_test2"));
        diff_cases.push_back(createCase< Compare< elem > >("abcbda",            "bdcaba",              "diff_test3"));
        diff_cases.push_back(createCase< Compare< elem > >("bokko",             "bokkko",              "diff_test4"));
        diff_cases.push_back(createCase< Compare< elem > >("",                  "",                    "diff_test5"));
        diff_cases.push_back(createCase< Compare< elem > >("a",                 "",                    "diff_test6"));
        diff_cases.push_back(createCase< Compare< elem > >("",                  "b",                   "diff_test7"));
        diff_cases.push_back(createCase< Compare< elem > >("acbdeaqqqqqqqcbed", "acebdabbqqqqqqqabed", "diff_test8"));
        
        only_editdis_cases.push_back(createCase< Compare< elem > >("abc",               "abd",                 "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("acbdeacbed",        "acebdabbabed",        "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("abcdef",            "dacfea",              "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("abcbda",            "bdcaba",              "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("bokko",             "bokkko",              "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("",                  "",                    "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("a",                 "",                    "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("",                  "b",                   "", true));
        only_editdis_cases.push_back(createCase< Compare< elem > >("acbdeaqqqqqqqcbed", "acebdabbqqqqqqqabed", "", true));
        
        custom_cases.push_back(createCase< CaseInsensitive >("abc", "Abc", "custom_test0"));
    }
    
    void TearDown () {
        for_each(diff_cases.begin(), diff_cases.end(), Remover< case_t >());
        for_each(custom_cases.begin(), custom_cases.end(), Remover< case_t >());
    }
};


/**
 * Strdifftest
 * check list is following
 * - editdistance
 * - LCS
 * - SES
 * - Unified Format Difference
 * - onOnlyEditDistance
 */

TEST_F (Strdifftest, diff_test0) {
    
    EXPECT_EQ(2,    diff_cases[0].editdis);
    
    EXPECT_EQ("ab", diff_cases[0].lcs_s);
    
    ASSERT_EQ(0,    diff_cases[0].editdis_ses);

    ASSERT_EQ(0,    diff_cases[0].editdis_uni);
}

TEST_F (Strdifftest, diff_test1) {
    EXPECT_EQ(6,          diff_cases[1].editdis);
    
    EXPECT_EQ("acbdabed", diff_cases[1].lcs_s);
    
    ASSERT_EQ(0,          diff_cases[1].editdis_ses);
    
    ASSERT_EQ(0,          diff_cases[1].editdis_uni);
}

TEST_F (Strdifftest, diff_test2) {
    EXPECT_EQ(6,     diff_cases[2].editdis);
    
    EXPECT_EQ("acf", diff_cases[2].lcs_s);
    
    ASSERT_EQ(0,     diff_cases[2].editdis_ses);
    
    ASSERT_EQ(0,     diff_cases[2].editdis_uni);
}

TEST_F (Strdifftest, diff_test3) {
    EXPECT_EQ(4,      diff_cases[3].editdis);
    
    EXPECT_EQ("bcba", diff_cases[3].lcs_s);
    
    ASSERT_EQ(0,      diff_cases[3].editdis_ses);
    
    ASSERT_EQ(0,      diff_cases[3].editdis_uni);
}

TEST_F (Strdifftest, diff_test4) {
    EXPECT_EQ(1,       diff_cases[4].editdis);
    
    EXPECT_EQ("bokko", diff_cases[4].lcs_s);
    
    ASSERT_EQ(0,       diff_cases[4].editdis_ses);
    
    ASSERT_EQ(0,       diff_cases[4].editdis_uni);
}

TEST_F (Strdifftest, diff_test5) {
    EXPECT_EQ(0,  diff_cases[5].editdis);
    
    EXPECT_EQ("", diff_cases[5].lcs_s);
    
    ASSERT_EQ(0,  diff_cases[5].editdis_ses);
    
    ASSERT_EQ(0,  diff_cases[5].editdis_uni);
}

TEST_F (Strdifftest, diff_test6) {
    EXPECT_EQ(1,  diff_cases[6].editdis);
    
    EXPECT_EQ("", diff_cases[6].lcs_s);
    
    ASSERT_EQ(0,  diff_cases[6].editdis_ses);
    
    ASSERT_EQ(0,  diff_cases[6].editdis_uni);
}

TEST_F (Strdifftest, diff_test7) {
    EXPECT_EQ(1,  diff_cases[7].editdis);
    
    EXPECT_EQ("", diff_cases[7].lcs_s);
    
    ASSERT_EQ(0,  diff_cases[7].editdis_ses);
    
    ASSERT_EQ(0,  diff_cases[7].editdis_uni);
}

TEST_F (Strdifftest, diff_test8) {
    EXPECT_EQ(6,                 diff_cases[8].editdis);
    
    EXPECT_EQ("acbdaqqqqqqqbed", diff_cases[8].lcs_s);
    
    ASSERT_EQ(0,                 diff_cases[8].editdis_ses);

    ASSERT_EQ(0,                 diff_cases[8].editdis_uni);
}

TEST_F (Strdifftest, only_editdis_test0) {
    EXPECT_EQ(2,  only_editdis_cases[0].editdis);
    
    EXPECT_EQ("", only_editdis_cases[0].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[0].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[0].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test1) {
    EXPECT_EQ(6,  only_editdis_cases[1].editdis);
    
    EXPECT_EQ("", only_editdis_cases[1].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[1].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[1].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test2) {
    EXPECT_EQ(6,  only_editdis_cases[2].editdis);
    
    EXPECT_EQ("", only_editdis_cases[2].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[2].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[2].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test3) {
    EXPECT_EQ(4,  only_editdis_cases[3].editdis);
    
    EXPECT_EQ("", only_editdis_cases[3].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[3].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[3].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test4) {
    EXPECT_EQ(1,  only_editdis_cases[4].editdis);
    
    EXPECT_EQ("", only_editdis_cases[4].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[4].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[4].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test5) {
    EXPECT_EQ(0,  only_editdis_cases[5].editdis);
    
    EXPECT_EQ("", only_editdis_cases[5].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[5].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[5].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test6) {
    EXPECT_EQ(1,  only_editdis_cases[6].editdis);
    
    EXPECT_EQ("", only_editdis_cases[6].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[6].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[6].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test7) {
    EXPECT_EQ(1,  only_editdis_cases[7].editdis);
    
    EXPECT_EQ("", only_editdis_cases[7].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[7].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[7].hunk_v.empty());
}

TEST_F (Strdifftest, only_editdis_test8) {
    EXPECT_EQ(6,  only_editdis_cases[8].editdis);
    
    EXPECT_EQ("", only_editdis_cases[8].lcs_s);
    
    ASSERT_TRUE(only_editdis_cases[8].ses_seq.empty());
    
    ASSERT_TRUE(only_editdis_cases[8].hunk_v.empty());
}

TEST_F (Strdifftest, custom_comparator_test0) {
    EXPECT_EQ(0,     custom_cases[0].editdis);
    
    EXPECT_EQ("abc", custom_cases[0].lcs_s);

    ASSERT_EQ(0,     custom_cases[0].editdis_ses);
    
    ASSERT_TRUE(custom_cases[0].hunk_v.empty());
}
