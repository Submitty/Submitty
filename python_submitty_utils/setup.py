from setuptools import setup, find_packages

setup(
    name='submitty_utils',
    author='Submitty',
    version='0.6.0',
    packages=find_packages(exclude=('tests',)),
    license='BSD',
    description='Python Submitty Utils',
    install_requires=[
        'tzlocal',
        'scipy>=1.10.0'
    ],
    tests_require=[],
    zip_safe=True
)
