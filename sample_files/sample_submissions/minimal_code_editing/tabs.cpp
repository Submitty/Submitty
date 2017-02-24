#include <iostream>
#include <string>

int main() {
	std::string hw = "hello world!";
	for (int i = 0; i < hw.size()+4; i++) {
		for (int j = 0; j < hw.size()+4; j++) {
			if (i == 0 || j == 0 || i == hw.size()+3 || j == hw.size()+3) {
				std::cout << '*';
			} else if (i == j && i > 1 && i < hw.size()+2) {
				std::cout << hw[i-2];
			} else {
				std::cout << ' ';
			}
		}
		std::cout << std::endl;
	}
}
