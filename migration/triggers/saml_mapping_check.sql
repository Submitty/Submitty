--
-- Name: saml_mapping_check(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.saml_mapping_check() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        BEGIN
            IF (SELECT count(*) FROM saml_mapped_users WHERE NEW.user_id = user_id) = 2
            THEN
                IF (SELECT count(*) FROM saml_mapped_users WHERE NEW.user_id = user_id AND user_id = saml_id) > 0
                THEN
                    RAISE EXCEPTION 'SAML mapping already exists for this user';
                end if;
                IF NEW.user_id = NEW.saml_id
                THEN
                    RAISE EXCEPTION 'Cannot create SAML mapping for proxy user';
                end if;
            end if;
            RETURN NEW;
        END;
        $$;
