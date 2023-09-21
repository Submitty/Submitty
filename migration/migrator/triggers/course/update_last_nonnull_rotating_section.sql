--
-- Name: update_last_nonnull_rotating_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.update_last_nonnull_rotating_section() RETURNS trigger
	LANGUAGE plpgsql
    AS $$
BEGIN
	IF (NEW.rotating_section IS NOT NULL AND OLD.rotating_section IS NULL)
		OR (NEW.rotating_section != OLD.rotating_section) THEN
		NEW.last_nonnull_rotating_section := NEW.rotating_section;
	END IF;
	RETURN NEW;
END;
$$