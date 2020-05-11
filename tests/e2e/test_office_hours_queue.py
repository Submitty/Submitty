from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select

class TestOfficeHoursQueue(BaseTestCase):
    """
    Test cases revolving around the office hours queue
    """
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_office_hours_queue(self):
        self.log_in(user_id='instructor', user_password='instructor')

        # Turn the queue on
        self.get(self.get_current_semester()+"/sample/config")
        if(not self.driver.find_element(By.ID, 'queue-enabled').is_selected()):
            self.driver.find_element(By.ID, 'queue-enabled').click()
        if(self.driver.find_element(By.ID, 'queue-contact-info').is_selected()):
            self.driver.find_element(By.ID, 'queue-contact-info').click()

        # Delete any old queues (this should remove anyone that was currently in the queue as well)
        deleteAllQueues(self)

        base_queue_history_count = queueHistoryCount(self, False)

        openQueue(self, "custom code", "this queue rocks")
        expectedAlerts(self, 1, 0, success_text=['New queue added'], error_text=[])
        openQueue(self, "custom code", "same name new code")
        expectedAlerts(self, 0, 1, success_text=[], error_text=['Unable to add queue. Make sure you have a unique queue name'])
        openQueue(self, "random code")
        expectedAlerts(self, 1, 0, success_text=['New queue added'], error_text=[])

        changeQueueCode(self, "random code")
        expectedAlerts(self, 1, 0, success_text=['Queue Access Code Changed'], error_text=[])
        changeQueueCode(self, "custom code", "new code")
        expectedAlerts(self, 1, 0, success_text=['Queue Access Code Changed'], error_text=[])

        switchToStudent(self, 'student')
        base_queue_history_count_student = queueHistoryCount(self, False)
        studentJoinQueue(self, 'custom code', 'new code')
        expectedAlerts(self, 1, 0, success_text=['Added to queue'], error_text=[])
        studentRemoveSelfFromQueue(self)
        expectedAlerts(self, 1, 0, success_text=['Removed from queue'], error_text=[])
        self.assertEqual(base_queue_history_count_student+1, queueHistoryCount(self, False))
        studentJoinQueue(self, 'custom code', 'new code')
        expectedAlerts(self, 1, 0, success_text=['Added to queue'], error_text=[])
        switchToInstructor(self, 'instructor')

        self.assertEqual(1, currentQueueCount(self))
        helpFirstStudent(self)
        expectedAlerts(self, 1, 0, success_text=['Started helping student'], error_text=[])
        self.assertEqual(1, currentQueueCount(self))

        switchToStudent(self, 'student')
        studentFinishHelpSelf(self)
        expectedAlerts(self, 1, 0, success_text=['Finished helping student'], error_text=[])
        self.assertEqual(base_queue_history_count_student+2, queueHistoryCount(self, False))
        studentJoinQueue(self, 'custom code', 'new code')
        expectedAlerts(self, 1, 0, success_text=['Added to queue'], error_text=[])
        switchToStudent(self, 'aphacker')
        base_queue_history_count_aphacker = queueHistoryCount(self, False)
        studentJoinQueue(self, 'custom code', 'new code', 'nick name hacker')
        expectedAlerts(self, 1, 0, success_text=['Added to queue'], error_text=[])
        switchToInstructor(self, 'instructor')
        self.assertEqual(2, currentQueueCount(self))
        helpFirstStudent(self)
        expectedAlerts(self, 1, 0, success_text=['Started helping student'], error_text=[])
        self.assertEqual(base_queue_history_count+2, queueHistoryCount(self, False))
        finishHelpFirstStudent(self)
        expectedAlerts(self, 1, 0, success_text=['Finished helping student'], error_text=[])
        self.assertEqual(base_queue_history_count+3, queueHistoryCount(self, False))
        self.assertEqual(1, currentQueueCount(self))
        removeFirstStudent(self)
        expectedAlerts(self, 1, 0, success_text=['Removed from queue'], error_text=[])
        self.assertEqual(base_queue_history_count+4, queueHistoryCount(self, False))
        self.assertEqual(0, currentQueueCount(self))
        restoreFirstStudent(self)
        expectedAlerts(self, 1, 0, success_text=['Student restored'], error_text=[])
        self.assertEqual(base_queue_history_count+3, queueHistoryCount(self, False))
        self.assertEqual(1, currentQueueCount(self))
        restoreFirstStudent(self)
        expectedAlerts(self, 1, 0, success_text=['Student restored'], error_text=[])
        self.assertEqual(base_queue_history_count+2, queueHistoryCount(self, False))
        self.assertEqual(2, currentQueueCount(self))
        restoreFirstStudent(self)#should fail because the student is already in the queue
        expectedAlerts(self, 0, 1, success_text=[], error_text=['Cannot restore a user that is currently in the queue. Please remove them first.'])
        self.assertEqual(base_queue_history_count+2, queueHistoryCount(self, False))
        self.assertEqual(2, currentQueueCount(self))
        openFilterSettings(self)
        toggleQueueFilter(self, 'custom code')#turn it off
        self.assertEqual(0, currentQueueCount(self))
        toggleQueueFilter(self, 'custom code')#turn it back on
        self.assertEqual(2, currentQueueCount(self))
        closeFirstQueue(self)
        expectedAlerts(self, 1, 0, success_text=['Closed queue: "custom_code"'], error_text=[])
        openFilterSettings(self)
        emptyFirstQueue(self)
        expectedAlerts(self, 1, 0, success_text=['Queue emptied'], error_text=[])
        self.assertEqual(base_queue_history_count+4, queueHistoryCount(self, False))
        self.assertEqual(0, currentQueueCount(self))
        switchToStudent(self, 'student')

        # Students should not be able to see any of theses elements
        self.assertEqual(True, verifyElementMissing(self, 'class', ['help_btn','finish_helping_btn','remove_from_queue_btn','queue_restore_btn','close_queue_btn','empty_queue_btn']))
        self.assertEqual(True, verifyElementMissing(self, 'id', ['toggle_filter_settings', 'new_queue_code', 'new_queue_token', 'new_queue_rand_token', 'open_new_queue_btn']))

        switchToInstructor(self, 'instructor')
        deleteAllQueues(self)

        self.get(self.get_current_semester()+"/sample/config")
        self.driver.find_element(By.ID, 'queue-enabled').click()

        self.get(self.test_url)

