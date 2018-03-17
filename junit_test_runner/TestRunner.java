import org.junit.runner.RunWith;
import org.junit.runner.JUnitCore;
import org.junit.runner.Result;
import org.junit.internal.TextListener;

import static org.junit.Assert.assertArrayEquals;

import java.io.*;

public class TestRunner {

    private static String HIDDEN_TESTS_PREFIX = "ALMHidden";
    private static String TESTS_SUFFIX = "Test";

    /*
    @param: relative name of the folder where the tests are
    @effects: runs all tests in folder ./$homework/test/ with JUnit
    */
    public static void runAllTestsInTestDirectory(String homework, 
    		String subFolderPath) {
        //System.out.println("Working Directory = " + System.getProperty("user.dir"));
	String folderName = homework + subFolderPath;

	File folder = new File(folderName);

	// Retrieves the listOfFiles in folder
	File[] listOfFiles = folder.listFiles();

	if (listOfFiles == null) {
	    System.out.println("Folder not found: " + folderName);
	    return;
	}

	int failures = 0;
	int tests = 0;

	header(); // prints JUnit header string

	for (int i = 0; i < listOfFiles.length; i++) {
	    if (listOfFiles[i].isFile()) {
		String filename = listOfFiles[i].getName();
		if (filename.indexOf("Test.class") > -1) {
		    // if class file is a hidden test, skip
		    if (filename.startsWith(HIDDEN_TESTS_PREFIX))
			continue;
		    // Found XyzTest.class in test directory, and this is not a hidden test
		    // Strip the .class off the name, then append to "hw.test."
		    filename = folderName.replace('/','.')+filename.substring(0,filename.indexOf(".class"));
		    try {
			Result result = runTestsInFile(filename);
			// Add #failures to current failure count, add #tests to current test count
			failures += result.getFailureCount();
			tests += result.getRunCount();
		    }
		    catch (ClassNotFoundException e) {
			System.out.println(filename+" class is not found.");
			//TODO: Change behavior if unknown test class.
		    }
		    // catch (NoClassDefFoundError e) {
		    //	System.out.println(filename+" class is not found.");
		    // }
		}
		// System.out.println("File " + listOfFiles[i].getName());
		
	    } else if (listOfFiles[i].isDirectory()) { // If file is a directory, ignore
		// System.out.println("Directory " + listOfFiles[i].getName());
	    }
	}
	footer(tests,failures); // Print result of running all tests
    }

    private static void header() {
	System.out.println("JUnit version 4.12");
    }
    private static void footer(int tests, int failures) {
	if (failures != 0) {
            System.out.println("TEST-RUNNER-FAILURES!!!");
            System.out.println("Tests run: "+tests+", Failures: "+failures);
        }
        else {
            System.out.println("TEST-RUNNER-OK ("+tests+" tests)");
        }
    }

    private static Result runTestsInFile(String filename) throws ClassNotFoundException {
	Class clazz = Class.forName(filename);
	System.out.println("Running Junit tests in "+clazz);
	JUnitCore junit = new JUnitCore();
	junit.addListener(new TextListener(System.out));
	Result result = junit.run(clazz);
	return result;
    }

    public static void main(String args[]) {

	String folderName = args[0];
	String subFolderPath = "/test/";
	if (args.length > 1) {
		subFolderPath = args[1];
	}
	runAllTestsInTestDirectory(folderName, subFolderPath);

    }
}

