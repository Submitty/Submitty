"""SQLAlchemy Tables module."""

from pathlib import Path
from sqlalchemy import Column, create_engine
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
        self.Session = sessionmaker()
        self.Session.configure(bind=self.engine)
        self.session = self.Session()

        self.migration_table = get_migration_table(environment, DynamicBase)

    def execute(self, query):
        return self.engine.execute(query)

    def close(self):
        self.session.close()
        self.engine.dispose()


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
