def up (config, database, semester, course):
    database.execute("UPDATE categories_list SET color = '#c40233' WHERE color = '#800000'")
    
    database.execute("UPDATE categories_list SET color = '#c40233' WHERE color = '#808000'")
    
    database.execute("UPDATE categories_list SET color = '#008000' WHERE color = '#008000'")
    
    database.execute("UPDATE categories_list SET color = '#008000' WHERE color = '#008080'")
    
    database.execute("UPDATE categories_list SET color = '#800080' WHERE color = '#808080'")
    
    database.execute("UPDATE categories_list SET color = '#800080' WHERE color = '#800080'")
    
    database.execute("UPDATE categories_list SET color = '#0000ff' WHERE color = '#000080'")
    
    database.execute("UPDATE categories_list SET color = '#0000ff' WHERE color = '#000000'")


def down (config, database, semester, course):
    #Note that this does lose some data as a result of going from 8 to 4 color options
    database.execute("UPDATE categories_list SET color = '#800000' WHERE color = '#c40233'")
    
    # database.execute("UPDATE categories_list SET color = '#008000' WHERE color = '#008000'")
    
    # database.execute("UPDATE categories_list SET color = '#800080' WHERE color = '#800080'")
    
    database.execute("UPDATE categories_list SET color = '#000080' WHERE color = '#0000ff'")
