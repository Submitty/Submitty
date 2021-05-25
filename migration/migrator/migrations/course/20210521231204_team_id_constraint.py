"""There was already a unique key constraint between user_id and g_id
in the gradeable_data table, but not between team_id and g_id, which
means we could have multiple grades for a team despite only allowing
one grade per individual.
"""


def up(config, database, semester, course):
    database.execute("ALTER TABLE public.gradeable_data DROP CONSTRAINT g_id_gd_team_id_unique;");
    database.execute("ALTER TABLE ONLY public.gradeable_data ADD CONSTRAINT g_id_gd_team_id_unique UNIQUE (g_id, gd_team_id);");
    pass


def down(config, database, semester, course):
    pass
