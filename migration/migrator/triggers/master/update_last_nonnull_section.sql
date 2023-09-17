--
-- Name: update_last_nonnull_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.update_last_nonnull_section() RETURNS trigger
	LANGUAGE plpgsql
    AS $$
BEGIN
	IF (NEW.registration_section IS NOT NULL AND OLD.registration_section IS NULL)
		OR (NEW.registration_section != OLD.registration_section) THEN
		NEW.last_nonnull_registration_section := NEW.registration_section;
	END IF;
	RETURN NEW;
END;
$$