SELECT firstname || ' ' || lastname as contributor, endyear - startyear + 1 as tenure
FROM contributors
WHERE endyear = 2018 
ORDER BY tenure DESC;