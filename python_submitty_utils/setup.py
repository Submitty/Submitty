from setuptools import setup, find_packages

setup(
    name='submitty_utils',
    author='Submitty',
    version='0.5.0',
    packages=find_packages(exclude=('tests',)),
    license='BSD',
    description='Python Submitty Utils',
    install_requires=[
        'tzlocal'
    ],
    tests_require=[
        'parameterized'
    ],
    zip_safe=True
)
