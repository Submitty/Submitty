rainfall = [ (2007, 45.4), (2008, 37.4), (2009, 49.3), (2010, 33.6), \
             (2011, 50.6), (2012, 43.8) ]

max_i = 0
i = 1
while i < len(rainfall):
    if rainfall[i][1] > rainfall[max_i][1]:
        max_i = i
    i += 1
print(rainfall[max_i][0], rainfall[max_i][1])
