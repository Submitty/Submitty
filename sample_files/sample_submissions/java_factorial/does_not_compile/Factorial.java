public class Factorial {
    
    /**
     * Calculates the desired term in the factorial sequence.
     *
     * @param n the index of the desired term; the first index of the sequence is 0
     * @return the <var>n</var>th term in the Factorial sequence
     * @throws IllegalArgumentException if <code>n</code> is not a nonnegative number
     */
    public int getFact(int n) {
	if (n < 0) {
	    throw new IllegalArgumentException(n + " is negative");
	} else if (n < 2) {
	    return 1;
	} else {
	    return tailFact(n, 1);
	}
    }

    private int tailFact(int n, int acc) {
	if (n == 0)
	    return acc;
	else {
	    return tailFac(n-1, acc*n);
	}
    }
    
}