"""SQLAlchemy Tables module."""

from pathlib import Path
from sqlalchemy import Column, create_engine
from sqlalchemy.engine import reflection
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.types import SmallInteger, String, TIMESTAMP
from sqlalchemy.orm import sessionmaker
from sqlalchemy.sql import func


class Database:
    def __init__(self, params, environment):
        DynamicBase = declarative_base(class_registry=dict())
        if params['driver'] == 'sqlite':
            pass
        else:
            if params['driver'] == 'psql':
                connection_string = 'postgresql+psycopg2://'
            else:
                raise RuntimeError('Invalid driver')
            connection_string += '{}:{}@{}/{}'.format(
                params['user'],
                params['password'],
                params['host'] if not Path(params['host']).exists() else '',
                params['dbname']
            )

            if Path(params['host']).exists():
                connection_string += '?host={}'.format(params['host'])

        self.engine = create_engine(connection_string)
        self.inspector = reflection.Inspector.from_engine(self.engine)
        self.Session = sessionmaker()
        self.Session.configure(bind=self.engine)
        self.session = self.Session()

        self.migration_table = get_migration_table(environment, DynamicBase)

    def execute(self, query):
        """
        Run a raw query through the session.

        :param query: raw query string to execute
        :type query: str
        :rtype: sqlalchemy.engine.ResultProxy
        """
        return self.session.execute(query)

    def close(self):
        """Close the session and DB connnection."""
        self.session.close()
        self.engine.dispose()

    def table_has_column(self, table, search_column):
        """
        Check if a table has a given column.

        :param table: Table to search in
        :type table: str
        :param search_column: column to search for
        :type search_column: str
        :rtype: bool
        """
        for column in self.inspector.get_columns(table):
            if search_column == column['name']:
                return True
        return False


def get_migration_table(environment, Base):
    """Test."""
    class MigrationTable(Base):
        __tablename__ = "migrations_{}".format(environment)

        id = Column(String(100), primary_key=True)
        commit_time = Column(
            TIMESTAMP,
            onupdate=func.current_timestamp(),
            default=func.current_timestamp()
        )
        status = Column(SmallInteger, default=0)

    return MigrationTable
