import org.junit.Test;
import org.junit.BeforeClass;
import static org.junit.Assert.*;

/**
 * FactorialTest is a glassbox test of the Factorial class.
 *
 * @see Factorial
 *
 *
 * Adapted from UW CS 331 hw0 FibonacciTest.java
 */


public class FactorialTest {

    private static Factorial fac = null;

    @BeforeClass
	public static void setupBeforeTests() throws Exception {
        fac = new Factorial();
    }

    /**
     * Tests that Factorial throws an IllegalArgumentException
     * for a negative number.
     */
    @Test(expected=IllegalArgumentException.class)
	public void expectedIllegalArgumentException() {
        fac.getFact(-1);
    }

    /**
     * Tests that Factorial throws no IllegalArgumentException
     * for zero or for a positive number.
     */
    @Test
	public void testThrowsIllegalArgumentException() {

        // test 0
        try {
            fac.getFact(0);
        } catch (IllegalArgumentException ex) {
            fail("Threw IllegalArgumentException for 0 but 0 is nonnegative: "
		 + ex);
        }
        // test 1
        try {
            fac.getFact(1);
        } catch (IllegalArgumentException ex) {
            fail("Threw IllegalArgumentException for 1 but 1 is nonnegative: "
		 + ex);
        }
    }

    /** Tests to see that Fibonacci returns the correct value for the base cases, n=0 and n=1 */
    @Test
	public void testBaseCase() {
        assertEquals("getFact(0)", 1, fac.getFact(0));
        assertEquals("getFact(1)", 1, fac.getFact(1));
    }

    /** Tests inductive cases of the Factorial sequence */
    @Test
	public void testInductiveCase() {  
	int[][] cases = new int[][] {
	    { 2, 2 },
	    { 3, 6 },
	    { 4, 24 },
	    { 5, 120 },
	    { 6, 720 },
	    { 7, 5040 }
	};       
	for (int i = 0; i < cases.length; i++) {
	    assertEquals("getFact(" + cases[i][0] + ")",
			 cases[i][1], fac.getFact(cases[i][0]));
	}
    }

}
