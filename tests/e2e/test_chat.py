import os
import time
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from tests.e2e.base_test_case import BaseTestCase


class TestChatWebSocket(BaseTestCase):
    """
    Test live chat WebSocket functionality including real-time updates
    """
    
    def test_chatroom_edit_updates_via_websocket(self):
        """
        Test that when a chatroom is edited, other users see updates in real-time
        without page reload
        """
        # Login as instructor
        self.login_user("instructor")
        
        # Create a chatroom
        self.driver.find_element(By.LINK_TEXT, "Live Chat").click()
        self.driver.find_element(By.ID, "new-chatroom-btn").click()
        
        title_input = self.driver.find_element(By.ID, "chatroom-title-input")
        desc_input = self.driver.find_element(By.ID, "chatroom-description-input")
        
        title_input.send_keys("Test Chatroom")
        desc_input.send_keys("Initial Description")
        self.driver.find_element(By.ID, "create-chatroom-submit").click()
        
        # Get chatroom ID from the URL or page
        chatroom_row = self.driver.find_element(By.XPATH, "//tr[contains(., 'Test Chatroom')]")
        chatroom_id = chatroom_row.get_attribute("id")
        
        # Open second browser session as student
        student_driver = self.create_second_driver()
        self.login_user("student", driver=student_driver)
        student_driver.find_element(By.LINK_TEXT, "Live Chat").click()
        
        # Wait for student to see the chatroom
        WebDriverWait(student_driver, 10).until(
            EC.presence_of_element_located((By.XPATH, f"//tr[@id='{chatroom_id}']"))
        )
        
        # Instructor edits the chatroom
        self.driver.find_element(By.XPATH, f"//tr[@id='{chatroom_id}']//button[contains(@class, 'edit-btn')]").click()
        
        edit_title = self.driver.find_element(By.ID, "chatroom-title-input")
        edit_desc = self.driver.find_element(By.ID, "chatroom-description-input")
        
        # Clear and update fields
        edit_title.clear()
        edit_title.send_keys("Updated Chatroom Title")
        edit_desc.clear()
        edit_desc.send_keys("Updated Description")
        
        self.driver.find_element(By.ID, "edit-chatroom-submit").click()
        
        # Verify student sees the updates without reload
        WebDriverWait(student_driver, 10).until(
            EC.text_to_be_present_in_element(
                (By.XPATH, f"//tr[@id='{chatroom_id}']//td[contains(@class, 'chat-room-title')]"), 
                "Updated Chatroom Title"
            )
        )
        
        WebDriverWait(student_driver, 10).until(
            EC.text_to_be_present_in_element(
                (By.XPATH, f"//tr[@id='{chatroom_id}']//td[contains(@class, 'chat-room-description')]"), 
                "Updated Description"
            )
        )
        
        # Cleanup
        student_driver.quit()
    
    def test_chatroom_header_updates_via_websocket(self):
        """
        Test that users in a chatroom see header updates when room is edited
        """
        # Login as instructor and student
        self.login_user("instructor")
        student_driver = self.create_second_driver()
        self.login_user("student", driver=student_driver)
        
        # Instructor creates and opens chatroom
        self.driver.find_element(By.LINK_TEXT, "Live Chat").click()
        self.driver.find_element(By.ID, "new-chatroom-btn").click()
        
        title_input = self.driver.find_element(By.ID, "chatroom-title-input")
        desc_input = self.driver.find_element(By.ID, "chatroom-description-input")
        
        title_input.send_keys("Header Test Chatroom")
        desc_input.send_keys("Initial Header Description")
        self.driver.find_element(By.ID, "create-chatroom-submit").click()
        
        # Open the chatroom
        chatroom_row = self.driver.find_element(By.XPATH, "//tr[contains(., 'Header Test Chatroom')]")
        chatroom_id = chatroom_row.get_attribute("id")
        self.driver.find_element(By.XPATH, f"//tr[@id='{chatroom_id}']//a[contains(@class, 'join-btn')]").click()
        
        # Student joins the same chatroom
        student_driver.find_element(By.LINK_TEXT, "Live Chat").click()
        student_driver.find_element(By.XPATH, f"//tr[@id='{chatroom_id}']//a[contains(@class, 'join-btn')]").click()
        
        # Instructor edits the chatroom while both are inside
        self.driver.find_element(By.ID, "edit-chatroom-btn").click()
        
        edit_title = self.driver.find_element(By.ID, "chatroom-title-input")
        edit_desc = self.driver.find_element(By.ID, "chatroom-description-input")
        
        edit_title.clear()
        edit_title.send_keys("Live Updated Title")
        edit_desc.clear()
        edit_desc.send_keys("Live Updated Description")
        
        self.driver.find_element(By.ID, "edit-chatroom-submit").click()
        
        # Verify student sees header updates without reload
        WebDriverWait(student_driver, 10).until(
            EC.text_to_be_present_in_element(
                (By.CLASS_NAME, "room-title"), 
                "Live Updated Title"
            )
        )
        
        WebDriverWait(student_driver, 10).until(
            EC.text_to_be_present_in_element(
                (By.CLASS_NAME, "room-description"), 
                "Live Updated Description"
            )
        )
        
        # Cleanup
        student_driver.quit()


if __name__ == "__main__":
    import unittest
    unittest.main()