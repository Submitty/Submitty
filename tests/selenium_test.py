from selenium import webdriver
driver = webdriver.PhantomJS()
driver.get("http://localhost/index.php?semester=f16&course=csci1000")
print(driver.page_source)
#assert "CSCI1000" in driver.title
elem = driver.find_element_by_name('user_id')
elem.send_keys("student")
elem = driver.find_element_by_name('password')
elem.send_keys("student")
driver.find_element_by_name('login').click()
#print(driver.page_source)
print(driver.current_url)
print(driver.page_source)
#assert "Joe" == driver.find_element_by_id("login-id").text
driver.close()