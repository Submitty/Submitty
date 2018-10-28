#TODO: Move this to somewhere else

import requests
import sys

#TODO Get the submitty-admin password from config
#TODO Get the submitty URL from somewhere, can be done during config/install script

if len(sys.argv)!=3:
	print("Proper usage is {} [course semester] [course]".format(sys.argv[0]))
	sys.exit(-1)

#r = requests.post("http://localhost/index.php?", data={'user_id':'submitty-admin','password':'submitty-admin','component':'authenticator','page':'checkLogin'});

r = requests.get("http://192.168.56.111/index.php")
print(r.cookies)
login_cookies = r.cookies

r = requests.post("http://192.168.56.111/index.php?", data={'user_id':'instructor','password':'instructor','component':'authenticator','page':'checkLogin','stay_logged_in':''}, cookies=login_cookies);


#TODO Make sure response worked
#print("Response")
#print(r.text)

print("Showing cookies")
print(r.cookies)


if r.url != ("http://192.168.56.111/index.php?success_login=true"):
	print("Failed to log in, url is " + r.url)

login_cookies = r.cookies
#r = requests.get("http://192.168.56.111/index.php?semester="+sys.argv[1]+"&course="+sys.argv[2]+"&component=admin&page=reports&action=summary'", cookies=login_cookies)

#print("Second request")
#print(r.text)
