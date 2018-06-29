import tempfile
import os
import urllib
import pdb
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from .base_testcase import BaseTestCase

class TestLateDays(BaseTestCase):
    def __init__(self,testname):
        super().__init__(testname,user_id="lakinh", user_password="lakinh", user_name="Hazel")

    def test_late_days(self):
        #First, load the late day info table, check if it is correct, and load the data into an array
        #Second, goto the navigation page, check if the buttons are correctly colored
        #Third, click on each gradeable and see if the correct info (banners, popups, etc) displays correctly
        #Fourth, log out and login as another user
        late_info = self.load_and_test_table('lakinh')
        self.check_student_late_gradeable_view('lakinh')
        super().log_out()
        super().log_in(user_id='bauchg', user_password='bauchg', user_name='Gwen')
        self.load_and_test_table('bauchg')
        super().log_out()
        super().log_in(user_id='stracm', user_password='stracm', user_name='Malvina')
        self.load_and_test_table('stracm')

    def check_student_late_gradeable_view(self, user_id):
        #Check on each button and check if the banner displays the correct info
        #Also checks if the show late info button displays correctly
        #Drag in a random file and see if the popup displays correctly
        # open_gradeables = self.driver.find_element_by_xpath("//tbody[contains(text(),'Course Settings')]")
        if user_id == "lakinh":
            self.click_nav_gradeable_button("items_being_graded", 'grading_team_homework', "view submission", (By.XPATH, "//h2[@class='upperinfo-left']"))
            assert self.driver.find_element_by_id('late_day_banner')
            assert self.driver.find_element_by_id('late_day_banner').value_of_css_property("background-color") == "rgba(217, 83, 79, 1)"
            self.click_nav_gradeable_button("graded", 'grades_released_homework', "view grade", (By.XPATH, "//h2[@class='upperinfo-left']"))
        pass


    def load_and_test_table(self, user_id):
        #Test the late days table using given infomation, compares the rows and column of three users
        #Will return back to the course page after testing
        self.driver.find_element_by_id(self.get_current_semester() + '_sample').click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Show my late days information')]").click()
        assert 'page=view_late_table' in self.driver.current_url
        table_info = []
        if(user_id == 'lakinh'):
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Initial total late days allowed')]").text == 'Initial total late days allowed: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days used')]").text == 'Total late days used: 1'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days remaining for future assignments')]").text == 'Total late days remaining for future assignments: 2'
            table_info = ["Closed Homework","01/01/1972","0","","","Good","",
                          "Closed Team Homework","01/01/1972","0","","","Good","",
                          "TA Only w/ Extra Credit (Upload Only)","01/01/1972","1","","","Good","",
                          "TA Only w/ Penalty (Upload Only)","01/01/1972","0","","","Good","",
                          "Grading Homework","01/01/1972","0","","","Good","",
                          "Grading Team Homework","01/01/1972","2","3","","Bad (too many late days used on this assignment)","",
                          "Grades Released Homework","01/01/1972","2","","","Good","",
                          "Autograder Hidden and Extra Credit (C++ Hidden Tests)","01/01/1972","1","","","Good","",
                          "Autograde and TA Homework (C System Calls)","01/01/1972","2","1","","Late","1",
                          "Autograde Only Homework (Simple Python)","01/01/1972","1","","","Good","",
                          "TA Grade Only Homework (Upload Only)","01/01/1972","0","","","Good","",
                          "Future (No TAs) Homework","12/31/9996","1","","","No submission","",
                          "Future (TAs) Homework","12/31/9996","2","","","No submission","",
                          "Open Team Homework","12/31/9996","1","","","Good","",
                          "Open Homework","12/31/9996","0","","","No submission",""]
        elif(user_id == 'bauchg'):
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Initial total late days allowed')]").text == 'Initial total late days allowed: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days used')]").text == 'Total late days used: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days remaining for future assignments')]").text == 'Total late days remaining for future assignments: 0'
            table_info = ["Closed Homework","01/01/1972","0","","","Good","",
                          "Closed Team Homework","01/01/1972","0","","","Good","",
                          "TA Only w/ Extra Credit (Upload Only)","01/01/1972","1","1","","Late","1",
                          "TA Only w/ Penalty (Upload Only)","01/01/1972","0","","","Good","",
                          "Grading Homework","01/01/1972","0","","","Good","",
                          "Grading Team Homework","01/01/1972","2","1","1","Good","",
                          "Grades Released Homework","01/01/1972","2","","","Good","",
                          "Autograder Hidden and Extra Credit (C++ Hidden Tests)","01/01/1972","1","1","","Late","1",
                          "Autograde and TA Homework (C System Calls)","01/01/1972","2","","","Good","",
                          "Autograde Only Homework (Simple Python)","01/01/1972","1","2","1","Late","1",
                          "TA Grade Only Homework (Upload Only)","01/01/1972","0","","","Good","",
                          "Future (No TAs) Homework","12/31/9996","1","","","No submission","",
                          "Future (TAs) Homework","12/31/9996","2","","","No submission","",
                          "Open Team Homework","12/31/9996","1","","","Good","",
                          "Open Homework","12/31/9996","0","2","","Bad (too many late days used this term)",""]
        elif(user_id == 'stracm'):
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Initial total late days allowed')]").text == 'Initial total late days allowed: 3'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days used')]").text == 'Total late days used: 2'
            assert self.driver.find_element_by_xpath("//p[contains(text(),'Total late days remaining for future assignments')]").text == 'Total late days remaining for future assignments: 1'
            table_info = ["Closed Homework","01/01/1972","0","","","Good","",
                          "Closed Team Homework","01/01/1972","0","","","Good","",
                          "TA Only w/ Extra Credit (Upload Only)","01/01/1972","1","","","Good","",
                          "TA Only w/ Penalty (Upload Only)","01/01/1972","0","","","Good","",
                          "Grading Homework","01/01/1972","0","1","","Bad (too many late days used on this assignment)","",
                          "Grading Team Homework","01/01/1972","2","1","","Late","1",
                          "Grades Released Homework","01/01/1972","2","","","Good","",
                          "Autograder Hidden and Extra Credit (C++ Hidden Tests)","01/01/1972","1","1","","Late","1",
                          "Autograde and TA Homework (C System Calls)","01/01/1972","2","","","Good","",
                          "Autograde Only Homework (Simple Python)","01/01/1972","1","","","No submission","",
                          "TA Grade Only Homework (Upload Only)","01/01/1972","0","","","No submission","",
                          "Future (No TAs) Homework","12/31/9996","1","","","No submission","",
                          "Future (TAs) Homework","12/31/9996","2","","","No submission","",
                          "Open Team Homework","12/31/9996","1","","","Good","",
                          "Open Homework","12/31/9996","0","","","Good",""]
        table_id = self.driver.find_element(By.ID, 'late_day_table')
        cols = table_id.find_elements(By.TAG_NAME, "td")
        counter = 0
        for col in cols:
            # print(col.text + " " + table_info[counter])
            assert col.text == table_info[counter]
            counter+=1
        self.click_header_link_text("sample", (By.XPATH, "//table[@class='gradeable_list']"))
        return table_info

    def get_info_from_table(self, table, gradeable_name, which_col):
        #Get info for a particular cell
        for i in range(0, len(table), 7):
            if table[i] == gradeable_name:
                return table[i+which_col]
        return NULL
if __name__ == "__main__":
    import unittest
    unittest.main()
