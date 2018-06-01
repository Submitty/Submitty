import tempfile
import os
import urllib
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from .base_testcase import BaseTestCase

class TestForum(BaseTestCase):
    def __init__(self,testname):
        super().__init__(testname,user_id="instructor", user_password="instructor", user_name="Quinn")

    def init_and_enable_discussion(self):
        self.driver.find_element_by_id(self.get_current_semester() + '_sample').click()
        if len(self.driver.find_elements_by_xpath("//a[contains(text(),'Discussion Forum')]")) == 0:
            self.driver.find_element_by_xpath("//a[contains(text(),'Course Settings')]").click()
            self.driver.find_element_by_name("forum_enabled").click()
            self.driver.find_element_by_xpath("//button[@form = 'configForm']").click()
            self.driver.find_element_by_xpath("//a[contains(text(),'sample')]").click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Discussion Forum')]").click()
        self.forum_page_url = self.driver.current_url

    def upload_attachment(self, suffix):
        tfname = self.create_dummy_file()
        self.driver.find_element_by_id("file_input{}".format(suffix)).send_keys(tfname)
        return os.path.basename(tfname)

    def create_thread(self, title, first_post, ignore_if_exists = False, upload_attachment = False):
        assert 'page=view_thread' in self.driver.current_url
        if ignore_if_exists and self.thread_exists(title):
            return
        attachment_file = None
        self.driver.find_element_by_xpath("//a[contains(text(),'Create Thread')]").click()
        self.driver.find_element_by_id("title").send_keys(title)
        self.driver.find_element_by_id("thread_content").send_keys(first_post)
        categories = Select(self.driver.find_element_by_id('cat'))
        categories.select_by_value("1")
        if upload_attachment:
            attachment_file = self.upload_attachment("")
        self.driver.find_element_by_xpath("//input[@value='Submit Post']").click()
        return attachment_file

    def thread_exists(self, title):
        assert 'page=view_thread' in self.driver.current_url
        return len(self.driver.find_elements_by_xpath("//div[contains(@class, 'thread_box') and contains(string(),'{}')]".format(title))) > 0

    def view_thread(self, title):
        assert 'page=view_thread' in self.driver.current_url
        self.driver.find_element_by_xpath("//div[contains(@class, 'thread_box') and contains(string(),'{}')]".format(title)).click()

    def find_posts(self, content, must_exists = True, move_to_thread = None, check_attachment = None):
        if move_to_thread is not None:
            self.view_thread(move_to_thread)
        posts = self.driver.find_elements_by_xpath("//div[contains(@class, 'post_box') and contains(string(),'{}')]".format(content))
        if must_exists:
            assert len(posts) > 0
        if check_attachment is not None:
            assert len(posts[0].find_elements(By.XPATH, ".//a[contains(normalize-space(.), '{}')]".format(check_attachment))) > 0
        return posts

    def reply_and_test(self, post_content, newcontent, first_post, upload_attachment = False):
        attachment_file = None
        post = self.find_posts(post_content)[0]
        post_id = post.get_attribute("id")
        if first_post:
            text_area_name = "post_content"
            assert "first_post" in post.get_attribute("class")
        else:
            text_area_name = "post_content_{}".format(post_id)
            assert "first_post" not in post.get_attribute("class")
        if not self.driver.find_element_by_name(text_area_name).is_displayed():
            post.find_element(By.XPATH, ".//a[contains(normalize-space(.), 'Reply')]").click()
        assert self.driver.find_element_by_name(text_area_name).is_displayed()
        self.driver.find_element_by_name(text_area_name).send_keys(newcontent)
        if first_post:
            if upload_attachment:
                attachment_file = self.upload_attachment("")
            self.driver.find_element_by_xpath("//input[contains(@value,'Submit Reply to All')]").click()
        else:
            if upload_attachment:
                attachment_file = self.upload_attachment("_"+post_id)
            self.driver.find_element_by_id("{}-reply".format(post_id)
                ).find_element(By.XPATH, ".//input[@type='submit']").click()
        # Test existence only
        self.find_posts(newcontent, must_exists = True, check_attachment = attachment_file)
        return attachment_file

    def delete_thread(self, title):
        self.view_thread(title)
        self.driver.find_elements_by_xpath("//a[@title='Remove post']")[0].click()
        self.driver.switch_to.alert.accept();
        # Workaround, not working without force redirection
        self.driver.get(self.forum_page_url)

    def create_dummy_file(self):
        # Download image to create dummy image file
        tf = tempfile.NamedTemporaryFile().name + ".png"
        image_url = self.test_url + "/img/submitty_logo.png"
        urllib.request.urlretrieve(image_url, tf)
        return tf

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

    def test_basic_operations_thread(self):
        title = "E2E Sample Title"
        content = "E2E Sample Content"
        reply_content1 = "E2E sample reply 1 content"
        reply_content2 = "E2E sample reply 2 content"
        reply_content3 = "E2E sample reply 3 content"

        self.init_and_enable_discussion()
        for upload_attachment in [False, True]:
            assert not self.thread_exists(title)
            attachment = self.create_thread(title, content, upload_attachment = upload_attachment)

            self.find_posts(content, must_exists = True, check_attachment = attachment)
            self.view_thread(title)
            self.find_posts(content, must_exists = True)
            self.reply_and_test(content, reply_content1, first_post = True, upload_attachment = upload_attachment)
            self.reply_and_test(reply_content1, reply_content2, first_post = False)
            self.reply_and_test(reply_content2, reply_content3, first_post = False, upload_attachment = upload_attachment)

            assert self.thread_exists(title)
            self.delete_thread(title)
            assert not self.thread_exists(title)

    def test_forum_merge_thread(self):
        self.init_and_enable_discussion()
        title1 = "E2E Test 1"
        title2 = "E2E Test 2"
        title3 = "E2E Test 3"
        content1 = "E2E Content 1"
        content2 = "E2E Content 2"
        content3 = "E2E Content 3"

        reply1 = "E2E Reply 1"
        reply2 = "E2E Reply 2"
        reply3 = "E2E Reply 3"

        content1_attachment = self.create_thread(title1, content1, upload_attachment = True)
        self.reply_and_test(content1, reply1, first_post = True)
        self.create_thread(title2, content2)
        reply2_attachment = self.reply_and_test(content2, reply2, first_post = True, upload_attachment = True)
        self.create_thread(title3, content3)
        self.reply_and_test(content3, reply3, first_post = True)

        # Not Merging
        self.merge_threads(title1, None)
        self.merge_threads(title2, title1, press_cancel = True)

        self.find_posts(content1, must_exists = True, move_to_thread = title1, check_attachment = content1_attachment)
        self.find_posts(reply1, must_exists = True, move_to_thread = title1)
        self.find_posts(reply2, must_exists = True, move_to_thread = title2, check_attachment = reply2_attachment)
        self.find_posts(reply3, must_exists = True, move_to_thread = title3)

        # Merging success
        self.merge_threads(title2, title1, press_cancel = False)

        self.find_posts(content1, must_exists = True, move_to_thread = title1, check_attachment = content1_attachment)
        self.find_posts(reply1, must_exists = True)
        self.find_posts(content2, must_exists = True)
        self.find_posts(reply2, must_exists = True, check_attachment = reply2_attachment)
        self.find_posts(content3, must_exists = True, move_to_thread = title3)
        self.find_posts(reply3, must_exists = True)
        assert not self.thread_exists(title2)

        # Cleanup
        self.delete_thread(title3)
        self.delete_thread(title1)

if __name__ == "__main__":
    import unittest
    unittest.main()
