--
-- Name: update_previous_rotating_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.update_previous_rotating_section() RETURNS trigger
	LANGUAGE plpgsql
    AS $$
BEGIN
	IF (
		(NEW.rotating_section IS NULL AND OLD.rotating_section IS NOT NULL)
		OR NEW.rotating_section != OLD.rotating_section
	) THEN
		NEW.previous_rotating_section := OLD.rotating_section;
	END IF;
	RETURN NEW;
END;
$$