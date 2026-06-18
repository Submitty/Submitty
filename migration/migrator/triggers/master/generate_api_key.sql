--
-- Name: generate_api_key(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.generate_api_key() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
-- TRIGGER function to generate api_key on INSERT or UPDATE of user_password in
-- table users.
BEGIN
    NEW.api_key := encode(gen_random_bytes(16), 'hex');
    RETURN NEW;
END;
$$;
