print(out, cats)
  regular print out where the output is what is expected

print_extraLines(out, cats)
  print out with everything correct except that every line has an extra
  newline printed at the end

print_extraSpaces(out, cats)
  print out with everything correct excpet that every line has an extra
  space printed out at the end

print_lineOrder(out, cats)
  print out where the information is correctly spaced but the line 
  order is wrong (not sorted alphabetically by cat breed)

print_frontSpacing(out, cats)
  print out where everything is correct except that the spacing
  in front of the first column is wrong

print_columnSpacing(out, cats)
  print out where everything is correct except that the spacing
  betwen the first and second column is wrong

print_spacingOff(out, cats)
  print out where all information is correct and correctly sorted
  but all the spacing is off

print_spellingOff(out, cats)
  print out with correct order and spacing but the spelling of some words
  is off


AUTOGRADING RESULTS: 5 pts README & compilation, 4 pts for each of 4 test cases
print(out, cats)
  21/21     All 4 versions of Myers Diff gave full points.
  No problem. 

print_extraLines(out, cats)
  13/21     All four versions of Myers Diff gave half of the points (2/4) for an
            extra newline at the end of every line.
  myersDiffbyLinebyChar: expected given how picky the line by char is
  myersDiffbyLinebyWord: Kind of surprising because I thought the diff would be based
                         on the words there, not the spacing
  myersDiffbyLine:  expected given that there are more lines than the expected solution
  myersDiffbyLineNoWhite: Surprising. Expected to get full points since I thought that
                          no white would mean that it would ignore the extra newlines
  *note: want myersDiffbyLineNoWhite to also ignore extra newlines?

print_extraSpaces(out, cats)
  13/21     myersDiffbyLinebyChar and myersDiffbyLine gave no points
            myersDiffbyLinebyWord and myersDiffbyLineNo white gave full points
  myersDiffbyLinebyChar: expected. Everything is shown as red with the extra space
            highlighted in pale yellow (a bit hard to see)
  myersDiffbyLinebyWord: expected since it's supposed to compare by word
  myersDiffbyLine: kind of unexpected. Expected to get some points since the lines
            only had extra spaces at the end of the line. Also, diff comparision
            didn't seem to show the extra space in yellow at the end of the lines
            (just a block of red)
  myersDiffbyLineNoWhite: expected since it's supposed to ignore whitespace
  *note: would like myersDiffbyChar and myersDiffbyLine to give at least 1 or 2 points
         since the only thing wrong is one extra space at the end of the lines


print_lineOrder(out, cats)
  9/21      All four versions of Myers Diff gave 1 point for having the line order
            wrong
  myersDiffbyLinebyChar: expected
  myersDiffbyLinebyWord: kind of expected
  myersDiffbyLine: kind of expected 1 or 2 more points since the only thing wrong is the
             line order
  myersDiffbyLineNoWhite: expected
  *note: in a previous submission with line order off all test cases had given 2 points
         so points off kind of based on just how off the ordering is


print_frontSpacing(out, cats)
  15/21      myersDiffbyLinebyChar and myersDiffbyLine gave 1 point
             myersDiffbyLinebyWord adn myersDiffbyLineNo white gave full points
  myersDiffbyLinebyChar: expected - picky
  myersDiffbyLinebyWord: expected since it compares by word
  myesrDiffbyLine: kind of expected at this point in testing, still not satisfied with
             result though since the only thing wrong is a little bit of spacing
             and only the lines (not the spaces) are highlighted
  myersDiffbyLineNoWhite: expected since it ignores whitespace differences


print_columnSpacing(out, cats)
  17/21       myersDiffbyLinebyChar and myersDiffbyLine gave 2 points
              myersDiffbyLinebyWord and myersDiffbyLineNoWhite gave full points
  myersDiffbyLinebyChar: expected
  myersDiffbyLinebyWord: expected
  myersDiffbyLine: expected (at this point in testing)
  myersDiffbyLineNoWhite: expected
  *note: points given by myersDiffbyLinebyChar and myersDiffbyLine will probably
         differ based on how off the columns are


print_spacingOff(out, cats)
  11/21        myersDiffbyLinebyChar and myersDiffbyLine gave no points
               myersDiffbyLinebyWord and myersDiffbyLineNoWhite gave 3/4 points
  myersDiffbyLinebyChar: expected
  myersDiffbyLinebyWord: expected - with some spaces missing some words ended up
               being combined into one word
  myersDiffbyLine: expected (at this point in testing)
  myersDiffbyLineNoWhite: unexpected - expected to get full points for this 
               test case since whitespace should be completely ignored
               Also, does not highlight the extra whitespace.
  *note: seems that myersDiffbyLineNoWhite can take out extra whitespaces but
         cannot figure out where to add them back in so doesn't exactly ignore
         all whitespace differences


print_spellingOff(out, cats)
  5/21         All four versions of myersDiff gave no points
  myersDiffbyLinebyChar: expected
  myersDiffbyLinebyWord: expected
  myersDiffbyLine: expected
  myersDiffbyLineNoWhite: expected
  *note: myersDiffbyLine and myersDiffbyLineNoWhite don't hightlight the exact
         differences - they just show blocks of red


ANALYSIS:

myersDiffbyLinebyChar: picky as expected, could stand to give a little extra credit

myersDiffbyLinebyWord: ignores most whitespace differences (expect in the case where
                       one word is combined into two)

myersDiffbyLine: I have seen no reason so far for this version - it seems to basially
                 do what the by char version does but doesn't highlight the
                 differences in yellow

myersDiffbyLineNoWhite: acts almost the same as the by word version except doesn't 
                        show differences in yellow