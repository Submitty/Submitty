import sys
days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]

def is_leap_year ( year ):
    return year % 4 == 0 and ( year % 100 != 0 or year % 400 == 0 ) 

def julian_day (month, day, year):
    jday = 0
    for m in range(month):
        jday += days_in_month[m]
        if m == 2 and is_leap_year(year):
            jday += 1

    jday += day
    return jday 

def main():
    print("Please enter three integers (a month, a day and a year): That is Julian day ", end='')
    input_files = open(sys.argv[1],'r')
    splitted_words = input_files.readlines()[0].split()

    month = int(splitted_words[0])
    days = int(splitted_words[1])
    year = int(splitted_words[2])
    if month < 1 or month > 12:
        print ("ERROR bad month:", month)
        return 1
    
    if year < 1:
        print ("ERROR bad year:", year)
        return 1

    days_this_month = days_in_month[month]
    if month == 2 and is_leap_year(year):
        days_this_month += 1
    
    if days < 1 or days > days_this_month:
        print ("ERROR bad day:", days)
        return 1

    print(julian_day(month, days, year))
    return 0

if __name__ == "__main__":
    main()