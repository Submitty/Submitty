import tempfile
import os
import urllib
import pdb
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from .base_testcase import BaseTestCase

class TestLateDays(BaseTestCase):
    def __init__(self,testname):
        super().__init__(testname,user_id="aphacker", user_password="aphacker", user_name="Alyssa P")

    def test_late_days(self):
        #First, load the late day info table, check if it is correct, and load the data into an array
        #Second, goto the navigation page, check if the buttons are correctly colored
        #Third, click on each gradeable and see if the correct info (banners, popups, etc) displays correctly
        #Fourth, log out and login as another user
        late_info = self.load_and_test_table('aphacker')
        self.check_student_late_gradeable_view('aphacker', late_info)
        super().log_out()
        super().log_in(user_id='bitdiddle', user_password='bitdiddle', user_name='Ben')
        self.load_and_test_table('bitdiddle')
        super().log_out()
        super().log_in(user_id='damorw', user_password='damorw', user_name='Wendell')
        self.load_and_test_table('damorw')

    def check_navigation(self):
        #Check if all the buttons in the navigation view is good

    def check_student_late_gradeable_view(self, user_id, late_info):
        #Check on each button and check if the banner displays the correct info
        #Also checks if the show late info button displays correctly
        #Drag in a random file and see if the popup displays correctly
        # open_gradeables = self.driver.find_element_by_xpath("//tbody[contains(text(),'Course Settings')]")
        pass


    def load_and_test_table(self, user_id):
        #Test the late days table using given infomation, compares the rows and column of three users
        self.driver.find_element_by_id(self.get_current_semester() + '_sample').click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Show my late days information')]").click()
        assert 'page=view_late_table' in self.driver.current_url
        table_info = []
        if(user_id == 'aphacker'):
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Initial total late days allowed')]").text == 'Initial total late days allowed: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days used')]").text == 'Total late days used: 1'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days remaining')]").text == 'Total late days remaining: 2'
            table_info = ['Autograde and TA Homework (C System Calls)', '01/01/72', '2', '0', '0', 'Good', '0',
                        'Autograder Hidden and Extra Credit (C++ Hidden Tests)', '01/01/72', '0', '3', '0', 'Bad too many used for this assignment', '0',
                        'TA Only w/ Extra Credit (Upload Only)', '01/01/72', '0', '0', '0', 'Good', '0',
                        'TA Only w/ Penalty (Upload Only)', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Closed Team Homework', '01/01/72', '0', '0', '0', 'Good', '0',
                        'Closed Homework', '01/01/72', '2', '0', '0', 'Good', '0',
                        'Grading Homework', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Grades Released Homework', '01/01/72', '0', '0', '0', 'Good', '0',
                        'TA Grade Only Homework (Upload Only)', '01/01/72', '0', '2', '0', 'Bad too many used for this assignment', '0',
                        'Autograde Only Homework (Simple Python)', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Future (No TAs) Homework', '12/31/96', '1', '0', '0', 'No submission', '0',
                        'Future (TAs) Homework', '12/31/96', '2', '0', '0', 'No submission', '0',
                        'Open Homework', '12/31/96', '1', '1', '0', 'Late', '1',
                        'Open Team Homework', '12/31/96', '2', '0', '0', 'Good', '0',
                        'Grading Team Homework', '12/31/99', '0', '0', '0', 'Good', '0']
        elif(user_id == 'bitdiddle'):
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Initial total late days allowed')]").text == 'Initial total late days allowed: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days used')]").text == 'Total late days used: 1'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days remaining')]").text == 'Total late days remaining: 2'
            table_info = ['Autograde and TA Homework (C System Calls)', '01/01/72', '2', '1', '0', 'Late', '1',
                        'Autograder Hidden and Extra Credit (C++ Hidden Tests)', '01/01/72', '0', '0', '0', 'Good', '0',
                        'TA Only w/ Extra Credit (Upload Only)', '01/01/72', '0', '1', '0', 'Bad too many used for this assignment', '0',
                        'TA Only w/ Penalty (Upload Only)', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Closed Team Homework', '01/01/72', '0', '0', '0', 'Good', '0',
                        'Closed Homework', '01/01/72', '2', '0', '0', 'Good', '0',
                        'Grading Homework', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Grades Released Homework', '01/01/72', '0', '0', '0', 'Good', '0',
                        'TA Grade Only Homework (Upload Only)', '01/01/72', '0', '0', '0', 'Good', '0',
                        'Autograde Only Homework (Simple Python)', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Future (No TAs) Homework', '12/31/96', '1', '0', '0', 'No submission', '0',
                        'Future (TAs) Homework', '12/31/96', '2', '0', '0', 'No submission', '0',
                        'Open Homework', '12/31/96', '1', '2', '0', 'Bad too many used for this assignment', '0',
                        'Open Team Homework', '12/31/96', '2', '0', '0', 'Good', '0',
                        'Grading Team Homework', '12/31/99', '0', '0', '0', 'Good', '0']
        elif(user_id == 'damorw'):
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Initial total late days allowed')]").text == 'Initial total late days allowed: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days used')]").text == 'Total late days used: 2'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days remaining')]").text == 'Total late days remaining: 1'
            table_info = ['Autograde and TA Homework (C System Calls)', '01/01/72', '2', '1', '0', 'Late', '1',
                        'Autograder Hidden and Extra Credit (C++ Hidden Tests)', '01/01/72', '0', '0', '0', 'Good', '0',
                        'TA Only w/ Extra Credit (Upload Only)', '01/01/72', '0', '0', '0', 'Good', '0',
                        'TA Only w/ Penalty (Upload Only)', '01/01/72', '1', '0', '0', 'Good', '0',
                        'Closed Team Homework', '01/01/72', '0', '0', '0', 'Good', '0',
                        'Closed Homework', '01/01/72', '2', '0', '0', 'No submission', '0',
                        'Grading Homework', '01/01/72', '1', '1', '0', 'Late', '1',
                        'Grades Released Homework', '01/01/72', '0', '0', '0', 'No submission', '0',
                        'TA Grade Only Homework (Upload Only)', '01/01/72', '0', '0', '0', 'Good', '0',
                        'Autograde Only Homework (Simple Python)', '01/01/72', '1', '0', '0', 'No submission', '0',
                        'Future (No TAs) Homework', '12/31/96', '1', '0', '0', 'No submission', '0',
                        'Future (TAs) Homework', '12/31/96', '2', '0', '0', 'No submission', '0',
                        'Open Homework', '12/31/96', '1', '0', '0', 'No submission', '0',
                        'Open Team Homework', '12/31/96', '2', '0', '0', 'Good', '0',
                        'Grading Team Homework', '12/31/99', '0', '0', '0', 'Good', '0']
        table_id = self.driver.find_element(By.ID, 'late_day_table')
        cols = table_id.find_elements(By.TAG_NAME, "td")
        counter = 0
        for col in cols:
            # print(col.text + " " + table_info[counter])
            assert col.text == table_info[counter]
            counter+=1
        return table_info

    def get_info_from_table(self, table, gradeable_name, which_col):
        pdb.set_trace()
        #Get info for a particular cell
        for i in range(0, len(table), 7):
            if table[i] == gradeable_name:
                return table[i+which_col]
        return NULL
if __name__ == "__main__":
    import unittest
    unittest.main()
