SELECT * FROM(
    SELECT gd_id, SUM(comp_score) AS g_score, SUM(gc_max_value) AS max, COUNT(comp.*), autograding FROM(
      SELECT  gd_id, gc_title, gc_max_value, gc_is_peer, gc_order, autograding,
      CASE WHEN (gc_default + sum_points + gcd_score) > gc_upper_clamp THEN gc_upper_clamp
      WHEN (gc_default + sum_points + gcd_score) < gc_lower_clamp THEN gc_lower_clamp
      ELSE (gc_default + sum_points + gcd_score) END AS comp_score FROM(
        SELECT gcd.gd_id, gc_title, gc_max_value, gc_is_peer, gc_order, gc_lower_clamp, gc_default, gc_upper_clamp,
        CASE WHEN sum_points IS NULL THEN 0 ELSE sum_points END AS sum_points, gcd_score, CASE WHEN autograding IS NULL THEN 0 ELSE autograding END AS autograding
        FROM gradeable_component_data AS gcd
        LEFT JOIN gradeable_component AS gc ON gcd.gc_id=gc.gc_id
        LEFT JOIN(
          SELECT SUM(gcm_points) AS sum_points, gcmd.gc_id, gcmd.gd_id
          FROM gradeable_component_mark_data AS gcmd
          LEFT JOIN gradeable_component_mark AS gcm ON gcmd.gcm_id=gcm.gcm_id AND gcmd.gc_id=gcm.gc_id
          GROUP BY gcmd.gc_id, gcmd.gd_id
          )AS marks
        ON gcd.gc_id=marks.gc_id AND gcd.gd_id=marks.gd_id
        LEFT JOIN gradeable_data AS gd ON gd.gd_id=gcd.gd_id
        LEFT JOIN (
          SELECT egd.g_id, egd.{$user_or_team_id}, (autograding_non_hidden_non_extra_credit + autograding_non_hidden_extra_credit + autograding_hidden_non_extra_credit + autograding_hidden_extra_credit) AS autograding
          FROM electronic_gradeable_version AS egv
          LEFT JOIN electronic_gradeable_data AS egd ON egv.g_id=egd.g_id AND egv.{$user_or_team_id}=egd.{$user_or_team_id} AND active_version=g_version AND active_version>0
          )AS auto
        ON gd.g_id=auto.g_id AND gd_{$user_or_team_id}=auto.{$user_or_team_id}
        INNER JOIN {$users_or_teams} AS {$u_or_t} ON {$u_or_t}.{$user_or_team_id} = auto.{$user_or_team_id}
        WHERE gc.g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL
      )AS parts_of_comp
      )g