from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By

class TestOfficeHoursQueue(BaseTestCase):
    """
    Test cases revolving around the logging in functionality of the site
    """
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_office_hours_queue(self):
        self.log_in(user_id='instructor', user_password='instructor')

        # Turn the queue on
        self.get(self.get_current_semester()+"/development/config")
        self.driver.execute_script("document.getElementById('queue-enabled').checked = false;")#turn the queue off
        self.driver.find_element_by_id('queue-enabled').click()#click the button to test it actually saving as well as turn it on

        # Delete any old queues (this should remove anyone that was currently in the queue as well)
        deleteAllQueues(self)

        openQueue(self, "custom code", "this queue rocks")
        openQueue(self, "random code")

        changeQueueCode(self, "random code")
        changeQueueCode(self, "custom code", "new code")

        switchToStudent(self, 'student')
        studentJoinQueue(self, 'custom code', 'new code')
        studentRemoveSelfFromQueue(self)
        studentJoinQueue(self, 'custom code', 'new code')
        switchToInstructor(self, 'instructor')
        helpFirstStudent(self)
        switchToStudent(self, 'student')
        studentFinishHelpSelf(self)
        studentJoinQueue(self, 'custom code', 'new code')
        switchToStudent(self, 'aphacker')
        studentJoinQueue(self, 'custom code', 'new code', 'nick name hacker')
        switchToInstructor(self, 'instructor')
        helpFirstStudent(self)
        finishHelpFirstStudent(self)
        removeFirstStudent(self)




        # deleteAllQueues(self)
        # This must be at the end otherwise sometimes the last command will not finish before the browser is closed
        goToQueuePage(self)
        # self.wait_user_input()

def goToQueuePage(self):
    queue_url = self.get_current_semester()+"/development/office_hours_queue"
    self.get(queue_url)
    self.assertEqual(self.driver.current_url, self.test_url+"/"+queue_url)

def openFilterSettings(self):
    goToQueuePage(self)
    self.assertEqual(True,self.driver.execute_script("return $('#filgerSettingsCollapse').is(':hidden')"))
    self.driver.find_element_by_id('toggle_filter_settings').click()
    self.assertEqual(False,self.driver.execute_script("return $('#filgerSettingsCollapse').is(':hidden')"))


def deleteAllQueues(self):
    openFilterSettings(self)
    while(self.driver.find_elements_by_class_name('delete_queue_btn')):
        self.driver.find_element_by_class_name('delete_queue_btn').click()
        self.driver.switch_to.alert.accept()
        openFilterSettings(self)

def openQueue(self, name, code=None):
    openFilterSettings(self)
    self.driver.find_element_by_id('new_queue_code').send_keys(name)
    if(code):
        self.driver.find_element_by_id('new_queue_token').send_keys(code)
    else:
        self.driver.find_element_by_id('new_queue_rand_token').click()
    self.driver.find_element_by_id('open_new_queue_btn').click()

def changeQueueCode(self, name, code=None):
    openFilterSettings(self)
    self.driver.find_element_by_xpath(f'//*[@id="old_queue_code"]/option[text()="{name}"]').click()
    if(code):
        self.driver.find_element_by_id('old_queue_token').send_keys(code)
    else:
        self.driver.find_element_by_id('old_queue_rand_token').click()
    self.driver.find_element_by_id('change_code_btn').click()

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
        self.driver.find_element_by_id('name_box').send_keys(studentName)
    self.driver.find_element_by_xpath(f'//*[@id="queue_code"]/option[text()="{queueName}"]').click()
    self.driver.find_element_by_id('token_box').send_keys(queueCode)
    self.driver.find_element_by_id('join_queue_btn').click()

def studentRemoveSelfFromQueue(self):
    self.driver.find_element_by_id('leave_queue').click()
    self.driver.switch_to.alert.accept()

def studentFinishHelpSelf(self):
    self.driver.find_element_by_id('self_finish_help').click()
    self.driver.switch_to.alert.accept()

def helpFirstStudent(self):
    self.driver.find_element_by_class_name('help_btn').click()

def finishHelpFirstStudent(self):
    self.driver.find_element_by_class_name('finish_helping_btn').click()

def removeFirstStudent(self):
    self.driver.find_element_by_class_name('remove_from_queue_btn').click()
    self.driver.switch_to.alert.accept()
