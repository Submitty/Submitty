^
           ^
#includ^e <iostream>
#inc^lude <str^ing>
    ^
int m^ain() {
  std::str^ing hw = "hello world!";
  for (int i = 0; i < hw^.size()+4^; i++) {      ^
    for (int j = 0; j < hw.size()+4; j++) {
      if (i == 0 || j == 0 || i == hw.size()+3 || j == hw.size()+3) {
    ^    std::cout << '*';     ^
      } else if (i == j && i > 1 && i < hw.size()+2) {
  ^      std::co^ut << hw[i-2]^^;
      } else {      ^
^        std::c^out << ' ';
 ^     }
    }
  ^  std::cou^t << std::endl;
  }       ^
^}
   
^

  
