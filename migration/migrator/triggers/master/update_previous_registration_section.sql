--
-- Name: update_previous_registration_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.update_previous_registration_section() RETURNS trigger
	LANGUAGE plpgsql
    AS $$
BEGIN
	IF (
		(NEW.registration_section IS NULL AND OLD.registration_section IS NOT NULL)
		OR NEW.registration_section != OLD.registration_section
	) THEN
		NEW.previous_registration_section := OLD.registration_section;
	END IF;
	RETURN NEW;
END;
$$