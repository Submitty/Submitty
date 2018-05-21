from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from .base_testcase import BaseTestCase

class TestForumMergeThread(BaseTestCase):
    def __init__(self,testname):
        super().__init__(testname,user_id="instructor", user_password="instructor", user_name="Quinn")
    
    def create_thread(self, title, first_post, ignore_if_exists):
        assert 'page=view_thread' in self.driver.current_url
        if ignore_if_exists and self.thread_exists(title):
            return
        self.driver.find_element_by_xpath("//a[contains(text(),'Create Thread')]").click()
        self.driver.find_element_by_id("title").send_keys(title)
        self.driver.find_element_by_id("thread_content").send_keys(first_post)
        categories = Select(self.driver.find_element_by_id('cat'))
        categories.select_by_value("1")
        self.driver.find_element_by_xpath("//input[@value='Submit Post']").click()
        
    def thread_exists(self, title):
        assert 'page=view_thread' in self.driver.current_url
        return len(self.driver.find_elements_by_xpath("//div[contains(@class, 'thread_box') and contains(string(),'{}')]".format(title))) > 0

    def view_thread(self, title):
        assert 'page=view_thread' in self.driver.current_url
        self.driver.find_element_by_xpath("//div[contains(@class, 'thread_box') and contains(string(),'{}')]".format(title)).click()
        
    def delete_thread(self, title):
        # Workaround, not working without force redirection
        self.driver.get(self.forum_page_url)
        self.view_thread(title)
        self.driver.find_elements_by_xpath("//a[@title='Remove post']")[0].click()
        self.driver.switch_to_alert().accept();
        
    def merge_threads(self, child_thread_title, parent_thread_title, press_cancel = False):
        self.view_thread(child_thread_title)
        merge_threads_div = self.driver.find_element_by_id("merge-threads")
        self.driver.find_element_by_xpath("//a[contains(text(),'Merge Threads')]").click()
        cancel_button = merge_threads_div.find_element(By.XPATH, ".//a[contains(normalize-space(.), 'Cancel')]")
        assert merge_threads_div.value_of_css_property("display") == "block"
        if parent_thread_title is None:
            cancel_button.click()
            assert merge_threads_div.value_of_css_property("display") == "none"
        else:
            submit_button = merge_threads_div.find_element(By.XPATH, ".//input[@value='Submit']")
            possible_parents = self.driver.find_element_by_name("merge_thread_parent")
            possible_parents.find_element(By.XPATH, ".//option[contains(normalize-space(.), '{}')]".format(parent_thread_title)).click()
            if press_cancel:
                cancel_button.click()
            else:
                submit_button.click()
            assert self.driver.find_element_by_id("merge-threads").value_of_css_property("display") == "none"
        
    def test_forum_merge_thread(self):
        self.driver.find_element_by_id(self.get_current_semester() + '_sample').click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Discussion Forum')]").click()
        self.forum_page_url = self.driver.current_url
        
        self.create_thread("Test 1", "Content 1", ignore_if_exists = True)
        self.create_thread("Test 2", "Content 2", ignore_if_exists = True)
        self.create_thread("Test 3", "Content 3", ignore_if_exists = True)
        
        # Not Merging
        self.merge_threads("Test 1",None)
        self.merge_threads("Test 2", "Test 1", press_cancel = True)
        
        assert self.thread_exists("Test 1")
        assert self.thread_exists("Test 2")
        assert self.thread_exists("Test 3")
        
        # Merging success
        self.merge_threads("Test 2", "Test 1", press_cancel = False)
        assert self.thread_exists("Test 1")
        assert not self.thread_exists("Test 2")
        assert self.thread_exists("Test 3")

        # Cleanup
        self.delete_thread("Test 3")
        self.delete_thread("Test 1")
        # TODO: entries will still persist in db and will keep on increasing on every test run

if __name__ == "__main__":
    import unittest
    unittest.main()
