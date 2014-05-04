all:
	g++ -g runner.cpp -o runner
	rm -r test_user_after || true
	cp -r test_user test_user_after
	./runner test_user_after 1