#include "dtl_test_common.hpp"

class Intdifftest : public ::testing::Test
{
protected :
    dtl_test_typedefs(int, vector< int >)
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
    caseVec cases;
    
    case_t createCase (const sequence a, const sequence b, string test_name) {
        case_t c;
        string diff_name("intdiff");
        Diff< elem > diff(a, b);
        diff.compose();
        diff.composeUnifiedHunks();
        
        if (test_name != "") {
            string path_lses   = create_path(test_name, diff_name, TYPE_DIFF_SES);
            string path_rses   = create_path(test_name, diff_name, TYPE_DIFF_SES, true);
            string path_lhunks = create_path(test_name, diff_name, TYPE_DIFF_UNI);
            string path_rhunks = create_path(test_name, diff_name, TYPE_DIFF_UNI, true);

            create_file< elem, sequence, Compare< elem > >(path_rses,   diff, TYPE_DIFF_SES);
            create_file< elem, sequence, Compare< elem > >(path_rhunks, diff, TYPE_DIFF_UNI);
            c.editdis_ses = cal_diff_uni(path_lses,   path_rses);
            c.editdis_uni = cal_diff_uni(path_lhunks, path_rhunks);
            c.path_rses   = path_rses;
            c.path_rhunks = path_rhunks;
        }


        c.A = a;
        c.B = b;
        c.editdis = diff.getEditDistance();
        c.lcs_v   = diff.getLcsVec();
        c.ses_seq = diff.getSes().getSequence();
        return c;
    }
    
    void SetUp() {
        cases.push_back(createCase(sequence(0), sequence(0), "diff_test0"));
        sequence B1;
        B1.push_back(1);
        cases.push_back(createCase(sequence(0), B1, "diff_test1"));
        sequence A2;
        A2.push_back(1);
        cases.push_back(createCase(A2, sequence(0), "diff_test2"));
        int a4[]   = {1, 2, 3, 4, 5, 6, 7, 8, 9, 10};
        int b4[]   = {3, 5, 1, 4, 5, 1, 7, 9, 6, 10};
        int a4siz  = sizeof(a4) / sizeof(int);
        int b4siz  = sizeof(b4) / sizeof(int);
        sequence A4(&a4[0], &a4[a4siz]);
        sequence B4(&b4[0], &b4[b4siz]);
        cases.push_back(createCase(A4, B4, "diff_test3"));
        int a5[]   = {1, 2, 3, 4, 5};
        int b5[]   = {3, 5, 1, 4, 5};
        int a5siz  = sizeof(a5) / sizeof(int);
        int b5siz  = sizeof(b5) / sizeof(int);
        sequence A5(&a5[0], &a5[a5siz]);
        sequence B5(&b5[0], &b5[b5siz]);
        cases.push_back(createCase(A5, B5, "diff_test4"));
    }
    
    void TearDown () {
        for_each(cases.begin(), cases.end(), Remover< case_t >());
    }
    
};

/**
 * Intdifftest
 * check list is following
 * - editdistance
 * - LCS
 * - SES
 */
TEST_F (Intdifftest, diff_test0) {
    EXPECT_EQ(0, cases[0].editdis);
    
    EXPECT_TRUE(cases[0].lcs_v.empty());
    
    ASSERT_EQ(0, cases[0].editdis_ses);

    ASSERT_EQ(0, cases[0].editdis_uni);
}

TEST_F (Intdifftest, diff_test1) {
    EXPECT_EQ(1, cases[1].editdis);
    
    EXPECT_TRUE(cases[1].lcs_v.empty());
    
    ASSERT_EQ(0, cases[1].editdis_ses);

    ASSERT_EQ(0, cases[1].editdis_uni);
}

TEST_F (Intdifftest, diff_test2) {
    EXPECT_EQ(1, cases[2].editdis);
    
    EXPECT_TRUE(cases[2].lcs_v.empty());
    
    ASSERT_EQ(0, cases[2].editdis_ses);

    ASSERT_EQ(0, cases[2].editdis_uni);
}

TEST_F (Intdifftest, diff_test3) {
    EXPECT_EQ(8, cases[3].editdis);
    
    EXPECT_EQ(3, cases[3].lcs_v[0]);
    EXPECT_EQ(4, cases[3].lcs_v[1]);
    EXPECT_EQ(5, cases[3].lcs_v[2]);
    EXPECT_EQ(7, cases[3].lcs_v[3]);
    EXPECT_EQ(9, cases[3].lcs_v[4]);
    
    ASSERT_EQ(0, cases[3].editdis_ses);

    ASSERT_EQ(0, cases[3].editdis_uni);
}

TEST_F (Intdifftest, diff_test4) {
    EXPECT_EQ(4, cases[4].editdis);
    
    EXPECT_EQ(3, cases[4].lcs_v[0]);
    EXPECT_EQ(4, cases[4].lcs_v[1]);
    EXPECT_EQ(5, cases[4].lcs_v[2]);
    
    ASSERT_EQ(0, cases[4].editdis_ses);

    ASSERT_EQ(0, cases[4].editdis_uni);
}
