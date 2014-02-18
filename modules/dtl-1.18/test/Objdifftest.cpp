#include "dtl_test_common.hpp"
#include "comparators.hpp"

class Objdifftest : public ::testing::Test
{
protected :
    dtl_test_typedefs(string, vector<elem>)
    typedef struct case_t {
        sequence   A;
        sequence   B;
        sesElemVec expected;
        sesElemVec ses_seq;
    } case_t;
    typedef vector< case_t > caseVec;
    
    caseVec obj_diff_cases;
    
    template < typename comparator >
    case_t createCase (const sequence a, const sequence b, sesElemVec ses, string test_name) {
        case_t  c;
        elemVec lcs_v;
        string  diff_name("objdiff");

        Diff< elem, sequence, comparator > diff(a, b, true);

        diff.compose();
        
        c.A         = a;
        c.B         = b;
        c.ses_seq   = diff.getSes().getSequence();
        c.expected  = ses;
        
        return c;
    }
    
    void SetUp(void) {
        {
            string array1[] = {"the", "quick", "brown"};
            string array2[] = {"The", "Quick", "Fox"};
            
            sequence A(array1, array1 + (sizeof(array1) / sizeof(array1[0])));
            sequence B(array2, array2 + (sizeof(array2) / sizeof(array2[0])));
            
            dtl::Ses< elem > ses;
            ses.addSequence("the", 1, 1, dtl::SES_COMMON);
            ses.addSequence("quick", 2, 2, dtl::SES_COMMON);
            ses.addSequence("brown", 3, 0, dtl::SES_DELETE);
            ses.addSequence("Fox", 0, 3, dtl::SES_ADD);
            
            obj_diff_cases.push_back(createCase< StringCaseInsensitive >(A, B, ses.getSequence(), "objdiff_test0_pattern"));
        }
        
        {
            string array1[] = {"b", "c", "e", "g"};
            string array2[] = {"a", "d", "e", "f", "h"};
            
            sequence A(array1, array1 + (sizeof(array1) / sizeof(array1[0])));
            sequence B(array2, array2 + (sizeof(array2) / sizeof(array2[0])));
            
            dtl::Ses< elem > ses;
            ses.addSequence("b", 1, 0, dtl::SES_DELETE);
            ses.addSequence("c", 2, 0, dtl::SES_DELETE);
            ses.addSequence("a", 0, 1, dtl::SES_ADD);
            ses.addSequence("d", 0, 2, dtl::SES_ADD);
            ses.addSequence("e", 3, 3, dtl::SES_COMMON);
            ses.addSequence("g", 4, 0, dtl::SES_DELETE);
            ses.addSequence("f", 0, 4, dtl::SES_ADD);
            ses.addSequence("h", 0, 5, dtl::SES_ADD);
            
            obj_diff_cases.push_back(createCase< StringCaseInsensitive >(A, B, ses.getSequence(), "objdiff_test1_unswapped"));
        }
        {
            string array1[] = {"a", "d", "e", "f", "h"};
            string array2[] = {"b", "c", "e", "g"};
            
            sequence A(array1, array1 + (sizeof(array1) / sizeof(array1[0])));
            sequence B(array2, array2 + (sizeof(array2) / sizeof(array2[0])));
            
            dtl::Ses< elem > ses;
            ses.addSequence("a", 1, 0, dtl::SES_DELETE);
            ses.addSequence("d", 2, 0, dtl::SES_DELETE);
            ses.addSequence("b", 0, 1, dtl::SES_ADD);
            ses.addSequence("c", 0, 2, dtl::SES_ADD);
            ses.addSequence("e", 3, 3, dtl::SES_COMMON);
            ses.addSequence("f", 4, 0, dtl::SES_DELETE);
            ses.addSequence("h", 5, 0, dtl::SES_DELETE);
            ses.addSequence("g", 0, 4, dtl::SES_ADD);
            
            obj_diff_cases.push_back(createCase< StringCaseInsensitive >(A, B, ses.getSequence(), "objdiff_test2_swapped"));
        }
    }
    
    void TearDown () {
        //for_each(obj_diff_cases.begin(), obj_diff_cases.end(), Remover< case_t >());
    }
};


/**
 * Objdifftest
 * check list:
 * - SES pattern "SES_COMMON, SES_DELETE, SES_ADD"
 * - Indepence of results from swapping 
 */

TEST_F (Objdifftest, objdiff_test0_pattern) {
    EXPECT_EQ(obj_diff_cases[0].expected, obj_diff_cases[0].ses_seq);
}

TEST_F (Objdifftest, objdiff_test1_unswapped) {
    EXPECT_EQ(obj_diff_cases[1].expected, obj_diff_cases[1].ses_seq);
}

TEST_F (Objdifftest, objdiff_test2_swapped) {
    EXPECT_EQ(obj_diff_cases[2].expected, obj_diff_cases[2].ses_seq);
}
