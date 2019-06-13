def percent_change(old,new):
    return int(100*(float(new)-old)/old)

def print_change(old1, new1, old2, new2):
    p1 = percent_change(old1,new1)
    p2 = percent_change(old2,new2)
    print p1, "vs", p2

print "#icebucketchallenge vs #alsicebucketchallenge, percentage change"
printchange(200,500,100,300)
printchange(500,2000,300,1500)
printchange(2000,12000,1500,13000)
printchange(12000,24000,13000,25000)
printchange(24000,65000,25000,105000)
printchange(65000,70000,105000,85000)
