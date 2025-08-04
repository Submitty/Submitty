#!/usr/bin/env python3 

import sys
from tkinter import *
from tkinter import ttk


def echo(*args):
  global displayed_text
  try:
    displayed_text.set(user_text.get())
  except ValueError:
    pass
    
root = Tk()
root.title("Echo Input")

program_window = ttk.Frame(root, padding="3 3 12 12")
program_window.grid(column=0, row=0, sticky=(N, W, E, S))
program_window.columnconfigure(0, weight=1)
program_window.rowconfigure(0, weight=1)

user_text = StringVar()
displayed_text = StringVar()

user_text = ttk.Entry(program_window, width=7, textvariable=user_text)
user_text.grid(column=2, row=1, sticky=(W, E))

#ttk.Label(program_window, textvariable=meters).grid(column=2, row=2, sticky=(W, E))
ttk.Button(program_window, text="Echo", command=echo).grid(column=3, row=3, sticky=W)

ttk.Label(program_window, text="Input").grid(column=3, row=1, sticky=W)
ttk.Label(program_window, text="You Entered: ").grid(column=1, row=2, sticky=E)
label = ttk.Label(program_window, textvar=displayed_text)
label.grid(column=2, row=2, sticky=W)

for child in program_window.winfo_children(): child.grid_configure(padx=5, pady=5)

user_text.focus()
root.bind('<Return>', echo)

root.mainloop()
