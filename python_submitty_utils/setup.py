from setuptools import setup, find_packages

setup(
    name='submitty_utils',
    author='Submitty',
    version='0.1.0',
    packages=find_packages(),
    license='BSD',
    description='Python Submitty Utils',
    install_requires=[
        'tzlocal'
    ]
)
