from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from random import choice
from string import ascii_lowercase
import urllib.parse


class TestOfficeHoursQueue(BaseTestCase):
    """
    Test cases revolving around the office hours queue
    """
    def __init__(self, testname):
        super().__init__(testname, user_id="instructor", user_password="instructor", user_name="Quinn", use_websockets=True, socket_page='office_hours_queue')

    def test_office_hours_queue(self):
        # Turn the queue on
        enableQueue(self)

        # Delete any old queues (this should remove anyone that was currently in the queue as well)
        self.deleteAllQueues()

        base_queue_history_count = self.queueHistoryCount(False)
        self.openQueue("custom code", "this queue rocks")
        self.expectedAlerts(1, 0, success_text=['New queue added'], error_text=[])
        self.openQueue("custom code", "same name new code")
        self.expectedAlerts(0, 1, success_text=[], error_text=['Unable to add queue. Make sure you have a unique queue name'])
        self.openQueue("random code")
        self.expectedAlerts(1, 0, success_text=['New queue added'], error_text=[])
        self.changeQueueCode("random code")
        self.expectedAlerts(1, 0, success_text=['Queue Access Code Changed'], error_text=[])
        self.changeQueueCode("custom code", "new code")
        self.expectedAlerts(1, 0, success_text=['Queue Access Code Changed'], error_text=[])

        self.switchToUser('student')
        base_queue_history_count_student = self.queueHistoryCount(False)
        self.studentJoinQueue('custom code', 'new code')
        self.expectedAlerts(1, 0, success_text=['Added to queue'], error_text=[])
        self.studentRemoveSelfFromQueue()
        self.expectedAlerts(1, 0, success_text=['Removed from queue'], error_text=[])
        self.assertEqual(base_queue_history_count_student+1, self.queueHistoryCount(False))
        self.studentJoinQueue('custom code', 'new code')
        self.expectedAlerts(1, 0, success_text=['Added to queue'], error_text=[])

        self.switchToUser('instructor')
        self.assertEqual(1, self.currentQueueCount())
        self.helpFirstStudent()
        self.expectedAlerts(1, 0, success_text=['Started helping student'], error_text=[])
        self.assertEqual(1, self.currentQueueCount())

        self.switchToUser('student')
        self.studentFinishHelpSelf()
        self.expectedAlerts(1, 0, success_text=['Finished helping student'], error_text=[])
        self.assertEqual(base_queue_history_count_student+2, self.queueHistoryCount(False))
        self.studentJoinQueue('custom code', 'new code')
        self.expectedAlerts(1, 0, success_text=['Added to queue'], error_text=[])

        self.switchToUser('aphacker')
        base_queue_history_count_aphacker = self.queueHistoryCount(False)
        self.studentJoinQueue('custom code', 'new code', 'nick name hacker')
        self.expectedAlerts(1, 0, success_text=['Added to queue'], error_text=[])

        self.switchToUser('instructor')
        self.assertEqual(2, self.currentQueueCount())
        self.helpFirstStudent()
        self.expectedAlerts(1, 0, success_text=['Started helping student'], error_text=[])
        self.assertEqual(base_queue_history_count+2, self.queueHistoryCount(False))
        self.finishHelpFirstStudent()
        self.expectedAlerts(1, 0, success_text=['Finished helping student'], error_text=[])
        self.assertEqual(base_queue_history_count+3, self.queueHistoryCount(False))
        self.assertEqual(1, self.currentQueueCount())
        self.removeFirstStudent()
        self.expectedAlerts(1, 0, success_text=['Removed from queue'], error_text=[])
        self.assertEqual(base_queue_history_count+4, self.queueHistoryCount(False))
        self.assertEqual(0, self.currentQueueCount())
        self.restoreFirstStudent()
        self.expectedAlerts(1, 0, success_text=['Student restored'], error_text=[])
        self.assertEqual(base_queue_history_count+3, self.queueHistoryCount(False))
        self.assertEqual(1, self.currentQueueCount())
        self.restoreFirstStudent()
        self.expectedAlerts(1, 0, success_text=['Student restored'], error_text=[])
        self.assertEqual(base_queue_history_count+2, self.queueHistoryCount(False))
        self.assertEqual(2, self.currentQueueCount())
        self.restoreFirstStudent()#should fail because the student is already in the queue
        self.expectedAlerts(0, 1, success_text=[], error_text=['Cannot restore a user that is currently in the queue. Please remove them first.'])
        self.assertEqual(base_queue_history_count+2, self.queueHistoryCount(False))
        self.assertEqual(2, self.currentQueueCount())
        self.toggleFirstQueueFilter()#turn 'custom code' off
        self.assertEqual(0, self.currentQueueCount())
        self.toggleFirstQueueFilter()#turn 'custom code' back on
        self.assertEqual(2, self.currentQueueCount())
        self.openFilterSettings()
        self.closeFirstQueue()
        self.openFilterSettings()
        self.emptyFirstQueue()
        self.expectedAlerts(1, 0, success_text=['Queue emptied'], error_text=[])
        self.assertEqual(base_queue_history_count+4, self.queueHistoryCount(False))
        self.assertEqual(0, self.currentQueueCount())
        announcement_string = ''.join(choice(ascii_lowercase) for i in range(10))
        self.editAnnouncement(announcement_string)
        self.assertEqual(' '.join(self.driver.find_element(By.ID, 'announcement').text.split()), f"Office Hours Queue Announcements: {announcement_string}")
        self.editAnnouncement("")
        self.assertEqual(True, self.verifyElementMissing('id', ['announcement']))

        self.switchToUser('student')
        # Students should not be able to see any of theses elements
        self.assertEqual(True, self.verifyElementMissing('class', ['help_btn','finish_helping_btn','remove_from_queue_btn','queue_restore_btn','close_queue_btn','empty_queue_btn']))
        self.assertEqual(True, self.verifyElementMissing('id', ['toggle_filter_settings', 'new_queue_code', 'new_queue_token', 'new_queue_rand_token', 'open_new_queue_btn']))

        # Turn the queue off
        disableQueue(self)

    def goToQueuePage(self):
        queue_url = f"courses/{self.semester}/sample/office_hours_queue"
        self.get(queue_url)
        self.assertEqual(self.driver.current_url, self.test_url+"/"+queue_url)

    def openFilterSettings(self):
        self.goToQueuePage()
        self.assertEqual(True,self.driver.execute_script("return $('#filter-settings').is(':hidden')"))
        self.driver.find_element(By.ID, 'toggle_filter_settings').click()
        self.assertEqual(False,self.driver.execute_script("return $('#filter-settings').is(':hidden')"))

    def closeFilterSettings(self):
        self.assertEqual(False,self.driver.execute_script("return $('#filter-settings').is(':hidden')"))
        self.driver.find_element(By.XPATH, '//*[@id="filter-settings"]//*[@class="form-button-container"]/*').click()
        self.assertEqual(True,self.driver.execute_script("return $('#filter-settings').is(':hidden')"))

    def openAnnouncementSettings(self):
        self.goToQueuePage()
        self.assertEqual(True,self.driver.execute_script("return $('#announcement-settings').is(':hidden')"))
        self.driver.find_element(By.ID, 'toggle_announcement_settings').click()
        self.assertEqual(False,self.driver.execute_script("return $('#announcement-settings').is(':hidden')"))

    def saveAnnouncementSettings(self):
        self.assertEqual(False,self.driver.execute_script("return $('#announcement-settings').is(':hidden')"))
        self.driver.find_element(By.ID, 'save_announcement').click()
        self.check_socket_message('announcement_update')
        self.assertEqual(True,self.driver.execute_script("return $('#announcement-settings').is(':hidden')"))

    def editAnnouncement(self, text):
        self.openAnnouncementSettings()
        self.driver.find_element(By.ID, 'queue-announcement-message').clear()
        self.driver.find_element(By.ID, 'queue-announcement-message').send_keys(text)
        self.saveAnnouncementSettings()

    def deleteAllQueues(self):
        self.openFilterSettings()
        while(self.driver.find_elements(By.CLASS_NAME, 'delete_queue_btn')):
            self.driver.find_element(By.CLASS_NAME, 'delete_queue_btn').click()
            self.driver.switch_to.alert.accept()
            self.check_socket_message('full_update')
            self.openFilterSettings()
        self.closeFilterSettings()

    def openQueue(self, name, code=None):
        self.openFilterSettings()
        self.driver.find_element(By.ID, 'new_queue_code').send_keys(name)
        if(code):
            self.driver.find_element(By.ID, 'new_queue_token').send_keys(code)
        else:
            self.driver.find_element(By.ID, 'new_queue_rand_token').click()
        self.driver.find_element(By.ID, 'open_new_queue_btn').click()

    def changeQueueCode(self, name, code=None):
        self.openFilterSettings()
        self.driver.find_element(By.XPATH, f'//*[@id="old_queue_code"]/option[text()="{name}"]').click()
        if(code):
            self.driver.find_element(By.ID, 'old_queue_token').send_keys(code)
        else:
            self.driver.find_element(By.ID, 'old_queue_rand_token').click()
        self.driver.find_element(By.ID, 'change_code_btn').click()

    def switchToUser(self, account):
        self.log_out()
        self.log_in(user_id=account, user_password=account)
        self.goToQueuePage()

    def studentJoinQueue(self, queueName, queueCode, studentName=None):
        if(studentName):
            self.driver.find_element(By.ID, 'name_box').send_keys(studentName)
        select = Select(self.driver.find_element(By.ID, 'queue_code'))
        select.select_by_visible_text(queueName)
        self.driver.find_element(By.ID, 'token_box').send_keys(queueCode)
        self.assertIn(urllib.parse.quote(queueName), self.driver.find_element(By.ID, 'add_to_queue').get_attribute('action'))
        self.assertEqual(queueCode, self.driver.find_element(By.ID, 'token_box').get_attribute('value'))
        self.driver.find_element(By.ID, 'join_queue_btn').click()
        self.check_socket_message('queue_update')

    def studentRemoveSelfFromQueue(self):
        self.wait_for_element((By.ID, 'leave_queue'))
        self.driver.find_element(By.ID, 'leave_queue').click()
        self.driver.switch_to.alert.accept()
        self.check_socket_message('full_update')

    def studentFinishHelpSelf(self):
        self.wait_for_element((By.ID, 'self_finish_help'))
        self.driver.find_element(By.ID, 'self_finish_help').click()
        self.driver.switch_to.alert.accept()
        self.check_socket_message('full_update')

    def helpFirstStudent(self):
        self.wait_for_element((By.CLASS_NAME, 'help_btn'))
        self.driver.find_element(By.CLASS_NAME, 'help_btn').click()
        self.check_socket_message('queue_status_update')

    def finishHelpFirstStudent(self):
        self.wait_for_element((By.CLASS_NAME, 'finish_helping_btn'))
        self.driver.find_element(By.CLASS_NAME, 'finish_helping_btn').click()
        self.check_socket_message('full_update')

    def removeFirstStudent(self):
        self.driver.find_element(By.CLASS_NAME, 'remove_from_queue_btn').click()
        self.driver.switch_to.alert.accept()
        self.check_socket_message('full_update')

    def restoreFirstStudent(self):
        self.wait_for_element((By.CLASS_NAME, 'queue_restore_btn'))
        self.driver.find_element(By.CLASS_NAME, 'queue_restore_btn').click()
        self.driver.switch_to.alert.accept()
        self.check_socket_message('queue_status_update')


    #this checks how many visible students are in the queue
    def toggleFirstQueueFilter(self):
        self.wait_for_element((By.CLASS_NAME, f'filter-buttons'))
        self.driver.find_element(By.CLASS_NAME, f'filter-buttons').click()

    def closeFirstQueue(self):
        self.wait_for_element((By.CLASS_NAME, 'toggle-queue-checkbox'))
        self.driver.find_element(By.CLASS_NAME, 'toggle-queue-checkbox').click()
        self.check_socket_message('toggle_queue')

    def emptyFirstQueue(self):
        self.wait_for_element((By.CLASS_NAME, 'empty_queue_btn'))
        self.driver.find_element(By.CLASS_NAME, 'empty_queue_btn').click()
        self.driver.switch_to.alert.accept()
        self.check_socket_message('full_update')

    def currentQueueCount(self):
        return len(self.driver.find_elements(By.CLASS_NAME, "shown_queue_row"))

    # counts the number of rows in the queue history
    # if limited is false it will get the fully history otherwise it will only get the limited history
    def queueHistoryCount(self, limited=True):
        if(limited):
            return len(self.driver.find_elements(By.CLASS_NAME, "queue_history_row"))
        else:
            if('full_history=true' not in self.driver.current_url):
                self.driver.find_element(By.ID, 'view_history_button').click()

            count = len(self.driver.find_elements(By.CLASS_NAME, "queue_history_row"))
            self.driver.find_element(By.ID, 'view_history_button').click()
            return count

    # type is 'id' or 'class'
    def verifyElementMissing(self, type, values):
        for value in values:
            if(type == 'id'):
                try:
                    self.driver.find_element(By.ID, value)
                    return False
                except:
                    pass
            elif(type == 'class'):
                try:
                    self.driver.find_element(By.CLASS_NAME, value)
                    return False
                except:
                    pass
            else:
                print(f"invalid type: {type}")
                print("make sure the test code is correct")
                exit(1)
        return True

    def countAlertSuccess(self, text=[]):
        alerts = self.driver.find_elements(By.CLASS_NAME, "alert-success")
        alerts = [ x.text for x in alerts ]
        self.assertEqual(set(alerts), set(text))
        return len(alerts)

    def countAlertError(self, text=[]):
        alerts = self.driver.find_elements(By.CLASS_NAME, "alert-error")
        alerts = [ x.text for x in alerts ]
        self.assertEqual(set(alerts), set(text))
        return len(alerts)

    def expectedAlerts(self, success=0, error=0, success_text=[], error_text=[]):
        if(success > 0):
            self.wait_for_element((By.CLASS_NAME, 'alert-success'))
        if(error > 0):
            self.wait_for_element((By.CLASS_NAME, 'alert-error'))
        self.assertEqual(self.countAlertSuccess(success_text), success)
        self.assertEqual(self.countAlertError(error_text), error)


def enableQueue(self):
    self.get(f"/courses/{self.semester}/sample/config")
    self.wait_for_element((By.ID, 'queue-enabled'))
    if(not self.driver.find_element(By.ID, 'queue-enabled').is_selected()):
        self.driver.find_element(By.ID, 'queue-enabled').click()
    if(self.driver.find_element(By.ID, 'queue-contact-info').is_selected()):
        self.driver.find_element(By.ID, 'queue-contact-info').click()

    self.assertEqual(True, self.driver.find_element(By.ID, 'queue-enabled').is_selected())
    self.assertEqual(False, self.driver.find_element(By.ID, 'queue-contact-info').is_selected())


def disableQueue(self):
    self.log_out()
    self.log_in(user_id='instructor')
    self.get(f"/courses/{self.semester}/sample/config")
    self.wait_for_element((By.ID, 'queue-enabled'))
    if(self.driver.find_element(By.ID, 'queue-enabled').is_selected()):
        self.driver.find_element(By.ID, 'queue-enabled').click()
    self.assertEqual(False, self.driver.find_element(By.ID, 'queue-enabled').is_selected())
