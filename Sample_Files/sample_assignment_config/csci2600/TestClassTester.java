import static org.junit.Assert.assertEquals;

import org.junit.Test;
import org.junit.Ignore;
import org.junit.runner.RunWith;
//import org.junit.runners.junit4;

public class TestClassTester {
    @Test
    public void test1() {
        assertEquals(3, TestClass.add(1.0, 2.0), 0);
    }
}
