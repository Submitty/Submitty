--
-- Name: store_previous_nonull_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.update_last_nonnull_section() RETURNS trigger
	LANGUAGE plpgsql
    AS $$
BEGIN
	IF NEW.registration_section <> OLD.registration_section
		AND NEW.registration_section != null THEN
		SET last_nonnull_registration_section=NEW.registration_section;
	END IF;
	RETURN NEW;
END;
$$