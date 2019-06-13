def percent_change(old,new):
    return int(100*(float(new)-old)/old)

def print_change(old1, new1, old2, new2):
    p1 = percent_change(old1,new1)
    p2 = percent_change(old2,new2)
    print p1, "vs.", p2

print "#icebucketchallenge vs #alsicebucketchallenge, percentage change"
print_change(200,500,100,300)
print_change(500,2000,300,1500)
print_change(2000,12000,1500,13000)
print_change(12000,24000,13000,25000)
print_change(24000,65000,25000,105000)
print_change(65000,70000,105000,85000)
