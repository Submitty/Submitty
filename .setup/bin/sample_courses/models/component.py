"""
Contains the Component class, which represents a component of a gradeable.
Contains function:
- create()
"""

from __future__ import print_function, division

from sample_courses.models import generate_random_marks, Mark
from sqlalchemy import insert


class Component(object):
    def __init__(self, component, order) -> None:
        self.title = component["gc_title"]
        self.ta_comment = ""
        self.student_comment = ""
        self.is_text = False
        self.is_peer_component = False
        self.page = 0
        self.order = order
        self.marks = []

        if "gc_ta_comment" in component:
            self.ta_comment = component["gc_ta_comment"]
        if "gc_is_peer" in component:
            self.is_peer_component = component["gc_is_peer"]
        if "gc_student_comment" in component:
            self.student_comment = component["gc_student_comment"]
        if "gc_is_text" in component:
            self.is_text = component["gc_is_text"] is True
        if "gc_page" in component:
            self.page = int(component["gc_page"])

        if self.is_text:
            self.lower_clamp = 0
            self.default = 0
            self.max_value = 0
            self.upper_clamp = 0
        else:
            self.lower_clamp = float(component["gc_lower_clamp"])
            self.default = float(component["gc_default"])
            self.max_value = float(component["gc_max_value"])
            self.upper_clamp = float(component["gc_upper_clamp"])

        if "marks" in component:
            for i in range(len(component["marks"])):
                mark = component["marks"][i]
                self.marks.append(Mark(mark, i))
        else:
            self.marks = generate_random_marks(self.default, self.max_value)

        self.key = None

    def create(self, g_id, conn, table, mark_table) -> None:
        ins = insert(table).values(
            g_id=g_id,
            gc_title=self.title,
            gc_ta_comment=self.ta_comment,
            gc_student_comment=self.student_comment,
            gc_lower_clamp=self.lower_clamp,
            gc_default=self.default,
            gc_max_value=self.max_value,
            gc_upper_clamp=self.upper_clamp,
            gc_is_text=self.is_text,
            gc_is_peer=self.is_peer_component,
            gc_order=self.order,
            gc_page=self.page,
        )
        res = conn.execute(ins)
        conn.commit()
        self.key = res.inserted_primary_key[0]

        for mark in self.marks:
            mark.create(self.key, conn, mark_table)
