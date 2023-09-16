--
-- Name: store_previous_nonull_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.store_previous_nonnull_section() RETURNS trigger
	LANGUAGE plpgsql
    AS $$
BEGIN
	 