def goToQueuePage(self):
    queue_url = self.get_current_semester()+"/sample/office_hours_queue"
    self.get(queue_url)
    self.assertEqual(self.driver.current_url, self.test_url+"/"+queue_url)

def openFilterSettings(self):
    goToQueuePage(self)
    self.assertEqual(True,self.driver.execute_script("return $('#filterSettingsCollapse').is(':hidden')"))
    self.driver.find_element(By.ID, 'toggle_filter_settings').click()
    self.assertEqual(False,self.driver.execute_script("return $('#filterSettingsCollapse').is(':hidden')"))


def deleteAllQueues(self):
    openFilterSettings(self)
    while(self.driver.find_elements(By.CLASS_NAME, 'delete_queue_btn')):
        self.driver.find_element(By.CLASS_NAME, 'delete_queue_btn').click()
        self.driver.switch_to.alert.accept()
        openFilterSettings(self)

def openQueue(self, name, code=None):
    openFilterSettings(self)
    self.driver.find_element(By.ID, 'new_queue_code').send_keys(name)
    if(code):
        self.driver.find_element(By.ID, 'new_queue_token').send_keys(code)
    else:
        self.driver.find_element(By.ID, 'new_queue_rand_token').click()
    self.driver.find_element(By.ID, 'open_new_queue_btn').click()

def changeQueueCode(self, name, code=None):
    openFilterSettings(self)
    self.driver.find_element(By.XPATH, f'//*[@id="old_queue_code"]/option[text()="{name}"]').click()
    if(code):
        self.driver.find_element(By.ID, 'old_queue_token').send_keys(code)
    else:
        self.driver.find_element(By.ID, 'old_queue_rand_token').click()
    self.driver.find_element(By.ID, 'change_code_btn').click()

def switchToStudent(self, account):
    self.log_out()
    self.log_in(user_id=account, user_password=account)
    goToQueuePage(self)

def switchToInstructor(self, account):
    self.log_out()
    self.log_in(user_id=account, user_password=account)
    goToQueuePage(self)

def studentJoinQueue(self, queueName, queueCode, studentName=None):
    if(studentName):
        self.driver.find_element(By.ID, 'name_box').send_keys(studentName)
    select = Select(self.driver.find_element(By.ID, 'queue_code'))
    select.select_by_visible_text(queueName)
    self.driver.find_element(By.ID, 'token_box').send_keys(queueCode)
    self.assertIn("_".join(queueName.split()), self.driver.find_element(By.ID, 'add_to_queue').get_attribute('action'))
    self.assertEqual(queueCode, self.driver.find_element(By.ID, 'token_box').get_attribute('value'))
    self.driver.find_element(By.ID, 'join_queue_btn').click()

def studentRemoveSelfFromQueue(self):
    self.driver.find_element(By.ID, 'leave_queue').click()
    self.driver.switch_to.alert.accept()

def studentFinishHelpSelf(self):
    self.driver.find_element(By.ID, 'self_finish_help').click()
    self.driver.switch_to.alert.accept()

def helpFirstStudent(self):
    self.driver.find_element(By.CLASS_NAME, 'help_btn').click()

def finishHelpFirstStudent(self):
    self.driver.find_element(By.CLASS_NAME, 'finish_helping_btn').click()

def removeFirstStudent(self):
    self.driver.find_element(By.CLASS_NAME, 'remove_from_queue_btn').click()
    self.driver.switch_to.alert.accept()

def restoreFirstStudent(self):
    self.driver.find_element(By.CLASS_NAME, 'queue_restore_btn').click()
    self.driver.switch_to.alert.accept()

#this checks how many visible students are in the queue
def toggleQueueFilter(self, code):
    self.driver.find_element(By.ID, f'queue_filter_{code.replace(" ", "_").upper()}').click()

def closeFirstQueue(self):
    self.driver.find_element(By.CLASS_NAME, 'close_queue_btn').click()

def emptyFirstQueue(self):
    self.driver.find_element(By.CLASS_NAME, 'empty_queue_btn').click()
    self.driver.switch_to.alert.accept()


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
    self.assertEqual(countAlertError(self, error_text), error)
    self.assertEqual(countAlertSuccess(self, success_text), success)
