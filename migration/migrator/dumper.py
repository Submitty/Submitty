from subprocess import check_output
import re


def dump_database(db_name: str, out_file: str) -> bool:
    out = check_output([
        'su',
        '-',
        'postgres',
        '-c',
        f'pg_dump -d {db_name} --schema-only --no-privileges --no-owner'
    ], universal_newlines=True)
    out = out.replace("SELECT pg_catalog.set_config(\'search_path\', \'\', false);\n", "")
    out = re.sub(r"\-\- Dumped (from|by)[^\n]*\n", '', out, flags=re.IGNORECASE)
    out = re.sub(r"FOR EACH ROW EXECUTE FUNCTION", "FOR EACH ROW EXECUTE PROCEDURE", out)
    out_file.write_text(out)
    return True
